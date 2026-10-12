<?php

declare(strict_types=1);

namespace MitinSany\RouterOS;

use Exception;
use MitinSany\RouterOS\Protocol\SentenceWords;
use MitinSany\RouterOS\Enum\LoginMode;
use MitinSany\RouterOS\Protocol\StreamTransport;
use MitinSany\RouterOS\Protocol\WordCodec;

/**
 * Synchronous RouterOS API client using the native word protocol.
 */
class Api
{
    public $debug = false;
    public $errorNo;
    public $errorString;
    public $attempts = 5;
    public $connected = false;
    public $delay = 3;
    public $port = 8728;
    public $timeout = 3;
    public $sock;

    protected $transport;

    /**
     * Opens a connection and authenticates with a RouterOS login mode.
     *
     * @return bool True after a !done reply.
     */
    public function connect($ip, $login, $password, $loginMode = LoginMode::AUTO)
    {
        $this->connected = false;
        $this->errorNo = null;
        $this->errorString = null;

        try {
            $this->transport = $this->createTransport($ip);
            if ($this->transport === false) {
                return false;
            }

            $this->transport->setTimeout($this->timeout);
            if ($this->authenticate($login, $password, $loginMode)) {
                $this->connected = true;

                return true;
            }
        } catch (Exception $exception) {
            $this->errorString = $exception->getMessage();
        }

        $this->disconnect();

        return false;
    }

    /**
     * Emits a diagnostic message when debugging is enabled.
     */
    public function debug($text)
    {
        if ($this->debug) {
            echo $text . PHP_EOL;
        }
    }

    /**
     * Writes one RouterOS sentence.
     */
    public function write($command, $param2 = true)
    {
        if (is_array($command)) {
            $words = $command;
        } elseif (is_array($param2)) {
            $words = array_merge([$command], $param2);
        } else {
            $words = [$command];
        }

        $this->getTransport()->writeSentence($words);
    }

    /**
     * Encodes a RouterOS word length.
     *
     * @return string Binary length prefix.
     */
    public function encodeLength($length)
    {
        return WordCodec::encodeLength($length);
    }

    /**
     * Reads response sentences through !done or !trap.
     *
     * @return array|false Parsed records or raw sentences when disabled.
     */
    public function read($parse = true)
    {
        $responses = [];

        while (true) {
            $sentence = $this->getTransport()->readSentence();
            $responses[] = $sentence;

            if ($this->sentenceHasType($sentence, '!done')) {
                break;
            }
        }

        if (!$parse) {
            return $responses;
        }

        return $this->parseResponse($responses);
    }

    /**
     * Converts !re sentences to attribute records and handles RouterOS errors.
     *
     * @return array|false
     */
    public function parseResponse($response)
    {
        if (empty($response)) {
            return [];
        }

        if (isset($response[0]) && is_string($response[0])) {
            $response = [$response];
        }

        $records = [];
        foreach ($response as $sentence) {
            if ($this->sentenceHasType($sentence, '!re')) {
                $records[] = SentenceWords::attributes($sentence);
                continue;
            }

            if ($this->sentenceHasType($sentence, '!trap') || $this->sentenceHasType($sentence, '!fatal')) {
                $this->setErrorFromResponse([$sentence]);

                return false;
            }

            if ($this->sentenceHasType($sentence, '!done')) {
                break;
            }
        }

        return $records;
    }

    /**
     * Closes the active RouterOS transport.
     */
    public function disconnect()
    {
        if ($this->transport !== null) {
            $this->transport->close();
        }

        $this->transport = null;
        $this->sock = null;
        $this->connected = false;
    }

    /**
     * Compatibility-named response parser for template consumers.
     *
     * @return array|false
     */
    public function parseResponseForSmarty($response)
    {
        return $this->parseResponse($response);
    }

    /**
     * Returns the supplied array without changing its key structure.
     *
     * @return array
     */
    public function changeArrayKeyName(&$array)
    {
        return $array;
    }

    /**
     * Sends a command with key/value attributes.
     *
     * @return array|false
     */
    public function command($com, $arr = [])
    {
        $words = SentenceWords::command($com, $arr);

        try {
            $this->write($words);

            return $this->read(true);
        } catch (Exception $exception) {
            $this->errorString = $exception->getMessage();

            return false;
        }
    }

    protected function createTransport($ip)
    {
        $this->sock = @fsockopen($ip, $this->port, $this->errorNo, $this->errorString, $this->timeout);
        if ($this->sock === false) {
            return false;
        }

        return new StreamTransport($this->sock);
    }

    private function authenticate($login, $password, $loginMode)
    {
        if ($loginMode === LoginMode::PLAIN) {
            return $this->authenticatePlain($login, $password);
        }
        if ($loginMode === LoginMode::LEGACY) {
            return $this->authenticateLegacy($login, $password);
        }
        if ($loginMode === LoginMode::AUTO) {
            return $this->authenticateAuto($login, $password);
        }

        $this->errorString = 'Unknown RouterOS login mode.';

        return false;
    }

    private function authenticatePlain($login, $password)
    {
        $response = $this->sendLogin(['=name=' . $login, '=password=' . $password]);
        if (!$this->isSuccessfulLoginResponse($response)) {
            return false;
        }
        if ($this->loginChallenge($response) !== null) {
            $this->errorString = 'RouterOS requires legacy login.';

            return false;
        }

        return true;
    }

    private function authenticateLegacy($login, $password)
    {
        $response = $this->sendLogin([]);
        if (!$this->isSuccessfulLoginResponse($response)) {
            return false;
        }

        $challenge = $this->loginChallenge($response);
        if (!$this->isValidLegacyChallenge($challenge)) {
            $this->errorString = 'RouterOS did not provide a valid legacy login challenge.';

            return false;
        }

        return $this->sendLegacyResponse($login, $password, $challenge);
    }

    private function authenticateAuto($login, $password)
    {
        $response = $this->sendLogin(['=name=' . $login, '=password=' . $password]);
        if (!$this->isSuccessfulLoginResponse($response)) {
            return false;
        }

        $challenge = $this->loginChallenge($response);
        if ($challenge === null) {
            return true;
        }
        if (!$this->isValidLegacyChallenge($challenge)) {
            $this->errorString = 'RouterOS returned an invalid legacy login challenge.';

            return false;
        }

        return $this->sendLegacyResponse($login, $password, $challenge);
    }

    private function sendLegacyResponse($login, $password, $challenge)
    {
        $response = '00' . md5(chr(0) . $password . pack('H*', $challenge));

        return $this->isSuccessfulLoginResponse($this->sendLogin([
            '=name=' . $login,
            '=response=' . $response
        ]));
    }

    private function sendLogin(array $words)
    {
        array_unshift($words, '/login');
        $this->write($words);

        return $this->read(false);
    }

    private function isSuccessfulLoginResponse($response)
    {
        if (!is_array($response)) {
            $this->errorString = 'RouterOS login did not return a valid response.';

            return false;
        }
        if ($this->hasResponseType($response, '!trap') || $this->hasResponseType($response, '!fatal')) {
            $this->setErrorFromResponse($response);

            return false;
        }
        if ($this->hasResponseType($response, '!done')) {
            return true;
        }

        $this->errorString = 'RouterOS login did not complete.';

        return false;
    }

    private function loginChallenge(array $response)
    {
        foreach ($response as $sentence) {
            if (!$this->sentenceHasType($sentence, '!done')) {
                continue;
            }
            $attributes = SentenceWords::attributes($sentence);
            if (array_key_exists('ret', $attributes)) {
                return $attributes['ret'];
            }
        }

        return null;
    }

    private function isValidLegacyChallenge($challenge)
    {
        return is_string($challenge) && preg_match('/^[a-f0-9]{32}$/iD', $challenge) === 1;
    }

    private function getTransport()
    {
        if ($this->transport === null) {
            throw new Exception('RouterOS transport is not connected.');
        }

        return $this->transport;
    }

    private function hasResponseType(array $responses, $type)
    {
        foreach ($responses as $sentence) {
            if ($this->sentenceHasType($sentence, $type)) {
                return true;
            }
        }

        return false;
    }

    private function sentenceHasType(array $sentence, $type)
    {
        return SentenceWords::type($sentence) === $type;
    }

    private function setErrorFromResponse(array $responses)
    {
        foreach ($responses as $sentence) {
            if (!$this->sentenceHasType($sentence, '!trap') && !$this->sentenceHasType($sentence, '!fatal')) {
                continue;
            }

            $attributes = SentenceWords::attributes($sentence);
            $this->errorString = isset($attributes['message']) ? $attributes['message'] : 'RouterOS request failed.';

            return;
        }
    }
}

<?php

declare(strict_types=1);

namespace MitinSany\RouterOS;

use Exception;
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
     * Opens a connection and authenticates with a plain-text RouterOS login.
     *
     * @return bool True after a !done reply.
     */
    public function connect($ip, $login, $password)
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
            $this->write(array('/login', '=name=' . $login, '=password=' . $password));
            $response = $this->read(false);

            if ($this->hasResponseType($response, '!trap') || $this->hasResponseType($response, '!fatal')) {
                $this->setErrorFromResponse($response);
            } elseif ($this->hasResponseType($response, '!done')) {
                $this->connected = true;

                return true;
            }

            $this->setErrorFromResponse($response);
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
            $words = array_merge(array($command), $param2);
        } else {
            $words = array($command);
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
        $responses = array();

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
            return array();
        }

        if (isset($response[0]) && is_string($response[0])) {
            $response = array($response);
        }

        $records = array();
        foreach ($response as $sentence) {
            if ($this->sentenceHasType($sentence, '!re')) {
                $records[] = $this->parseAttributes($sentence);
                continue;
            }

            if ($this->sentenceHasType($sentence, '!trap') || $this->sentenceHasType($sentence, '!fatal')) {
                $this->setErrorFromResponse(array($sentence));

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
    public function command($com, $arr = array())
    {
        $words = array($com);
        foreach ($arr as $key => $value) {
            $words[] = '=' . $key . '=' . $value;
        }

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
        return isset($sentence[0]) && $sentence[0] === $type;
    }

    private function setErrorFromResponse(array $responses)
    {
        foreach ($responses as $sentence) {
            if (!$this->sentenceHasType($sentence, '!trap') && !$this->sentenceHasType($sentence, '!fatal')) {
                continue;
            }

            $attributes = $this->parseAttributes($sentence);
            $this->errorString = isset($attributes['message']) ? $attributes['message'] : 'RouterOS request failed.';

            return;
        }
    }

    private function parseAttributes(array $sentence)
    {
        $attributes = array();
        foreach ($sentence as $word) {
            if (!is_string($word) || substr($word, 0, 1) !== '=') {
                continue;
            }

            $parts = explode('=', substr($word, 1), 2);
            if (count($parts) === 2) {
                $attributes[$parts[0]] = $parts[1];
            }
        }

        return $attributes;
    }
}

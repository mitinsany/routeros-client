<?php

declare(strict_types=1);

namespace MitinSany\RouterOS;

use Exception;
use MitinSany\RouterOS\Protocol\SentenceWords;

/**
 * RouterOS command helpers built on the synchronous API client.
 */
class RouterOS extends Api
{
    public $hostname;
    public $readOnly = false;

    private $callbacks = [];
    private $tagIndex = 1;

    /**
     * Sets the transport timeout in seconds.
     *
     * @return bool
     */
    public function setTimeout($timeout = 5)
    {
        if ($this->transport === null) {
            return false;
        }

        $this->timeout = $timeout;
        $this->transport->setTimeout($timeout);

        return true;
    }

    /**
     * Reads records using the RouterOS print command.
     *
     * @return array|false|string
     */
    public function getAll($cmd, $proplist = false, $args = [], $assoc = false, $callback = false, $queryWords = [])
    {
        $attributes = [];
        if ($proplist !== false) {
            $attributes['.proplist'] = is_array($proplist) ? implode(',', $proplist) : $proplist;
        }

        foreach ($args as $key => $value) {
            $attributes[$key] = $value;
        }

        $result = $this->request($this->commandPath($cmd, 'print'), $attributes, $callback, $queryWords);
        if ($callback !== false || $result === false || !$assoc) {
            return $result;
        }

        $records = [];
        foreach ($result as $record) {
            if (isset($record['.id'])) {
                $records[$record['.id']] = $record;
            } else {
                $records[] = $record;
            }
        }

        return $records;
    }

    /** @return bool|string */
    public function set($cmd, $args = [], $callback = false)
    {
        return $this->mutate($this->commandPath($cmd, 'set'), $args, $callback);
    }

    /** @return bool */
    public function reboot()
    {
        return $this->mutate('/system/reboot');
    }

    /** @return bool|string */
    public function fetchUrl($url, $callback = false)
    {
        return $this->mutate('/tool/fetch', ['url' => $url], $callback);
    }

    /** @return bool|string */
    public function cancel($tag = false, $callback = false)
    {
        $attributes = $tag === false ? [] : ['tag' => $tag];

        return $this->mutate('/cancel', $attributes, $callback);
    }

    /** @return bool|string */
    public function move($cmd, $id, $before, $callback = false)
    {
        return $this->mutate(
            $this->commandPath($cmd, 'move'),
            ['.id' => $id, 'destination' => $before],
            $callback
        );
    }

    /** @return bool|string */
    public function add($cmd, $args = [], $callback = false)
    {
        return $this->mutate($this->commandPath($cmd, 'add'), $args, $callback);
    }

    /** @return bool|string */
    public function remove($cmd, $id, $callback = false)
    {
        return $this->mutate($this->commandPath($cmd, 'remove'), ['.id' => $id], $callback);
    }

    /** @return bool|string */
    public function unsetValue($cmd, $id, $value, $callback = false)
    {
        return $this->mutate(
            $this->commandPath($cmd, 'unset'),
            ['.id' => $id, 'value' => $value],
            $callback
        );
    }

    /** @return bool|string */
    public function scan($id, $duration = '00:10:00', $callback = false)
    {
        return $this->mutate(
            '/interface/wireless/scan',
            ['interface' => $id, 'duration' => $duration],
            $callback
        );
    }

    /** @return bool|string */
    public function frequencyMonitor($id, $duration = '00:02:00', $callback = false)
    {
        return $this->mutate(
            '/interface/wireless/frequency-monitor',
            ['interface' => $id, 'duration' => $duration],
            $callback
        );
    }

    /**
     * Starts a tagged listen request and returns its callback tag.
     *
     * @return string|false
     */
    public function listen($cmd, $args = false, $callback = null)
    {
        if (!is_callable($callback)) {
            return false;
        }

        return $this->request($this->commandPath($cmd, 'listen'), $args === false ? [] : $args, $callback);
    }

    /** @return bool|string */
    public function bandwidthTest($address, $speed = '1M', $protocol = 'tcp', $callback = false)
    {
        return $this->mutate(
            '/tool/bandwidth-test',
            ['address' => $address, 'speed' => $speed, 'protocol' => $protocol],
            $callback
        );
    }

    /**
     * Dispatches tagged asynchronous replies to their registered callbacks.
     *
     * @return bool
     */
    public function dispatch(&$continue)
    {
        try {
            $responses = $this->read(false);
        } catch (Exception $exception) {
            $this->errorString = $exception->getMessage();

            return false;
        }

        foreach ($responses as $sentence) {
            $tag = SentenceWords::tag($sentence);
            if ($tag === false || !isset($this->callbacks[$tag])) {
                continue;
            }

            $callback = $this->callbacks[$tag];
            if (SentenceWords::type($sentence) === '!trap') {
                call_user_func($callback, $this, false, SentenceWords::attributes($sentence, true));
                unset($this->callbacks[$tag]);
            } elseif (SentenceWords::type($sentence) === '!re') {
                call_user_func($callback, $this, true, SentenceWords::attributes($sentence, true));
            } elseif (SentenceWords::type($sentence) === '!done') {
                call_user_func($callback, $this, true, null);
                unset($this->callbacks[$tag]);
            }

            if ($continue === false) {
                return false;
            }
        }

        return true;
    }

    private function mutate($path, array $attributes = [], $callback = false)
    {
        if ($this->readOnly) {
            return false;
        }

        $result = $this->request($path, $attributes, $callback);
        if ($callback !== false) {
            return $result;
        }

        return $result !== false;
    }

    private function request($path, array $attributes, $callback, array $queryWords = [])
    {
        $words = SentenceWords::command($path, $attributes, $queryWords);

        if ($callback !== false) {
            $tag = 'callback-' . $this->tagIndex;
            $this->tagIndex++;
            $words[] = '=.tag=' . $tag;
            $this->callbacks[$tag] = $callback;
            $this->write($words);

            return $tag;
        }

        try {
            $this->write($words);

            return $this->read(true);
        } catch (Exception $exception) {
            $this->errorString = $exception->getMessage();

            return false;
        }
    }

    private function commandPath($cmd, $action)
    {
        return rtrim($cmd, '/') . '/' . $action;
    }
}

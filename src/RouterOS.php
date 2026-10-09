<?php

namespace MitinSany\RouterOS;

use Exception;

class RouterOS extends Api
{
    public $hostname;
    public $readOnly = false;

    private $callbacks = array();
    private $tagIndex = 1;

    public function setTimeout($timeout = 5)
    {
        if ($this->transport === null) {
            return false;
        }

        $this->timeout = $timeout;
        $this->transport->setTimeout($timeout);

        return true;
    }

    public function getAll($cmd, $proplist = false, $args = array(), $assoc = false, $callback = false)
    {
        $attributes = array();
        if ($proplist !== false) {
            $attributes['.proplist'] = is_array($proplist) ? implode(',', $proplist) : $proplist;
        }

        foreach ($args as $key => $value) {
            $attributes[$key] = $value;
        }

        $result = $this->request($this->commandPath($cmd, 'print'), $attributes, $callback);
        if ($callback !== false || $result === false || !$assoc) {
            return $result;
        }

        $records = array();
        foreach ($result as $record) {
            if (isset($record['.id'])) {
                $records[$record['.id']] = $record;
            } else {
                $records[] = $record;
            }
        }

        return $records;
    }

    public function set($cmd, $args = array(), $callback = false)
    {
        return $this->mutate($this->commandPath($cmd, 'set'), $args, $callback);
    }

    public function reboot()
    {
        return $this->mutate('/system/reboot');
    }

    public function fetchUrl($url, $callback = false)
    {
        return $this->mutate('/tool/fetch', array('url' => $url), $callback);
    }

    public function cancel($tag = false, $callback = false)
    {
        $attributes = $tag === false ? array() : array('tag' => $tag);

        return $this->mutate('/cancel', $attributes, $callback);
    }

    public function move($cmd, $id, $before, $callback = false)
    {
        return $this->mutate(
            $this->commandPath($cmd, 'move'),
            array('.id' => $id, 'destination' => $before),
            $callback
        );
    }

    public function add($cmd, $args = array(), $callback = false)
    {
        return $this->mutate($this->commandPath($cmd, 'add'), $args, $callback);
    }

    public function remove($cmd, $id, $callback = false)
    {
        return $this->mutate($this->commandPath($cmd, 'remove'), array('.id' => $id), $callback);
    }

    public function unsetValue($cmd, $id, $value, $callback = false)
    {
        return $this->mutate(
            $this->commandPath($cmd, 'unset'),
            array('.id' => $id, 'value' => $value),
            $callback
        );
    }

    public function scan($id, $duration = '00:10:00', $callback = false)
    {
        return $this->mutate(
            '/interface/wireless/scan',
            array('interface' => $id, 'duration' => $duration),
            $callback
        );
    }

    public function frequencyMonitor($id, $duration = '00:02:00', $callback = false)
    {
        return $this->mutate(
            '/interface/wireless/frequency-monitor',
            array('interface' => $id, 'duration' => $duration),
            $callback
        );
    }

    public function listen($cmd, $args = false, $callback = null)
    {
        if (!is_callable($callback)) {
            return false;
        }

        return $this->request($this->commandPath($cmd, 'listen'), $args === false ? array() : $args, $callback);
    }

    public function bandwidthTest($address, $speed = '1M', $protocol = 'tcp', $callback = false)
    {
        return $this->mutate(
            '/tool/bandwidth-test',
            array('address' => $address, 'speed' => $speed, 'protocol' => $protocol),
            $callback
        );
    }

    public function dispatch(&$continue)
    {
        try {
            $responses = $this->read(false);
        } catch (Exception $exception) {
            $this->error_str = $exception->getMessage();

            return false;
        }

        foreach ($responses as $sentence) {
            $tag = $this->tagFromSentence($sentence);
            if ($tag === false || !isset($this->callbacks[$tag])) {
                continue;
            }

            $callback = $this->callbacks[$tag];
            if ($this->sentenceType($sentence) === '!trap') {
                call_user_func($callback, $this, false, $this->attributesFromSentence($sentence));
                unset($this->callbacks[$tag]);
            } elseif ($this->sentenceType($sentence) === '!re') {
                call_user_func($callback, $this, true, $this->attributesFromSentence($sentence));
            } elseif ($this->sentenceType($sentence) === '!done') {
                call_user_func($callback, $this, true, null);
                unset($this->callbacks[$tag]);
            }

            if ($continue === false) {
                return false;
            }
        }

        return true;
    }

    private function mutate($path, array $attributes = array(), $callback = false)
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

    private function request($path, array $attributes, $callback)
    {
        $words = array($path);
        foreach ($attributes as $key => $value) {
            $words[] = '=' . $key . '=' . $value;
        }

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
            $this->error_str = $exception->getMessage();

            return false;
        }
    }

    private function commandPath($cmd, $action)
    {
        return rtrim($cmd, '/') . '/' . $action;
    }

    private function sentenceType(array $sentence)
    {
        return isset($sentence[0]) ? $sentence[0] : false;
    }

    private function tagFromSentence(array $sentence)
    {
        foreach ($sentence as $word) {
            if (substr($word, 0, 6) === '=.tag=') {
                return substr($word, 6);
            }

            if (substr($word, 0, 5) === '.tag=') {
                return substr($word, 5);
            }
        }

        return false;
    }

    private function attributesFromSentence(array $sentence)
    {
        $attributes = array();
        foreach ($sentence as $word) {
            if (substr($word, 0, 1) !== '=') {
                continue;
            }

            $parts = explode('=', substr($word, 1), 2);
            if (count($parts) === 2 && $parts[0] !== '.tag') {
                $attributes[$parts[0]] = $parts[1];
            }
        }

        return $attributes;
    }
}

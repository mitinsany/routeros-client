<?php

namespace MitinSany\RouterOS\Tests\Support;

use MitinSany\RouterOS\Protocol\TransportException;

class ScriptedTransport
{
    public $sentences = [];
    public $timeout;
    public $closed = false;

    private $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function writeSentence(array $words)
    {
        $this->sentences[] = $words;
    }

    public function readSentence()
    {
        if (empty($this->responses)) {
            throw new TransportException('No scripted RouterOS response remains.');
        }

        return array_shift($this->responses);
    }

    public function setTimeout($seconds)
    {
        $this->timeout = $seconds;
    }

    public function close()
    {
        $this->closed = true;
    }
}

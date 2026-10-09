<?php

namespace MitinSany\RouterOS;

class Api
{
    public $debug = false;
    public $error_no;
    public $error_str;
    public $attempts = 5;
    public $connected = false;
    public $delay = 3;
    public $port = 8728;
    public $timeout = 3;
    public $sock;

    public function connect($ip, $login, $password)
    {
    }
    public function debug($text)
    {
    }
    public function write($command, $param2 = true)
    {
    }
    public function encodeLength($length)
    {
    }
    public function read($parse = true)
    {
    }
    public function parseResponse($response)
    {
    }
    public function disconnect()
    {
    }
    public function parseResponseForSmarty($response)
    {
    }
    public function changeArrayKeyName(&$array)
    {
    }
    public function command($com, $arr = array())
    {
    }
}

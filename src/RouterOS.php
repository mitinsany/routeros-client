<?php

namespace MitinSany\RouterOS;

class RouterOS
{
    public $sock;
    public $hostname;
    public $readOnly = false;

    public function setTimeout($timeout = 5)
    {
    }
    public function getAll($cmd, $proplist = false, $args = array(), $assoc = false, $callback = false)
    {
    }
    public function set($cmd, $args = array(), $callback = false)
    {
    }
    public function reboot()
    {
    }
    public function fetchUrl($url, $callback = false)
    {
    }
    public function cancel($tag = false, $callback = false)
    {
    }
    public function move($cmd, $id, $before, $callback = false)
    {
    }
    public function add($cmd, $args = array(), $callback = false)
    {
    }
    public function remove($cmd, $id, $callback = false)
    {
    }
    public function unsetValue($cmd, $id, $value, $callback = false)
    {
    }
    public function scan($id, $duration = '00:10:00', $callback = false)
    {
    }
    public function frequencyMonitor($id, $duration = '00:02:00', $callback = false)
    {
    }
    public function listen($cmd, $args = false, $callback = null)
    {
    }
    public function bandwidthTest($address, $speed = '1M', $protocol = 'tcp', $callback = false)
    {
    }
    public function dispatch(&$continue)
    {
    }
}

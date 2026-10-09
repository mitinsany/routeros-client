<?php

namespace MitinSany\RouterOS;

class ConfigurationSynchronizer
{
    public $vars = array();
    public $logs = array();
    public $showIgnored = false;
    public $currentContext = '--internal--';

    public function __get($name)
    {
    }
    public function __set($name, $value)
    {
    }
    public function __isset($name)
    {
    }
    public function __unset($name)
    {
    }
    public function variable($key)
    {
    }
    public function configureWithArguments($cmd)
    {
    }
    public function configureReversedWithArguments($cmd)
    {
    }
    public function ignoreWithArguments($cmd)
    {
    }
    public function passWithArguments($cmd)
    {
    }
    public function parseFile($file)
    {
    }
    public function replace($string)
    {
    }
    public function error($message)
    {
    }
    public function define($key, $value = null)
    {
    }
    public function config($cmd, $line, $reverse = false)
    {
    }
    public function cmd($alias, $cmd)
    {
    }
    public function ignore($cmd, $line)
    {
    }
    public function pass($cmd, $line)
    {
    }
    public function section($alias, $cmd, $type, $keys = false, $defaults = false)
    {
    }
    public function call($cmd, $args)
    {
    }
    public function update($conn, $ret = false)
    {
    }
    public function updateSection($conn, $alias)
    {
    }
}

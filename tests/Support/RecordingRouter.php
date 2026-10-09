<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests\Support;

class RecordingRouter
{
    public $sets = array();
    public $responses = array();

    public function getAll($path)
    {
        return isset($this->responses[$path]) ? $this->responses[$path] : array();
    }

    public function set($path, $attributes)
    {
        $this->sets[] = array($path, $attributes);

        return true;
    }
}

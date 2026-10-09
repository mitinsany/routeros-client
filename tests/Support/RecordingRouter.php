<?php

namespace MitinSany\RouterOS\Tests\Support;

class RecordingRouter
{
    public $sets = [];
    public $responses = [];

    public function getAll($path)
    {
        return isset($this->responses[$path]) ? $this->responses[$path] : [];
    }

    public function set($path, $attributes)
    {
        $this->sets[] = [$path, $attributes];

        return true;
    }
}

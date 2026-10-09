<?php

namespace MitinSany\RouterOS\Tests\Support;

class RecordingRouter
{
    public $sets = array();

    public function set($path, $attributes)
    {
        $this->sets[] = array($path, $attributes);

        return true;
    }
}

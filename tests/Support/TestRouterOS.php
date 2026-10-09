<?php

namespace MitinSany\RouterOS\Tests\Support;

use MitinSany\RouterOS\RouterOS;

class TestRouterOS extends RouterOS
{
    public function __construct(ScriptedTransport $scriptedTransport)
    {
        $this->transport = $scriptedTransport;
    }
}

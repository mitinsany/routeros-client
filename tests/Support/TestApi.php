<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests\Support;

use MitinSany\RouterOS\Api;

class TestApi extends Api
{
    private $scriptedTransport;

    public function __construct(ScriptedTransport $scriptedTransport)
    {
        $this->scriptedTransport = $scriptedTransport;
        $this->transport = $scriptedTransport;
    }

    protected function createTransport($ip)
    {
        return $this->scriptedTransport;
    }
}

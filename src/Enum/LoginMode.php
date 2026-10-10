<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Enum;

final class LoginMode
{
    const AUTO = 'auto';
    const PLAIN = 'plain';
    const LEGACY = 'legacy';

    private function __construct()
    {
    }
}

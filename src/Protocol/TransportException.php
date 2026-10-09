<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Protocol;

use RuntimeException;

/**
 * Signals a RouterOS stream read, write, timeout, or close failure.
 */
class TransportException extends RuntimeException
{
}

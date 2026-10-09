<?php

namespace MitinSany\RouterOS\Protocol;

use RuntimeException;

/**
 * Signals a RouterOS stream read, write, timeout, or close failure.
 */
class TransportException extends RuntimeException
{
}

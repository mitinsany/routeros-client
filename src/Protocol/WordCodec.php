<?php

namespace MitinSany\RouterOS\Protocol;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Encodes and decodes RouterOS API words and variable-length prefixes.
 */
class WordCodec
{
    /**
     * Encodes an unsigned RouterOS word length.
     *
     * @return string Binary prefix.
     */
    public static function encodeLength($length)
    {
        if (!is_int($length) || $length < 0 || $length > 0xffffffff) {
            throw new InvalidArgumentException('RouterOS word length is out of range.');
        }

        if ($length < 0x80) {
            return chr($length);
        }

        if ($length < 0x4000) {
            return chr(($length >> 8) | 0x80) . chr($length & 0xff);
        }

        if ($length < 0x200000) {
            return chr(($length >> 16) | 0xc0)
                . chr(($length >> 8) & 0xff)
                . chr($length & 0xff);
        }

        if ($length < 0x10000000) {
            return chr(($length >> 24) | 0xe0)
                . chr(($length >> 16) & 0xff)
                . chr(($length >> 8) & 0xff)
                . chr($length & 0xff);
        }

        return "\xf0"
            . chr(($length >> 24) & 0xff)
            . chr(($length >> 16) & 0xff)
            . chr(($length >> 8) & 0xff)
            . chr($length & 0xff);
    }

    /**
     * Decodes a word length using a callable for trailing prefix bytes.
     *
     * @return int
     */
    public static function decodeLength($first, $reader)
    {
        if (!is_string($first) || strlen($first) !== 1) {
            throw new InvalidArgumentException('RouterOS word length prefix must be one byte.');
        }

        $value = ord($first);
        if ($value < 0x80) {
            return $value;
        }

        if ($value < 0xc0) {
            $suffix = self::readExactly($reader, 1);

            return (($value & 0x3f) << 8) | ord($suffix);
        }

        if ($value < 0xe0) {
            $suffix = self::readExactly($reader, 2);

            return (($value & 0x1f) << 16)
                | (ord($suffix[0]) << 8)
                | ord($suffix[1]);
        }

        if ($value < 0xf0) {
            $suffix = self::readExactly($reader, 3);

            return (($value & 0x0f) << 24)
                | (ord($suffix[0]) << 16)
                | (ord($suffix[1]) << 8)
                | ord($suffix[2]);
        }

        if ($value === 0xf0) {
            $suffix = self::readExactly($reader, 4);

            return (ord($suffix[0]) << 24)
                | (ord($suffix[1]) << 16)
                | (ord($suffix[2]) << 8)
                | ord($suffix[3]);
        }

        throw new InvalidArgumentException('RouterOS control byte is reserved.');
    }

    /**
     * Prefixes a string with its RouterOS word length.
     *
     * @return string
     */
    public static function encodeWord($value)
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('RouterOS word must be a string.');
        }

        return self::encodeLength(strlen($value)) . $value;
    }

    /**
     * Reads a single complete RouterOS word.
     *
     * @return string
     */
    public static function decodeWord($reader)
    {
        $first = self::readExactly($reader, 1);
        $length = self::decodeLength($first, $reader);

        return self::readExactly($reader, $length);
    }

    private static function readExactly($reader, $length)
    {
        if ($length === 0) {
            return '';
        }

        if (!is_callable($reader)) {
            throw new InvalidArgumentException('RouterOS reader must be callable.');
        }

        $value = call_user_func($reader, $length);
        if (!is_string($value) || strlen($value) !== $length) {
            throw new UnexpectedValueException('RouterOS word is incomplete.');
        }

        return $value;
    }
}

<?php

namespace MitinSany\RouterOS\Tests\Support;

class MemoryStream
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName

    public static $buffers = array();
    public static $chunkSizes = array();

    private $key;
    private $position = 0;

    public static function open($contents, $chunkSize)
    {
        if (!in_array('routeros-memory', stream_get_wrappers(), true)) {
            stream_wrapper_register('routeros-memory', __CLASS__);
        }

        $key = uniqid('stream-', true);
        self::$buffers[$key] = $contents;
        self::$chunkSizes[$key] = $chunkSize;

        return fopen('routeros-memory://' . $key, 'r+');
    }

    public function stream_open($path, $mode, $options, &$openedPath)
    {
        $this->key = substr($path, strlen('routeros-memory://'));

        return isset(self::$buffers[$this->key]);
    }

    public function stream_read($count)
    {
        $remaining = strlen(self::$buffers[$this->key]) - $this->position;
        $length = min($count, self::$chunkSizes[$this->key], $remaining);
        $value = substr(self::$buffers[$this->key], $this->position, $length);
        $this->position += $length;

        return $value;
    }

    public function stream_write($data)
    {
        $before = substr(self::$buffers[$this->key], 0, $this->position);
        $after = substr(self::$buffers[$this->key], $this->position + strlen($data));
        self::$buffers[$this->key] = $before . $data . $after;
        $this->position += strlen($data);

        return strlen($data);
    }

    public function stream_eof()
    {
        return $this->position >= strlen(self::$buffers[$this->key]);
    }

    public function stream_seek($offset, $whence)
    {
        if ($whence === SEEK_SET && $offset >= 0) {
            $this->position = $offset;

            return true;
        }

        return false;
    }

    public function stream_tell()
    {
        return $this->position;
    }

    public function stream_stat()
    {
        return array();
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        return true;
    }

    // phpcs:enable PSR1.Methods.CamelCapsMethodName
}

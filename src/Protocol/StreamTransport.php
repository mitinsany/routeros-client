<?php

namespace MitinSany\RouterOS\Protocol;

use InvalidArgumentException;

class StreamTransport
{
    private $stream;

    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('RouterOS transport requires a stream resource.');
        }

        $this->stream = $stream;
    }

    public function writeSentence(array $words)
    {
        foreach ($words as $word) {
            $this->writeAll(WordCodec::encodeWord($word));
        }

        $this->writeAll(WordCodec::encodeLength(0));
    }

    public function readSentence()
    {
        $words = array();

        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                return $words;
            }

            $words[] = $word;
        }
    }

    public function setTimeout($seconds)
    {
        $this->ensureOpen();

        if (!is_int($seconds) || $seconds < 0 || !stream_set_timeout($this->stream, $seconds)) {
            throw new InvalidArgumentException('RouterOS timeout must be a non-negative integer.');
        }
    }

    public function close()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    private function readWord()
    {
        $transport = $this;

        return WordCodec::decodeWord(function ($length) use ($transport) {
            return $transport->readBytes($length);
        });
    }

    private function readBytes($length)
    {
        $this->ensureOpen();
        $value = '';

        while (strlen($value) < $length) {
            $chunk = @fread($this->stream, $length - strlen($value));
            if ($chunk === false || $chunk === '') {
                $metadata = stream_get_meta_data($this->stream);
                if (!empty($metadata['timed_out'])) {
                    throw new TransportException('RouterOS transport timed out.');
                }

                throw new TransportException('RouterOS transport reached end of stream.');
            }

            $value .= $chunk;
        }

        return $value;
    }

    private function writeAll($value)
    {
        $this->ensureOpen();
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            $written = @fwrite($this->stream, substr($value, $offset));
            if ($written === false || $written === 0) {
                $metadata = stream_get_meta_data($this->stream);
                if (!empty($metadata['timed_out'])) {
                    throw new TransportException('RouterOS transport timed out.');
                }

                throw new TransportException('RouterOS transport could not write bytes.');
            }

            $offset += $written;
        }
    }

    private function ensureOpen()
    {
        if (!is_resource($this->stream)) {
            throw new TransportException('RouterOS transport is closed.');
        }
    }
}

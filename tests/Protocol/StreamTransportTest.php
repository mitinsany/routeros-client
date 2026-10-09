<?php

namespace MitinSany\RouterOS\Tests\Protocol;

use MitinSany\RouterOS\Protocol\StreamTransport;
use MitinSany\RouterOS\Protocol\TransportException;
use MitinSany\RouterOS\Tests\Support\MemoryStream;
use PHPUnit\Framework\TestCase;

class StreamTransportTest extends TestCase
{
    public function testWriteSentenceTerminatesWordsWithAnEmptyWord()
    {
        $stream = MemoryStream::open('', 2);
        $transport = new StreamTransport($stream);

        $transport->writeSentence(array('/interface/print', '=name=ether1'));
        rewind($stream);

        $this->assertSame("\x10/interface/print\x0c=name=ether1\x00", stream_get_contents($stream));
    }

    public function testReadSentenceAccumulatesWordsFromChunkedStream()
    {
        $stream = MemoryStream::open("\x03!re\x0c=name=ether1\x00", 1);
        $transport = new StreamTransport($stream);

        $this->assertSame(array('!re', '=name=ether1'), $transport->readSentence());
    }

    public function testReadSentenceRejectsAnIncompleteWord()
    {
        $this->setExpectedException('MitinSany\\RouterOS\\Protocol\\TransportException');

        $stream = MemoryStream::open("\x04ab", 2);
        $transport = new StreamTransport($stream);
        $transport->readSentence();
    }

    public function testReadSentenceRejectsSocketTimeout()
    {
        if (!function_exists('stream_socket_pair')) {
            $this->markTestSkipped('stream_socket_pair is unavailable.');
        }

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        stream_set_timeout($pair[0], 0, 1000);

        $this->setExpectedException('MitinSany\\RouterOS\\Protocol\\TransportException');

        $transport = new StreamTransport($pair[0]);
        $transport->readSentence();
    }
}

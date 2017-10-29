<?php

use Thrift\Exception\TTransportException;
use Jaeger\TUDPTransport;
use PHPUnit\Framework\TestCase;

class TUDPTransportTest extends TestCase
{
    private $transport;

    public function setUp()
    {
        $this->transport = new TUDPTransport('127.0.0.1', 12345);
    }

    public function testisOpenWhenOpen()
    {
        $this->assertTrue($this->transport->isOpen());
    }

    public function testisOpenWhenClosed()
    {
        $this->transport->close();
        $this->assertFalse($this->transport->isOpen());
    }

    public function testClose()
    {
        $this->transport->close();

        $this->expectException(TTransportException::class);
        $this->expectExceptionMessage('transport is closed');
        $this->transport->write('hello');
    }
}

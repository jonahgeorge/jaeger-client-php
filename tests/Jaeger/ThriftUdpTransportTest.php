<?php

namespace Jaeger;

use Thrift\Exception\TTransportException;
use PHPUnit\Framework\TestCase;

class ThriftUdpTransportTest extends TestCase
{
    /**
     * @var ThriftUdpTransport
     */
    private $transport;

    public function setUp()
    {
        $this->transport = new ThriftUdpTransport('127.0.0.1', 12345);
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

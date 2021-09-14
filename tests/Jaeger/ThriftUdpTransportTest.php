<?php

namespace Jaeger\Tests;

use Jaeger\ThriftUdpTransport;
use PHPUnit\Framework\TestCase;
use Thrift\Exception\TTransportException;

class ThriftUdpTransportTest extends TestCase
{
    /**
     * @var ThriftUdpTransport
     */
    private $transport;

    public function setUp(): void
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

    public function testException() {
        $this->transport->open();

        $this->expectException(TTransportException::class);

        $msgRegEx = "/socket_write failed: \[code - \d+\] Message too long/";
        if (method_exists($this, "expectExceptionMessageRegExp")) {
            $this->expectExceptionMessageRegExp($msgRegEx);
        } else {
            $this->expectExceptionMessageMatches($msgRegEx);
        }

        $this->transport->write(str_repeat("some string", 10000));
    }
}

<?php

namespace Jaeger\Tests;

use Jaeger\Tests\Logger\StackLogger;
use Jaeger\ThriftUdpTransport;
use PHPUnit\Framework\TestCase;
use Thrift\Exception\TTransportException;

class ThriftUdpTransportTest extends TestCase
{
    /**
     * @var ThriftUdpTransport
     */
    private $transport;

    /**
     * @var StackLogger
     */
    private $logger;

    public function setUp(): void
    {
        $this->logger = new StackLogger();
        $this->transport = new ThriftUdpTransport('127.0.0.1', 12345, $this->logger);
    }

    public function testisOpenWhenOpen()
    {
        $this->assertEquals($this->logger->getMessagesCount(), 0);
        $this->assertTrue($this->transport->isOpen());
        $this->assertEquals($this->logger->getMessagesCount(), 0);
    }

    public function testisOpenWhenClosed()
    {
        $this->assertEquals($this->logger->getMessagesCount(), 0);
        $this->transport->close();
        $this->assertFalse($this->transport->isOpen());
        $this->assertEquals($this->logger->getMessagesCount(), 0);
    }

    public function testClose()
    {
        $this->assertEquals($this->logger->getMessagesCount(), 0);
        $this->transport->close();

        $this->assertEquals($this->logger->getMessagesCount(), 0);
        $this->transport->write('hello');
        $this->assertEquals($this->logger->getMessagesCount(), 1);
        $this->assertEquals($this->logger->getLastMessage(), 'transport is closed');
        $this->assertEquals($this->logger->getMessagesCount(), 0);
    }

    public function testDoubleClose() {
        $this->assertEquals($this->logger->getMessagesCount(), 0);
        $this->transport->close();
        $this->assertEquals($this->logger->getMessagesCount(), 0);
        $this->transport->close();
        $this->assertEquals($this->logger->getMessagesCount(), 1);
        $this->assertEquals(
            $this->logger->getLastMessage(),
            "can't close empty socket"
        );
    }

    public function testException() {
        $this->assertEquals($this->logger->getMessagesCount(), 0);
        $this->transport->open();
        $this->assertEquals($this->logger->getMessagesCount(), 0);

        $this->transport->write(str_repeat("some string", 10000));

        $this->assertEquals($this->logger->getMessagesCount(), 1);
        $msg = $this->logger->getLastMessage();
        $pattern = "/socket_write failed: \[code - \d+\] Message too long/";

        if (method_exists($this, "assertMatchesRegularExpression")) {
            $this->assertMatchesRegularExpression($pattern, $msg);
        } else {
            $this->assertRegExp($pattern, $msg);
        }

    }
}

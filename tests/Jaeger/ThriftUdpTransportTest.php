<?php

namespace Jaeger\Tests;

use Jaeger\Config;
use Jaeger\Tests\Logger\StackLogger;
use Jaeger\ThriftUdpTransport;
use Jaeger\Tracer;
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

    public function testDoubleClose()
    {
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

    public function testException()
    {
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

    public function testProtocolVersionIPv4()
    {
        $config = new Config([
            Config::IP_VERSION => Config::IPV4
        ], "testServiceName");

        $transport = new ThriftUdpTransport('127.0.0.1', 12345, $this->logger, $config);

        $reflectionTransport = new \ReflectionClass($transport);
        $ipProtocolVersionMethod = $reflectionTransport->getMethod("ipProtocolVersion");
        $ipProtocolVersionMethod->setAccessible(true);

        $this->assertEquals(Config::IPV4, $ipProtocolVersionMethod->invoke($transport));
    }

    public function testProtocolVersionIPv6()
    {
        $config = new Config([
            Config::IP_VERSION => Config::IPV6
        ], "testServiceName");

        $transport = new ThriftUdpTransport('127.0.0.1', 12345, $this->logger, $config);
//
        $reflectionTransport = new \ReflectionClass($transport);
        $ipProtocolVersionMethod = $reflectionTransport->getMethod("ipProtocolVersion");
        $ipProtocolVersionMethod->setAccessible(true);
//
        $this->assertEquals(Config::IPV6, $ipProtocolVersionMethod->invoke($transport));
    }

    public function testProtocolVersionDefault()
    {
        $config = new Config([
        ], "testServiceName");

        $transport = new ThriftUdpTransport('127.0.0.1', 12345, $this->logger, $config);

        $reflectionTransport = new \ReflectionClass($transport);
        $ipProtocolVersionMethod = $reflectionTransport->getMethod("ipProtocolVersion");
        $ipProtocolVersionMethod->setAccessible(true);

        $this->assertEquals(Config::IPV4, $ipProtocolVersionMethod->invoke($transport));
    }

    public function testCreateSocket()
    {
        $transport = $this->getMockBuilder(ThriftUdpTransport::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflectionClass = new \ReflectionClass($transport);
        $method = $reflectionClass->getMethod("setLogger");
        $method->setAccessible(true);
        $method->invokeArgs($transport, [$this->logger]);

        $method = $reflectionClass->getMethod("createSocket");
        $method->setAccessible(true);
        $res = $method->invokeArgs($transport, [Config::IPV6]);

        $this->assertNotFalse($res);


        $transport = $this->getMockBuilder(ThriftUdpTransport::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflectionClass = new \ReflectionClass($transport);
        $method = $reflectionClass->getMethod("setLogger");
        $method->setAccessible(true);
        $method->invokeArgs($transport, [$this->logger]);

        $method = $reflectionClass->getMethod("createSocket");
        $method->setAccessible(true);
        $res = $method->invokeArgs($transport, [Config::IPV4]);

        $this->assertNotFalse($res);
    }
}

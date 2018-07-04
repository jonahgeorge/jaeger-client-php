<?php

namespace Jaeger\Sender;

use PHPUnit\Framework\TestCase;
use Jaeger\ThriftGen\AgentClient;
use Jaeger\Tracer;
use Jaeger\SpanContext;
use Jaeger\Span;

class UdpSenderTest extends TestCase
{
    /**
     * @var UdpSender
     */
    private $sender;

    /**
     * @var AgentClient
     */
    private $client;

    function setUp()
    {
        $this->client = $this->createMock(AgentClient::class);
        $this->sender = new UdpSender($this->client, 64000);
    }

    function testMaxBufferLength()
    {
        $tracer = $this->createMock(Tracer::class);
        $tracer->method('getIpAddress')->willReturn('');
        $tracer->method('getServiceName')->willReturn('');

        $context = $this->createMock(SpanContext::class);

        $span = $this->createMock(Span::class);
        $span->method('getOperationName')->willReturn('dummy-operation');
        $span->method('getTracer')->willReturn($tracer);
        $span->method('getContext')->willReturn($context);

        $sender = new UdpSender($this->client, 100);

        $this->client->expects($this->at(0))->method('emitZipkinBatch')->with($this->countOf(2));
        $this->client->expects($this->at(1))->method('emitZipkinBatch')->with($this->countOf(1));

        // one span has a length of ~25
        $sender->append($span); // 30 + 25 < 100 - chunk 1
        $sender->append($span); // 30 + 25 * 2 < 100 - chunk 1
        $sender->append($span); // 30 + 25 * 3 > 100 - chunk 2

        $this->assertEquals(3, $sender->flush($span));
    }

    function testFlush()
    {
        $this->assertEquals(0, $this->sender->flush());

        $tracer = $this->createMock(Tracer::class);
        $tracer->method('getIpAddress')->willReturn('');
        $tracer->method('getServiceName')->willReturn('');
        $context = $this->createMock(SpanContext::class);
        $span = $this->createMock(Span::class);
        $span->method('getTracer')->willReturn($tracer);
        $span->method('getContext')->willReturn($context);

        $this->sender->append($span);
        $this->assertEquals(1, $this->sender->flush());
    }
}
<?php

namespace Jaeger\Sender;

use PHPUnit\Framework\TestCase;
use Jaeger\Thrift\Agent\AgentClient;
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
        $this->sender = new UdpSender($this->client);
    }

    function testAppendUnderBatchSize()
    {
        $tracer = $this->createMock(Tracer::class);
        $tracer->method('getIpAddress')->willReturn('');
        $tracer->method('getServiceName')->willReturn('');

        $context = $this->createMock(SpanContext::class);

        $span = $this->createMock(Span::class);
        $span->method('getTracer')->willReturn($tracer);
        $span->method('getContext')->willReturn($context);

        $sender = new UdpSender($this->client);

        $this->assertEquals(0, $sender->append($span));
    }

    function testAppendAboveBatchSize()
    {
        $tracer = $this->createMock(Tracer::class);
        $tracer->method('getIpAddress')->willReturn('');
        $tracer->method('getServiceName')->willReturn('');
        $context = $this->createMock(SpanContext::class);
        $span = $this->createMock(Span::class);
        $span->method('getTracer')->willReturn($tracer);
        $span->method('getContext')->willReturn($context);

        $sender = new UdpSender($this->client, 0);

        $this->client->expects($this->once())->method('emitZipkinBatch');

        $this->assertEquals(1, $sender->append($span));
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
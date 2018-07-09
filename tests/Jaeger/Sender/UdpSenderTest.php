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

        $logTimeStamp = (int) (microtime(true) * 1000000);

        $tracer = $this->createMock(Tracer::class);
        $tracer->method('getIpAddress')->willReturn('');
        $tracer->method('getServiceName')->willReturn('');
        $context = $this->createMock(SpanContext::class);
        $span = $this->createMock(Span::class);
        $span->method('getTracer')->willReturn($tracer);
        $span->method('getContext')->willReturn($context);
        $span
            ->expects($this->atLeastOnce())
            ->method('getLogs')
            ->willReturn([
                [
                    'timestamp' => $logTimeStamp,
                    'fields' => [
                        'foo' => 'bar',
                    ],
                ],
            ]);

        $this->client
            ->expects($this->once())
            ->method('emitZipkinBatch')
            ->with($this->callback(function ($spans) use ($logTimeStamp) {
                $this->assertCount(1, $spans);

                /* @var $annotation \Jaeger\ThriftGen\Span */
                $span = $spans[0];
                $this->assertInstanceOf(\Jaeger\ThriftGen\Span::class, $span);
                $this->assertCount(1, $span->annotations);

                /* @var $annotation \Jaeger\ThriftGen\Annotation */
                $annotation = $span->annotations[0];
                $this->assertInstanceOf(\Jaeger\ThriftGen\Annotation::class, $annotation);
                $this->assertSame($logTimeStamp, $annotation->timestamp);
                $this->assertSame(
                    json_encode([
                        'foo' => 'bar',
                    ]),
                    $annotation->value
                );

                return true;
            }));

        $this->sender->append($span);
        $this->assertEquals(1, $this->sender->flush());
    }
}
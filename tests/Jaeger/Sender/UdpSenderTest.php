<?php

namespace Jaeger\Tests\Sender;

use Jaeger\Sender\UdpSender;
use Jaeger\Span;
use Jaeger\SpanContext;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Agent\Zipkin\Annotation as ZipkinAnnotation;
use Jaeger\Thrift\Agent\Zipkin\Span as ZipkinSpan;
use Jaeger\Tracer;
use PHPUnit\Framework\TestCase;

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

                /* @var $annotation ZipkinSpan */
                $span = $spans[0];
                $this->assertInstanceOf(ZipkinSpan::class, $span);
                $this->assertCount(1, $span->annotations);

                /* @var $annotation ZipkinAnnotation */
                $annotation = $span->annotations[0];
                $this->assertInstanceOf(ZipkinAnnotation::class, $annotation);
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

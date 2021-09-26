<?php


namespace Jaeger\Tests\Sender;

use Jaeger\Sender\JaegerSender;
use Jaeger\Sender\UdpSender;
use Jaeger\Span;
use Jaeger\SpanContext;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Batch;
use Jaeger\Tracer;
use PHPUnit\Framework\TestCase;

class JaegerThriftSenderTest extends TestCase
{
    /** @var Tracer|\PHPUnit\Framework\MockObject\MockObject  */
    private $tracer;

    /** @var SpanContext|\PHPUnit\Framework\MockObject\MockObject  */
    private $context;

    public function setUp(): void
    {
        $tracer = $this->createMock(Tracer::class);
        $tracer->method('getIpAddress')->willReturn('');
        $tracer->method('getServiceName')->willReturn('');

        $this->tracer = $tracer;

        $context = $this->createMock(SpanContext::class);
        $this->context = $context;
    }

    public function testFlush(): void
    {

        $span = $this->createMock(Span::class);
        $span->method('getOperationName')->willReturn('dummy-operation');
        $span->method('getTracer')->willReturn($this->tracer);
        $span->method('getContext')->willReturn($this->context);

        $client = $this->createMock(AgentClient::class);
        $sender = new JaegerSender($client);
        $sender->setMaxBufferLength(64000);

        $client
            ->expects(self::exactly(1))
            ->method('emitBatch');

        $sender->append($span);
        $sender->append($span);
        $sender->append($span);

        self::assertEquals(3, $sender->flush());
    }

    public function testEmitBatch() {
        $client = $this->createMock(AgentClient::class);
        $sender = new JaegerSender($client);
        $sender->setMaxBufferLength(64000);

        $span = $this->createMock(Span::class);
        $span->method('getOperationName')->willReturn('dummy-operation');
        $span->method('getTracer')->willReturn($this->tracer);
        $span->method('getContext')->willReturn($this->context);

        $client
            ->expects($this->once())
            ->method('emitBatch')
            ->with($this->callback(function ($batch) {
                /** @var Batch $batch */
                $this->assertInstanceOf(Batch::class, $batch);
                $this->assertCount(1, $batch->spans);

                /** @var \Jaeger\Thrift\Span $span */
                $span = $batch->spans[0];
                $this->assertInstanceOf(\Jaeger\Thrift\Span::class, $span);
                $this->assertSame("dummy-operation", $span->operationName);

                return true;

            }));

        $sender->append($span);
        $this->assertEquals(1, $sender->flush());
    }

    public function testMaxBufferLength() {
        $tracer = $this->createMock(Tracer::class);
        $tracer->method('getIpAddress')->willReturn('');
        $tracer->method('getServiceName')->willReturn('');

        $context = $this->createMock(SpanContext::class);

        $span = $this->createMock(Span::class);
        $span->method('getOperationName')->willReturn('dummy-operation');
        $span->method('getTracer')->willReturn($tracer);
        $span->method('getContext')->willReturn($context);

        $client = $this->createMock(AgentClient::class);

        $mockBuilder = $this->getMockBuilder(JaegerSender::class);
        $mockMethods = ['emitJaegerBatch'];
        if (method_exists($mockBuilder, "onlyMethods")) {
            $mockBuilder = $mockBuilder->onlyMethods($mockMethods);
        } else {
            $mockBuilder = $mockBuilder->setMethods($mockMethods);
        }
        $sender = $mockBuilder->setConstructorArgs([$client])->getMock();
        $sender->setMaxBufferLength(800);
        $sender->expects(self::exactly(2))
            ->method('emitJaegerBatch')
            ->withConsecutive(
                [self::countOf(2)],
                [self::countOf(1)]
            );

        // jaeger batch overhead parameter = 512
        $sender->append($span); // 512 + 143 < 800 - chunk 1
        $sender->append($span); // 512 + 143*2 => 798 < 800 - chunk 1
        $sender->append($span); // 512 + 143*3 > 800 - chunk 2

        self::assertEquals(3, $sender->flush());
    }
}

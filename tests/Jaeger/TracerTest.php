<?php

namespace Jaeger\Tests;

use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\SamplerInterface;
use Jaeger\Scope;
use Jaeger\ScopeManager;
use Jaeger\Span;
use Jaeger\SpanContext;
use Jaeger\Tracer;
use OpenTracing\Exceptions\UnsupportedFormat;
use OpenTracing\NoopSpanContext;
use OpenTracing\UnsupportedFormatException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use const Jaeger\BAGGAGE_HEADER_PREFIX;
use const Jaeger\DEBUG_ID_HEADER_KEY;
use const Jaeger\TRACE_ID_HEADER;
use const Jaeger\ZIPKIN_SPAN_FORMAT;
use const OpenTracing\Formats\TEXT_MAP;

class TracerTest extends TestCase
{
    /**
     * @var ReporterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $reporter;

    /**
     * @var SamplerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $sampler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeManager|\PHPUnit\Framework\MockObject\MockObject
     */
    private $scopeManager;

    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @var string
     */
    private $serviceName = 'test-service';

    /**
     * @var string
     */
    private $operationName = 'test-operation';

    public function setUp(): void
    {
        $this->scopeManager = $this->createMock(ScopeManager::class);
        $this->sampler = $this->createMock(SamplerInterface::class);
        $this->reporter = $this->createMock(ReporterInterface::class);
        $this->logger = new NullLogger();

        $this->tracer = new Tracer($this->serviceName, $this->reporter, $this->sampler, true, $this->logger, $this->scopeManager);
    }

    public function testStartSpan(): void
    {
        $span = $this->tracer->startSpan($this->operationName);

        $this->assertEquals($this->operationName, $span->getOperationName());
    }

   public function testStartActiveSpan(): void
   {
        $tracer = new Tracer($this->serviceName, $this->reporter, $this->sampler);

        $tracer->startActiveSpan('test-operation1');
        $this->assertEquals('test-operation1', $tracer->getActiveSpan()->getOperationName());

        $scope = $tracer->startActiveSpan('test-operation2');
        $this->assertEquals('test-operation2', $tracer->getActiveSpan()->getOperationName());
        $scope->close();

        $this->assertEquals('test-operation1', $tracer->getActiveSpan()->getOperationName());
   }

    /** @test */
    public function shouldAddConfiguredTagsToStartedSpanWhenSampled(): void
    {
        $this->sampler->expects($this->any())
            ->method('isSampled')
            ->willReturn([true, []]);

        $tags = [
            'bar' => 'a-value',
            'other.tag' => 'foo',
        ];

        $tracer = new Tracer(
            $this->serviceName,
            $this->reporter,
            $this->sampler,
            true,
            $this->logger,
            $this->scopeManager,
            TRACE_ID_HEADER,
            BAGGAGE_HEADER_PREFIX,
            DEBUG_ID_HEADER_KEY,
            $tags
        );

        $span = $tracer->startSpan('test-span');
        $spanTags = $span->getTags();

        foreach ($tags as $name => $value) {
            $this->assertArrayHasKey($name, $spanTags, "Tag '$name' should be set on span");
            $this->assertEquals($value, $spanTags[$name]->value, "Tag '$name' should have configured value");
        }
    }

    /** @test */
    public function shouldAddNoConfiguredTagsToStartedSpanWhenNotSampled(): void
    {
        $this->sampler->expects($this->any())
            ->method('isSampled')
            ->willReturn([false, []]);

        $tags = [
            'bar' => 'a-value',
            'other.tag' => 'foo',
        ];

        $tracer = new Tracer(
            $this->serviceName,
            $this->reporter,
            $this->sampler,
            true,
            $this->logger,
            $this->scopeManager,
            TRACE_ID_HEADER,
            BAGGAGE_HEADER_PREFIX,
            DEBUG_ID_HEADER_KEY,
            $tags
        );

        $span = $tracer->startSpan('test-span');

        $this->assertEquals([], $span->getTags(), 'No tags should be set when not sampled');
    }

    /** @test */
    public function shouldThrowExceptionOnInvalidFormat(): void
    {
        $spanContext = new SpanContext(0, 0, 0, 0);
        $carrier = [];

        $this->expectException(UnsupportedFormatException::class);
        $this->expectExceptionMessage('The format "bad-format" is not supported.');

        $this->tracer->inject($spanContext, 'bad-format', $carrier);
        $this->assertSame([], $carrier);
    }

    /** @test */
    public function shouldNotThrowExceptionOnInvalidContext(): void
    {
        $spanContext = new NoopSpanContext();
        $carrier = [];

        $this->tracer->inject($spanContext, ZIPKIN_SPAN_FORMAT, $carrier);
        $this->assertSame([], $carrier);
    }

    /** @test */
    public function shouldInjectSpanContextToCarrier(): void
    {
        $spanContext = new SpanContext(0, 0, 0, 0);
        $carrier = [];

        $this->tracer->inject($spanContext, TEXT_MAP, $carrier);

        $this->assertCount(1, $carrier);
        $this->assertEquals('0000000000000000:0:0:0', $carrier[TRACE_ID_HEADER]);
    }

    /** @test */
    public function shouldThrowExceptionOnExtractInvalidFormat(): void
    {
        $this->expectException(UnsupportedFormatException::class);
        $this->expectExceptionMessage('The format "bad-format" is not supported.');

        $this->tracer->extract('bad-format', []);
    }

    /** @test */
    public function shouldNotThrowExceptionOnExtractFromMalformedState(): void
    {
        $this->assertNull($this->tracer->extract(TEXT_MAP, ['uber-trace-id' => '']));
    }

    /** @test */
    public function shouldExtractSpanContextFromCarrier(): void
    {
        $carrier = ['uber-trace-id' => '32834e4115071776:f7802330248418d:f123456789012345:1'];

        $this->assertInstanceOf(SpanContext::class, $this->tracer->extract(TEXT_MAP, $carrier));
    }

    public function testGetScopeManager()
    {
        $this->assertEquals($this->scopeManager, $this->tracer->getScopeManager());
    }

    public function testGetActiveSpan(): void
    {
        $span = $this->createMock(Span::class);
        $scope = $this->createMock(Scope::class);
        $scope->expects($this->once())->method('getSpan')->willReturn($span);

        $this->scopeManager->expects($this->once())->method('getActive')->willReturn($scope);

        $this->assertEquals($span, $this->tracer->getActiveSpan());
    }

    public function testGetActiveSpanNull(): void
    {
        $this->scopeManager->expects($this->once())->method('getActive')->willReturn(null);

        $this->assertEquals(null, $this->tracer->getActiveSpan());
    }

    public function testFlush(): void
    {
        $this->reporter->expects($this->once())->method('close');

        $this->tracer->flush();
    }

    /** @test */
    public function shouldHandleEmptyHostName(): void
    {
        $tracer = new \ReflectionClass(Tracer::class);

        $getHostByName = $tracer->getMethod('getHostByName');
        $getHostByName->setAccessible(true);

        $stub = $this->getMockBuilder(Tracer::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $logger = $tracer->getProperty('logger');
        $logger->setAccessible(true);
        $logger->setValue($stub, $this->logger);

        $this->assertEquals('127.0.0.1', $getHostByName->invokeArgs($stub, [null]));
    }
}

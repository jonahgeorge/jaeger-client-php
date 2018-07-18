<?php

namespace Jaeger;

use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\SamplerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use InvalidArgumentException;
use OpenTracing\NoopSpanContext;
use OpenTracing\Exceptions\UnsupportedFormat;

class TracerTest extends TestCase
{
    /**
     * @var ReporterInterface
     */
    private $reporter;

    /**
     * @var SamplerInterface
     */
    private $sampler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeManager
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

    function setUp()
    {
        $this->scopeManager = $this->createMock(ScopeManager::class);
        $this->sampler = $this->createMock(SamplerInterface::class);
        $this->reporter = $this->createMock(ReporterInterface::class);
        $this->logger = new NullLogger();

        $this->tracer = new Tracer($this->serviceName, $this->reporter, $this->sampler, true, $this->logger, $this->scopeManager);
    }

    function testStartSpan()
    {
        $span = $this->tracer->startSpan($this->operationName);

        $this->assertEquals($this->operationName, $span->getOperationName());
    }

   function testStartActiveSpan()
   {
        $tracer = new Tracer($this->serviceName, $this->reporter, $this->sampler);

        $tracer->startActiveSpan('test-operation1');
        $this->assertEquals('test-operation1', $tracer->getActiveSpan()->getOperationName());

        $scope = $tracer->startActiveSpan('test-operation2');
        $this->assertEquals('test-operation2', $tracer->getActiveSpan()->getOperationName());
        $scope->close();

        $this->assertEquals('test-operation1', $tracer->getActiveSpan()->getOperationName());
   }

    function testInjectInvalidContext()
    {
        $spanContext = new NoopSpanContext();
        $carrier = [];

        $this->expectException(InvalidArgumentException::class);

        $this->tracer->inject($spanContext, ZIPKIN_SPAN_FORMAT, $carrier);
    }

    function testExtractInvalidFormat()
    {
        $this->expectException(UnsupportedFormat::class);

        $spanContext = $this->tracer->extract("bad-format", []);
    }

    function testGetScopeManager()
    {
        $this->assertEquals($this->scopeManager, $this->tracer->getScopeManager());
    }

    function testGetActiveSpan()
    {
        $span = $this->createMock(Span::class);
        $scope = $this->createMock(Scope::class);
        $scope->expects($this->once())->method('getSpan')->willReturn($span);

        $this->scopeManager->expects($this->once())->method('getActive')->willReturn($scope);

        $this->assertEquals($span, $this->tracer->getActiveSpan());
    }

    function testGetActiveSpanNull()
    {
        $this->scopeManager->expects($this->once())->method('getActive')->willReturn(null);

        $this->assertEquals(null, $this->tracer->getActiveSpan());
    }

    function testFlush()
    {
        $this->reporter->expects($this->once())->method('close');

        $this->tracer->flush();
    }

    /** @test */
    public function shouldHandleEmptyHostName()
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

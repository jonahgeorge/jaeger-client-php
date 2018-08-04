<?php

namespace Jaeger\Tests;

use Exception;
use Jaeger\Config;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\SamplerInterface;
use Jaeger\Tracer;
use OpenTracing\GlobalTracer;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ReporterInterface
     */
    private $reporter;

    /**
     * @var SamplerInterface
     */
    private $sampler;

    /**
     * @var string
     */
    private $serviceName = 'test-service';

    function setUp()
    {
        $this->config = new Config([], $this->serviceName);
        $this->reporter = $this->createMock(ReporterInterface::class);
        $this->sampler = $this->createmock(SamplerInterface::class);
    }

    function testCreateTracer()
    {
        $tracer = $this->config->createTracer($this->reporter, $this->sampler);

        $this->assertEquals(Tracer::class, get_class($tracer));
        $this->assertEquals($this->serviceName, $tracer->getServiceName());
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage service_name required in the config or param
     */
    function testThrowExceptionWhenServiceNameIsNotDefined()
    {
        new Config([]);
    }

    function testSetServiceNameFromConfig()
    {
        $config = new Config(['service_name' => 'test-service-name-from-config']);

        $serviceName = $config->getServiceName();

        $this->assertEquals('test-service-name-from-config', $serviceName);
    }

    /**
     * @test
     */
    public function shouldSetGlobalTracerAfterInitialize()
    {
        //given
        $config = new Config(['service_name' => 'test-service-name']);

        //when
        $config->initializeTracer();

        //then
        $tracer = GlobalTracer::get();
        $this->assertInstanceOf(Tracer::class, $tracer);
    }
}

<?php

namespace Jaeger;

use PHPUnit\Framework\TestCase;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\SamplerInterface;

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
}
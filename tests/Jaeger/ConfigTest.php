<?php

namespace Jaeger\Tests;

use Exception;
use Jaeger\Config;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\SamplerInterface;
use Jaeger\Tracer;
use OpenTracing\GlobalTracer;
use PHPUnit\Framework\TestCase;
use const Jaeger\SAMPLER_TYPE_CONST;

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

    function setUp(): void
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

    function testThrowExceptionWhenServiceNameIsNotDefined()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('service_name required in the config or param.');

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

    /** @test */
    public function shouldThrowExceptionWhenCreatingNotSupportedSampler()
    {
        $config = new Config(['service_name' => 'test-service-name', 'sampler' => ['type' => 'unsupportedSampler']]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown sampler type unsupportedSampler');

        $config->initializeTracer();
    }

    /** @test */
    public function shouldThrowExceptionWhenCreatingRateLimitingSamplerWithoutCacheComponent()
    {
        $config = new Config([
            'service_name' => 'test-service-name',
            'sampler' => ['type' => \Jaeger\SAMPLER_TYPE_RATE_LIMITING]
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You cannot use RateLimitingSampler without cache component');

        $config->initializeTracer();
    }

    /** @test  */
    public function shouldPassDifferentDispatchMode() {
        foreach (Config::getAvailableDispatchModes() as $dispatchMode) {
            $config = new Config(
                [
                    'sampler' => [
                        'type' => SAMPLER_TYPE_CONST,
                        'param' => true,
                    ],
                    'logging' => false,
                    "local_agent" => [
                        "reporting_host" => "localhost",
                    ],
                    'dispatch_mode' => $dispatchMode,
                ],
                'your-app-name'
            );
            $config->initializeTracer();
            $this->expectNotToPerformAssertions();
        }
    }

    /** @test */
    public function shouldPassConfiguredTagsToTracer()
    {
        $tags = [
            'bar' => 'a-value',
            'other.tag' => 'foo',
        ];

        $config = new Config([
            'sampler' => [
                'type' => SAMPLER_TYPE_CONST,
                'param' => true,
            ],
            'service_name' => 'test-service-name',
            'tags' => $tags,
        ]);

        $tracer = $config->initializeTracer();
        $span = $tracer->startSpan('test-span');
        $spanTags = $span->getTags();

        foreach ($tags as $name => $value) {
            $this->assertArrayHasKey($name, $spanTags, "Tag '$name' should be set on span");
            $this->assertEquals($value, $spanTags[$name]->value, "Tag '$name' should have configured value");
        }
    }

    /**
     * @test
     * @dataProvider shouldSetConfigPropertiesFromEnvVarsProvider
     */
    public function shouldSetConfigPropertiesFromEnvVars($varName, $varVal, $initialConfig, $valueGetter, $expectedVal)
    {
        $_ENV[$varName] = $varVal;

        $config = new Config([]);
        $configProperty = (new \ReflectionObject($config))->getProperty('config');
        $configProperty->setAccessible('true');
        $configArray = $configProperty->getValue($config);

        $this->assertSame($expectedVal, $valueGetter($configArray));
    }

    /**
     * @test
     * @dataProvider shouldSetConfigPropertiesFromEnvVarsProvider
     */
    public function shouldNotSetConfigPropertiesFromEnvVars($varName, $varVal, $initialConfig, $valueGetter, $expectedVal)
    {
        $_ENV[$varName] = $varVal;

        $config = new Config($initialConfig);
        $configProperty = (new \ReflectionObject($config))->getProperty('config');
        $configProperty->setAccessible('true');
        $configArray = $configProperty->getValue($config);

        $this->assertNotEquals($expectedVal, $valueGetter($configArray));
    }

    /**
     *  0 -> varName
     *  1 -> varVal
     *  2 -> initialConfig
     *  3 -> valueGetter
     *  4 -> expectedVal
     */
    public function shouldSetConfigPropertiesFromEnvVarsProvider() {
        return [
            [
                'JAEGER_SERVICE_NAME',
                'some-str',
                ['service_name' => 'some-other-str'],
                function ($a) { return $a['service_name']; },
                'some-str',
            ],
            [
                'JAEGER_TAGS',
                'some-str',
                ['tags' => 'some-other-str'],
                function ($a) { return $a['tags']; },
                'some-str',
            ],
            [
                'JAEGER_AGENT_HOST',
                'some-str',
                ['local_agent' => ['reporting_host' => 'some-other-str']],
                function ($a) { return $a['local_agent']['reporting_host'];},
                'some-str',
            ],
            [
                'JAEGER_AGENT_PORT',
                '2222',
                ['local_agent' => ['reporting_port' => 1111]],
                function ($a) { return $a['local_agent']['reporting_port']; },
                2222,
            ],
            [
                'JAEGER_REPORTER_LOG_SPANS',
                'true',
                ['logging' => false],
                function ($a) { return $a['logging']; },
                true,
            ],
            [
                'JAEGER_REPORTER_MAX_QUEUE_SIZE',
                '2222',
                ['max_buffer_length' => 1111],
                function ($a) { return $a['max_buffer_length']; },
                2222,
            ],
            [
                'JAEGER_SAMPLER_TYPE',
                'some-str',
                ['sampler' => ['type' => 'some-other-str']],
                function ($a) { return $a['sampler']['type']; },
                'some-str',
            ],
            [
                'JAEGER_SAMPLER_PARAM',
                'some-str',
                ['sampler' => ['param' => 'some-other-str']],
                function ($a) { return $a['sampler']['param']; },
                'some-str',
            ],
        ];
    }
}

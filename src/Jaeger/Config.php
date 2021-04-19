<?php

namespace Jaeger;

use Exception;
use Jaeger\Reporter\CompositeReporter;
use Jaeger\Reporter\LoggingReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\ReporterFactory\JaegerHttpReporterFactory;
use Jaeger\ReporterFactory\JaegerReporterFactory;
use Jaeger\ReporterFactory\ZipkinReporterFactory;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\ProbabilisticSampler;
use Jaeger\Sampler\RateLimitingSampler;
use Jaeger\Sampler\SamplerInterface;
use Jaeger\Util\RateLimiter;
use OpenTracing\GlobalTracer;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Config
{
    const ZIPKIN_OVER_COMPACT_UDP   = "zipkin_over_compact_udp";
    const JAEGER_OVER_BINARY_UDP    = "jaeger_over_binary_udp";
    const JAEGER_OVER_BINARY_HTTP   = "jaeger_over_binary_http";

    /**
     * @return string[]
     */
    public static function getAvailableDispatchModes()
    {
        return [self::ZIPKIN_OVER_COMPACT_UDP, self::JAEGER_OVER_BINARY_UDP, self::JAEGER_OVER_BINARY_HTTP];
    }

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $serviceName;

    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * Config constructor.
     * @param array $config
     * @param string|null $serviceName
     * @param LoggerInterface|null $logger
     * @param CacheItemPoolInterface|null $cache
     * @throws Exception
     */
    public function __construct(
        array $config,
        string $serviceName = null,
        LoggerInterface $logger = null,
        CacheItemPoolInterface $cache = null
    ) {
        $this->config = $config;

        $this->setConfigFromEnv();

        if (empty($this->config["dispatch_mode"])) {
            $this->config["dispatch_mode"] = self::ZIPKIN_OVER_COMPACT_UDP;
        }

        $this->serviceName = $this->config['service_name'] ?? $serviceName;
        if ($this->serviceName === null) {
            throw new Exception('service_name required in the config or param.');
        }

        $this->logger = $logger ?: new NullLogger();
        $this->cache = $cache;
    }

    /**
     * @return Tracer|null
     * @throws Exception
     */
    public function initializeTracer()
    {
        if ($this->initialized) {
            $this->logger->warning('Jaeger tracer already initialized, skipping');
            return null;
        }

        $reporter = $this->getReporter();
        $sampler = $this->getSampler();


        $tracer = $this->createTracer($reporter, $sampler);

        $this->initializeGlobalTracer($tracer);

        return $tracer;
    }

    /**
     * @param ReporterInterface $reporter
     * @param SamplerInterface $sampler
     * @return Tracer
     */
    public function createTracer(ReporterInterface $reporter, SamplerInterface $sampler): Tracer
    {
        return new Tracer(
            $this->serviceName,
            $reporter,
            $sampler,
            $this->shouldUseOneSpanPerRpc(),
            $this->logger,
            null,
            $this->getTraceIdHeader(),
            $this->getBaggageHeaderPrefix(),
            $this->getDebugIdHeaderKey(),
            $this->getConfiguredTags()
        );
    }

    /**
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * @param Tracer $tracer
     */
    private function initializeGlobalTracer(Tracer $tracer)
    {
        GlobalTracer::set($tracer);
        $this->logger->debug('OpenTracing\GlobalTracer initialized to ' . $tracer->getServiceName());
    }

    /**
     * @return bool
     */
    private function getLogging(): bool
    {
        return (bool)($this->config['logging'] ?? false);
    }

    /**
     * @return ReporterInterface
     */
    private function getReporter(): ReporterInterface
    {
        switch ($this->config["dispatch_mode"]) {
            case self::JAEGER_OVER_BINARY_UDP:
                $reporter = (new JaegerReporterFactory($this))->createReporter();
                break;
            case self::ZIPKIN_OVER_COMPACT_UDP:
                $reporter = (new ZipkinReporterFactory($this))->createReporter();
                break;
            case self::JAEGER_OVER_BINARY_HTTP:
                $reporter = (new JaegerHttpReporterFactory($this))->createReporter();
                break;
            default:
                throw new \RuntimeException(
                    sprintf(
                        "Unsupported `dispatch_mode` value: %s. Allowed values are: %s",
                        $this->config["dispatch_mode"],
                        implode(", ", Config::getAvailableDispatchModes())
                    )
                );
        }

        if ($this->getLogging()) {
            $reporter = new CompositeReporter($reporter, new LoggingReporter($this->logger));
        }

        return $reporter;
    }

    /**
     * @return SamplerInterface
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws Exception
     */
    private function getSampler(): SamplerInterface
    {
        $samplerConfig = $this->config['sampler'] ?? [];
        $samplerType = $samplerConfig['type'] ?? null;
        $samplerParam = $samplerConfig['param'] ?? null;

        if ($samplerType === null || $samplerType === SAMPLER_TYPE_REMOTE) {
            // todo: implement remote sampling
            return new ProbabilisticSampler((float)$samplerParam);
        } elseif ($samplerType === SAMPLER_TYPE_CONST) {
            return new ConstSampler($samplerParam ?? false);
        } elseif ($samplerType === SAMPLER_TYPE_PROBABILISTIC) {
            return new ProbabilisticSampler((float)$samplerParam);
        } elseif ($samplerType === SAMPLER_TYPE_RATE_LIMITING) {
            if (!$this->cache) {
                throw new Exception('You cannot use RateLimitingSampler without cache component');
            }
            $cacheConfig = $samplerConfig['cache'] ?? [];
            return new RateLimitingSampler(
                $samplerParam ?? 0,
                new RateLimiter(
                    $this->cache,
                    $cacheConfig['currentBalanceKey'] ?? 'rate.currentBalance',
                    $cacheConfig['lastTickKey'] ?? 'rate.lastTick'
                )
            );
        }
        throw new Exception('Unknown sampler type ' . $samplerType);
    }

    /**
     * The UDP max buffer length.
     *
     * @return int
     */
    public function getMaxBufferLength(): int
    {
        return (int)($this->config['max_buffer_length'] ?? 64000);
    }

    /**
     * @return string
     */
    public function getLocalAgentReportingHost(): string
    {
        return $this->getLocalAgentGroup()['reporting_host'] ?? DEFAULT_REPORTING_HOST;
    }

    /**
     * @return int
     */
    public function getLocalAgentReportingPort(): int
    {
        $port = $this->getLocalAgentGroup()['reporting_port'] ?? null;
        if (empty($this->getLocalAgentGroup()['reporting_port'])) {
            switch ($this->config['dispatch_mode']) {
                case self::JAEGER_OVER_BINARY_UDP:
                    $port = DEFAULT_JAEGER_UDP_BINARY_REPORTING_PORT;
                    break;
                case self::JAEGER_OVER_BINARY_HTTP:
                    $port = DEFAULT_JAEGER_HTTP_BINARY_REPORTING_PORT;
                    break;
                default:
                    $port = DEFAULT_ZIPKIN_UDP_COMPACT_REPORTING_PORT;
            }
        }
        return (int)$port;
    }

    /**
     * @return array
     */
    private function getLocalAgentGroup(): array
    {
        return $this->config['local_agent'] ?? [];
    }

    /**
     * @return string
     */
    private function getTraceIdHeader(): string
    {
        return $this->config['trace_id_header'] ?? TRACE_ID_HEADER;
    }

    /**
     * @return string
     */
    private function getBaggageHeaderPrefix(): string
    {
        return $this->config['baggage_header_prefix'] ?? BAGGAGE_HEADER_PREFIX;
    }

    /**
     * @return string
     */
    private function getDebugIdHeaderKey(): string
    {
        return $this->config['debug_id_header_key'] ?? DEBUG_ID_HEADER_KEY;
    }

    /**
     * Get a list of user-defined tags to be added to each span created by the tracer initialized by this config.
     * @return string[]
     */
    private function getConfiguredTags(): array
    {
        return $this->config['tags'] ?? [];
    }

    /**
     * Whether to follow the Zipkin model of using one span per RPC,
     * as opposed to the model of using separate spans on the RPC client and server.
     * Defaults to true.
     *
     * @return bool
     */
    private function shouldUseOneSpanPerRpc(): bool
    {
        return $this->config['one_span_per_rpc'] ?? true;
    }

    /**
     * Sets values from env vars into config props, unless ones has been already set.
     */
    private function setConfigFromEnv()
    {
        // general
        if (isset($_ENV['JAEGER_SERVICE_NAME']) && !isset($this->config['service_name'])) {
            $this->config['service_name'] = $_ENV['JAEGER_SERVICE_NAME'];
        }

        if (isset($_ENV['JAEGER_TAGS']) && !isset($this->config["tags"])) {
            $this->config['tags'] = $_ENV['JAEGER_TAGS'];
        }

        if (isset($_ENV['JAEGER_DISPATCH_MODE']) && !isset($this->config['dispatch_mode'])) {
            $this->config['dispatch_mode'] = $_ENV['JAEGER_DISPATCH_MODE'];
        }

        // reporting
        if (isset($_ENV['JAEGER_AGENT_HOST']) && !isset($this->config['local_agent']['reporting_host'])) {
            $this->config['local_agent']['reporting_host'] = $_ENV['JAEGER_AGENT_HOST'];
        }

        if (isset($_ENV['JAEGER_AGENT_PORT']) && !isset($this->config['local_agent']['reporting_port'])) {
            $this->config['local_agent']['reporting_port'] = intval($_ENV['JAEGER_AGENT_PORT']);
        }

        if (isset($_ENV['JAEGER_REPORTER_LOG_SPANS']) && !isset($this->config['logging'])) {
            $this->config['logging'] = filter_var($_ENV['JAEGER_REPORTER_LOG_SPANS'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($_ENV['JAEGER_REPORTER_MAX_QUEUE_SIZE']) && !isset($this->config['max_buffer_length'])) {
            $this->config['max_buffer_length'] = intval($_ENV['JAEGER_REPORTER_MAX_QUEUE_SIZE']);
        }

        // sampling
        if (isset($_ENV['JAEGER_SAMPLER_TYPE']) && !isset($this->config['sampler']['type'])) {
            $this->config['sampler']['type'] = $_ENV['JAEGER_SAMPLER_TYPE'];
        }

        if (isset($_ENV['JAEGER_SAMPLER_PARAM']) && !isset($this->config['sampler']['param'])) {
            $this->config['sampler']['param'] = $_ENV['JAEGER_SAMPLER_PARAM'];
        }
    }
}

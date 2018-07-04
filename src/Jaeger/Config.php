<?php

namespace Jaeger;

use Exception;
use Jaeger\Reporter\CompositeReporter;
use Jaeger\Reporter\LoggingReporter;
use Jaeger\Reporter\RemoteReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\ProbabilisticSampler;
use Jaeger\Sampler\SamplerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use OpenTracing\GlobalTracer;
use Thrift\Exception\TTransportException;
use Thrift\Transport\TTransport;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TBufferedTransport;
use Jaeger\ThriftGen\AgentClient;
use Jaeger\Sender\UdpSender;

class Config
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $serviceName;

    private $errorReporter;

    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Config constructor.
     * @param array $config
     * @param string|null $serviceName
     * @param LoggerInterface|null $logger
     * @throws Exception
     */
    public function __construct(
        array $config,
        string $serviceName = null,
        LoggerInterface $logger = null
//        $metricsFactory = null
    )
    {
        $this->config = $config;

        $this->serviceName = $config['service_name'] ?? $serviceName;
        if ($this->serviceName === null) {
            throw new Exception('service_name required in the config or param');
        }

//        $this->errorReporter = new ErrorReporter(
//            metrics=Metrics(),
//            logger=logger if self.logging else None,
//        );

        $this->logger = $logger ?? new NullLogger();
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

        $channel = $this->getLocalAgentSender();

        $sampler = $this->getSampler();
        if ($sampler === null) {
            $sampler = new ConstSampler(true);
        }

        $reporter = new RemoteReporter(
            $channel
        );

        if ($this->getLogging()) {
            $reporter = new CompositeReporter($reporter, new LoggingReporter($this->logger));
        }

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
            true,
            $this->logger
        );
    }

    /**
     * @param Tracer $tracer
     */
    private function initializeGlobalTracer(Tracer $tracer)
    {
        GlobalTracer::set($tracer);
        $this->logger->info('OpenTracing\GlobalTracer initialized to ' . $tracer->getServiceName());
    }

    /**
     * @return bool
     */
    private function getLogging(): bool
    {
        return (bool)$this->config['logging'] ?? false;
    }

    /**
     * @return SamplerInterface|null
     * @throws Exception
     */
    private function getSampler()
    {
        $samplerConfig = $this->config['sampler'] ?? [];
        $samplerType = $samplerConfig['type'] ?? null;
        $samplerParam = $samplerConfig['param'] ?? null;

        if ($samplerType === null) {
            return null;
        } elseif ($samplerType === SAMPLER_TYPE_CONST) {
            return new ConstSampler($samplerParam ?? false);
        } elseif ($samplerType === SAMPLER_TYPE_PROBABILISTIC) {
            return new ProbabilisticSampler((float) $samplerParam);
        }
//        } elseif (in_array($samplerType, [SAMPLER_TYPE_RATE_LIMITING, 'rate_limiting'])) {
//            return RateLimitingSampler(max_traces_per_second=float(sampler_param))
//        }

        throw new Exception('Unknown sampler type ' . $samplerType);
    }

    /**
     * The UDP max buffer length.
     *
     * @return int
     */
    private function getMaxBufferLength(): int
    {
        return (int) ($this->config['max_buffer_length'] ?? 64000);
    }

    /**
     * @return UdpSender
     */
    private function getLocalAgentSender(): UdpSender
    {
        $udp = new ThriftUdpTransport(
            $this->getLocalAgentReportingHost(),
            $this->getLocalAgentReportingPort(),
            $this->logger
        );

        $transport = new TBufferedTransport($udp, $this->getMaxBufferLength(), $this->getMaxBufferLength());
        try {
            $transport->open();
        } catch (TTransportException $e) {
            $this->logger->warning($e->getMessage());
        }

        $protocol = new TCompactProtocol($transport);
        $client = new AgentClient($protocol);

        $this->logger->info('Initializing Jaeger Tracer with UDP reporter');
        return new UdpSender(
            $client,
            $this->getMaxBufferLength(),
            $this->logger
        );
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
    private function getLocalAgentReportingHost(): string
    {
        return $this->getLocalAgentGroup()['reporting_host'] ?? DEFAULT_REPORTING_HOST;
    }

    /**
     * @return int
     */
    private function getLocalAgentSamplingPort(): int
    {
        return $this->getLocalAgentGroup()['sampling_port'] ?? DEFAULT_SAMPLING_PORT;
    }

    /**
     * @return int
     */
    private function getLocalAgentReportingPort(): int
    {
        return $this->getLocalAgentGroup()['reporting_port'] ?? DEFAULT_REPORTING_PORT;
    }
}

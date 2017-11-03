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
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use OpenTracing\GlobalTracer;

class Config
{
    private $config;

    /** @var string */
    private $serviceName;

    private $errorReporter;

    private $initialized = false;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        $config,
        $serviceName = null,
        LoggerInterface $logger = null
//        $metricsFactory = null
    )
    {
        $this->config = $config;

        $this->serviceName = array_key_exists('service_name', $config) ? $config['service_name'] : $serviceName;
        if ($this->serviceName === null) {
            throw new Exception('service_name required in the config or param');
        }

//        $this->errorReporter = new ErrorReporter(
//            metrics=Metrics(),
//            logger=logger if self.logging else None,
//        );

        $this->logger = $logger ?: new Logger('jaeger_tracing');
    }

    /** @return Tracer|null */
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
        $this->logger->info('Using sampler ' . $sampler);

        $reporter = new RemoteReporter(
            $channel,
            $this->serviceName,
            $this->getBatchSize(),
            $this->logger
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
    public function createTracer(ReporterInterface $reporter, SamplerInterface $sampler)
    {
        return new Tracer(
            $this->serviceName,
            $reporter,
            $sampler,
            true,
            $this->logger
        );
    }

    private function initializeGlobalTracer($tracer)
    {
        GlobalTracer::set($tracer);
        $this->logger->info(
            sprintf(
                'OpenTracing\GlobalTracer initialized to %s[app_name=%s]',
                $tracer,
                $this->serviceName
            )
        );
    }

    private function getLogging()
    {
        return $this->config['logging'] ? true : false;
    }

    /**
     * @return SamplerInterface|null
     * @throws Exception
     */
    private function getSampler()
    {
        $samplerConfig = array_key_exists('sampler', $this->config) ? $this->config['sampler'] : [];
        $samplerType = array_key_exists('type', $samplerConfig) ? $samplerConfig['type'] : null;
        $samplerParam = array_key_exists('param', $samplerConfig) ? $samplerConfig['param'] : null;

        if ($samplerType === null) {
            return null;
        } elseif ($samplerType === SAMPLER_TYPE_CONST) {
            return new ConstSampler($samplerParam ?: false);
        } elseif ($samplerType === SAMPLER_TYPE_PROBABILISTIC) {
            return new ProbabilisticSampler((float) $samplerParam);
        }
//        } elseif (in_array($samplerType, [SAMPLER_TYPE_RATE_LIMITING, 'rate_limiting'])) {
//            return RateLimitingSampler(max_traces_per_second=float(sampler_param))
//        }

        throw new Exception('Unknown sampler type ' . $samplerType);
    }

    /**
     * @return int
     */
    private function getBatchSize()
    {
        if (isset($this->config['reporter_batch_size'])) {
            return (int) $this->config['reporter_batch_size'];
        }
        return 10;
    }

    /**
     * @return LocalAgentSender
     */
    private function getLocalAgentSender()
    {
        $this->logger->info('Initializing Jaeger Tracer with UDP reporter');
        return new LocalAgentSender(
            $this->getLocalAgentReportingHost(),
//            $this->getLocalAgentSamplingPort(),
            $this->getLocalAgentReportingPort()
        );
    }

    private function getLocalAgentGroup()
    {
        return array_key_exists('local_agent', $this->config) ? $this->config['local_agent'] : null;
    }

    private function getLocalAgentReportingHost()
    {
        return $this->getLocalAgentGroup()['reporting_host'] ?: DEFAULT_REPORTING_HOST;
    }

    private function getLocalAgentSamplingPort()
    {
        return $this->getLocalAgentGroup()['sampling_port'] ?: DEFAULT_SAMPLING_PORT;
    }

    private function getLocalAgentReportingPort()
    {
        return $this->getLocalAgentGroup()['reporting_port'] ?: DEFAULT_REPORTING_PORT;
    }
}

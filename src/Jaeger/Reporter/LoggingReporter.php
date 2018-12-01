<?php

namespace Jaeger\Reporter;

use Jaeger\Span;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * LoggingReporter logs all spans.
 */
class LoggingReporter implements ReporterInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * LoggingReporter constructor.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     *
     * @param Span $span
     * @return void
     */
    public function reportSpan(Span $span)
    {
        $this->logger->debug('Reporting span ' . $span->getOperationName());
    }

    /**
     * {@inheritdoc}
     *
     * Only implemented to satisfy the sampler interface.
     *
     * @return void
     */
    public function close()
    {
        // nothing to do
    }
}

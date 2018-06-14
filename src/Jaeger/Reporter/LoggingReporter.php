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
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param Span $span
     */
    public function reportSpan(Span $span)
    {
        $this->logger->info('Reporting span ' . $span->getOperationName());
    }

    public function close()
    {
    }
}

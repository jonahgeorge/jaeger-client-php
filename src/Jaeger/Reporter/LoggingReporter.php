<?php

namespace Jaeger\Reporter;

use Jaeger\Span;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * LoggingReporter logs all spans.
 */
class LoggingReporter implements ReporterInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new Logger('jaeger_tracing');
    }

    public function reportSpan(Span $span)
    {
        $this->logger->info('Reporting span ' . $span);
    }

    public function close()
    {
    }
}
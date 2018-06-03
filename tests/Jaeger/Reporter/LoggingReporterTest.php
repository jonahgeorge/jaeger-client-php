<?php

namespace Jaeger\Reporter;

use Jaeger\Span;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LoggingReporterTest extends TestCase
{
    public function testReportSpan()
    {
        $logger = $this->createMock(NullLogger::class);
        $span = $this->createMock(Span::class);

        $reporter = new LoggingReporter($logger);

        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringStartsWith('Reporting span'));

        $reporter->reportSpan($span);
    }
}

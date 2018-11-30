<?php

namespace Jaeger\Tests\Reporter;

use Jaeger\Reporter\LoggingReporter;
use Jaeger\Span;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LoggingReporterTest extends TestCase
{
    /** @test */
    public function shouldReportSpan()
    {
        /**
         * @var NullLogger|\PHPUnit\Framework\MockObject\MockObject v
         * @var Span|\PHPUnit\Framework\MockObject\MockObject $span
         */
        $logger = $this->createMock(NullLogger::class);
        $span = $this->createMock(Span::class);

        $reporter = new LoggingReporter($logger);

        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringStartsWith('Reporting span'));

        $reporter->reportSpan($span);
        $reporter->close();
    }
}

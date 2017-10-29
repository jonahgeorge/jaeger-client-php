<?php

use Jaeger\Reporter\InMemoryReporter;
use Jaeger\Reporter\LoggingReporter;
use Jaeger\Reporter\NullReporter;
use Jaeger\Span;
use \PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReporterTest extends TestCase
{
    public function testNullReporter()
    {
        $span = $this->createMock(Span::class);

        $reporter = new NullReporter();
        $reporter->reportSpan($span);
        $reporter->close();
    }

    public function testInMemoryReporter()
    {
        $span = $this->createMock(Span::class);
        $reporter = new InMemoryReporter();

        $reporter->reportSpan($span);
        $reporter->close();

        $spans = $reporter->getSpans();
        $this->assertEquals([$span], $spans);
    }

    public function testLoggingReporter()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $span = $this->createMock(Span::class);
        $reporter = new LoggingReporter($logger);

        $logger->method('info')
            ->with($this->stringStartsWith('Reporting span'));
        $reporter->reportSpan($span);
    }
}
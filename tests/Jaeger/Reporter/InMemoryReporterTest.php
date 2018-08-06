<?php

namespace Jaeger\Tests\Reporter;

use Jaeger\Reporter\InMemoryReporter;
use Jaeger\Span;
use PHPUnit\Framework\TestCase;

class InMemoryReporterTest extends TestCase
{
    public function testInMemoryReporter()
    {
        $span = $this->createMock(Span::class);
        $reporter = new InMemoryReporter();

        $reporter->reportSpan($span);
        $reporter->close();

        $spans = $reporter->getSpans();
        $this->assertEquals([$span], $spans);
    }
}

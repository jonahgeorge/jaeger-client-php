<?php

namespace Jaeger\Reporter;

use Jaeger\Span;
use PHPUnit\Framework\TestCase;

class NullReporterTest extends TestCase
{
    public function testNullReporter()
    {
        $span = $this->createMock(Span::class);

        $reporter = new NullReporter();
        $reporter->reportSpan($span);
        $reporter->close();
    }
}

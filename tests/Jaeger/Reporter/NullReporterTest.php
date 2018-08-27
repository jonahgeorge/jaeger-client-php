<?php

namespace Jaeger\Tests\Reporter;

use Jaeger\Reporter\NullReporter;
use Jaeger\Span;
use PHPUnit\Framework\TestCase;

class NullReporterTest extends TestCase
{
    /**
     * Nothing to test because NullReporter doing nothing.
     *
     * @test
     */
    public function shouldReportSpan()
    {
        /** @var \Jaeger\Span|\PHPUnit\Framework\MockObject\MockObject $span */
        $span = $this->createMock(Span::class);

        $reporter = new NullReporter();

        $reporter->reportSpan($span);
        $reporter->close();

        // Only needed to avoid PhpUnit message: "This test did not perform any assertions"
        $this->assertTrue(true);
    }
}

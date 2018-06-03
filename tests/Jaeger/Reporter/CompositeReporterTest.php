<?php

namespace Jaeger\Reporter;

use Jaeger\Span;
use PHPUnit\Framework\TestCase;

class CompositeReporterTest extends TestCase
{
    /**
     * @var CompositeReporter
     */
    private $reporter;

    /**
     * @var ReporterInterface
     */
    private $childReporter1;

    /**
     * @var ReporterInterface
     */
    private $childReporter2;

    function setUp()
    {
        $this->childReporter1 = $this->createMock(ReporterInterface::class);
        $this->childReporter2 = $this->createMock(ReporterInterface::class);

        $this->reporter = new CompositeReporter($this->childReporter1, $this->childReporter2);
    }

    function testReportSpan()
    {
        $span = $this->createMock(Span::class);

        $this->childReporter1->expects($this->once())->method('reportSpan')->with($span);
        $this->childReporter2->expects($this->once())->method('reportSpan')->with($span);

        $this->reporter->reportSpan($span);
    }

    function testClose()
    {
        $this->childReporter1->expects($this->once())->method('close');
        $this->childReporter2->expects($this->once())->method('close');

        $this->reporter->close();
    }
}
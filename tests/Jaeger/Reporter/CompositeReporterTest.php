<?php

namespace Jaeger\Tests\Reporter;

use Jaeger\Reporter\CompositeReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Span;
use PHPUnit\Framework\TestCase;

class CompositeReporterTest extends TestCase
{
    /** @var CompositeReporter */
    private $reporter;

    /** @var ReporterInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $childReporter1;

    /** @var ReporterInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $childReporter2;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->childReporter1 = $this->createMock(ReporterInterface::class);
        $this->childReporter2 = $this->createMock(ReporterInterface::class);

        $this->reporter = new CompositeReporter($this->childReporter1, $this->childReporter2);
    }

    /** @test */
    public function shouldReportSpan()
    {
        /** @var \Jaeger\Span|\PHPUnit\Framework\MockObject\MockObject $span */
        $span = $this->createMock(Span::class);

        $this->childReporter1->expects($this->once())->method('reportSpan')->with($span);
        $this->childReporter2->expects($this->once())->method('reportSpan')->with($span);

        $this->reporter->reportSpan($span);
    }

    /** @test */
    public function shouldCloseReporter()
    {
        $this->childReporter1->expects($this->once())->method('close');
        $this->childReporter2->expects($this->once())->method('close');

        $this->reporter->close();
    }
}

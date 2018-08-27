<?php

namespace Jaeger\Tests\Reporter;

use Jaeger\Reporter\RemoteReporter;
use Jaeger\Sender\UdpSender;
use Jaeger\Span;
use PHPUnit\Framework\TestCase;

class RemoteReporterTest extends TestCase
{
    /** @var RemoteReporter */
    private $reporter;

    /** @var UdpSender|\PHPUnit\Framework\MockObject\MockObject */
    private $transport;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->transport = $this->createMock(UdpSender::class);
        $this->reporter = new RemoteReporter($this->transport);
    }

    /** @test */
    public function shouldReportSpan()
    {
        /** @var Span|\PHPUnit\Framework\MockObject\MockObject $span */
        $span = $this->createMock(Span::class);

        $this->transport->expects($this->once())->method('append')->with($span);

        $this->reporter->reportSpan($span);
    }

    /** @test */
    public function shouldCloseReporter()
    {
        $this->transport->expects($this->once())->method('flush');
        $this->transport->expects($this->once())->method('close');

        $this->reporter->close();
    }
}

<?php

namespace Jaeger\Reporter;

use Jaeger\Span;
use Jaeger\Sender\UdpSender;
use PHPUnit\Framework\TestCase;

class RemoteReporterTest extends TestCase
{
    /**
     * @var RemoteReporter
     */
    private $reporter;

    /**
     * @var string
     */
    private $serviceName = 'test-service';

    function setUp()
    {
        $this->transport = $this->createMock(UdpSender::class);
        $this->reporter = new RemoteReporter($this->transport, $this->serviceName);
    }

    function testReportSpan()
    {
        $span = $this->createMock(Span::class);

        $this->transport->expects($this->once())->method('append')->with($span);

        $this->reporter->reportSpan($span);
    }

    function testClose()
    {
        $this->transport->expects($this->once())->method('flush');
        $this->transport->expects($this->once())->method('close');

        $this->reporter->close();
    }
}
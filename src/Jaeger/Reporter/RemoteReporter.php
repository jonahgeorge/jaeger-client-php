<?php

namespace Jaeger\Reporter;

use Jaeger\Sender\UdpSender;
use Jaeger\Span;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RemoteReporter implements ReporterInterface
{
    /**
     * @var UdpSender
     */
    private $transport;

    /**
     * RemoteReporter constructor.
     *
     * @param UdpSender $transport
     */
    public function __construct(UdpSender $transport)
    {
        $this->transport = $transport;
    }

    /**
     * @param Span $span
     */
    public function reportSpan(Span $span)
    {
        $this->transport->append($span);
    }

    public function close()
    {
        $this->transport->flush();
        $this->transport->close();
    }
}

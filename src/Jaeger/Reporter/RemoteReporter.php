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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $serviceName;

    /**
     * @var int
     */
    private $batchSize;

    /**
     * RemoteReporter constructor.
     * @param UdpSender $transport
     * @param string $serviceName
     * @param int $batchSize
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        UdpSender $transport,
        string $serviceName,
        int $batchSize = 10,
        LoggerInterface $logger = null
    )
    {
        $this->transport = $transport;
        $this->serviceName = $serviceName;
        $this->batchSize = $batchSize;
        $this->logger = $logger ?? new NullLogger();
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

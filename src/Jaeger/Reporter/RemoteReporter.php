<?php

namespace Jaeger\Reporter;

use Jaeger\LocalAgentSender;
use Jaeger\Span;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class RemoteReporter implements ReporterInterface
{
    /** @var LocalAgentSender */
    private $transport;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $serviceName;

    /** @var int */
    private $batchSize;

    public function __construct(
        $transport,
        string $serviceName,
        int $batchSize = 10,
        LoggerInterface $logger = null
    )
    {
        $this->transport = $transport;
        $this->serviceName = $serviceName;
        $this->batchSize = $batchSize;
        $this->logger = $logger ?: new Logger('jaeger_tracing');
    }

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
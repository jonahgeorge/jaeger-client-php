<?php

namespace Jaeger\ReporterFactory;

use Jaeger\Reporter\RemoteReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sender\UdpSender;
use Jaeger\Thrift\Agent\AgentClient;
use Psr\Log\LoggerInterface;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TTransport;

class ZipkinReporterFactory extends AbstractReporterFactory implements ReporterFactoryInterface
{
    /**
     * @var int
     */
    private $maxBufferLength;

    public function __construct(TTransport $transport, LoggerInterface $logger, int $maxBufferLength)
    {
        $this->maxBufferLength = $maxBufferLength;
        parent::__construct($transport, $logger);
    }

    public function createReporter(): ReporterInterface
    {
        $protocol = new TCompactProtocol($this->transport);
        $client = new AgentClient($protocol);
        $this->logger->debug('Initializing Jaeger Tracer with Zipkin over Compact reporter');
        $sender = new UdpSender($client, $this->maxBufferLength, $this->logger);
        return new RemoteReporter($sender);
    }
}

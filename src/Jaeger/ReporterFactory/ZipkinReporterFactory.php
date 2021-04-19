<?php

namespace Jaeger\ReporterFactory;

use Jaeger\Reporter\JaegerReporter;
use Jaeger\Reporter\RemoteReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sender\JaegerSender;
use Jaeger\Sender\UdpSender;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\ThriftUdpTransport;
use Psr\Log\LoggerInterface;
use Thrift\Exception\TTransportException;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TTransport;

class ZipkinReporterFactory extends AbstractReporterFactory implements ReporterFactoryInterface
{
    public function createReporter() : ReporterInterface
    {
        $udp = new ThriftUdpTransport(
            $this->config->getLocalAgentReportingHost(),
            $this->config->getLocalAgentReportingPort(),
            $this->config->getLogger()
        );

        $transport = new TBufferedTransport(
            $udp,
            $this->config->getMaxBufferLength(),
            $this->config->getMaxBufferLength()
        );

        try {
            $transport->open();
        } catch (TTransportException $e) {
            $this->config->getLogger()->warning($e->getMessage());
        }
        $protocol = new TCompactProtocol($transport);
        $client = new AgentClient($protocol);
        $this->config->getLogger()->debug('Initializing UDP Jaeger Tracer with Zipkin.Thrift over Compact protocol');
        $sender = new UdpSender($client, $this->config->getMaxBufferLength(), $this->config->getLogger());
        return new RemoteReporter($sender);
    }
}

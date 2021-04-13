<?php

namespace Jaeger\ReporterFactory;

use Jaeger\AgentClient\HttpAgentClient;
use Jaeger\Reporter\JaegerReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sender\JaegerSender;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\ThriftUdpTransport;
use Thrift\Exception\TTransportException;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;

class JaegerReporterFactory extends AbstractReporterFactory implements ReporterFactoryInterface
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
        $protocol = new TBinaryProtocol($transport);
        $client = new AgentClient($protocol);
        $this->config->getLogger()->debug('Initializing UDP Jaeger Tracer with Jaeger.Thrift over Binary protocol');
        $sender = new JaegerSender($client, $this->config->getLogger());
        return new JaegerReporter($sender);
    }
}

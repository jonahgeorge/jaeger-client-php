<?php

namespace Jaeger\ReporterFactory;

use Jaeger\Config;
use Jaeger\Reporter\JaegerReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sender\JaegerSender;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\ThriftUdpTransport;
use Thrift\Exception\TTransportException;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TBufferedTransport;

class JaegerReporterFactory extends AbstractReporterFactory implements ReporterFactoryInterface
{
    public function createReporter(): ReporterInterface
    {
        $udp = new ThriftUdpTransport(
            $this->config->getLocalAgentReportingHost(),
            $this->config->getLocalAgentReportingPort(),
            $this->config->getLogger(),
            $this->config
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
        $protocol = $this->config->getDispatchMode() === Config::JAEGER_OVER_COMPACT_UDP ?
            new TCompactProtocol($transport) : new TBinaryProtocol($transport);
        $client = new AgentClient($protocol);
        $this->config->getLogger()->debug('Initializing UDP Jaeger Tracer with Jaeger.Thrift over Binary protocol');
        $sender = new JaegerSender($client, $this->config->getLogger());
        $sender->setMaxBufferLength($this->config->getMaxBufferLength());
        return new JaegerReporter($sender);
    }
}

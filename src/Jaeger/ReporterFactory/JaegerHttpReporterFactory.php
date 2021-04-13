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
use Thrift\Transport\TCurlClient;

class JaegerHttpReporterFactory extends AbstractReporterFactory implements ReporterFactoryInterface
{
    public function createReporter() : ReporterInterface
    {
        $transport = new TCurlClient(
            $this->config->getLocalAgentReportingHost(),
            $this->config->getLocalAgentReportingPort(),
            "/api/traces"
        );

        try {
            $transport->open();
        } catch (TTransportException $e) {
            $this->config->getLogger()->warning($e->getMessage());
        }
        $protocol = new TBinaryProtocol($transport);
        $client = new HttpAgentClient($protocol);
        $this->config->getLogger()->debug('Initializing HTTP Jaeger Tracer with Jaeger.Thrift over Binary protocol');
        $sender = new JaegerSender($client, $this->config->getLogger());
        return new JaegerReporter($sender);
    }
}

<?php

namespace Jaeger\ReporterFactory;

use Jaeger\Reporter\JaegerReporter;
use Jaeger\Reporter\ReporterInterface;
use Jaeger\Sender\JaegerSender;
use Jaeger\Thrift\Agent\AgentClient;
use Thrift\Protocol\TBinaryProtocol;

class JaegerReporterFactory extends AbstractReporterFactory implements ReporterFactoryInterface
{
    public function createReporter() : ReporterInterface
    {
        $protocol = new TBinaryProtocol($this->transport);
        $client = new AgentClient($protocol);
        $this->logger->debug('Initializing Jaeger Tracer with Jaeger over Binary reporter');
        $sender = new JaegerSender($client, $this->logger);
        return new JaegerReporter($sender);
    }
}

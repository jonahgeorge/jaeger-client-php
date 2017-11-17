<?php

namespace Jaeger;

use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Agent\Zipkin\AnnotationType;
use Jaeger\Thrift\Agent\Zipkin\BinaryAnnotation;
use Jaeger\Thrift\Agent\Zipkin\Endpoint;
use Jaeger\Thrift\Agent\Zipkin\Span;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TBufferedTransport;

class LocalAgentSender
{
    /** @var Span[] */
    private $spans = [];

    /** @var int */
    private $batchSize;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var AgentClient */
    private $client;

    public function __construct(string $host, int $port, int $batchSize = 10)
    {
        $this->host = $host;
        $this->port = $port;
        $this->batchSize = $batchSize;

        $udp = new TUDPTransport($this->host, $this->port);
        $transport = new TBufferedTransport($udp, 4096, 4096);
        $transport->open();
        $protocol = new TCompactProtocol($transport);

        // Create client
        $this->client = new AgentClient($protocol);
    }

    /**
     * @param \Jaeger\Span $span
     *
     * @return int the number of flushed spans
     */
    public function append(\Jaeger\Span $span): int
    {
        $this->spans[] = $span;

        if (count($this->spans) >= $this->batchSize) {
            return $this->flush();
        }

        return 0;
    }

    /** @return int the number of flushed spans */
    public function flush(): int
    {
        $count = count($this->spans);
        if ($count === 0) {
            return 0;
        }

        $zipkinSpans = $this->makeZipkinBatch($this->spans);

        $this->send($zipkinSpans);
        $this->spans = [];

        return $count;
    }

    public function close()
    {
    }

    private function send(array $spans)
    {
        $this->client->emitZipkinBatch($spans);
    }

    /**
     * @param \Jaeger\Span[] $spans
     * @return Span[]
     */
    private function makeZipkinBatch(array $spans): array
    {
        /** @var Span[] */
        $zipkinSpans = [];

        foreach ($spans as $span) {
            /** @var \Jaeger\Span $span */

            $endpoint = $this->makeEndpoint(
                $span->getTracer()->getIpAddress(),
                0,  // span.port,
                $span->getTracer()->getServiceName()
            );

//            foreach ($span->getLogs() as $event) {
//                $event->setHost($endpoint);
//            }

            $timestamp = $span->getStartTime();
            $duration = $span->getEndTime() - $span->getStartTime();

            $this->addZipkinAnnotations($span, $endpoint);

            $zipkinSpan = new Span([
                'name' => $span->getOperationName(),
                'id' => $span->getContext()->getSpanId(),
                'parent_id' => $span->getContext()->getParentId() ?? null,
                'trace_id' => $span->getContext()->getTraceId(),
                'annotations' => array(), // logs
                'binary_annotations' => $span->getTags(),
                'debug' => $span->isDebug(),
                'timestamp' => $timestamp,
                'duration' => $duration,
            ]);

            $zipkinSpans[] = $zipkinSpan;
        }

        return $zipkinSpans;
    }

    private function addZipkinAnnotations(\Jaeger\Span $span, $endpoint)
    {
        $tag = $this->makeLocalComponentTag(
            $span->getComponent() ?? $span->getTracer()->getServiceName(),
            $endpoint
        );

        $span->tags[] = $tag;
    }

    private function makeLocalComponentTag(string $componentName, $endpoint): BinaryAnnotation
    {
        return new BinaryAnnotation([
            'key' => "lc",
            'value' => $componentName,
            'annotation_type' => AnnotationType::STRING,
            'host' => $endpoint,
        ]);
    }

    private function makeEndpoint(string $ipv4, int $port, string $serviceName): Endpoint
    {
        $ipv4 = $this->ipv4ToInt($ipv4);

        return new Endpoint([
            'ipv4' => $ipv4,
            'port' => $port,
            'service_name' => $serviceName,
        ]);
    }

    private function ipv4ToInt($ipv4): int
    {
        if ($ipv4 == 'localhost') {
            $ipv4 = '127.0.0.1';
        } elseif ($ipv4 == '::1') {
            $ipv4 = '127.0.0.1';
        }

        return ip2long($ipv4);
    }
}

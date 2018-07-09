<?php

namespace Jaeger\Sender;

use Exception;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Agent\Zipkin\Annotation;
use Jaeger\Thrift\Agent\Zipkin\AnnotationType;
use Jaeger\Thrift\Agent\Zipkin\BinaryAnnotation;
use Jaeger\Thrift\Agent\Zipkin\Endpoint;
use Jaeger\Thrift\Agent\Zipkin\Span as ThriftSpan;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Jaeger\Span as JaegerSpan;

use const OpenTracing\Tags\COMPONENT;

class UdpSender
{
    const CLIENT_ADDR = "ca";
    const SERVER_ADDR = "sa";

    /**
     * @var JaegerSpan[]
     */
    private $spans = [];

    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var AgentClient
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * UdpSender constructor.
     * @param AgentClient $client
     * @param int $batchSize
     * @param LoggerInterface $logger
     */
    public function __construct(
        AgentClient $client,
        int $batchSize = 10,
        LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->batchSize = $batchSize;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param JaegerSpan $span
     *
     * @return int the number of flushed spans
     */
    public function append(JaegerSpan $span): int
    {
        $this->spans[] = $span;

        if (count($this->spans) >= $this->batchSize) {
            return $this->flush();
        }

        return 0;
    }

    /**
     * @return int the number of flushed spans
     */
    public function flush(): int
    {
        $count = count($this->spans);
        if ($count === 0) {
            return 0;
        }

        $zipkinSpans = $this->makeZipkinBatch($this->spans);

        try {
            $this->send($zipkinSpans);
        } catch (Exception $e) {
            $this->logger->warning($e->getMessage());
        }

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
     * @param JaegerSpan[] $spans
     * @return ThriftSpan[]
     */
    private function makeZipkinBatch(array $spans): array
    {
        /** @var ThriftSpan[] */
        $zipkinSpans = [];

        foreach ($spans as $span) {
            /** @var JaegerSpan $span */

            $endpoint = $this->makeEndpoint(
                $span->getTracer()->getIpAddress(),
                0,  // span.port,
                $span->getTracer()->getServiceName()
            );

            $timestamp = $span->getStartTime();
            $duration = $span->getEndTime() - $span->getStartTime();

            $this->addZipkinAnnotations($span, $endpoint);

            $zipkinSpan = new ThriftSpan([
                'name' => $span->getOperationName(),
                'id' => $span->getContext()->getSpanId(),
                'parent_id' => $span->getContext()->getParentId() ?? null,
                'trace_id' => $span->getContext()->getTraceId(),
                'annotations' => $this->createAnnotations($span, $endpoint),
                'binary_annotations' => $span->getTags(),
                'debug' => $span->isDebug(),
                'timestamp' => $timestamp,
                'duration' => $duration,
            ]);

            $zipkinSpans[] = $zipkinSpan;
        }

        return $zipkinSpans;
    }

    private function addZipkinAnnotations(JaegerSpan $span, Endpoint $endpoint)
    {
        if ($span->isRpc()) {
            $isClient = $span->isRpcClient();

            if ($span->peer) {
                $host = $this->makeEndpoint(
                    $span->peer['ipv4'] ?? 0,
                    $span->peer['port'] ?? 0,
                    $span->peer['service_name'] ?? '');

                $key = ($isClient) ? self::SERVER_ADDR : self::CLIENT_ADDR;

                $peer = $this->makePeerAddressTag($key, $host);
                $span->tags[$key] = $peer;
            }
        } else {
            $tag = $this->makeLocalComponentTag(
                $span->getComponent() ?? $span->getTracer()->getServiceName(),
                $endpoint
            );

            $span->tags[COMPONENT] = $tag;
        }
    }

    private function makeLocalComponentTag(string $componentName, Endpoint $endpoint): BinaryAnnotation
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

    private function ipv4ToInt(string $ipv4): int
    {
        if ($ipv4 == 'localhost') {
            $ipv4 = '127.0.0.1';
        } elseif ($ipv4 == '::1') {
            $ipv4 = '127.0.0.1';
        }

        return ip2long($ipv4);
    }

    // Used for Zipkin binary annotations like CA/SA (client/server address).
    // They are modeled as Boolean type with '0x01' as the value.
    private function makePeerAddressTag(string $key, Endpoint $host): BinaryAnnotation
    {
        return new BinaryAnnotation([
            "key" => $key,
            "value" => '0x01',
            "annotation_type" => AnnotationType::BOOL,
            "host" => $host,
        ]);
    }

    /**
     * @param JaegerSpan $span
     * @param Endpoint   $endpoint
     *
     * @return array|Annotation[]
     */
    private function createAnnotations(JaegerSpan $span, Endpoint $endpoint): array
    {
        $annotations = [];

        foreach ($span->getLogs() as $values) {
            $annotations[] = new Annotation([
                'timestamp' => $values['timestamp'],
                'value' => json_encode($values['fields']),
                'host' => $endpoint,
            ]);
        }

        return $annotations;
    }
}

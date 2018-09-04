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
use Thrift\Base\TBase;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TMemoryBuffer;
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
     * @var AgentClient
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * The maximum length of the thrift-objects for a zipkin-batch.
     *
     * @var int
     */
    private $maxBufferLength;

    /**
     * The length of the zipkin-batch overhead.
     *
     * @var int
     */
    private $zipkinBatchOverheadLength = 30;

    /**
     * UdpSender constructor.
     *
     * @param AgentClient          $client
     * @param int                  $maxBufferLength
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        AgentClient $client,
        int $maxBufferLength,
        LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->maxBufferLength = $maxBufferLength;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param JaegerSpan $span
     */
    public function append(JaegerSpan $span)
    {
        $this->spans[] = $span;
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

    /**
     * Emits the thrift-objects.
     *
     * @param array|ThriftSpan[]|TBase[] $thrifts
     */
    private function send(array $thrifts)
    {
        foreach ($this->chunkSplit($thrifts) as $chunk) {
            /* @var $chunk ThriftSpan[] */
            $this->client->emitZipkinBatch($chunk);
        }
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
        if ($span->isRpc() && $span->peer) {
            $isClient = $span->isRpcClient();

            $host = $this->makeEndpoint(
                $span->peer['ipv4'] ?? 0,
                $span->peer['port'] ?? 0,
                $span->peer['service_name'] ?? ''
            );

            $key = ($isClient) ? self::SERVER_ADDR : self::CLIENT_ADDR;

            $peer = $this->makePeerAddressTag($key, $host);
            $span->tags[$key] = $peer;
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

        $long = ip2long($ipv4);
        if (PHP_INT_SIZE === 8) {
            return $long >> 31 ? $long - (1 << 32) : $long;
        }
        return $long;
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
     * Splits an array of thrift-objects into several chunks when the buffer limit has been reached.
     *
     * @param array|ThriftSpan[]|TBase[] $thrifts
     *
     * @return array
     */
    private function chunkSplit(array $thrifts): array
    {
        $actualBufferSize = $this->zipkinBatchOverheadLength;
        $chunkId = 0;
        $chunks = [];

        foreach ($thrifts as $thrift) {
            $spanBufferLength = $this->getBufferLength($thrift);

            if (!empty($chunks[$chunkId]) && ($actualBufferSize + $spanBufferLength) > $this->maxBufferLength) {
                // point to next chunk
                ++$chunkId;

                // reset buffer size
                $actualBufferSize = $this->zipkinBatchOverheadLength;
            }

            if (!isset($chunks[$chunkId])) {
                $chunks[$chunkId] = [];
            }

            $chunks[$chunkId][] = $thrift;
            $actualBufferSize += $spanBufferLength;
        }

        return $chunks;
    }

    /**
     * Returns the length of a thrift-object.
     *
     * @param ThriftSpan|TBase $thrift
     *
     * @return int
     */
    private function getBufferLength($thrift): int
    {
        $memoryBuffer = new TMemoryBuffer();

        $thrift->write(new TCompactProtocol($memoryBuffer));

        return $memoryBuffer->available();
    }

    /*
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

<?php


namespace Jaeger\Sender;

use Jaeger\Span as JaegerSpan;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Agent\Zipkin\AnnotationType;
use Jaeger\Thrift\Agent\Zipkin\BinaryAnnotation;
use Jaeger\Thrift\Agent\Zipkin\Endpoint;
use Jaeger\Thrift\Batch;
use Jaeger\Thrift\Log;
use Jaeger\Thrift\Process;
use Jaeger\Thrift\Span as JaegerThriftSpan;
use Jaeger\Thrift\Tag;
use Jaeger\Thrift\TagType;
use Jaeger\Tracer;
use Psr\Log\LoggerInterface;
use const OpenTracing\Tags\COMPONENT;

class JaegerThriftSender implements SenderInterface
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
    private $agentClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * PekhotaSender constructor.
     * @param AgentClient $agentClient
     * @param LoggerInterface $logger
     */
    public function __construct(AgentClient $agentClient, LoggerInterface $logger)
    {
        $this->agentClient = $agentClient;
        $this->logger = $logger;
    }


    public function flush(): int
    {
        $count = count($this->spans);
        if ($count === 0) {
            return 0;
        }

        $jaegerThriftSpans = $this->makeJaegerBatch($this->spans);

        try {
            $this->send($jaegerThriftSpans);
        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage());
        }

        $this->spans = [];

        return $count;
    }

    /**
     * @param JaegerSpan[] $spans
     * @return array
     */
    private function makeJaegerBatch(array $spans) : array {
        /** @var JaegerThriftSpan[] $jaegerSpans */
        $jaegerSpans = [];

        foreach ($spans as $span) {
            if (empty($this->tracer)) {
                $this->tracer = $span->getTracer();
            }

            $timestamp = $span->getStartTime();
            $duration = $span->getEndTime() - $span->getStartTime();

            /** @var Tag[] $tags */
            $tags = [];

            $endpoint = $this->makeEndpoint(
                $span->getTracer()->getIpAddress(),
                0,  // span.port,
                $span->getTracer()->getServiceName()
            );
            $this->addZipkinAnnotations($span, $endpoint);

            /** @var BinaryAnnotation[] $binaryAnnotationTags */
            $binaryAnnotationTags = $span->getTags();
            foreach ($binaryAnnotationTags as $binaryAnnotationTag) {
                if (in_array($binaryAnnotationTag->key, [ "jaeger.hostname", "jaeger.version"], true)) { // todo fix it
                    continue ;
                }

                if ($binaryAnnotationTag->key === "lc") {
                    $binaryAnnotationTag->key = "component";
                }

                $tags[] = new Tag([
                    "key" => $binaryAnnotationTag->key,
                    "vType" => TagType::STRING,
                    "vStr" => $binaryAnnotationTag->value,

                ]);
            }

            /** @var Log[] $logs */
            $logs = [];

            $spanLogs = $span->getLogs();
            foreach ($spanLogs as $spanLog) {
                /** @var Tag $fields */
                $fields = [];
                foreach ($spanLog["fields"] as $k=>$v) {
                    $type = null;
                    $vKey = "";
                    $varType = gettype($v);
                    switch ($varType) {
                        case "boolean":
                            $type = TagType::BOOL;
                            $vKey = "vBool";
                            break;
                        case "integer":
                            $type = TagType::LONG;
                            $vKey = "vLong";
                            break;
                        case "string":
                            $type = TagType::STRING;
                            $vKey = "vStr";
                            break;
                        default:
                            $this->logger->warning("Unsupported type while processing span log fields. Expected bool|int|str, got ${varType}. This field was casted to string");
                            $type = TagType::STRING;
                            $vKey = "vStr";
                            $v = (string)$v;
                    }

                    $fields[] = new Tag([
                        "key" => $k,
                        "vType" => $type,
                        $vKey => $v,
                    ]);
                }

                $logs[] = new Log([
                    "timestamp" => $spanLog["timestamp"],
                    "fields" => $fields
                ]);
            }

            $jaegerSpan = new JaegerThriftSpan([
                "traceIdLow" => (int)$span->getContext()->getTraceId(),
                "traceIdHigh" => 0,
                "spanId" => (int)$span->getContext()->getSpanId(),
                "parentSpanId" => (int)$span->getContext()->getParentId(),
                "operationName" => $span->getOperationName(),
                "startTime" => $timestamp,
                "duration" => $duration,
                "flags" => 1 + (int)$span->isDebug(), // todo i'm not sure check this later
                "tags" => $tags,
                "logs" => $logs
            ]);

            $jaegerSpans[] = $jaegerSpan;
        }

        return $jaegerSpans;
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
     * @param JaegerThriftSpan[] $spans
     */
    private function send(array $spans) {
        /** @var Tag[] $tags */
        $tags = [];

        // append tracer global tags to process tags
        foreach ($this->tracer->getTags() as $k => $v) {
            $tags[] = new Tag([
                "key" => $k,
                "vType" => TagType::STRING,
                "vStr" => $v
            ]);
        }

        // we need to add ip tag manually
        $tags[] = new Tag([
            "key" => "ip",
            "vType" => TagType::STRING,
            "vStr" => $this->tracer->getIpAddress()
        ]);

        $batch = new Batch([
           "spans" => $spans,
           "process" => new Process([
               "serviceName" => $this->tracer->getServiceName(),
               "tags" => $tags
           ])
        ]);

        $this->agentClient->emitBatch($batch);
    }

    /**
     * @param JaegerSpan $span
     */
    public function append(JaegerSpan $span)
    {
        $this->spans[] = $span;
    }

    public function close()
    {
    }
}

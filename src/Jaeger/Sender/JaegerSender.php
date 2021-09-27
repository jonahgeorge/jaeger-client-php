<?php


namespace Jaeger\Sender;

use Jaeger\Mapper\SpanToJaegerMapper;
use Jaeger\Span as JaegerSpan;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Thrift\Agent\AgentIf;
use Jaeger\Thrift\Batch;
use Jaeger\Thrift\Process;
use Jaeger\Thrift\Span as JaegerThriftSpan;
use Jaeger\Thrift\Tag;
use Jaeger\Thrift\TagType;
use Jaeger\Tracer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TMemoryBuffer;
use const Jaeger\JAEGER_HOSTNAME_TAG_KEY;

class JaegerSender implements SenderInterface
{
    /**
     * @var JaegerSpan[]
     */
    private $spans = [];

    /**
     * @var AgentIf
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
     * @var SpanToJaegerMapper
     */
    private $mapper;

    /**
     * @var int
     */
    private $jaegerBatchOverheadLength = 512;

    /**
     * The maximum length of the thrift-objects for a jaeger-batch.
     *
     * @var int
     */
    private $maxBufferLength = 64000;

    /**
     * @param AgentIf $agentClient
     * @param LoggerInterface|null $logger
     * @param SpanToJaegerMapper|null $mapper
     */
    public function __construct(
        AgentIf $agentClient,
        LoggerInterface $logger = null,
        SpanToJaegerMapper $mapper = null
    ) {
        $this->agentClient = $agentClient;
        $this->logger = $logger ?? new NullLogger();
        $this->mapper = $mapper ?? new SpanToJaegerMapper();
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

    public function setMaxBufferLength($maxBufferLength)
    {
        $this->maxBufferLength = $maxBufferLength;
    }

    /**
     * @param JaegerSpan[] $spans
     * @return array
     */
    private function makeJaegerBatch(array $spans) : array
    {
        /** @var JaegerThriftSpan[] $jaegerSpans */
        $jaegerSpans = [];

        foreach ($spans as $span) {
            if (empty($this->tracer)) {
                $this->tracer = $span->getTracer();
            }

            $jaegerSpans[] = $this->mapper->mapSpanToJaeger($span);
        }

        return $jaegerSpans;
    }

    /**
     * @param JaegerThriftSpan[] $spans
     */
    private function send(array $spans)
    {
        if (empty($this->tracer)) {
            return ;
        }

        $chunks = $this->chunkSplit($spans);
        foreach ($chunks as $chunk) {
            /** @var JaegerThriftSpan[] $chunk */
            $this->emitJaegerBatch($chunk);
        }
    }

    /**
     * @param JaegerThriftSpan $span
     */
    private function getBufferLength($span)
    {
        $memoryBuffer = new TMemoryBuffer();
        $span->write(new TBinaryProtocol($memoryBuffer));
        return $memoryBuffer->available();
    }

    private function chunkSplit(array $spans): array
    {
        $actualBufferSize = $this->jaegerBatchOverheadLength;
        $chunkId = 0;
        $chunks = [];

        foreach ($spans as $span) {
            $spanBufferLength = $this->getBufferLength($span);
            if (!empty($chunks[$chunkId]) && ($actualBufferSize + $spanBufferLength) > $this->maxBufferLength) {
                // point to next chunk
                ++$chunkId;

                // reset buffer size
                $actualBufferSize = $this->jaegerBatchOverheadLength;
            }

            if (!isset($chunks[$chunkId])) {
                $chunks[$chunkId] = [];
            }

            $chunks[$chunkId][] = $span;
            $actualBufferSize += $spanBufferLength;
        }

        return $chunks;
    }

    protected function emitJaegerBatch(array $spans)
    {
        /** @var Tag[] $tags */
        $tags = [];

        foreach ($this->tracer->getTags() as $k => $v) {
            if (!in_array($k, $this->mapper->getSpecialSpanTags())) {
                if (strpos($k, $this->mapper->getProcessTagsPrefix()) !== 0) {
                    continue ;
                }

                $quoted = preg_quote($this->mapper->getProcessTagsPrefix());
                $k = preg_replace(sprintf('/^%s/', $quoted), '', $k);
            }

            if ($k === JAEGER_HOSTNAME_TAG_KEY) {
                $k = "hostname";
            }

            $tags[] = new Tag([
                "key" => $k,
                "vType" => TagType::STRING,
                "vStr" => $v
            ]);
        }

        $tags[] = new Tag([
            "key" => "format",
            "vType" => TagType::STRING,
            "vStr" => "jaeger.thrift"
        ]);

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

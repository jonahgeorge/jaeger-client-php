<?php

namespace Jaeger;

use Jaeger\Thrift\Agent\Zipkin\AnnotationType;
use Jaeger\Thrift\Agent\Zipkin\BinaryAnnotation;
use OpenTracing\Span as OTSpan;
use DateTime;
use DateTimeInterface;

use const OpenTracing\Tags\COMPONENT;
use const OpenTracing\Tags\PEER_HOST_IPV4;
use const OpenTracing\Tags\PEER_PORT;
use const OpenTracing\Tags\PEER_SERVICE;
use const OpenTracing\Tags\SPAN_KIND;
use const OpenTracing\Tags\SPAN_KIND_RPC_CLIENT;
use const OpenTracing\Tags\SPAN_KIND_RPC_SERVER;

class Span implements OTSpan
{
    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @var SpanContext
     */
    private $context;

    /**
     * @var string
     */
    private $operationName;

    /**
     * @var int|float|DateTime|null
     */
    private $startTime;

    /**
     * @var int|float|DateTime|null
     */
    private $endTime;

    /**
     * SPAN_RPC_CLIENT
     * @var null|string
     */
    private $kind;

    /**
     * @var array|null
     */
    public $peer;

    /**
     * @var string|null
     */
    private $component;

    /**
     * @var array
     */
    private $logs = [];

    /**
     * @var BinaryAnnotation[]
     */
    public $tags = [];

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * Span constructor.
     * @param SpanContext $context
     * @param Tracer $tracer
     * @param string $operationName
     * @param array $tags
     * @param int|float|DateTime|null $startTime
     */
    public function __construct(
        SpanContext $context,
        Tracer $tracer,
        string $operationName,
        array $tags = [],
        $startTime = null
    ) {
        $this->context = $context;
        $this->tracer = $tracer;

        $this->operationName = $operationName;
        $this->startTime = $startTime ?? $this->timestampMicro();
        $this->endTime = null;
        $this->kind = null;
        $this->peer = null;
        $this->component = null;

        foreach ($tags as $key => $value) {
            $this->setTag($key, $value);
        }
    }

    /**
     * @return Tracer
     */
    public function getTracer(): Tracer
    {
        return $this->tracer;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @return DateTime|float|int|null
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @return DateTime|float|int|null
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * @return string
     */
    public function getOperationName(): string
    {
        return $this->operationName;
    }

    /**
     * @return mixed
     */
    public function getComponent()
    {
        // TODO
        return $this->component;
    }

    /**
     * {@inheritdoc}
     *
     * @return SpanContext
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function finish($finishTime = null, array $logRecords = [])
    {
        if (!$this->isSampled()) {
            return;
        }

        foreach ($logRecords as $logRecord) {
            $this->log($logRecord);
        }

        $this->endTime = $finishTime ?? $this->timestampMicro();
        $this->tracer->reportSpan($this);
    }

    /**
     * Returns true if the trace should be measured.
     *
     * @return bool
     */
    public function isSampled(): bool
    {
        $context = $this->getContext();

        return ($context->getFlags() & SAMPLED_FLAG) == SAMPLED_FLAG;
    }

    /**
     * {@inheritdoc}
     */
    public function overwriteOperationName($newOperationName)
    {
        // TODO log warning
        $this->operationName = $newOperationName;
    }

    /**
     * {@inheritdoc}
     *
     * @param array|\Traversable $tags
     * @return void
     */
    public function setTags($tags)
    {
        foreach ($tags as $key => $value) {
            $this->setTag($key, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setTag($key, $value)
    {
        if ($this->isSampled()) {
            $special = self::SPECIAL_TAGS[$key] ?? null;
            $handled = false;

            if ($special !== null && is_callable([$this, $special])) {
                $handled = $this->$special($value);
            }

            if (!$handled) {
                $tag = $this->makeStringTag($key, (string) $value);
                $this->tags[$key] = $tag;
            }
        }

        return $this;
    }

    const SPECIAL_TAGS = [
        PEER_SERVICE => 'setPeerService',
        PEER_HOST_IPV4 => 'setPeerHostIpv4',
        PEER_PORT => 'setPeerPort',
        SPAN_KIND => 'setSpanKind',
        COMPONENT => 'setComponent',
    ];

    /**
     * Sets a low-cardinality identifier of the module, library,
     * or package that is generating a span.
     *
     * @see Span::setTag()
     *
     * @param string $value
     * @return bool
     */
    private function setComponent($value): bool
    {
        $this->component = $value;
        return true;
    }

    /**
     * @return bool
     */
    private function setSpanKind($value): bool
    {
        if ($value === null || $value === SPAN_KIND_RPC_CLIENT || $value === SPAN_KIND_RPC_SERVER) {
            $this->kind = $value;
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    private function setPeerPort($value): bool
    {
        if ($this->peer === null) {
            $this->peer = ['port' => $value];
        } else {
            $this->peer['port'] = $value;
        }
        return true;
    }

    /**
     * @return bool
     */
    private function setPeerHostIpv4($value): bool
    {
        if ($this->peer === null) {
            $this->peer = ['ipv4' => $value];
        } else {
            $this->peer['ipv4'] = $value;
        }
        return true;
    }

    /**
     * @return bool
     */
    private function setPeerService($value): bool
    {
        if ($this->peer === null) {
            $this->peer = ['service_name' => $value];
        } else {
            $this->peer['service_name'] = $value;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function isRpc(): bool
    {
        return $this->kind == SPAN_KIND_RPC_CLIENT || $this->kind == SPAN_KIND_RPC_SERVER;
    }

    /**
     * @return bool
     */
    public function isRpcClient(): bool
    {
        return $this->kind == SPAN_KIND_RPC_CLIENT;
    }

    /**
     * {@inheritdoc}
     */
    public function log(array $fields = [], $timestamp = null)
    {
        if ($timestamp instanceof \DateTimeInterface || $timestamp instanceof \DateTime) {
            $timestamp = $timestamp->getTimestamp();
        }

        if ($timestamp !== null) {
            $timestamp = (int) ($timestamp * 1000000);
        }

        if ($timestamp < $this->getStartTime()) {
            $timestamp = $this->timestampMicro();
        }

        $this->logs[] = [
            'fields' => $fields,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Returns the logs.
     *
     * [
     *      [
     *          'timestamp' => timestamp in microsecond,
     *          'fields' => [
     *              'error' => 'message',
     *          ]
     *      ]
     * ]
     *
     * @return array
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * {@inheritdoc}
     */
    public function addBaggageItem($key, $value)
    {
        $this->context = $this->context->withBaggageItem($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getBaggageItem($key)
    {
        return $this->context->getBaggageItem($key);
    }

    /**
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @return int
     */
    private function timestampMicro(): int
    {
        return round(microtime(true) * 1000000);
    }

    /**
     * @param string $key
     * @param string $value
     * @return BinaryAnnotation
     */
    private function makeStringTag(string $key, string $value): BinaryAnnotation
    {
        if (strlen($value) > 256) {
            $value = substr($value, 0, 256);
        }
        return new BinaryAnnotation([
            'key' => $key,
            'value' => $value,
            'annotation_type' => AnnotationType::STRING,
        ]);
    }
}

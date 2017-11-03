<?php

namespace Jaeger;

use Jaeger\ThriftGen\AnnotationType;
use Jaeger\ThriftGen\BinaryAnnotation;
use OpenTracing;
use const OpenTracing\Ext\Tags\COMPONENT;
use const OpenTracing\Ext\Tags\PEER_HOST_IPV4;
use const OpenTracing\Ext\Tags\PEER_PORT;
use const OpenTracing\Ext\Tags\PEER_SERVICE;
use const OpenTracing\Ext\Tags\SPAN_KIND;
use const OpenTracing\Ext\Tags\SPAN_KIND_RPC_CLIENT;
use const OpenTracing\Ext\Tags\SPAN_KIND_RPC_SERVER;

class Span implements OpenTracing\Span
{
    /** @var Tracer */
    private $tracer;

    /** @var SpanContext */
    private $context;

    /** @var string */
    private $operationName;

    /** @var float */
    private $startTime;

    /** @var float */
    private $endTime;

    private $kind;

    /** @var array|null */
    private $peer;

    private $component;

    private $logs;

    /** @var BinaryAnnotation[] */
    public $tags;

    /** @var bool */
    private $debug = false;

    public function __construct(
        SpanContext $context,
        Tracer $tracer,
        $operationName,
        array $tags = [],
        $startTime = null
    )
    {
        $this->context = $context;
        $this->tracer = $tracer;

        $this->operationName = $operationName;
        $this->startTime = $startTime ?: $this->timestampMicro();
        $this->endTime = null;
        $this->kind = null;
        $this->peer = null;
        $this->component = null;

        $this->tags = [];
        $this->logs = [];
        foreach ($tags as $key => $value) {
            $this->setTag($key, $value);
        }
    }

    /**
     * @return Tracer
     */
    public function getTracer()
    {
        return $this->tracer;
    }

    /**
     * @return bool
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /** @return float|null */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /** @return float|null */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * @return string
     */
    public function getOperationName()
    {
        return $this->operationName;
    }

    /** @return mixed */
    public function getComponent()
    {
        // TODO
        return $this->component;
    }

    /**
     * Yields the SpanContext for this Span. Note that the return value of
     * Span::getContext() is still valid after a call to Span::finish(), as is
     * a call to Span::getContext() after a call to Span::finish().
     *
     * @return OpenTracing\SpanContext
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Sets the end timestamp and finalizes Span state.
     *
     * With the exception of calls to Context() (which are always allowed),
     * finish() must be the last call made to any span instance, and to do
     * otherwise leads to undefined behavior
     *
     * If the span is already finished, a warning should be logged.
     *
     * @param float|int|\DateTimeInterface|null $finishTime if passing float or int
     * it should represent the timestamp (including as many decimal places as you need)
     * @param array $logRecords
     */
    public function finish($finishTime = null, array $logRecords = [])
    {
        if (!$this->isSampled()) {
            return;
        }

        foreach ($logRecords as $logRecord) {
            $this->log($logRecord);
        }

        $this->endTime = $finishTime ?: $this->timestampMicro();
        $this->tracer->reportSpan($this);
    }

    /**
     * @return bool
     */
    public function isSampled()
    {
        return $this->getContext()->getFlags() & SAMPLED_FLAG == SAMPLED_FLAG;
    }

    /**
     * If the span is already finished, a warning should be logged.
     *
     * @param string $newOperationName
     */
    public function overwriteOperationName($newOperationName)
    {
        // TODO log warning
        $this->operationName = $newOperationName;
    }

    /**
     * Sets tags to the Span in key:value format, key must be a string and tag must be either
     * a string, a boolean value, or a numeric type.
     *
     * As an implementor, consider using "standard tags" listed in {@see \OpenTracing\Ext\Tags}
     *
     * If the span is already finished, a warning should be logged.
     *
     * @param array $tags
     */
    public function setTags(array $tags)
    {
        foreach ($tags as $key => $value) {
            $this->setTag($key, $value);
        }
    }

    public function setTag($key, $value)
    {
//        if ($key == SAMPLING_PRIORITY) {
//        }

        if ($this->isSampled()) {
            $special = array_key_exists($key, self::SPECIAL_TAGS) ? self::SPECIAL_TAGS[$key] : null;
            $handled = False;

            if ($special !== null && is_callable($special)) {
                $handled = $this->$special($value);
            }

            if (!$handled) {
                $tag = $this->makeStringTag($key, (string) $value);
                $this->tags[] = $tag;
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
     * @param $value
     * @return bool
     */
    private function setComponent($value)
    {
        $this->component = $value;
        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    private function setSpanKind($value)
    {
        if ($value === null || $value === SPAN_KIND_RPC_CLIENT || $value === SPAN_KIND_RPC_SERVER) {
            $this->kind = $value;
            return true;
        }
        return false;
    }

    /**
     * @param $value
     * @return bool
     */
    private function setPeerPort($value)
    {
        if ($this->peer === null) {
            $this->peer = ['port' => $value];
        } else {
            $this->peer['port'] = $value;
        }
        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    private function setPeerHostIpv4($value)
    {
        if ($this->peer === null) {
            $this->peer = ['ipv4' => $value];
        } else {
            $this->peer['ipv4'] = $value;
        }
        return true;
    }

    /**
     * @param $value
     * @return bool
     */
    private function setPeerService($value)
    {
        if ($this->peer === null) {
            $this->peer = ['service_name' => $value];
        } else {
            $this->peer['service_name'] = $value;
        }
        return true;
    }

    /**
     * Adds a log record to the span
     *
     * If the span is already finished, a warning should be logged.
     *
     * @param array $fields
     * @param int|float|\DateTimeInterface $timestamp
     */
    public function log(array $fields = [], $timestamp = null)
    {
        // TODO: Implement log() method.
    }

    /**
     * Adds a baggage item to the SpanContext which is immutable so it is required to use SpanContext::withBaggageItem
     * to get a new one.
     *
     * If the span is already finished, a warning should be logged.
     *
     * @param string $key
     * @param string $value
     */
    public function addBaggageItem($key, $value)
    {
        // TODO: Implement addBaggageItem() method.
    }

    /**
     * @param string $key
     * @return string
     */
    public function getBaggageItem($key)
    {
        // TODO: Implement getBaggageItem() method.
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf(
            'Span(operationName=%s startTime=%s endTime=%s)',
            $this->operationName,
            $this->startTime,
            $this->endTime
        );
    }

    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @return int
     */
    private function timestampMicro()
    {
        return round(microtime(true) * 1000000);
    }

    /**
     * @param string $key
     * @param string $value
     * @return BinaryAnnotation
     */
    private function makeStringTag($key, $value)
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

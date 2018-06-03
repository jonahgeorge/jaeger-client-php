<?php

namespace Jaeger;

use ArrayIterator;
use OpenTracing\SpanContext as OTSpanContext;

class SpanContext implements OTSpanContext
{
    private $traceId;

    private $spanId;

    private $parentId;

    private $flags;

    /**
     * @var array
     */
    private $baggage;

    private $debugId;

    /**
     * SpanContext constructor.
     * @param string $traceId
     * @param string $spanId
     * @param string $parentId
     * @param $flags
     * @param array $baggage
     */
    public function __construct($traceId, $spanId, $parentId, $flags, $baggage = [])
    {
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->parentId = $parentId;
        $this->flags = $flags;
        $this->baggage = $baggage;
        $this->debugId = null;
    }

    /**
     * TODO
     * @deprecated
     * @param $debugId
     * @return SpanContext
     */
    public static function withDebugId($debugId)
    {
        $ctx = new SpanContext(null, null, null, null);
        $ctx->debugId = $debugId;

        return $ctx;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->baggage);
    }

    /**
     * {@inheritdoc}
     */
    public function getBaggageItem($key)
    {
        return array_key_exists($key, $this->baggage) ? $this->baggage[$key] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function withBaggageItem($key, $value)
    {
        return new self($this->traceId, $this->spanId, $this->parentId, $this->flags, [$key => $value] + $this->baggage);
    }

    public function getTraceId()
    {
        return $this->traceId;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function getSpanId()
    {
        return $this->spanId;
    }

    public function getFlags()
    {
        return $this->flags;
    }

    public function getBaggage()
    {
        return $this->baggage;
    }

    public function getDebugId()
    {
        return $this->debugId;
    }

    public function isDebugIdContainerOnly(): bool
    {
        return ($this->traceId === null) && ($this->debugId !== null);
    }
}

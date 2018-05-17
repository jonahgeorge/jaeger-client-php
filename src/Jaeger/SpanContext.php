<?php

namespace Jaeger;

use ArrayIterator;
use OpenTracing;

class SpanContext implements OpenTracing\SpanContext
{
    private $traceId;
    private $spanId;
    private $parentId;
    private $flags;
    private $baggage;
    private $debugId;

    public function __construct(int $traceId, int $spanId, int $parentId, int $flags, array $baggage = [])
    {
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->parentId = $parentId;
        $this->flags = $flags;
        $this->baggage = $baggage;
        $this->debugId = null;
    }

    public static function withDebugId($debugId)
    {
        $ctx = new SpanContext(null, null, null, null);
        $ctx->debugId = $debugId;

        return $ctx;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->baggage);
    }

    /**
     * @param string $key
     * @return string
     */
    public function getBaggageItem($key): string
    {
        return $this->baggage[$key];
    }

    /**
     * Creates a new SpanContext out of the existing one and the new key:value pair.
     *
     * @param string $key
     * @param string $value
     * @return \OpenTracing\SpanContext
     */
    public function withBaggageItem($key, $value)
    {
        $baggage = $this->baggage;
        $baggage[$key] = $value;

        return new SpanContext(
            $this->traceId,
            $this->spanId,
            $this->parentId,
            $this->flags,
            $baggage
        );
    }

    /** @return int */
    public function getTraceId()
    {
        return $this->traceId;
    }

    /** @return int|null */
    public function getParentId()
    {
        return $this->parentId;
    }

    /** @return int */
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
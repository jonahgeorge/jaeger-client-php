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
     *
     * @param string $traceId
     * @param string $spanId
     * @param string $parentId
     * @param int|null $flags
     * @param array $baggage
     * @param int|null $debugId
     */
    public function __construct($traceId, $spanId, $parentId, $flags = null, $baggage = [], $debugId = null)
    {
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->parentId = $parentId;
        $this->flags = $flags;
        $this->baggage = is_array($baggage) ? $baggage : [];
        $this->debugId = $debugId;
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
    public function getBaggageItem(string $key): ?string
    {
        return array_key_exists($key, $this->baggage) ? $this->baggage[$key] : null;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $key
     * @param string $value
     * @return SpanContext
     */
    public function withBaggageItem(string $key, string $value): OTSpanContext
    {
        return new self(
            $this->traceId,
            $this->spanId,
            $this->parentId,
            $this->flags,
            [$key => $value] + $this->baggage
        );
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

    /**
     * Get the span context flags.
     *
     * @return int|null
     */
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

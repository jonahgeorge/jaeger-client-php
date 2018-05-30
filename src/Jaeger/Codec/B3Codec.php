<?php

namespace Jaeger\Codec;

use Jaeger\SpanContext;
use const Jaeger\DEBUG_FLAG;
use const Jaeger\SAMPLED_FLAG;

class B3Codec implements CodecInterface
{
    const SAMPLED_NAME = 'X-B3-Sampled';
    const TRACE_ID_NAME = 'X-B3-TraceId';
    const SPAN_ID_NAME = 'X-B3-SpanId';
    const PARENT_ID_NAME = 'X-B3-ParentSpanId';
    const FLAGS_NAME = 'X-B3-Flags';

    public function inject(SpanContext $spanContext, &$carrier)
    {
        $carrier[self::TRACE_ID_NAME] = Utils::headerToHex($spanContext->getTraceId());
        $carrier[self::SPAN_ID_NAME] = Utils::headerToHex($spanContext->getSpanId());
        if ($spanContext->getParentId() != null) {
            $carrier[self::PARENT_ID_NAME] = Utils::headerToHex($spanContext->getParentId());
        }
        $carrier[self::FLAGS_NAME] = (int) $spanContext->getFlags();
    }

    /** @return SpanContext|null */
    public function extract($carrier)
    {
        $traceId = null;
        $spanId = null;
        $parentId = 0;
        $flags = 0;

        if (isset($carrier[strtolower(self::SAMPLED_NAME)])) {
            if ($carrier[strtolower(self::SAMPLED_NAME)] === "1" || strtolower($carrier[strtolower(self::SAMPLED_NAME)] === "true")) {
                $flags = $flags | SAMPLED_FLAG;
            }
        }

        if (isset($carrier[strtolower(self::TRACE_ID_NAME)])) {
            $traceId = Utils::hexToHeader($carrier[strtolower(self::TRACE_ID_NAME)]);
        }

        if (isset($carrier[strtolower(self::PARENT_ID_NAME)])) {
            $parentId = Utils::hexToHeader($carrier[strtolower(self::PARENT_ID_NAME)]);
        }

        if (isset($carrier[strtolower(self::SPAN_ID_NAME)])) {
            $spanId = Utils::hexToHeader($carrier[strtolower(self::SPAN_ID_NAME)]);
        }

        if (isset($carrier[strtolower(self::FLAGS_NAME)])) {
            if ($carrier[strtolower(self::FLAGS_NAME)] === "1") {
                $flags = $flags | DEBUG_FLAG;
            }
        }

        if ($traceId !== null && $spanId !== null) {
            return new SpanContext($traceId, $spanId, $parentId, $flags);
        }

        return null;
    }
}

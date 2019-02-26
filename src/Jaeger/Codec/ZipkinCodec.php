<?php

namespace Jaeger\Codec;

use Jaeger\SpanContext;

use const Jaeger\DEBUG_FLAG;
use const Jaeger\SAMPLED_FLAG;

use function Phlib\base_convert;

class ZipkinCodec implements CodecInterface
{
    const SAMPLED_NAME = 'X-B3-Sampled';
    const TRACE_ID_NAME = 'X-B3-TraceId';
    const SPAN_ID_NAME = 'X-B3-SpanId';
    const PARENT_ID_NAME = 'X-B3-ParentSpanId';
    const FLAGS_NAME = 'X-B3-Flags';

    /**
     * {@inheritdoc}
     *
     * @see \Jaeger\Tracer::inject
     *
     * @param SpanContext $spanContext
     * @param mixed $carrier
     *
     * @return void
     */
    public function inject(SpanContext $spanContext, &$carrier)
    {
        $carrier[self::TRACE_ID_NAME] = dechex($spanContext->getTraceId());
        $carrier[self::SPAN_ID_NAME] = dechex($spanContext->getSpanId());
        if ($spanContext->getParentId() != null) {
            $carrier[self::PARENT_ID_NAME] = dechex($spanContext->getParentId());
        }
        $carrier[self::FLAGS_NAME] = (int) $spanContext->getFlags();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Jaeger\Tracer::extract
     *
     * @param mixed $carrier
     * @return SpanContext|null
     */
    public function extract($carrier)
    {
        $traceId = "0";
        $spanId = "0";
        $parentId = "0";
        $flags = 0;

        if (isset($carrier[strtolower(self::SAMPLED_NAME)])) {
            if ($carrier[strtolower(self::SAMPLED_NAME)] === "1" ||
                strtolower($carrier[strtolower(self::SAMPLED_NAME)] === "true")
            ) {
                $flags = $flags | SAMPLED_FLAG;
            }
        }

        if (isset($carrier[strtolower(self::TRACE_ID_NAME)])) {
            $traceId =  CodecUtility::hexToInt64($carrier[strtolower(self::TRACE_ID_NAME)], 16, 10);
        }

        if (isset($carrier[strtolower(self::PARENT_ID_NAME)])) {
            $parentId =  CodecUtility::hexToInt64($carrier[strtolower(self::PARENT_ID_NAME)], 16, 10);
        }

        if (isset($carrier[strtolower(self::SPAN_ID_NAME)])) {
            $spanId =  CodecUtility::hexToInt64($carrier[strtolower(self::SPAN_ID_NAME)], 16, 10);
        }

        if (isset($carrier[strtolower(self::FLAGS_NAME)])) {
            if ($carrier[strtolower(self::FLAGS_NAME)] === "1") {
                $flags = $flags | DEBUG_FLAG;
            }
        }

        if ($traceId != "0" && $spanId != "0") {
            return new SpanContext($traceId, $spanId, $parentId, $flags);
        }

        return null;
    }
}

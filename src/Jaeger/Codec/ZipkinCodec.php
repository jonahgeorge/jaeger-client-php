<?php

namespace Jaeger\Codec;

use Jaeger\SpanContext;

class ZipkinCodec implements CodecInterface
{
    public function inject(SpanContext $spanContext, $carrier)
    {
        $carrier['trace_id'] = $spanContext->getTraceId();
        $carrier['span_id'] = $spanContext->getSpanId();
        $carrier['parent_id'] = $spanContext->getParentId();
        $carrier['traceflags'] = $spanContext->getFlags();
    }

    /** @return SpanContext|null */
    public function extract($carrier)
    {
        $traceId = $carrier['trace_id'];
        $spanId = $carrier['span_id'];
        $parentId = $carrier['parent_id'];
        $flags = $carrier['traceflags'];

        if ($traceId === null) {
            return null;
        }

        return new SpanContext($traceId, $spanId, $parentId, $flags);
    }
}
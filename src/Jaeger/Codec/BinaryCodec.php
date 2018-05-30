<?php

namespace Jaeger\Codec;

use Jaeger\SpanContext;
use OpenTracing\Exceptions\UnsupportedFormat;

class BinaryCodec implements CodecInterface
{
    public function inject(SpanContext $spanContext, &$carrier)
    {
        throw new UnsupportedFormat('Binary encoding not implemented');
    }

    /** @return SpanContext|null */
    public function extract($carrier)
    {
        throw new UnsupportedFormat('Binary encoding not implemented');
    }
}
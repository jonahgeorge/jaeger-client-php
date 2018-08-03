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

    /**
     * @param array $carrier
     * @return SpanContext|null
     *
     * @throws UnsupportedFormat
     */
    public function extract($carrier)
    {
        throw new UnsupportedFormat('Binary encoding not implemented');
    }
}

<?php

namespace Jaeger\Codec;

use Jaeger\SpanContext;
use OpenTracing\UnsupportedFormatException;

class BinaryCodec implements CodecInterface
{
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
        throw new UnsupportedFormatException('Binary encoding not implemented');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Jaeger\Tracer::extract
     *
     * @param mixed $carrier
     * @return SpanContext|null
     *
     * @throws UnsupportedFormatException
     */
    public function extract($carrier)
    {
        throw new UnsupportedFormatException('Binary encoding not implemented');
    }
}

<?php

namespace Jaeger\Codec;

use Jaeger\SpanContext;

interface CodecInterface
{
    public function inject(SpanContext $spanContext, $carrier);

    /** @return SpanContext|null */
    public function extract($carrier);
}
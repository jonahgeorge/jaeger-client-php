<?php

namespace Jaeger\Codec;

use Jaeger\SpanContext;

interface CodecInterface
{
    /**
     * Handle the logic behind injecting propagation scheme specific information into the carrier
     * (e.g. http request headers, amqp message headers, etc.).
     *
     * This method can modify the carrier.
     *
     * @see \Jaeger\Tracer::inject
     *
     * @param SpanContext $spanContext
     * @param mixed $carrier
     *
     * @return void
     */
    public function inject(SpanContext $spanContext, &$carrier);

    /**
     * Handle the logic behind extracting propagation-scheme specific information from carrier
     * (e.g. http request headers, amqp message headers, etc.).
     *
     * This method must not modify the carrier.
     *
     * @see \Jaeger\Tracer::extract
     *
     * @param mixed $carrier
     * @return SpanContext|null
     */
    public function extract($carrier);
}

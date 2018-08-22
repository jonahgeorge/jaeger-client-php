<?php

namespace Jaeger\Sampler;

/**
 * Sampler is responsible for deciding if a new trace should be sampled and captured for storage.
 *
 * @package Jaeger\Sampler
 */
interface SamplerInterface
{
    /**
     * Whether or not the new trace should be sampled.
     *
     * Implementations should return an array in the format [$decision, $tags].
     *
     * @param string $traceId   The traceId on the span.
     * @param string $operation The operation name set on the span.
     * @return array
     */
    public function isSampled(string $traceId, string $operation);

    /**
     * Release any resources used by the sampler.
     *
     * @return void
     */
    public function close();
}

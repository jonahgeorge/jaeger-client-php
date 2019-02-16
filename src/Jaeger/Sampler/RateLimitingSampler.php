<?php

namespace Jaeger\Sampler;

use Jaeger\Util\RateLimiter;

use const Jaeger\SAMPLER_PARAM_TAG_KEY;
use const Jaeger\SAMPLER_TYPE_RATE_LIMITING;
use const Jaeger\SAMPLER_TYPE_TAG_KEY;

class RateLimitingSampler implements SamplerInterface
{
    /**
     * @var RateLimiter
     */
    private $rateLimiter;

    /**
     * A list of the sampler tags.
     *
     * @var array
     */
    private $tags = [];

    public function __construct($maxTracesPerSecond, RateLimiter $rateLimiter)
    {
        $this->tags = [
            SAMPLER_TYPE_TAG_KEY => SAMPLER_TYPE_RATE_LIMITING,
            SAMPLER_PARAM_TAG_KEY => $maxTracesPerSecond,
        ];

        $maxTracesPerNanosecond = $maxTracesPerSecond / 1000000000.0;
        $this->rateLimiter = $rateLimiter;
        $this->rateLimiter->initialize($maxTracesPerNanosecond, $maxTracesPerSecond > 1.0 ? 1.0 : $maxTracesPerSecond);
    }

    /**
     * Whether or not the new trace should be sampled.
     *
     * Implementations should return an array in the format [$decision, $tags].
     *
     * @param string $traceId The traceId on the span.
     * @param string $operation The operation name set on the span.
     * @return array
     */
    public function isSampled(string $traceId = '', string $operation = '')
    {
        return [$this->rateLimiter->checkCredit(1.0), $this->tags];
    }

    /**
     * {@inheritdoc}
     *
     * Only implemented to satisfy the sampler interface.
     *
     * @return void
     */
    public function close()
    {
        // nothing to do
    }
}

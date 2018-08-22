<?php

namespace Jaeger\Sampler;

use const Jaeger\SAMPLER_PARAM_TAG_KEY;
use const Jaeger\SAMPLER_TYPE_CONST;
use const Jaeger\SAMPLER_TYPE_TAG_KEY;

/**
 * ConstSampler always returns the same decision.
 *
 * @package Jaeger\Sampler
 */
class ConstSampler implements SamplerInterface
{
    /**
     * Whether or not the new trace should be sampled.
     *
     * @var bool
     */
    private $decision;

    /**
     * A list of the sampler tags.
     *
     * @var array
     */
    private $tags = [];

    /**
     * ConstSampler constructor.
     *
     * @param bool $decision
     */
    public function __construct(bool $decision = true)
    {
        $this->tags = [
            SAMPLER_TYPE_TAG_KEY => SAMPLER_TYPE_CONST,
            SAMPLER_PARAM_TAG_KEY => $decision,
        ];

        $this->decision = $decision;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $traceId   The traceId on the span.
     * @param string $operation The operation name set on the span.
     * @return array
     */
    public function isSampled(string $traceId, string $operation = ''): array
    {
        return [$this->decision, $this->tags];
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

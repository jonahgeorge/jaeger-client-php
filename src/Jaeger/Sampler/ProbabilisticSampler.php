<?php

namespace Jaeger\Sampler;

use OutOfBoundsException;
use const Jaeger\SAMPLER_PARAM_TAG_KEY;
use const Jaeger\SAMPLER_TYPE_PROBABILISTIC;
use const Jaeger\SAMPLER_TYPE_TAG_KEY;

/**
 * A sampler that randomly samples a certain percentage of traces specified
 * by the samplingRate, in the range between 0.0 and 1.0.
 *
 * @package Jaeger\Sampler
 */
class ProbabilisticSampler implements SamplerInterface
{
    /**
     * The sampling rate rate between 0.0 and 1.0.
     *
     * @var float
     */
    private $rate;

    /**
     * A list of the sampler tags.
     *
     * @var array
     */
    private $tags = [];

    /**
     * The boundary of the sample sampling rate.
     *
     * @var float
     */
    private $boundary;

    /**
     * ProbabilisticSampler constructor.
     *
     * @param float $rate
     * @throws OutOfBoundsException
     */
    public function __construct(float $rate)
    {
        $this->tags = [
            SAMPLER_TYPE_TAG_KEY => SAMPLER_TYPE_PROBABILISTIC,
            SAMPLER_PARAM_TAG_KEY => $rate,
        ];

        if ($rate < 0.0 || $rate > 1.0) {
            throw new OutOfBoundsException('Sampling rate must be between 0.0 and 1.0.');
        }

        $this->rate = $rate;
        if ($rate < 0.5) {
            $this->boundary = (int)($rate * PHP_INT_MAX);
        } else {
            // more precise calculation due to int and float having different precision near PHP_INT_MAX
            $this->boundary = PHP_INT_MAX - (int)((1 - $rate) * PHP_INT_MAX);
        }
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
        return [($traceId < $this->boundary), $this->tags];
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

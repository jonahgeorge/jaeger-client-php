<?php

namespace Jaeger\Tests\Sampler;

use Jaeger\Sampler\ProbabilisticSampler;
use PHPUnit\Framework\TestCase;
use const Jaeger\SAMPLER_PARAM_TAG_KEY;
use const Jaeger\SAMPLER_TYPE_PROBABILISTIC;
use const Jaeger\SAMPLER_TYPE_TAG_KEY;

class ProbablisticSamplerTest extends TestCase
{
    /**
     * @test
     * @dataProvider samplerProvider
     * @param float $rate
     * @param mixed $traceId
     * @param bool $decision
     */
    public function shouldDetermineWhetherOrTraceShouldBeSampled($rate, $traceId, $decision)
    {
        $sampler = new ProbabilisticSampler($rate);

        list($sampled, $tags) = $sampler->isSampled($traceId);

        $this->assertEquals($decision, $sampled);
        $this->assertEquals([
            SAMPLER_TYPE_TAG_KEY  => SAMPLER_TYPE_PROBABILISTIC,
            SAMPLER_PARAM_TAG_KEY => $rate,
        ], $tags);

        $sampler->close();
    }

    public function samplerProvider()
    {
        return [
            [1.0, PHP_INT_MAX-1, true],
            [0, 0, false],
            [0.5, PHP_INT_MIN + 10, true],
            [0.5, PHP_INT_MAX - 10, false],
        ];
    }

    /**
     * @test
     * @dataProvider rateProvider
     * @param mixed $rate
     * @expectedException \OutOfBoundsException
     * @expectedExceptionMessage Sampling rate must be between 0.0 and 1.0.
     */
    public function shouldThrowOutOfBoundsExceptionInCaseOfInvalidRate($rate)
    {
        new ProbabilisticSampler($rate);
    }

    public function rateProvider()
    {
        return [
            [1.1],
            [-0.1],
            [PHP_INT_MAX],
            [PHP_INT_MIN],
        ];
    }
}

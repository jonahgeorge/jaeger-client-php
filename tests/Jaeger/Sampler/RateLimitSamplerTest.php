<?php

namespace Jaeger\Test\Sampler;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Jaeger\Sampler\RateLimitingSampler;
use Jaeger\Util\RateLimiter;
use PHPUnit\Framework\TestCase;
use const Jaeger\SAMPLER_PARAM_TAG_KEY;
use const Jaeger\SAMPLER_TYPE_RATE_LIMITING;
use const Jaeger\SAMPLER_TYPE_TAG_KEY;

class RateLimitSamplerTest extends TestCase
{
    /**
     * @test
     * @dataProvider maxRateProvider
     * @param integer $maxTracesPerSecond
     * @param array $traces array of traces result after being sampled one after another
     * @throws
     */
    public function shouldDetermineWhetherOrTraceShouldBeSampled($maxTracesPerSecond, $traces)
    {
        $sampler = new RateLimitingSampler(
            $maxTracesPerSecond,
            new RateLimiter(new ArrayCachePool(), 'balance', 'lastTick')
        );

        $sampler->isSampled();
        foreach ($traces as $trace) {
            list($sleep, $decision) = $trace;
            usleep($sleep);
            list($sampled, $tags) = $sampler->isSampled();
            $this->assertEquals($decision, $sampled);
            $this->assertEquals([
                SAMPLER_TYPE_TAG_KEY  => SAMPLER_TYPE_RATE_LIMITING,
                SAMPLER_PARAM_TAG_KEY => $maxTracesPerSecond,
            ], $tags);
        }

        $sampler->close();
    }

    public function maxRateProvider()
    {
        return [
            [1000000, [[1, true], [1, true], [1, true]]],
            [1000, [[1000, true], [0, false], [1000, true]]],
            [0, [0, false]],
        ];
    }
}
<?php

namespace Jaeger\Sampler;

use PHPUnit\Framework\TestCase;

class ProbablisticSamplerTest extends TestCase
{
    private function getTags($type, $param)
    {
        return [
            'sampler.type' => $type,
            'sampler.param' => $param,
        ];
    }

    public function testProbabilisticSampler()
    {
        $sampler = new ProbabilisticSampler(0.5);

        list($sampled, $tags) = $sampler->isSampled(PHP_INT_MIN + 10);
        $this->assertTrue($sampled);
        $this->assertEquals($tags, $this->getTags('probabilistic', 0.5));

        list($sampled, $tags) = $sampler->isSampled(PHP_INT_MAX - 10);
        $this->assertFalse($sampled);
        $sampler->close();
    }
}

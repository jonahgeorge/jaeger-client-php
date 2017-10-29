<?php

use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\ProbabilisticSampler;
use PHPUnit\Framework\TestCase;

class SamplerTest extends TestCase
{
    private function getTags($type, $param)
    {
        return [
            'sampler.type' => $type,
            'sampler.param' => $param,
        ];
    }

    public function testConstSampler()
    {
        $sampler = new ConstSampler(True);
        list($sampled, $tags) = $sampler->isSampled(1);
        $this->assertTrue($sampled);

        list($sampled, $tags) = $sampler->isSampled(PHP_INT_MAX);
        $this->assertTrue($sampled);

        $sampler = new ConstSampler(False);
        list($sampled, $tags) = $sampler->isSampled(1);
        $this->assertFalse($sampled);

        list($sampled, $tags) = $sampler->isSampled(PHP_INT_MAX);
        $this->assertFalse($sampled);
        $this->assertEquals($tags, $this->getTags('const', False));
        $this->assertEquals('ConstSampler(False)', $sampler->__toString());
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
        $this->assertEquals($sampler->__toString(), 'ProbabilisticSampler(0.5)');
    }
}

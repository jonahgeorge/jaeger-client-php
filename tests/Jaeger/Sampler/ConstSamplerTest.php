<?php

namespace Jaeger\Sampler;

use PHPUnit\Framework\TestCase;

class ConstSamplerTest extends TestCase
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
    }
}

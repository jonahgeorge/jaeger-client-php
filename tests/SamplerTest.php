<?php

use Jaeger\Sampler\ConstSampler;
use PHPUnit\Framework\TestCase;

const MAX_INT = 1 << 63;

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

        list($sampled, $tags) = $sampler->isSampled(MAX_INT);
        $this->assertTrue($sampled);

        $sampler = new ConstSampler(False);
        list($sampled, $tags) = $sampler->isSampled(1);
        $this->assertFalse($sampled);

        list($sampled, $tags) = $sampler->isSampled(MAX_INT);
        $this->assertFalse($sampled);
        $this->assertEquals($tags, $this->getTags('const', False));
        $this->assertEquals('ConstSampler(False)', $sampler->__toString());
    }
}

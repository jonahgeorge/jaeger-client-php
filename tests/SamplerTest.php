<?php

use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\ProbabilisticSampler;
use PHPUnit\Framework\TestCase;

class SamplerTest extends TestCase
{
    private $minInt;
    private $maxInt;

    protected function setUp()
    {
        $this->minInt = defined('PHP_INT_MIN') ? PHP_INT_MIN : 2147483648;
        $this->maxInt = defined('PHP_INT_MAX') ? PHP_INT_MAX : 2147483647;
    }

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

        list($sampled, $tags) = $sampler->isSampled($this->minInt);
        $this->assertTrue($sampled);

        $sampler = new ConstSampler(False);
        list($sampled, $tags) = $sampler->isSampled(1);
        $this->assertFalse($sampled);

        list($sampled, $tags) = $sampler->isSampled($this->maxInt);
        $this->assertFalse($sampled);
        $this->assertEquals($tags, $this->getTags('const', False));
        $this->assertEquals('ConstSampler(False)', $sampler->__toString());
    }

    public function testProbabilisticSampler()
    {
        $sampler = new ProbabilisticSampler(0.5);

        list($sampled, $tags) = $sampler->isSampled($this->minInt + 10);
        $this->assertTrue($sampled);
        $this->assertEquals($tags, $this->getTags('probabilistic', 0.5));

        list($sampled, $tags) = $sampler->isSampled($this->maxInt - 10);
        $this->assertFalse($sampled);
        $sampler->close();
        $this->assertEquals($sampler->__toString(), 'ProbabilisticSampler(0.5)');
    }
}

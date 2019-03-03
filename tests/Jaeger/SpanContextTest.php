<?php

namespace Jaeger\Tests;

use Jaeger\SpanContext;
use PHPUnit\Framework\TestCase;

class SpanContextTest extends TestCase
{
    public function testIsDebugIdContainerOnly()
    {
        $ctx = new SpanContext(null, null, null, null, null, 'value1');
        $this->assertTrue($ctx->isDebugIdContainerOnly());
        $this->assertEquals($ctx->getDebugId(), 'value1');

        $ctx = new SpanContext(1, 2, 3, 1);
        $this->assertFalse($ctx->isDebugIdContainerOnly());
    }

    /**
     * @dataProvider contextDataProvider
     */
    public function testBaggageInit($traceId, $spanId, $parentId, $flags, $baggage, $expected)
    {
        $ctx = new SpanContext($traceId, $spanId, $parentId, $flags, $baggage);
        $this->assertEquals($expected, $ctx->getBaggage());
    }

    public function contextDataProvider()
    {
        return [
            [null, null, null, null, [], []],
            [null, null, null, null, null, []],
            [null, null, null, null, ['key' => 'val'], ['key' => 'val']],
        ];
    }
}

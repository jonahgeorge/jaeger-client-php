<?php

namespace Jaeger;

use PHPUnit\Framework\TestCase;

class SpanContextTest extends TestCase
{
    public function test_is_debug_id_container_only()
    {
        $ctx = SpanContext::withDebugId('value1');
        $this->assertTrue($ctx->isDebugIdContainerOnly());
        $this->assertEquals($ctx->getDebugId(), 'value1');

        $ctx = new SpanContext(1, 2, 3, 1);
        $this->assertFalse($ctx->isDebugIdContainerOnly());
    }
}

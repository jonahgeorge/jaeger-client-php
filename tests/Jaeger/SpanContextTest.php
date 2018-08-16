<?php

namespace Jaeger\Tests;

use Jaeger\SpanContext;
use PHPUnit\Framework\TestCase;

class SpanContextTest extends TestCase
{
    public function test_is_debug_id_container_only()
    {
        $ctx = new SpanContext(null, null, null, null, [], 'value1');
        $this->assertTrue($ctx->isDebugIdContainerOnly());
        $this->assertEquals($ctx->getDebugId(), 'value1');

        $ctx = new SpanContext(1, 2, 3, 1);
        $this->assertFalse($ctx->isDebugIdContainerOnly());
    }
}

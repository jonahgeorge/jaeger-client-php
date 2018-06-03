<?php

namespace Jaeger;

use Jaeger\Reporter\NullReporter;
use Jaeger\Sampler\ConstSampler;
use PHPUnit\Framework\TestCase;

class SpanTest extends TestCase
{
    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @var SpanContext
     */
    private $context;

    function setUp()
    {
        $this->tracer = new Tracer('test-service', new NullReporter, new ConstSampler);
        $this->context = new SpanContext(0, 0,0, SAMPLED_FLAG);
    }

    function testSetTag_TagsKeysAreUnique()
    {
        // Given
        $span = new Span($this->context, $this->tracer, 'test-operation');

        // When
        $span->setTag('foo', 'test-component-1');
        $span->setTag('foo', 'test-component-2');

        // Then
        $this->assertEquals( 1, count($span->getTags()));
        $this->assertEquals( 'test-component-2', $span->getTags()['foo']->value);
    }
}

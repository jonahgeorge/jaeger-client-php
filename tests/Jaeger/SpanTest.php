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

    public function testLog()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');

        $fields01 = [
            'event' => 'error',
            'message' => 'dummy error message',
        ];
        $fields02 = [
            'foo' => 'bar',
        ];

        $dateTime01 = new \DateTime('+5 seconds');
        $dateTime02 = $dateTime01->getTimestamp();
        $dateTime03 = microtime(true) + 5;

        $span->log($fields01, $dateTime01);
        $span->log($fields02, $dateTime01->getTimestamp());
        $span->log($fields02, $dateTime03);
        $span->log($fields02);

        $logs = $span->getLogs();

        $this->assertCount(4, $logs);

        $this->assertInternalType('integer', $logs[0]['timestamp']);
        $this->assertSame($dateTime01->getTimestamp() * 1000000, $logs[0]['timestamp']);
        $this->assertSame($fields01, $logs[0]['fields']);

        $this->assertInternalType('integer', $logs[1]['timestamp']);
        $this->assertSame($dateTime02 * 1000000, $logs[1]['timestamp']);
        $this->assertSame($fields02, $logs[1]['fields']);

        $this->assertInternalType('integer', $logs[2]['timestamp']);
        $this->assertSame((int) ($dateTime03 * 1000000), $logs[2]['timestamp']);
        $this->assertSame($fields02, $logs[2]['fields']);

        $this->assertInternalType('integer', $logs[3]['timestamp']);
        $this->assertSame($fields02, $logs[3]['fields']);
    }
}

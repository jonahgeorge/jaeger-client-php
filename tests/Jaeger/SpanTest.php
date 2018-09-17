<?php

namespace Jaeger\Tests;

use Jaeger\Reporter\NullReporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Span;
use Jaeger\SpanContext;
use Jaeger\Tracer;
use PHPUnit\Framework\TestCase;
use const Jaeger\SAMPLED_FLAG;

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

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->tracer = new Tracer('test-service', new NullReporter, new ConstSampler);
        $this->context = new SpanContext(0, 0,0, SAMPLED_FLAG);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->tracer = null;
        $this->context = null;
    }

    /** @test */
    public function shouldProperlyInitializeAtConstructTime()
    {
        $tags = [
            'foo-1' => 'test-component-1',
            'foo-2' => 'test-component-2',
            'foo-3' => 'test-component-3',
        ];

        $span = new Span($this->context, $this->tracer, 'test-operation', $tags);

        $this->assertEquals( 3, count($span->getTags()));
        $this->assertEquals($this->tracer, $span->getTracer());
        $this->assertEquals(false, $span->isDebug());
        $this->assertEquals(null, $span->getEndTime());
    }

    /** @test */
    public function shouldSetComponentThroughTag()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');

        $span->setTag('component', 'libredis');

        $spanReflection = new \ReflectionClass(Span::class);
        $component = $spanReflection->getProperty('component');
        $component->setAccessible(true);

        $this->assertEquals( 0, count($span->getTags()));
        $this->assertEquals( 'libredis', $component->getValue($span));
        $this->assertEquals( 'libredis', $span->getComponent());
    }

    /** @test */
    public function shouldSetTags()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');

        $this->assertEquals( 0, count($span->getTags()));

        $span->setTags([
            'foo-1' => 'test-component-1',
            'foo-2' => 'test-component-2',
            'foo-3' => 'test-component-3',
        ]);

        $this->assertEquals( 3, count($span->getTags()));
    }

    /** @test */
    public function shouldOverwriteTheSameTag()
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
    /** @test */
    public function shouldAddLogRecordsToTheSpan()
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
        $span->log($fields02, $dateTime01->getTimestamp()*1000000);
        $span->log($fields02, $dateTime03);
        $span->log($fields02);

        $logs = $span->getLogs();

        $this->assertCount(4, $logs);

        $this->assertInternalType('integer', $logs[0]['timestamp']);
        $this->assertEquals((int)($dateTime01->format('U.u')*1000000), $logs[0]['timestamp']);
        $this->assertSame($fields01, $logs[0]['fields']);

        $this->assertInternalType('integer', $logs[1]['timestamp']);
        $this->assertSame($dateTime02*1000000, $logs[1]['timestamp']);
        $this->assertSame($fields02, $logs[1]['fields']);

        $this->assertInternalType('integer', $logs[2]['timestamp']);
        $this->assertSame((int) ($dateTime03 * 1000000), $logs[2]['timestamp']);
        $this->assertSame($fields02, $logs[2]['fields']);

        $this->assertInternalType('integer', $logs[3]['timestamp']);
        $this->assertSame($fields02, $logs[3]['fields']);
    }

    /** @test */
    public function timingDefaultTimes()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $span->finish();

        $this->assertEquals(0.0, round(($span->getEndTime() - $span->getStartTime()) / 1000000));
    }

    /** @test */
    public function timingSetStartTimeAsDateTime()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation', [], new \DateTime('-2 seconds'));
        $span->finish();

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetEndTimeAsDateTime()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');

        $endTime = new \DateTime('+2 seconds');
        // add microseconds because php < 7.1 has a bug
        // https://bugs.php.net/bug.php?id=48225
        if (version_compare(phpversion(), '7.1', '<')) {
            list($usec) = explode(' ', microtime());
            $endTime = \DateTime::createFromFormat('U.u', $endTime->format('U')+$usec);
        }
        $span->finish($endTime);

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetStartTimeAsInt()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation', [], (int) round((microtime(true) - 2) * 1000000));
        $span->finish();

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetEndTimeAsInt()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $span->finish((int) round((microtime(true) + 2) * 1000000));

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetStartTimeAsFloat()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation', [], microtime(true) - 2);
        $span->finish();

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetEndTimeAsFloat()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $span->finish(microtime(true) + 2);

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetMixedTimes()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation', [], new \DateTime());
        $span->finish(microtime(true) + 2);

        $this->assertSpanDuration($span);
    }

    protected function assertSpanDuration(Span $span)
    {
        $this->assertEquals(2, (int)(($span->getEndTime() - $span->getStartTime()) / 1000000));
    }

    /** @test */
    public function invalidStartTime()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Time should be one of the types int|float|DateTime|null, got string.');
        $span = new Span($this->context, $this->tracer, 'test-operation', [], 'string');
    }

    /** @test */
    public function invalidEndTime()
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Time should be one of the types int|float|DateTime|null, got array.');
        $span->finish([]);
    }
}

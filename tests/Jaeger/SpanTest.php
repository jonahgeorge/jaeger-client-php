<?php

namespace Jaeger\Tests;

use Jaeger\Reporter\NullReporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Span;
use Jaeger\SpanContext;
use Jaeger\Thrift\Agent\Zipkin\AnnotationType;
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
    public function setUp(): void
    {
        $this->tracer = new Tracer('test-service', new NullReporter, new ConstSampler);
        $this->context = new SpanContext(0, 0,0, SAMPLED_FLAG);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->tracer = null;
        $this->context = null;
    }

    /** @test */
    public function shouldProperlyInitializeAtConstructTime(): void
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
    public function shouldSetComponentThroughTag(): void
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
    public function shouldSetTags(): void
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
    public function shouldSetDifferentTypeOfTags() {
        $span = new Span($this->context, $this->tracer, 'test-operation');

        $this->assertEquals( 0, count($span->getTags()));

        $span->setTags([
            'tag-bool1' => true,
            'tag-bool2' => false,
            'tag-int' => 1234567,
            'tag-float' => 1.23456,
            'tag-string' => "hello-world"
        ]);

        $tags = array_values($span->getTags());
        $this->assertEquals( 5, count($tags));

        $index = 0;
        $this->assertTrue($tags[$index]->annotation_type === AnnotationType::BOOL);
        $this->assertTrue($tags[$index]->value === true);
        $this->assertTrue($tags[$index]->key === 'tag-bool1');
        $index++;

        $this->assertTrue($tags[$index]->annotation_type === AnnotationType::BOOL);
        $this->assertTrue($tags[$index]->value === false);
        $this->assertTrue($tags[$index]->key === 'tag-bool2');
        $index++;

        $this->assertTrue($tags[$index]->annotation_type === AnnotationType::I64);
        $this->assertTrue($tags[$index]->value === 1234567);
        $this->assertTrue($tags[$index]->key === 'tag-int');
        $index++;

        $this->assertTrue($tags[$index]->annotation_type === AnnotationType::DOUBLE);
        $this->assertTrue($tags[$index]->value === 1.23456);
        $this->assertTrue($tags[$index]->key === 'tag-float');
        $index++;

        $this->assertTrue($tags[$index]->annotation_type === AnnotationType::STRING);
        $this->assertTrue($tags[$index]->value === "hello-world");
        $this->assertTrue($tags[$index]->key === 'tag-string');
    }

    /** @test */
    public function shouldOverwriteTheSameTag(): void
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
    public function shouldAddLogRecordsToTheSpan(): void
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

        $this->assertIsInt($logs[0]['timestamp']);
        $this->assertEquals((int)($dateTime01->format('U.u')*1000000), $logs[0]['timestamp']);
        $this->assertSame($fields01, $logs[0]['fields']);

        $this->assertIsInt($logs[1]['timestamp']);
        $this->assertSame($dateTime02*1000000, $logs[1]['timestamp']);
        $this->assertSame($fields02, $logs[1]['fields']);

        $this->assertIsInt($logs[2]['timestamp']);
        $this->assertSame((int) ($dateTime03 * 1000000), $logs[2]['timestamp']);
        $this->assertSame($fields02, $logs[2]['fields']);

        $this->assertIsInt($logs[3]['timestamp']);
        $this->assertSame($fields02, $logs[3]['fields']);
    }

    /** @test */
    public function timingDefaultTimes(): void
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $span->finish();

        $this->assertEquals(0.0, round(($span->getEndTime() - $span->getStartTime()) / 1000000));
    }

    /** @test */
    public function timingSetStartTimeAsDateTime(): void
    {
        $span = new Span($this->context, $this->tracer, 'test-operation', [], new \DateTime('-2 seconds'));
        $span->finish();

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetEndTimeAsDateTime(): void
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
    public function timingSetStartTimeAsInt(): void
    {
        $span = new Span($this->context, $this->tracer, 'test-operation', [], (int) round((microtime(true) - 2) * 1000000));
        $span->finish();

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetEndTimeAsInt(): void
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $span->finish((int) round((microtime(true) + 2) * 1000000));

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetStartTimeAsFloat(): void
    {
        $span = new Span($this->context, $this->tracer, 'test-operation', [], microtime(true) - 2);
        $span->finish();

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetEndTimeAsFloat(): void
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $span->finish(microtime(true) + 2);

        $this->assertSpanDuration($span);
    }

    /** @test */
    public function timingSetMixedTimes(): void
    {
        $span = new Span($this->context, $this->tracer, 'test-operation', [], new \DateTime());
        $span->finish(microtime(true) + 2);

        $this->assertSpanDuration($span);
    }

    protected function assertSpanDuration(Span $span): void
    {
        $this->assertEquals(2, (int)(($span->getEndTime() - $span->getStartTime()) / 1000000));
    }

    /** @test */
    public function invalidStartTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Time should be one of the types int|float|DateTime|null, got string.');
        $span = new Span($this->context, $this->tracer, 'test-operation', [], 'string');
    }

    /** @test */
    public function invalidEndTime(): void
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Time should be one of the types int|float|DateTime|null, got array.');
        $span->finish([]);
    }
}

<?php


use Jaeger\Reporter\NullReporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Span;
use Jaeger\SpanContext;
use Jaeger\Tracer;
use const Jaeger\SAMPLED_FLAG;

class SpanToJaegerMapperTest extends \PHPUnit\Framework\TestCase
{
    private $serviceName = "test-service";
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
        $this->tracer = new Tracer($this->serviceName, new NullReporter, new ConstSampler);
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
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $span->setTags([
            "tag-bool1" => true,
            "tag-bool2" => false,
            "tag-int" => 1234567,
            "tag-float" => 1.23456,
            "tag-string" => "hello-world"
        ]);

        $mapper = new \Jaeger\Mapper\SpanToJaegerMapper();
        $thriftSpan = $mapper->mapSpanToJaeger($span);

        $index = 0;
        $this->assertEquals($thriftSpan->tags[$index]->key, "component");
        $this->assertEquals($thriftSpan->tags[$index]->vType, \Jaeger\Thrift\TagType::STRING);
        $this->assertEquals($thriftSpan->tags[$index]->vStr, $this->serviceName);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-bool1");
        $this->assertEquals($thriftSpan->tags[$index]->vType, \Jaeger\Thrift\TagType::BOOL);
        $this->assertEquals($thriftSpan->tags[$index]->vBool, true);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-bool2");
        $this->assertEquals($thriftSpan->tags[$index]->vType, \Jaeger\Thrift\TagType::BOOL);
        $this->assertEquals($thriftSpan->tags[$index]->vBool, false);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-int");
        $this->assertEquals($thriftSpan->tags[$index]->vType, \Jaeger\Thrift\TagType::LONG);
        $this->assertEquals($thriftSpan->tags[$index]->vLong, 1234567);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-float");
        $this->assertEquals($thriftSpan->tags[$index]->vType, \Jaeger\Thrift\TagType::DOUBLE);
        $this->assertEquals($thriftSpan->tags[$index]->vDouble, 1.23456);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-string");
        $this->assertEquals($thriftSpan->tags[$index]->vType, \Jaeger\Thrift\TagType::STRING);
        $this->assertEquals($thriftSpan->tags[$index]->vStr, "hello-world");
        $index++;

    }

}

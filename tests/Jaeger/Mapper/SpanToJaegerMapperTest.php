<?php

use Jaeger\Mapper\SpanToJaegerMapper;
use Jaeger\Reporter\NullReporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Span;
use Jaeger\SpanContext;
use Jaeger\Thrift\TagType;
use Jaeger\Tracer;
use const Jaeger\SAMPLED_FLAG;
use const OpenTracing\Tags\COMPONENT;
use const OpenTracing\Tags\PEER_HOST_IPV4;
use const OpenTracing\Tags\PEER_PORT;
use const OpenTracing\Tags\PEER_SERVICE;
use const OpenTracing\Tags\SPAN_KIND;
use const OpenTracing\Tags\SPAN_KIND_RPC_CLIENT;

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
        $this->context = new SpanContext('5f2c2ea76d359a165f2c2ea76d35b26b', 0, 0, SAMPLED_FLAG);
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

        $mapper = new SpanToJaegerMapper();
        $thriftSpan = $mapper->mapSpanToJaeger($span);

        $this->assertSame(6857907629205068310, $thriftSpan->traceIdHigh);
        $this->assertSame(6857907629205074539, $thriftSpan->traceIdLow);

        $index = 0;
        $this->assertEquals($thriftSpan->tags[$index]->key, "component");
        $this->assertEquals($thriftSpan->tags[$index]->vType, TagType::STRING);
        $this->assertEquals($thriftSpan->tags[$index]->vStr, $this->serviceName);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-bool1");
        $this->assertEquals($thriftSpan->tags[$index]->vType, TagType::BOOL);
        $this->assertEquals($thriftSpan->tags[$index]->vBool, true);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-bool2");
        $this->assertEquals($thriftSpan->tags[$index]->vType, TagType::BOOL);
        $this->assertEquals($thriftSpan->tags[$index]->vBool, false);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-int");
        $this->assertEquals($thriftSpan->tags[$index]->vType, TagType::LONG);
        $this->assertEquals($thriftSpan->tags[$index]->vLong, 1234567);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-float");
        $this->assertEquals($thriftSpan->tags[$index]->vType, TagType::DOUBLE);
        $this->assertEquals($thriftSpan->tags[$index]->vDouble, 1.23456);
        $index++;

        $this->assertEquals($thriftSpan->tags[$index]->key, "tag-string");
        $this->assertEquals($thriftSpan->tags[$index]->vType, TagType::STRING);
        $this->assertEquals($thriftSpan->tags[$index]->vStr, "hello-world");
        $index++;
    }

    /**
     * @dataProvider specialTagProvider
     * @param array<string, mixed> $tags
     * @return void
     */
    public function testSpecialTagsAreAdded(array $tags): void
    {
        $span = new Span($this->context, $this->tracer, 'test-operation');
        $span->setTags($tags);

        // The component tag is always added, even if it's not specified in tags
        $expectedTagValues = array_merge([COMPONENT => $this->serviceName], $tags);

        $mapper = new SpanToJaegerMapper();
        $thriftSpan = $mapper->mapSpanToJaeger($span);

        $foundTags = [];

        foreach ($thriftSpan->tags as $tag) {
            $foundTags[] = $tag->key;

            switch ($tag->key) {
                case PEER_SERVICE:
                case PEER_HOST_IPV4:
                case SPAN_KIND:
                case COMPONENT:
                    $this->assertEquals(TagType::STRING, $tag->vType, 'Incorrect tag value type');
                    $this->assertEquals($expectedTagValues[$tag->key], $tag->vStr, 'Incorrect tag value');
                    break;
                case PEER_PORT:
                    $this->assertEquals(TagType::LONG, $tag->vType, 'Incorrect tag value type');
                    $this->assertEquals($expectedTagValues[$tag->key], $tag->vLong, 'Incorrect tag value');
                    break;
            }
        }

        $this->assertEqualsCanonicalizing(array_keys($expectedTagValues), $foundTags, 'Some of the tags are missing');
    }

    public function specialTagProvider(): array
    {
        return [
            [
                [
                    'bool_tag' => true,
                    PEER_SERVICE => 'my_service',
                    PEER_HOST_IPV4 => '127.0.0.1',
                    PEER_PORT => 443,
                    SPAN_KIND => SPAN_KIND_RPC_CLIENT,
                    COMPONENT => 'grpc',
                ],
            ],
            [
                [
                    'int_tag' => 5,
                    PEER_HOST_IPV4 => '192.168.0.1',
                    PEER_PORT => 80,
                ],
            ],
            [
                [
                    'string_tag' => 'testing-tag',
                    PEER_PORT => 80,
                    COMPONENT => 'grpc',
                ],
            ],
            [
                [
                    'string_tag' => 'testing-tag',
                ],
            ],
        ];
    }
}

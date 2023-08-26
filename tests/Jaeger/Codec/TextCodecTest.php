<?php

namespace Jaeger\Tests\Codec;

use Exception;
use const Jaeger\BAGGAGE_HEADER_PREFIX;
use Jaeger\Codec\TextCodec;
use const Jaeger\DEBUG_ID_HEADER_KEY;
use Jaeger\SpanContext;
use const Jaeger\TRACE_ID_HEADER;
use PHPUnit\Framework\TestCase;

class TextCodecTest extends TestCase
{
    /** @var TextCodec */
    private $textCodec;

    public function setUp(): void
    {
        $this->textCodec = new TextCodec();
    }

    public function testCanInjectSimpleContextInCarrier(): void
    {
        $context = new SpanContext('trace-id', 'span-id', null, null);
        $carrier = [];

        $this->textCodec->inject($context, $carrier);

        $this->assertCount(1 , $carrier);
        $this->assertArrayHasKey(TRACE_ID_HEADER, $carrier);
        $this->assertSame('trace-id:0:0:0', $carrier[TRACE_ID_HEADER]);
    }

    public function testTraceIdConvertToHex(): void
    {
        $context = new SpanContext(3003082921159205154, 'span-id', null, null);
        $carrier = [];

        $this->textCodec->inject($context, $carrier);
        $this->assertSame('29ad18017abca122:0:0:0', $carrier[TRACE_ID_HEADER]);
    }

    /**
     * @dataProvider contextDataProvider
     * @param bool $urlEncode
     * @param $baggage
     */
    public function testCanInjectContextBaggageInCarrier(bool $urlEncode, $baggage, $injectedBaggage): void
    {
        $carrier = [];

        $context = new SpanContext('trace-id', 'span-id', null, null, $baggage);
        $textCodec = new TextCodec($urlEncode);
        $textCodec->inject($context, $carrier);

        $this->assertCount(1 + count($baggage) , $carrier);
        $this->assertArrayHasKey(TRACE_ID_HEADER, $carrier);
        foreach ($injectedBaggage as $key => $value) {
            $this->assertArrayHasKey(BAGGAGE_HEADER_PREFIX . $key, $carrier);
            $this->assertEquals($carrier[BAGGAGE_HEADER_PREFIX . $key], $value);
        }
    }

    public function contextDataProvider()
    {
        return [
            [false, ['baggage-1' => 'baggage value'], ['baggage-1' => 'baggage value']],
            [false, ['baggage-1' => 'https://testdomain.sk'], ['baggage-1' => 'https://testdomain.sk']],
            [true, ['baggage-1' => 'https://testdomain.sk'], ['baggage-1' => 'https%3A%2F%2Ftestdomain.sk']],
        ];
    }

    /**
     * @dataProvider carrierDataProvider
     * @param $urlEncode
     * @param $carrier
     * @param $traceId
     * @param $spanId
     * @param $parentId
     * @param $flags
     * @param $baggage
     * @throws \Exception
     */
    public function testSpanContextParsingFromHeader($urlEncode, $carrier, $traceId, $spanId, $parentId, $flags, $baggage): void
    {
        $textCodec = new TextCodec($urlEncode);
        $spanContext = $textCodec->extract($carrier);

        $this->assertEquals($traceId, $spanContext->getTraceId());
        $this->assertEquals($spanId, $spanContext->getSpanId());
        $this->assertEquals($parentId, $spanContext->getParentId());
        $this->assertEquals($flags, $spanContext->getFlags());
        $this->assertCount(count($baggage), $spanContext->getBaggage() ? $spanContext->getBaggage() : []);
        foreach ($baggage as $key => $value) {
            $this->assertEquals($value, $spanContext->getBaggageItem($key));
        }
    }

    public function carrierDataProvider(): array
    {
        return [
            [
                false,
                [
                    TRACE_ID_HEADER => '32834e4115071776:f7802330248418d:f123456789012345:1'
                ],
                "32834e4115071776",
                "1114643325879075213",
                "-1070935975401544891",
                1,
                []
            ],
            [
                false,
                [
                    TRACE_ID_HEADER => '32834e4115071776:f7802330248418d:f123456789012345:1',
                    BAGGAGE_HEADER_PREFIX . 'baggage-1' => 'https://testdomain.sk',
                ],
                "32834e4115071776",
                "1114643325879075213",
                "-1070935975401544891",
                1,
                ['baggage-1' => 'https://testdomain.sk']
            ],
            [
                true,
                [
                    TRACE_ID_HEADER => '32834e4115071776:f7802330248418d:f123456789012345:1',
                    BAGGAGE_HEADER_PREFIX . 'baggage-1' => 'https%3A%2F%2Ftestdomain.sk',
                ],
                "32834e4115071776",
                "1114643325879075213",
                "-1070935975401544891",
                1,
                ['baggage-1' => 'https://testdomain.sk']
            ]
        ];
    }

    public function testBaggageWithoutTraceContext(): void
    {
        $carrier = [BAGGAGE_HEADER_PREFIX.'test' => 'some data'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('baggage without trace ctx');

        $this->textCodec->extract($carrier);
    }

    public function testInvalidSpanContextParsingFromHeader(): void
    {
        $carrier = [TRACE_ID_HEADER => 'invalid_data'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Malformed tracer state string.');

        $this->textCodec->extract($carrier);
    }

    public function testExtractDebugSpanContext(): void
    {
        $carrier = [DEBUG_ID_HEADER_KEY => 'debugId'];

        $spanContext = $this->textCodec->extract($carrier);

        $this->assertEquals('debugId', $spanContext->getDebugId());
        $this->assertNull($spanContext->getTraceId());
        $this->assertNull($spanContext->getSpanId());
        $this->assertNull($spanContext->getParentId());
        $this->assertNull($spanContext->getFlags());
    }


    public function testExtractEmptySpanContext(): void
    {
        $spanContext = $this->textCodec->extract([]);
        $this->assertNull($spanContext);
    }
}

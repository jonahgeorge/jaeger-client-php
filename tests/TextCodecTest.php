<?php

use Jaeger\SpanContext;
use Jaeger\Codec\TextCodec;
use PHPUnit\Framework\TestCase;

class TextCodecTest extends TestCase
{
    /**
     * @var TextCodec
     */
    private $textCodec;

    public function setUp()
    {
        $this->ctx = new SpanContext('trace-id', 'span-id', null, null);
        $this->textCodec = new TextCodec();
    }

    public function testCanInjectContextInCarrier()
    {
        $carrier = [];

        $this->textCodec->inject($this->ctx, $carrier);

        $this->assertFalse(empty($carrier));
    }
}
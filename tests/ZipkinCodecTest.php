<?php

use Jaeger\SpanContext;
use Jaeger\Codec\ZipkinCodec;
use PHPUnit\Framework\TestCase;

class ZipkinCodecTest extends TestCase
{
    /**
     * @var TextCodec
     */
    private $textCodec;

    public function setUp()
    {
        $this->ctx = new SpanContext('trace-id', 'span-id', null, null);
        $this->textCodec = new ZipkinCodec();
    }

    public function testCanInjectContextInCarrier()
    {
        $carrier = [];

        $this->textCodec->inject($this->ctx, $carrier);

        $this->assertFalse(empty($carrier));
    }
}
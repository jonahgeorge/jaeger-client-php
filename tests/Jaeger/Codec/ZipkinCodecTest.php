<?php

namespace Jaeger\Tests\Codec;

use Jaeger\Codec\ZipkinCodec;
use Jaeger\SpanContext;
use PHPUnit\Framework\TestCase;
use const Jaeger\DEBUG_FLAG;
use const Jaeger\SAMPLED_FLAG;

class ZipkinCodecTest extends TestCase
{
    /** @var ZipkinCodec */
    private $codec;

    function setUp()
    {
        $this->codec = new ZipkinCodec;
    }

    function testInject()
    {
        // Given
        $traceId = 123;
        $spanId = 456;
        $parentId = 789;

        $spanContext = new SpanContext(
            $traceId,
            $spanId,
            $parentId,
            SAMPLED_FLAG
        );
        $carrier = [];

        // When
        $this->codec->inject($spanContext, $carrier);

        // Then
        $this->assertEquals('7b', $carrier['X-B3-TraceId']);
        $this->assertEquals('1c8', $carrier['X-B3-SpanId']);
        $this->assertEquals('315', $carrier['X-B3-ParentSpanId']);
        $this->assertEquals('1', $carrier['X-B3-Flags']);
    }

    function testExtract()
    {
        // Given
        $carrier = [
            'x-b3-traceid' => 'a53bf337d7e455e1',
            'x-b3-spanid' => '153bf227d1f455a1',
            'x-b3-parentspanid' => 'a53bf337d7e455e1',
            'x-b3-flags' => '1',
        ];

        // When
        $spanContext = $this->codec->extract($carrier);

        // Then
        $this->assertEquals(new SpanContext(
            '-6540366612654696991',
            '1530082751262512545',
            '-6540366612654696991',
            DEBUG_FLAG
        ), $spanContext);
    }

    function testExtractWithoutParentSpanId()
    {
        // Given
        $carrier = [
            'x-b3-traceid' => '8d824d69da5f50d9',
            'x-b3-spanid' => '8d824d69da5f50d9',
            'x-b3-flags' => '1',
        ];

        // When
        $spanContext = $this->codec->extract($carrier);

        // Then
        $this->assertEquals(new SpanContext(
            '-8249946450358742823',
            '-8249946450358742823',
            '0',
            DEBUG_FLAG
        ), $spanContext);
    }

    function testExtractInvalidHeader()
    {
        // Given
        $carrier = [
            'x-b3-traceid' => 'zzzz',
            'x-b3-spanid' => '463ac35c9f6413ad48485a3953bb6124',
            'x-b3-flags' => '1',
        ];

        // When
        $spanContext = $this->codec->extract($carrier);

        // Then
        $this->assertEquals(null, $spanContext);
    }
}
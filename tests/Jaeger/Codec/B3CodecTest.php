<?php

namespace Jaeger\Codec;

use const Jaeger\DEBUG_FLAG;
use const Jaeger\SAMPLED_FLAG;
use Jaeger\SpanContext;
use PHPUnit\Framework\TestCase;

class B3CodecTest extends TestCase
{
    /** @var B3Codec */
    private $codec;

    function setUp()
    {
        $this->codec = new B3Codec;
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
        $carrier = array();

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
        $carrier = array(
            'x-b3-traceid' => '463ac35c9f6413ad48485a3953bb6124',
            'x-b3-spanid' => '463ac35c9f6413ad48485a3953bb6124',
            'x-b3-parentspanid' => '463ac35c9f6413ad48485a3953bb6124',
            'x-b3-flags' => '1',
        );

        // When
        $spanContext = $this->codec->extract($carrier);

        // Then
        $this->assertEquals(new SpanContext(
            '93351075330931896558786731617803788580',
            '93351075330931896558786731617803788580',
            '93351075330931896558786731617803788580',
            DEBUG_FLAG
        ), $spanContext);
    }

    function testExtractWithoutParentSpanId()
    {
        // Given
        $carrier = array(
            'x-b3-traceid' => '463ac35c9f6413ad48485a3953bb6124',
            'x-b3-spanid' => '463ac35c9f6413ad48485a3953bb6124',
            'x-b3-flags' => '1',
        );

        // When
        $spanContext = $this->codec->extract($carrier);

        // Then
        $this->assertEquals(new SpanContext(
            '93351075330931896558786731617803788580',
            '93351075330931896558786731617803788580',
            '0',
            DEBUG_FLAG
        ), $spanContext);
    }

    function testExtractInvalidHeader()
    {
        // Given
        $carrier = array(
            'x-b3-traceid' => 'zzzz',
            'x-b3-spanid' => '463ac35c9f6413ad48485a3953bb6124',
            'x-b3-flags' => '1',
        );

        // When
        $spanContext = $this->codec->extract($carrier);

        // Then
        $this->assertEquals(null, $spanContext);
    }
}

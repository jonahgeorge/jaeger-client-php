<?php

namespace Jaeger\Codec;

use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    function testHeaderToHex()
    {
        // Given
        $traceId = '93351075330931896558786731617803788580';

        // When
        $traceIdHeader = Utils::headerToHex($traceId);

        // Then
        $this->assertEquals('463ac35c9f6413ad48485a3953bb6124', $traceIdHeader);
    }

    function testHexToHeader()
    {
        // Given
        $traceIdHeader = '463ac35c9f6413ad48485a3953bb6124';

        // When
        $traceId = Utils::hexToHeader($traceIdHeader);

        // Then
        $this->assertEquals('93351075330931896558786731617803788580', $traceId);
    }
}

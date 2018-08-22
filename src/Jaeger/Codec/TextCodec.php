<?php

namespace Jaeger\Codec;

use Exception;
use Jaeger\SpanContext;

use const Jaeger\TRACE_ID_HEADER;
use const Jaeger\BAGGAGE_HEADER_PREFIX;
use const Jaeger\DEBUG_ID_HEADER_KEY;

use function Phlib\base_convert;

class TextCodec implements CodecInterface
{
    private $urlEncoding;
    private $traceIdHeader;
    private $baggagePrefix;
    private $debugIdHeader;
    private $prefixLength;

    /**
     * @param bool $urlEncoding
     * @param string $traceIdHeader
     * @param string $baggageHeaderPrefix
     * @param string $debugIdHeader
     */
    public function __construct(
        bool $urlEncoding = false,
        string $traceIdHeader = TRACE_ID_HEADER,
        string $baggageHeaderPrefix = BAGGAGE_HEADER_PREFIX,
        string $debugIdHeader = DEBUG_ID_HEADER_KEY
    ) {
        $this->urlEncoding = $urlEncoding;
        $this->traceIdHeader = str_replace('_', '-', strtolower($traceIdHeader));
        $this->baggagePrefix = str_replace('_', '-', strtolower($baggageHeaderPrefix));
        $this->debugIdHeader = str_replace('_', '-', strtolower($debugIdHeader));
        $this->prefixLength = strlen($baggageHeaderPrefix);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Jaeger\Tracer::inject
     *
     * @param SpanContext $spanContext
     * @param mixed $carrier
     *
     * @return void
     */
    public function inject(SpanContext $spanContext, &$carrier)
    {
        $carrier[$this->traceIdHeader] = $this->spanContextToString(
            $spanContext->getTraceId(),
            $spanContext->getSpanId(),
            $spanContext->getParentId(),
            $spanContext->getFlags()
        );

        $baggage = $spanContext->getBaggage();
        if (empty($baggage)) {
            return;
        }

        foreach ($baggage as $key => $value) {
            $encodedValue = $value;

            if ($this->urlEncoding) {
                $encodedValue = urlencode($value);
            }

            $carrier[$this->baggagePrefix . $key] = $encodedValue;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Jaeger\Tracer::extract
     *
     * @param mixed $carrier
     * @return SpanContext|null
     *
     * @throws Exception
     */
    public function extract($carrier)
    {
        $traceId = null;
        $spanId  = null;
        $parentId  = null;
        $flags = null;
        $baggage = null;
        $debugId = null;

        foreach ($carrier as $key => $value) {
            $ucKey = strtolower($key);

            if ($ucKey === $this->traceIdHeader) {
                if ($this->urlEncoding) {
                    $value = urldecode($value);
                }
                list($traceId, $spanId, $parentId, $flags) =
                    $this->spanContextFromString($value);
            } elseif ($this->startsWith($ucKey, $this->baggagePrefix)) {
                if ($this->urlEncoding) {
                    $value = urldecode($value);
                }
                $attrKey = substr($key, $this->prefixLength);
                if ($baggage === null) {
                    $baggage = [strtolower($attrKey) => $value];
                } else {
                    $baggage[strtolower($attrKey)] = $value;
                }
            } elseif ($ucKey === $this->debugIdHeader) {
                if ($this->urlEncoding) {
                    $value = urldecode($value);
                }
                $debugId = $value;
            }
        }

        if ($traceId === null && $baggage !== null) {
            throw new Exception('baggage without trace ctx');
        }

        if ($traceId === null) {
            if ($debugId !== null) {
                return new SpanContext(null, null, null, null, [], $debugId);
            }
            return null;
        }

        return new SpanContext($traceId, $spanId, $parentId, $flags);
    }

    /**
     * Store a span context to a string.
     *
     * @param int $traceId
     * @param int $spanId
     * @param int $parentId
     * @param int $flags
     * @return string
     */
    private function spanContextToString($traceId, $spanId, $parentId, $flags)
    {
        $parentId = $parentId ?? 0;
        return sprintf('%x:%x:%x:%x', $traceId, $spanId, $parentId, $flags);
    }

    /**
     * Create a span context from a string.
     *
     * @param string $value
     * @return array
     *
     * @throws Exception
     */
    private function spanContextFromString($value): array
    {
        $parts = explode(':', $value);

        if (count($parts) != 4) {
            throw new Exception('Malformed tracer state string.');
        }

        return [
            $this->hexToInt64($parts[0]),
            $this->hexToInt64($parts[1]),
            $this->hexToInt64($parts[2]),
            $parts[3],
        ];
    }

    /**
     * Checks that a string ($haystack) starts with a given prefix ($needle).
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    private function startsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) == $needle;
    }

    /**
     * Incoming trace/span IDs are hex representations of 64-bit values. PHP
     * represents ints internally as signed 32- or 64-bit values, but base_convert
     * converts to string representations of arbitrarily large positive numbers.
     * This means at least half the incoming IDs will be larger than PHP_INT_MAX.
     *
     * Thrift, while building a binary representation of the IDs, performs bitwise
     * operations on the string values, implicitly casting to int and capping them
     * at PHP_INT_MAX. So, incoming IDs larger than PHP_INT_MAX will be serialized
     * and sent to the agent as PHP_INT_MAX, breaking trace/span correlation.
     *
     * This method therefore, on 64-bit architectures, splits the hex string into
     * high and low values, converts them separately to ints, and manually combines
     * them into a proper signed int. This int is then handled properly by the
     * Thrift package.
     *
     * On 32-bit architectures, it falls back to base_convert.
     *
     * @param string $hex
     * @return string|int
     */
    private function hexToInt64($hex)
    {
        // If we're on a 32-bit architecture, fall back to base_convert.
        if (PHP_INT_SIZE === 4) {
            return base_convert($hex, 16, 10);
        }

        $hi = intval(substr($hex, -16, -8), 16);
        $lo = intval(substr($hex, -8, 8), 16);

        return $hi << 32 | $lo;
    }
}

<?php

namespace Jaeger\Codec;

use Exception;
use const Jaeger\BAGGAGE_HEADER_PREFIX;
use const Jaeger\DEBUG_ID_HEADER_KEY;
use Jaeger\SpanContext;
use const Jaeger\TRACE_ID_HEADER;

class TextCodec implements CodecInterface
{
    private $urlEncoding;
    private $traceIdHeader;
    private $baggagePrefix;
    private $debugIdHeader;
    private $prefixLength;

    public function __construct(
        bool $urlEncoding = False,
        string $traceIdHeader = TRACE_ID_HEADER,
        string $baggageHeaderPrefix = BAGGAGE_HEADER_PREFIX,
        string $debugIdHeader = DEBUG_ID_HEADER_KEY
    )
    {
        $this->urlEncoding = $urlEncoding;
        $this->traceIdHeader = str_replace('_', '-', strtolower($traceIdHeader));
        $this->baggagePrefix = str_replace('_', '-', strtolower($baggageHeaderPrefix));
        $this->debugIdHeader = str_replace('_', '-', strtolower($debugIdHeader));
        $this->prefixLength = strlen($baggageHeaderPrefix);
    }

    public function inject(SpanContext $spanContext, $carrier)
    {
        $carrier[$this->traceIdHeader] = $this->spanContextToString(
            $spanContext->getTraceId(),
            $spanContext->getSpanId(),
            $spanContext->getParentId(),
            $spanContext->getFlags()
        );

        $baggage = $spanContext->getBaggage();
        if ($baggage) {
            foreach ($baggage as $key => $value) {
                if ($this->urlEncoding) {
                    $encodedValue = urlencode($value);
                } else {
                    $encodedValue = $value;
                }
                $carrier[$this->baggagePrefix . $key] = $encodedValue;
            }
        }
    }

    /** @return SpanContext|null */
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
                return SpanContext::withDebugId($debugId);
            }
            return null;
        }

        return new SpanContext($traceId, $spanId, $parentId, $flags);
    }

    private function spanContextToString($traceId, $spanId, $parentId, $flags)
    {
        $parentId = $parentId ?? 0;
        return sprintf('%x:%x:%x:%x', $traceId, $spanId, $parentId, $flags);
    }

    private function spanContextFromString($value): array
    {
        $parts = explode(':', $value);

        if (count($parts) != 4) {
            throw new \Exception('Malformed tracer state string');
        }

        return [
            $this->baseConvert($parts[0]),
            $this->baseConvert($parts[1]),
            $this->baseConvert($parts[2]),
            $parts[3],
        ];
    }

    /**
     * Converts any string of any base to any other base without PHP native
     * method base_convert's double and float limitations.
     *
     * @url https://github.com/credomane/php_baseconvert
     *
     * @param string $numstring
     * @param int $frombase
     * @param int $tobase
     *
     * @return string
     */
    private function baseConvert($numstring, $frombase = 16, $tobase = 10)
    {
        $chars = "0123456789abcdefghijklmnopqrstuvwxyz";
        $tostring = substr($chars, 0, $tobase);
        $length = strlen($numstring);
        $number = '';
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $number[$i] = strpos($chars, $numstring{$i});
        }

        do {
            $divide = 0;
            $newlen = 0;

            for ($i = 0; $i < $length; $i++) {
                $divide = $divide * $frombase + $number[$i];

                if ($divide >= $tobase) {
                    $number[$newlen++] = (int)($divide / $tobase);
                    $divide = $divide % $tobase;
                } elseif ($newlen > 0) {
                    $number[$newlen++] = 0;
                }
            }
            $length = $newlen;
            $result = $tostring{$divide} . $result;
        } while ($newlen != 0);

        return $result;
    }

    private function startsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) == $needle;
    }
}
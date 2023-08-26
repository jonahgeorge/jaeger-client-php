<?php

namespace Jaeger\Mapper;

use Jaeger\Codec\CodecUtility;
use Jaeger\Span;
use Jaeger\Thrift\Agent\Zipkin\AnnotationType;
use Jaeger\Thrift\Agent\Zipkin\BinaryAnnotation;
use Jaeger\Thrift\Log;
use Jaeger\Thrift\Span as JaegerThriftSpan;
use Jaeger\Thrift\Tag;
use Jaeger\Thrift\TagType;
use const OpenTracing\Tags\COMPONENT;
use const OpenTracing\Tags\PEER_HOST_IPV4;
use const OpenTracing\Tags\PEER_PORT;
use const OpenTracing\Tags\PEER_SERVICE;
use const OpenTracing\Tags\SPAN_KIND;

class SpanToJaegerMapper
{
    private $specialSpanTags = ["jaeger.hostname", "jaeger.version"];

    private $processTagsPrefix = "process.";

    /**
     * @return string[]
     */
    public function getSpecialSpanTags(): array
    {
        return $this->specialSpanTags;
    }

    /**
     * @return string
     */
    public function getProcessTagsPrefix(): string
    {
        return $this->processTagsPrefix;
    }

    public function mapSpanToJaeger(Span $span) : JaegerThriftSpan
    {
        $timestamp = $span->getStartTime();
        $duration = $span->getEndTime() - $span->getStartTime();

        /** @var Tag[] $tags */
        $tags = [];

        $tags[] = new Tag([
            "key" => COMPONENT,
            "vType" => TagType::STRING,
            "vStr" => $span->getComponent() ?? $span->getTracer()->getServiceName(),
        ]);

        // Handle special tags
        $peerService = $span->peer['service_name'] ?? null;
        if ($peerService !== null) {
            $tags[] = new Tag([
                "key" => PEER_SERVICE,
                "vType" => TagType::STRING,
                "vStr" => $peerService,
            ]);
        }

        $peerHostIpv4 = $span->peer['ipv4'] ?? null;
        if ($peerHostIpv4 !== null) {
            $tags[] = new Tag([
                "key" => PEER_HOST_IPV4,
                "vType" => TagType::STRING,
                "vStr" => $peerHostIpv4,
            ]);
        }

        $peerPort = $span->peer['port'] ?? null;
        if ($peerPort !== null) {
            $tags[] = new Tag([
                "key" => PEER_PORT,
                "vType" => TagType::LONG,
                "vLong" => $peerPort,
            ]);
        }

        $spanKind = $span->getKind();
        if ($spanKind !== null) {
            $tags[] = new Tag([
                "key" => SPAN_KIND,
                "vType" => TagType::STRING,
                "vStr" => $spanKind,
            ]);
        }

        /** @var BinaryAnnotation[] $binaryAnnotationTags */
        $binaryAnnotationTags = $span->getTags();
        foreach ($binaryAnnotationTags as $binaryAnnotationTag) {
            if (in_array($binaryAnnotationTag->key, $this->specialSpanTags, true)) {
                continue ;
            }

            if (strpos($binaryAnnotationTag->key, $this->processTagsPrefix) === 0) {
                continue;
            }

            $type = "";
            $vkey = "";
            switch ($binaryAnnotationTag->annotation_type) {
                case AnnotationType::BOOL:
                    $type = TagType::BOOL;
                    $vkey = "vBool";
                    break;
                case AnnotationType::BYTES:
                    $type = TagType::BINARY;
                    $vkey = "vBinary";
                    break;
                case AnnotationType::DOUBLE:
                    $type = TagType::DOUBLE;
                    $vkey = "vDouble";
                    break;
                case AnnotationType::I16:
                case AnnotationType::I32:
                case AnnotationType::I64:
                    $type = TagType::LONG;
                    $vkey = "vLong";
                    break;
                default:
                    $type = TagType::STRING;
                    $vkey = "vStr";
            }

            $tags[] = new Tag([
                "key" => $binaryAnnotationTag->key,
                "vType" => $type,
                $vkey => $binaryAnnotationTag->value,
            ]);
        }

        /** @var Log[] $logs */
        $logs = [];

        $spanLogs = $span->getLogs();

        foreach ($spanLogs as $spanLog) {
            /** @var Tag $fields */
            $fields = [];

            if (!empty($spanLog["fields"])) {
                $fields[] = new Tag([
                    "key" => "event",
                    "vType" => TagType::STRING,
                    "vStr" => json_encode($spanLog["fields"])
                ]);
            }

            $logs[] = new Log([
                "timestamp" => $spanLog["timestamp"],
                "fields" => $fields
            ]);
        }

        [$low, $high] = $this->extractTraceIdFromString($span->getContext()->getTraceId());

        return new JaegerThriftSpan([
            "traceIdLow" => $low,
            "traceIdHigh" => $high,
            "spanId" => (int)$span->getContext()->getSpanId(),
            "parentSpanId" => (int)$span->getContext()->getParentId(),
            "operationName" => $span->getOperationName(),
            "startTime" => $timestamp,
            "duration" => $duration,
            "flags" => (int)$span->isDebug(),
            "tags" => $tags,
            "logs" => $logs
        ]);
    }

    private function extractTraceIdFromString(?string $id): array
    {
        if ($id === null) {
            return [0, 0];
        }

        if (strlen($id) > 16) {
            $traceIdLow = CodecUtility::hexToInt64(substr($id, -16, 16));
            $traceIdHigh = CodecUtility::hexToInt64(substr($id, 0, 16));
        } else {
            $traceIdLow = (int) CodecUtility::hexToInt64($id);
            $traceIdHigh = 0;
        }

        return [$traceIdLow, $traceIdHigh];
    }
}

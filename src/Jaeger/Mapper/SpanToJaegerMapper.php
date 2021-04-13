<?php

namespace Jaeger\Mapper;

use Jaeger\Span;
use Jaeger\Thrift\Agent\Zipkin\BinaryAnnotation;
use Jaeger\Thrift\Log;
use Jaeger\Thrift\Span as JaegerThriftSpan;
use Jaeger\Thrift\Tag;
use Jaeger\Thrift\TagType;
use const OpenTracing\Tags\COMPONENT;

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

        /** @var BinaryAnnotation[] $binaryAnnotationTags */
        $binaryAnnotationTags = $span->getTags();
        foreach ($binaryAnnotationTags as $binaryAnnotationTag) {
            if (in_array($binaryAnnotationTag->key, $this->specialSpanTags, true)) {
                continue ;
            }

            if (strpos($binaryAnnotationTag->key, $this->processTagsPrefix) === 0) {
                continue;
            }

            $tags[] = new Tag([
                "key" => $binaryAnnotationTag->key,
                "vType" => TagType::STRING,
                "vStr" => $binaryAnnotationTag->value,

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

        return new JaegerThriftSpan([
            "traceIdLow" => (int)$span->getContext()->getTraceId(),
            "traceIdHigh" => 0,
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
}

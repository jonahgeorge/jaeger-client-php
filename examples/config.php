<?php

use Jaeger\Config;

return [
    'sampler' => [
        'type' => Jaeger\SAMPLER_TYPE_CONST,
        'param' => true,
    ],
    'logging' => true,
    "tags" => [
        "process.process-tag-key-1" => "process-value-1", // all tags with `process.` prefix goes to process section
        "process.process-tag-key-2" => "process-value-2", // all tags with `process.` prefix goes to process section
        "global-tag-key-1" => "global-tag-value-1", // this tag will be appended to all spans
        "global-tag-key-2" => "global-tag-value-2", // this tag will be appended to all spans
    ],
    // The way how to send data to Jaeger Agent
    // Available options:
    // - Config::JAEGER_OVER_BINARY
    // - Config::ZIPKIN_OVER_COMPACT - default
    'dispatch_mode' => Config::JAEGER_OVER_BINARY,
];

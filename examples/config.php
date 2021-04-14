<?php

use Jaeger\Config;

return [
    'sampler' => [
        'type' => Jaeger\SAMPLER_TYPE_CONST,
        'param' => true,
    ],
    'logging' => true,
    "tags" => [
        // process. prefix works only with JAEGER_OVER_HTTP, JAEGER_OVER_BINARY
        // otherwise it will be shown as simple global tag
        "process.process-tag-key-1" => "process-value-1", // all tags with `process.` prefix goes to process section
        "process.process-tag-key-2" => "process-value-2", // all tags with `process.` prefix goes to process section
        "global-tag-key-1" => "global-tag-value-1", // this tag will be appended to all spans
        "global-tag-key-2" => "global-tag-value-2", // this tag will be appended to all spans
    ],
    "local_agent" => [
        "reporting_host" => "localhost",
//        You can override port by setting local_agent.reporting_port value
//        "reporting_port" => 6832
    ],
//     Different ways to send data to Jaeger. Config::ZIPKIN_OVER_COMPACT - default):
    'dispatch_mode' => Config::JAEGER_OVER_BINARY_UDP,
];

<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Jaeger\Config;
use OpenTracing\GlobalTracer;

$config = new Config(
    [
        'sampler' => ['type' => 'const', 'param' => true],
        'logging' => true,
    ],
    'your-app-name'
);
$tracer = $config->initializeTracer();

$parentSpan = GlobalTracer::get()->startSpan('ParentSpan', []);
usleep(100);
$childSpan = GlobalTracer::get()->startSpan('ChildSpan', ['child_of' => $parentSpan]);
usleep(150);
$childSpan->finish();
$parentSpan->finish();

GlobalTracer::get()->flush();

<?php

require_once __DIR__.'/../vendor/autoload.php';

use Jaeger\Config;
use OpenTracing\GlobalTracer;

$config = new Config(
    require_once __DIR__.'/config.php',
    'your-app-name'
);

$config->initializeTracer();

$tracer = GlobalTracer::get();

$scope = $tracer->startActiveSpan('JaegerSpan', []);
$scope->getSpan()->setTag("tag1", "value1");
$scope->getSpan()->setTag("tag2", "value2");
$scope->getSpan()->setTag("tag3", "value2");
$scope->getSpan()->log([
    "key1" => "value1",
    "key2" => 2,
    "key3" => true
]);

$scope->getSpan()->addBaggageItem("baggage-item1", "baggage-value1");
$scope->getSpan()->addBaggageItem("baggage-item2", "baggage-value2");
$scope->getSpan()->addBaggageItem("baggage-item3", "baggage-value3");

    $nestedSpanScope = $tracer->startActiveSpan("Nested1");
    $nestedSpanScope->getSpan()->setTag("tag1", "value1");
    $nestedSpanScope->getSpan()->setTag("tag2", "value2");
    $nestedSpanScope->getSpan()->setTag("tag3", "value2");
    $nestedSpanScope->getSpan()->log([
        "key1" => "value1",
        "key2" => 2,
        "key3" => true
    ]);

    $nestedSpanScope->getSpan()->addBaggageItem("baggage-item1", "baggage-value1");
    $nestedSpanScope->getSpan()->addBaggageItem("baggage-item2", "baggage-value2");
    $nestedSpanScope->getSpan()->addBaggageItem("baggage-item3", "baggage-value3");

    sleep(1);

    $nestedSpanScope->close();

sleep(1);
$scope->close();
$tracer->flush();

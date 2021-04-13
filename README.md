[![Build Status](https://github.com/jonahgeorge/jaeger-client-php/workflows/Test/badge.svg) [![PHP version][packagist-img]][packagist]

# Jaeger Bindings for PHP OpenTracing API

This is a client-side library that can be used to instrument PHP apps for distributed trace collection,
and to send those traces to Jaeger. See the [OpenTracing PHP API](https://github.com/opentracing/opentracing-php)
for additional detail.

## Contributing and Developing

Please see [CONTRIBUTING.md](./CONTRIBUTING.md).

## Installation

Jaeger client can be installed via Composer:

```bash
composer require jonahgeorge/jaeger-client-php
```

## Getting Started

```php
<?php

require_once 'vendor/autoload.php';

use Jaeger\Config;
use OpenTracing\GlobalTracer;

$config = new Config(
    [
        'sampler' => [
            'type' => Jaeger\SAMPLER_TYPE_CONST,
            'param' => true,
        ],
        'logging' => true,
    ],
    'your-app-name'
);
$config->initializeTracer();

$tracer = GlobalTracer::get();

$scope = $tracer->startActiveSpan('TestSpan', []);
$scope->close();

$tracer->flush();
```

### Samplers

List of supported samplers, for more info about samplers, please read [Jaeger Sampling](https://www.jaegertracing.io/docs/1.9/sampling/) guide.

#### Const sampler
This sampler either samples everything, or nothing.

##### Configuration
```
'sampler' => [
    'type' => Jaeger\SAMPLER_TYPE_CONST,
    'param' => true, // boolean wheter to trace or not
],
```

#### Probabilistic sampler
This sampler samples request by given rate.

##### Configuration
```
'sampler' => [
    'type' => Jaeger\SAMPLER_TYPE_PROBABILISTIC,
    'param' => 0.5, // float [0.0, 1.0]
],
```

#### Rate limiting sampler
Samples maximum specified number of traces (requests) per second.

##### Requirements
* `psr/cache` PSR-6 cache component to store and retrieve sampler state between requests.
Cache component is passed to `Jaeger\Config` trough its constructor.
* `hrtime()` function, that can retrieve time in nanoseconds. You need either `php 7.3` or [PECL/hrtime](http://pecl.php.net/package/hrtime) extension.

##### Configuration
```
'sampler' => [
    'type' => Jaeger\SAMPLER_TYPE_RATE_LIMITING,
    'param' => 100 // integer maximum number of traces per second,
    'cache' => [
        'currentBalanceKey' => 'rate.currentBalance' // string
        'lastTickKey' => 'rate.lastTick' // string
    ]
],
```
## Dispatch mode

The library supports 2 ways of sending data to Jaeger Agent:  

1. `Zipkin.thrift` over Compact protocol (default)
2. `Jaeger.thrift` over Binary protocol

If you want to enable "`Jaeger.thrift` over Binary protocol" one, than
you need to set `dispatch_mode` config option or `JAEGER_DISPATCH_MODE` env
variable.

Allowed values for `dispatch_mode` are:
- `jaeger_over_binary`
- `zipkin_over_compact`

There are 2 constants available, so it is better to use them:
```php
class Config
{
    const JAEGER_OVER_BINARY = "jaeger_over_binary";
    const ZIPKIN_OVER_COMPACT = "zipkin_over_compact";
    ...
}
```

A possible config with custom `dispatch_mode` can look like this:
```php
<?php
// config.php
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
```

The full example you can see at `examples` directory.

## Testing

Tests are located in the `tests` directory. See [tests/README.md](./tests/README.md).

## Roadmap

- [Support Span baggage](https://github.com/jonahgeorge/jaeger-client-php/issues/5)
- [Support Tracer metrics](https://github.com/jonahgeorge/jaeger-client-php/issues/12)
- [Support Tracer error reporting](https://github.com/jonahgeorge/jaeger-client-php/issues/13)

## License

[MIT License](./LICENSE).

[ci-img]: https://travis-ci.org/jonahgeorge/jaeger-client-php.svg?branch=travis
[ci]: https://travis-ci.org/jonahgeorge/jaeger-client-php
[packagist-img]: https://badge.fury.io/ph/jonahgeorge%2Fjaeger-client-php.svg
[packagist]: https://badge.fury.io/ph/jonahgeorge%2Fjaeger-client-php

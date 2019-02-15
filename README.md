[![Build Status][ci-img]][ci] [![PHP version][packagist-img]][packagist]

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
            'params' => [
                'sample' => true
            ],
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

#### Const sampler `Jaeger\SAMPLER_TYPE_CONST`
This sampler either samples everything, or nothing.

##### Parameters
```
'sample' => true|false // boolean
```

#### Probabilistic sampler `Jaeger\SAMPLER_TYPE_PROBABILISTIC`
This sampler samples request by given rate.

##### Parameters
```
'rate' => 0.0 - 1.0 // float
```

#### Rate limiting sampler `Jaeger\SAMPLER_TYPE_RATE_LIMITING`
Samples maximum specified number of traces per second.

##### Requirements
* `psr/cache` PSR-6 cache component to store and retrieve sampler state between requests.
Cache component is passed to `Jaeger\Config` trough its constructor.
* `hrtime()` function, that can retrieve time in nanoseconds. You need either `php 7.3` or [PECL/hrtime](http://pecl.php.net/package/hrtime) extension.

##### Parameters
```
'maxTracesPerSecond' => 100 // integer
'currentBalanceKey' => 'rate.currentBalance' // string
'lastTickKey' => 'rate.lastTick' // string
```

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

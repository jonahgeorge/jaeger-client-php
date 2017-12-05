[![Build Status][ci-img]][ci] [![PHP version][packagist-img]][packagist]

# Jaegar Bindings for PHP OpenTracing API

This is a client-side library that can be used to instrument PHP apps for distributed trace collection, and to send those traces to Jaeger. See the [OpenTracing PHP API](https://github.com/opentracing/opentracing-php) for additional detail.

## Contributing and Developing

Please see [CONTRIBUTING.md](./CONTRIBUTING.md).

## Installation

```sh
{
  "require": {
    "jonahgeorge/jaeger-client-php": "0.2.0"
  }
}
```

## Getting Started

```php
<?php

require_once 'vendor/autoload.php';

use Jaeger\Config;
use OpenTracing\GlobalTracer;

$config = new Config(
    [
        'sampler' => ['type' => 'const', 'param' => true],
        'logging' => true,
    ],
    'your-app-name'
);
$config->initializeTracer();

$tracer = GlobalTracer::get();

$span = $tracer->startSpan('TestSpan', []);
$span->finish();

$tracer->flush();
```

## Roadmap

- [Support Span logging](https://github.com/jonahgeorge/jaeger-client-php/issues/1)
- [Support Span baggage](https://github.com/jonahgeorge/jaeger-client-php/issues/5)
- [Support Tracer metrics](https://github.com/jonahgeorge/jaeger-client-php/issues/12)
- [Support Tracer error reporting](https://github.com/jonahgeorge/jaeger-client-php/issues/13)

## License

[MIT License](./LICENSE).

[ci-img]: https://travis-ci.org/jonahgeorge/jaeger-client-php.svg?branch=travis  
[ci]: https://travis-ci.org/jonahgeorge/jaeger-client-php
[packagist-img]: https://badge.fury.io/ph/jonahgeorge%2Fjaeger-client-php.svg
[packagist]: https://badge.fury.io/ph/jonahgeorge%2Fjaeger-client-php

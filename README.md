![Build Status](https://github.com/jonahgeorge/jaeger-client-php/workflows/Test/badge.svg) [![PHP version][packagist-img]][packagist]

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

The library supports 3 ways of sending data to Jaeger Agent:  

1. `Zipkin.thrift` over Compact protocol (socket - UDP) - default 
2. `Jaeger.thrift` over Binary protocol (socket - UDP)
2. `Jaeger.thrift` over Binary protocol (HTTP)

If you want to enable "`Jaeger.thrift` over Binary protocol" one or other, than
you need to set `dispatch_mode` config option or `JAEGER_DISPATCH_MODE` env
variable.

Allowed values for `dispatch_mode` are:
- `jaeger_over_binary_udp`
- `jaeger_over_binary_http`
- `zipkin_over_compact_udp`

There are 3 constants available, so it is better to use them:
```php
class Config
{
    const ZIPKIN_OVER_COMPACT_UDP   = "zipkin_over_compact_udp";
    const JAEGER_OVER_BINARY_UDP    = "jaeger_over_binary_udp";
    const JAEGER_OVER_BINARY_HTTP   = "jaeger_over_binary_http";
    ...
}
```

A possible config with custom `dispatch_mode` can look like this:
```php
// config.php

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
        "reporting_port" => 6832
    ],
//     Different ways to send data to Jaeger. Config::ZIPKIN_OVER_COMPACT - default):
    'dispatch_mode' => Config::JAEGER_OVER_BINARY_UDP,
];
```
The full example you can see at `examples` directory.

By default, for each `dispatch_mode` there is default `reporting_port` config value. Table with
default values you can see below: 

`dispatch_mode`          | default `reporting_port` 
------------------------ | ---------------- 
ZIPKIN_OVER_COMPACT_UDP  | 5775
JAEGER_OVER_BINARY_UDP   | 6832
JAEGER_OVER_BINARY_HTTP  | 14268

## IPv6

In case you need IPv6 support you need to set `ip_version` Config variable.
By default, IPv4 is used. There is an alias `Config::IP_VERSION` which you can use
as an alternative to raw `ip_version`.

Example:

```php
use Jaeger\Config;

$config = new Config(
    [
        "ip_version" => Config::IPV6, // <-- or use Config::IP_VERSION constant
        'logging' => true,
        'dispatch_mode' => Config::JAEGER_OVER_BINARY_UDP,
    ],
    'serviceNameExample',
);
$config->initializeTracer();
```
or

```php
use Jaeger\Config;

$config = new Config(
    [
        Config::IP_VERSION => Config::IPV6, // <--
        'logging' => true,
        'dispatch_mode' => Config::JAEGER_OVER_BINARY_UDP,
    ],
    'serviceNameExample',
);
$config->initializeTracer();
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

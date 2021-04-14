<?php

namespace Jaeger;

// Max number of bits to use when generating random ID
const MAX_ID_BITS = 64;

// How often remotely controller sampler polls for sampling strategy
const DEFAULT_SAMPLING_INTERVAL = 60;

// How often remote reporter does a preemptive flush of its buffers
const DEFAULT_FLUSH_INTERVAL = 1;

// Name of the HTTP header used to encode trace ID
const TRACE_ID_HEADER = 'uber-trace-id';

// Prefix for HTTP headers used to record baggage items
const BAGGAGE_HEADER_PREFIX = 'uberctx-';

// The name of HTTP header or a TextMap carrier key which, if found in the
// carrier, forces the trace to be sampled as "debug" trace. The value of the
// header is recorded as the tag on the # root span, so that the trace can
// be found in the UI using this value as a correlation ID.
const DEBUG_ID_HEADER_KEY = 'jaeger-debug-id';

const JAEGER_CLIENT_VERSION = 'PHP-' . PHP_VERSION;

// Tracer-scoped tag that tells the version of Jaeger client library
const JAEGER_VERSION_TAG_KEY = 'jaeger.version';

// Tracer-scoped tag that contains the hostname
const JAEGER_HOSTNAME_TAG_KEY = 'jaeger.hostname';

const SAMPLER_TYPE_TAG_KEY = 'sampler.type';

const SAMPLER_PARAM_TAG_KEY = 'sampler.param';

const DEFAULT_SAMPLING_PROBABILITY = 0.001;

const DEFAULT_LOWER_BOUND = 1.0 / (10.0 * 60.0); # sample once every 10 minutes

const DEFAULT_MAX_OPERATIONS = 2000;

const STRATEGIES_STR = 'perOperationStrategies';

const OPERATION_STR = 'operation';

const DEFAULT_LOWER_BOUND_STR = 'defaultLowerBoundTracesPerSecond';

const PROBABILISTIC_SAMPLING_STR = 'probabilisticSampling';

const SAMPLING_RATE_STR = 'samplingRate';

const DEFAULT_SAMPLING_PROBABILITY_STR = 'defaultSamplingProbability';

const OPERATION_SAMPLING_STR = 'operationSampling';

const MAX_TRACES_PER_SECOND_STR = 'maxTracesPerSecond';

const RATE_LIMITING_SAMPLING_STR = 'rateLimitingSampling';

const STRATEGY_TYPE_STR = 'strategyType';

// the type of sampler that always makes the same decision.
const SAMPLER_TYPE_CONST = 'const';

// the type of sampler that polls Jaeger agent for sampling strategy.
const SAMPLER_TYPE_REMOTE = 'remote';

// the type of sampler that samples traces with a certain fixed probability.
const SAMPLER_TYPE_PROBABILISTIC = 'probabilistic';

// the type of sampler that samples only up to a fixed number
// of traces per second.
// noinspection SpellCheckingInspection
const SAMPLER_TYPE_RATE_LIMITING = 'ratelimiting';

// the type of sampler that samples only up to a fixed number
// of traces per second.
// noinspection SpellCheckingInspection
const SAMPLER_TYPE_LOWER_BOUND = 'lowerbound';

const DEFAULT_REPORTING_HOST = 'localhost';

/** @deprecated  */
const DEFAULT_REPORTING_PORT = 5775;

const DEFAULT_ZIPKIN_UDP_COMPACT_REPORTING_PORT = 5775;
const DEFAULT_JAEGER_UDP_BINARY_REPORTING_PORT = 6832;
const DEFAULT_JAEGER_HTTP_BINARY_REPORTING_PORT = 14268;

const DEFAULT_SAMPLING_PORT = 5778;

const LOCAL_AGENT_DEFAULT_ENABLED = true;

const ZIPKIN_SPAN_FORMAT = 'zipkin-span-format';

const SAMPLED_FLAG = 0x01;

const DEBUG_FLAG = 0x02;

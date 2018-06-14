<?php

namespace Jaeger\Sampler;

interface SamplerInterface
{
    public function isSampled(string $traceId, string $operation);
    public function close();
}

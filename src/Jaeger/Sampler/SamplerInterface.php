<?php

namespace Jaeger\Sampler;

interface SamplerInterface
{
    public function isSampled($traceId, $operation);
    public function close();
    public function __toString(): string;
}
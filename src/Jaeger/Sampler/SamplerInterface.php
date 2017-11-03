<?php

namespace Jaeger\Sampler;

interface SamplerInterface
{
    public function isSampled($traceId, $operation);
    public function close();

    /**
     * @return string
     */
    public function __toString();
}
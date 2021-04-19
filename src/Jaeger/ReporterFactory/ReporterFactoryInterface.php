<?php

namespace Jaeger\ReporterFactory;

use Jaeger\Reporter\ReporterInterface;

interface ReporterFactoryInterface
{
    public function createReporter() : ReporterInterface;
}

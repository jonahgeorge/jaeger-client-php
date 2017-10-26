<?php

namespace Jaeger\Reporter;

use Jaeger\Span;

interface ReporterInterface
{
    public function reportSpan(Span $span);
    public function close();
}

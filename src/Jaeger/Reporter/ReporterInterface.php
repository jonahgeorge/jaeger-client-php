<?php

namespace Jaeger\Reporter;

use Jaeger\Span;

/**
 * Uses to report finished span to something that collects those spans.
 *
 * @package Jaeger\Reporter
 */
interface ReporterInterface
{
    /**
     * Report finished span.
     *
     * @param Span $span
     * @return void
     */
    public function reportSpan(Span $span);

    /**
     * Release any resources used by the reporter and flushes/sends the data.
     *
     * @return void
     */
    public function close();
}

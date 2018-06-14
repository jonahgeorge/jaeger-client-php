<?php

namespace Jaeger\Reporter;

use Jaeger\Span;

/**
 * NullReporter ignores all spans.
 */
class NullReporter implements ReporterInterface
{
    /**
     * @param Span $span
     */
    public function reportSpan(Span $span)
    {
    }

    public function close()
    {
    }
}

<?php

namespace Jaeger\Reporter;

use Jaeger\Span;

/**
 * NullReporter ignores all spans.
 */
class NullReporter implements ReporterInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Span $span
     * @return void
     */
    public function reportSpan(Span $span)
    {
        // nothing to do
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function close()
    {
        // nothing to do
    }
}

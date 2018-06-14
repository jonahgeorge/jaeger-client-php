<?php

namespace Jaeger\Reporter;

use Jaeger\Span;

/**
 * InMemoryReporter stores spans in memory and returns them via getSpans().
 */
class InMemoryReporter implements ReporterInterface
{
    /**
     * @var array
     */
    private $spans = [];

    /**
     * @param Span $span
     */
    public function reportSpan(Span $span)
    {
        $this->spans[] = $span;
    }

    /**
     * @return array
     */
    public function getSpans(): array
    {
        return $this->spans;
    }

    public function close()
    {
    }
}

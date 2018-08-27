<?php

namespace Jaeger\Reporter;

use Jaeger\Span;

/**
 * InMemoryReporter stores spans in memory and returns them via getSpans().
 */
class InMemoryReporter implements ReporterInterface
{
    /**
     * @var Span[]
     */
    private $spans = [];

    /**
     * {@inheritdoc}
     *
     * @param Span $span
     * @return void
     */
    public function reportSpan(Span $span)
    {
        $this->spans[] = $span;
    }

    /**
     * @return Span[]
     */
    public function getSpans(): array
    {
        return $this->spans;
    }

    /**
     * {@inheritdoc}
     *
     * Only implemented to satisfy the sampler interface.
     *
     * @return void
     */
    public function close()
    {
        // nothing to do
    }
}

<?php

namespace Jaeger\Reporter;

use Jaeger\Sender\SenderInterface;
use Jaeger\Span;

class JaegerReporter implements ReporterInterface
{
    /**
     * @var SenderInterface
     */
    private $sender;

    /**
     * RemoteReporter constructor.
     *
     * @param SenderInterface $sender
     */
    public function __construct(SenderInterface $sender)
    {
        $this->sender = $sender;
    }

    public function reportSpan(Span $span)
    {
        $this->sender->append($span);
    }

    public function close()
    {
        $this->sender->flush();
        $this->sender->close();
    }
}

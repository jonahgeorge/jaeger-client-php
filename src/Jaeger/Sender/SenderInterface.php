<?php

namespace Jaeger\Sender;

use Jaeger\Span as JaegerSpan;

interface SenderInterface
{
    public function flush(): int;
    public function append(JaegerSpan $span);
    public function close();
}

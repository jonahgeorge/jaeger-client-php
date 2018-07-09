<?php

namespace Jaeger\Sender;

abstract class ThriftSender implements SenderInterface
{
    abstract public function send(array $spans);

    protected function serialize()
    {
    }
}

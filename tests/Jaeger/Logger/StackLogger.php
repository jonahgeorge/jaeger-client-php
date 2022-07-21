<?php

namespace Jaeger\Tests\Logger;

use Psr\Log\LoggerTrait;

class StackLogger implements \Psr\Log\LoggerInterface
{
    /** @var array */
    protected $messagesStack = [];

    use LoggerTrait;

    public function log($level, $message, array $context = array()): void
    {
        $this->messagesStack[] = $message;
    }

    public function getLastMessage() {
        return array_pop($this->messagesStack);
    }

    public function getMessagesCount() {
        return count($this->messagesStack);
    }

    public function clear() {
        $this->messagesStack = [];
    }
}

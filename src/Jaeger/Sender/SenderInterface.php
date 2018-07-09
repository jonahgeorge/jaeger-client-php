<?php

namespace Jaeger\Sender;

interface SenderInterface
{
  function send(array $spans);
}

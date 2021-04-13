<?php

namespace Jaeger\ReporterFactory;

use Psr\Log\LoggerInterface;
use Thrift\Transport\TTransport;

abstract class AbstractReporterFactory implements ReporterFactoryInterface
{
    /** @var TTransport */
    protected $transport;

    /** @var LoggerInterface  */
    protected $logger;

    /**
     * JaegerReporterFactory constructor.
     * @param $transport
     * @param $logger
     */
    public function __construct(TTransport $transport, LoggerInterface $logger)
    {
        $this->transport = $transport;
        $this->logger = $logger;
    }
}

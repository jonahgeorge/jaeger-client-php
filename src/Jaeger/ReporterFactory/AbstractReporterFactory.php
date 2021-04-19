<?php

namespace Jaeger\ReporterFactory;

use Jaeger\Config;
use Psr\Log\LoggerInterface;
use Thrift\Transport\TTransport;

abstract class AbstractReporterFactory implements ReporterFactoryInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * AbstractReporterFactory constructor.
     * @param Config $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }
}

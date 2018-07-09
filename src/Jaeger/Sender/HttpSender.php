<?php

namespace Jaeger\Sender;

use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Use of this class can cause nonobvious performance regressions based on a
 * typical PHP installation. As such, unless you are using this in conjunction
 * with `fastcgi_finish_request`, it likely should only be used for debug purposes.
 */
class HttpSender extends ThriftSender
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * HttpSender constructor.
     * @param HttpClient $httpClient
     * @param LoggerInterface $logger
     *
     *     $client = new Client([
     *         'base_uri'        => 'http://www.foo.com/1.0/',
     *         'timeout'         => 0,
     *         'allow_redirects' => false,
     *         'proxy'           => '192.168.16.1:10'
     *     ]);
     */
    public function __construct(HttpClient $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $client ?? new HttpClient();
        $this->logger = $logger ?? new NullLogger();
    }

    public function send(array $spans)
    {
        // Serialize spans
        $batch = $this->serialize($spans);

        // Create request
        $this->httpClient->post('/', [
            "headers" => "",
            "body" => "",
            "version" => "",
        ]);
    }
}

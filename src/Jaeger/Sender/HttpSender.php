<?php

namespace Jaeger\Sender;

use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Use of this class can cause nonobvious performance regressions based on a
 * typical PHP installation. As such, unless you are using this in conjuction
 * with `fastcgi_finish_request`, it likely should only be used for debug purposes.
 */
class HttpSender extends ThriftSender
{
  /**
   * @var HttpClient
   */
  private $httpClient;

  /**
   * @var string
   */
  private $collectorUrl;

  /**
   * @var LoggerInterface
   */
  private $logger;

  public function __construct(HttpClient $client, LoggerInterface $logger)
  {
    $this->client = $client ?? new HttpClient();
    $this->logger = $logger ?? new NullLogger();
  }

  public function send(array $spans)
  {
    // Serialize spans

    // Create request

    // POST request
  }
}

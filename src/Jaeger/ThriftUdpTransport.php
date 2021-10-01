<?php

namespace Jaeger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Thrift\Transport\TTransport;

class ThriftUdpTransport extends TTransport
{
    private $socket;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    /**
     * ThriftUdpTransport constructor.
     * @param string $host
     * @param int $port
     * @param LoggerInterface $logger
     */
    public function __construct(string $host, int $port, LoggerInterface $logger = null, Config $config = null)
    {
        $this->setLogger($logger);

        $this->config = $config;

        $ipProtocol = $this->ipProtocolVersion();
        $this->socket = $this->createSocket($ipProtocol);

        $this->host = $host;
        $this->port = $port;
    }

    protected function setLogger($logger)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    protected function createSocket(string $ipProtocol)
    {
        $socketDomain = AF_INET;
        if ($ipProtocol === Config::IPV6) {
            $socketDomain = AF_INET6;
        }

        $socket = @socket_create($socketDomain, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            $this->handleSocketError("socket_create failed");
        }
        return $socket;
    }

    protected function ipProtocolVersion()
    {
        if (!empty($this->config)) {
            return $this->config->ipProtocolVersion();
        }
        return "";
    }

    /**
     * Whether this transport is open.
     *
     * @return boolean true if open
     */
    public function isOpen()
    {
        return $this->socket !== null;
    }

    /**
     * Open the transport for reading/writing
     */
    public function open()
    {
        $ok = @socket_connect($this->socket, $this->host, $this->port);
        if ($ok === false) {
            $this->handleSocketError('socket_connect failed');
        }
    }

    /**
     * Close the transport.
     */
    public function close()
    {
        if (is_null($this->socket)) {
            $this->logger->warning("can't close empty socket");
            return ;
        }

        @socket_close($this->socket);
        $this->socket = null;
    }

    /**
     * Read some data into the array.
     *
     * @todo
     *
     * @param int $len How much to read
     * @return string The data that has been read
     */
    public function read($len)
    {
    }

    /**
     * Writes the given data out.
     *
     * @param string $buf The data to write
     */
    public function write($buf)
    {
        if (!$this->isOpen()) {
            $this->logger->warning('transport is closed');
            return ;
        }

        $ok = @socket_write($this->socket, $buf);
        if ($ok === false) {
            $this->handleSocketError("socket_write failed");
        }
    }

    public function handleSocketError($msg)
    {
        $errorCode = socket_last_error($this->socket);
        $errorMsg = socket_strerror($errorCode);

        $this->logger->warning(sprintf('%s: [code - %d] %s', $msg, $errorCode, $errorMsg));
    }
}

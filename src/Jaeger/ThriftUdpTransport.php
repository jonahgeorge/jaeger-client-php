<?php

namespace Jaeger;

use Thrift\Exception\TException;
use Thrift\Exception\TTransportException;
use Thrift\Transport\TSocket;

class ThriftUdpTransport extends TSocket
{
    public function open()
    {
        if ($this->isOpen()) {
            throw new TTransportException('Socket already connected', TTransportException::ALREADY_OPEN);
        }

        if (empty($this->host_)) {
            throw new TTransportException('Cannot open null host', TTransportException::NOT_OPEN);
        }

        if ($this->port_ <= 0) {
            throw new TTransportException('Cannot open without port', TTransportException::NOT_OPEN);
        }

        if ($this->persist_) {
            $this->handle_ = @pfsockopen(
                $this->host_,
                $this->port_,
                $errno,
                $errstr,
                $this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000)
            );
        } else {
            $this->handle_ = @fsockopen(
                $this->host_,
                $this->port_,
                $errno,
                $errstr,
                $this->sendTimeoutSec_ + ($this->sendTimeoutUsec_ / 1000000)
            );
        }

        // Connect failed?
        if ($this->handle_ === false) {
            $error = 'TSocket: Could not connect to ' .
                $this->host_ . ':' . $this->port_ . ' (' . $errstr . ' [' . $errno . '])';
            if ($this->debug_) {
                call_user_func($this->debugHandler_, $error);
            }
            throw new TException($error);
        }
    }

    public function __construct($host = 'localhost', $port = 9090, $persist = false, $debugHandler = null)
    {
        $host = "udp://".$host;

        parent::__construct($host, $port, $persist, $debugHandler);
    }
}

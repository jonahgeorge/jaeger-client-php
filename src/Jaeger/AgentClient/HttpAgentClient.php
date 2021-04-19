<?php

namespace Jaeger\AgentClient;

class HttpAgentClient implements \Jaeger\Thrift\Agent\AgentIf
{
    protected $input_ = null;
    protected $output_ = null;

    protected $seqid_ = 0;

    public function __construct($input, $output = null)
    {
        $this->input_ = $input;
        $this->output_ = $output ? $output : $input;
    }

    public function emitZipkinBatch(array $spans)
    {
    }

    public function emitBatch(\Jaeger\Thrift\Batch $batch)
    {
        $batch->write($this->output_);
        $this->output_->getTransport()->flush();
    }
}

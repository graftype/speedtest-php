<?php

namespace NextpostTech\Speedtest;

/**
 * Speedtest.net for PHP
 * 
 * Library and command line interface to run server-side speedtests via Speedtest.net from cli or web interface.
 * 
 * @link https://github.com/nextpost-tech/speedtest-php
 * 
 * @author Nextpost.tech (https://nextpost.tech)
 *
 * @source Original PHP version (https://github.com/aln-1/speedtest-php)
 * @source Python version (https://github.com/sivel/speedtest-cli)
 */

class Progress
{
    /**
     * 
     * @var int
     */
    protected $start;

    /**
     * 
     * @var array
     */
    protected $status = [];
    
    /**
     * 
     * @var Result
     */
    protected $result;
    
    public function __construct() {
        $this->result = new Result();
    }
    
    public function start() {
        $this->status = [];
        $this->start = microtime(true);
    }
    
    /**
     * 
     * @return \NextpostTech\Speedtest\Result
     */
    public function getResult() {
        return $this->result;
    }

    /**
     * 
     * @param int $id
     * @param string $mode
     * @param int $size
     */
    public function progress($id, $mode, $size) {
        $this->status[$id] = $size;
        $duration = max(microtime(true) - $this->start, 0.001);
        $bytes = array_sum($this->status);
        $bits = $bytes * 8;
        $speed = $bits / $duration;
        
        if($mode == 'upload') {
            $this->result->setUpload($speed);
            $this->result->setBytesSent($bytes);
        } else {
            $this->result->setDownload($speed);
            $this->result->setBytesReceived($bytes);
        }
    }
}

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

class Result
{
    /**
     * 
     * @var float
     */
    protected $latency;
    
    /**
     *
     * @var float
     */
    protected $download;
    
    /**
     *
     * @var float
     */
    protected $upload;
    
    /**
     *
     * @var int
     */
    protected $bytesReceived = 0;
    
    /**
     *
     * @var int
     */
    protected $bytesSent = 0;
    
    /**
     * @return int
     */
    public function getBytesReceived()
    {
        return $this->bytesReceived;
    }

    /**
     * @param int $bytesReceived
     */
    public function setBytesReceived($bytesReceived)
    {
        $this->bytesReceived = $bytesReceived;
    }

    /**
     * @return int
     */
    public function getBytesSent()
    {
        return $this->bytesSent;
    }

    /**
     * @param int $bytesSent
     */
    public function setBytesSent($bytesSent)
    {
        $this->bytesSent = $bytesSent;
    }

    /**
     * @return float
     */
    public function getLatency()
    {
        return $this->latency;
    }

    /**
     * @param float $latency
     */
    public function setLatency($latency)
    {
        $this->latency = $latency;
    }

    /**
     * @return float
     */
    public function getDownload()
    {
        return $this->download;
    }

    /**
     * @param float $download
     */
    public function setDownload($download)
    {
        $this->download = $download;
    }

    /**
     * @return float
     */
    public function getUpload()
    {
        return $this->upload;
    }

    /**
     * @param float $upload
     */
    public function setUpload($upload)
    {
        $this->upload = $upload;
    }

}

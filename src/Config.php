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

class Config
{
    /**
     * 
     * @var string
     */
    protected $sourceAddress = null;
    
    /**
     * 
     * @var int
     */
    protected $timeout = 10;
    
    /**
     *
     * @var bool
     */
    protected $single = false;
    
    /**
     * 
     * @var array
     */
    protected $client = [];
    
    /**
     * 
     * @var array
     */
    protected $ignoreServers = [];
    
    /**
     *
     * @var array
     */
    protected $useServers = [];
    
    /**
     * 
     * @var array
     */
    protected $sizes = [];
    
    /**
     * 
     * @var array
     */
    protected $counts = [];
    
    /**
     * 
     * @var array
     */
    protected $threads = [];
    
    /**
     * 
     * @var array
     */
    protected $length = [];
    
    /**
     * 
     * @var integer
     */
    protected $uploadMax = 0;

    /**
     * 
     * @var string
     */
    protected $proxy = "";

    /**
     * 
     * @var string
     */
    protected $proxyType = "http";
    
    /**
     * 
     * @var Callable
     */
    protected $callback = null;
    
    /**
     * @return Callable
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * @param Callable $callback
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return boolean
     */
    public function isSingle()
    {
        return $this->single;
    }

    /**
     * @param boolean $single
     */
    public function setSingle($single)
    {
        $this->single = $single;
    }

    /**
     * @return string
     */
    public function getSourceAddress()
    {
        return $this->sourceAddress;
    }

    /**
     * @param string $sourceAddress
     */
    public function setSourceAddress($sourceAddress)
    {
        $this->sourceAddress = $sourceAddress;
    }

    /**
     * @return number
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param number $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return array
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param array $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @return array
     */
    public function getIgnoreServers()
    {
        return $this->ignoreServers;
    }

    /**
     * @param array $ignoreServers
     */
    public function setIgnoreServers($ignoreServers)
    {
        $this->ignoreServers = $ignoreServers;
    }

    /**
     * @return array
     */
    public function getUseServers()
    {
        return $this->useServers;
    }
    
    /**
     * @param array $useServers
     */
    public function setUseServers($useServers)
    {
        $this->useServers = $useServers;
    }
    
    /**
     * @return array
     */
    public function getSizes()
    {
        return $this->sizes;
    }

    /**
     * @param array $sizes
     */
    public function setSizes($sizes)
    {
        $this->sizes = $sizes;
    }

    /**
     * @return array
     */
    public function getCounts()
    {
        return $this->counts;
    }

    /**
     * @param array $counts
     */
    public function setCounts($counts)
    {
        $this->counts = $counts;
    }

    /**
     * @return array
     */
    public function getThreads()
    {
        return $this->threads;
    }

    /**
     * @param array $threads
     */
    public function setThreads($threads)
    {
        $this->threads = $threads;
    }

    /**
     * @return array
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @param array $length
     */
    public function setLength($length)
    {
        $this->length = $length;
    }

    /**
     * @return int
     */
    public function getUploadMax()
    {
        return $this->uploadMax;
    }

    /**
     * @param int $uploadMax
     */
    public function setUploadMax($uploadMax)
    {
        $this->uploadMax = $uploadMax;
    }

    /**
     * @return string
     */
    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * @return string $proxy
     */
    public function setProxy($proxy)
    {
        $proxy = str_replace("https://", "", $proxy);
        $proxy = str_replace("http://", "", $proxy);
        if (strpos($proxy, "socks5://") !==  false) {
            $proxy = str_replace("socks5://", "", $proxy);
            $this->proxyType = "socks5";
        }
        $this->proxy = $proxy;
    }

    /**
     * @param int $proxyType
     */
    public function setProxyType($proxyType)
    {
        $this->proxyType = $proxyType;
    }

    /**
     * @return string
     */
    public function getProxyType()
    {
        return $this->proxyType;
    }
}

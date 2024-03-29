<?php

namespace Graftype\Speedtest;

/**
 * Speedtest.net for PHP
 * 
 * Library and command line interface to run server-side speedtests via Speedtest.net from cli or web interface.
 * 
 * @link https://github.com/graftype/speedtest-php
 * 
 * @author Graftype (https://graftype.com)
 *
 * @source Original PHP version (https://github.com/aln-1/speedtest-php)
 * @source Python version (https://github.com/sivel/speedtest-cli)
 */

class Cli
{
    /**
     * 
     * @var Speedtest
     */
    protected $speedtest;
    
    /**
     * 
     * @var bool
     */
    protected $upload = true;
    
    /**
     *
     * @var bool
     */
    protected $download = true;
    
    /**
     *
     * @var bool
     */
    protected $bytes = false;
    
    /**
     *
     * @var bool
     */
    protected $share = false;
    
        /**
     *
     * @var bool
     */
    protected $json = false;
    
    /**
     *
     * @var bool
     */
    protected $simple = false;
    
    public function __construct() {
        $this->getOptions();
        $this->speedtest();
    }
    
    protected function speedtest() {
        if(!$this->simple) {
            $clientInfo = $this->speedtest->clientInfo();
            echo sprintf("Testing from %s (%s)...\n", $clientInfo['isp'], $clientInfo['ip']);
        }

        if(!$this->simple) {
            echo "Retrieving speedtest.net server list...\n";
        }
        $this->speedtest->getServers();
        if(!$this->simple) {
            echo "Selecting best server based on ping...\n";
        }
        $best = $this->speedtest->getBestServer();

        if(!$this->simple) {
            echo sprintf('(%d) Hosted by %s (%s) [%s Km]: %s ms' . "\n", $best['id'], $best['sponsor'], $best['name'], $best['d'], $best['latency']);
        }
        
        if($this->download) {
            $this->speedtest->download();
        }
    
        if($this->upload) {
            $this->speedtest->upload();
        }
        
        $this->progress($this->speedtest->results());
        
        echo "\n";
        
        if($this->share && !$this->simple) {
            $url = $this->speedtest->share();
            echo "Share results: $url\n";
        }
    }
    
    /**
     * 
     * @param Result $result
     */
    public function progress(Result $result) {
        $latency = (float)$result->getLatency();
        $download = (float)$result->getDownload() / 1000 / 1000;
        $upload = (float)$result->getUpload() / 1000 / 1000;
        if(!$this->json) {
            $latency = number_format($latency, 2) . ' ms';
            $download = number_format($download / ($this->bytes ? 8 : 1), 2) . ($this->bytes ? " MBps" : " Mbps");
            $upload = number_format($upload / ($this->bytes ? 8 : 1), 2) . ($this->bytes ? " MBps" : " Mbps");
        } else {
            $download = round($download, 2);
            $upload = round($upload, 2);
        }
        
        if($this->json) {
            echo json_encode(['latency' => $latency, 'download' => $download, 'upload' => $upload]);
        } else {
            echo "Latency: $latency Download: $download Upload: $upload";
        }
        echo "                   \r";
    }
    
    /**
     * 
     * @param string $list
     * @return array
     */
    protected function filterIds($list) {
        return array_values(array_unique(array_map('intval', explode(',', $list))));
    }
    
    protected function getOptions() {
        global $argc;
        
        $shortopts = "h::";
        $longopts = [
            "help",
            "version",
            "list",
            "simple",
            "bytes",
            "no-download",
            "no-upload",
            "single",
            "share",
            "json",
            "server::",
            "exclude::",
            "source::",
            "timeout::",
            "proxy::"
        ];
        
        $options = getopt($shortopts, $longopts);
        
        if(count($options) != ($argc - 1)) {
            echo "Invalid option\n";
            die();
        }
        
        if(array_key_exists('h', $options) || array_key_exists('help', $options)) {
            $this->help();
            exit();
        }
        
        if(array_key_exists('version', $options)) {
            $this->version();
            exit();
        }
        
        $config = new Config();
        if(array_key_exists('timeout', $options)) {
            $config->setTimeout((int)$options['timeout']);
        }

        if(array_key_exists('proxy', $options)) {
            $config->setProxy($options['proxy']);
        }
        
        if(array_key_exists('source', $options)) {
            $config->setSourceAddress($options['source']);
        }
        
        if(array_key_exists('no-download', $options)) {
            $this->download = false;
        }
        
        if(array_key_exists('no-upload', $options)) {
            $this->upload = false;
        }
        
        if(array_key_exists('simple', $options)) {
            $this->simple = true;
        } else {
            $config->setCallback([$this, 'progress']);
        }
        
        if(array_key_exists('bytes', $options)) {
            $this->bytes = true;
        }
        
        if(array_key_exists('share', $options)) {
            $this->share = true;
        }
        
        if(array_key_exists('json', $options)) {
            $this->json = true;
            $this->simple = true;
        }
        
        if(array_key_exists('single', $options)) {
            $config->setSingle(true);
        }
        
        if(array_key_exists('server', $options)) {
            $config->setUseServers($this->filterIds($options['server']));
        }
        
        if(array_key_exists('exclude', $options)) {
            $config->setIgnoreServers($this->filterIds($options['exclude']));
        }
        
        $this->speedtest = new Speedtest($config);
        
        if(array_key_exists('list', $options)) {
            $this->getServers();
            exit();
        }
    }
    
    protected function help() {
        echo '
usage: speedtest [-h] [--no-download] [--no-upload] [--single] [--bytes]
                 [--share] [--simple] [--json] [--list] [--server=SERVER]
                 [--exclude=EXCLUDE] [--source=SOURCE] [--timeout=TIMEOUT]
                 [--proxy=PROXY] [--version]

Command line interface for testing internet bandwidth using speedtest.net.
--------------------------------------------------------------------------
https://github.com/NextpostTech-1/speedtest-php

optional arguments:
  -h, --help            show this help message and exit
  --no-download         Do not perform download test
  --no-upload           Do not perform upload test
  --single              Only use a single connection instead of multiple. This
                        simulates a typical file transfer
  --bytes               Display values in bytes instead of bits. Does not
                        affect the image generated by --share, nor output from
                        --json
  --share               Generate and provide a URL to the speedtest.net share
                        results image
  --simple              Suppress verbose output and progress, only shows results
  --json                Output in JSON format. Speeds listed in bits and not
                        affected by --bytes. Can be combined with --simple
                        to supress progress
  --list                Display a list of speedtest.net servers sorted by
                        distance
  --server=SERVER       Specify a server ID to test against. Can be comma
                        separated values
  --exclude=EXCLUDE     Exclude a server from selection. Can be comma
                        separated values
  --source=SOURCE       Source IP address to bind to or interface name
  --timeout=TIMEOUT     HTTP timeout in seconds, default 10
  --proxy=PROXY         Use a proxied connection for test
  --version             Show the version number
';
    }
    
    protected function getServers() {
        foreach ($this->speedtest->getServers() as $server) {
            echo $server['id'] . "\t" .
                $server['sponsor'] .
                ' (' . $server['name'] . ', ' . $server['country'] . ') [' .
                number_format($server['d'], 2) . " Km]\n";
        }
    }
    
    protected function version() {
        // try composer
        $path = realpath(dirname(__FILE__) . '/../../../') . "/composer/installed.json";
        if(is_readable($path)) {
            $packages = json_decode(file_get_contents($path));
            foreach ($packages as $package) {
                if($package->name == "NextpostTech/speedtest-php") {
                    echo $package->version . "\n";
                    return;
                }
            }
        }

        // try git
        $ver = shell_exec('git describe --always');
        if($ver) {
            $ver = trim($ver);
            if($ver && substr($ver, 0, 1) == 'v') {
                echo "$ver\n";
                return;
            }
        }
        
        echo "unknown\n";
    }
}

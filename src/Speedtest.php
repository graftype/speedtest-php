<?php

namespace NextpostTech\Speedtest;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\TransferStats as GuzzleTransferStats;

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

class Speedtest
{
    /**
     * 
     * @var Config
     */
    protected $config;
    
    /**
     * 
     * @var array
     */
    protected $servers = [];
    
    /**
     *
     * @var array
     */
    protected $best = null;
    
    /**
     * 
     * @var Progress
     */
    protected $progress;
    
    /**
     * 
     * @var string
     */
    protected $mode;
    
    public function __construct(Config $config = null) {
        $this->progress = new Progress();
        $this->config = $config ?: new Config();
        $this->getRemoteConfig();
    }
    
    /**
     * 
     * @param \SimpleXMLElement $element
     * @return string[]
     */
    protected function xmlAttributesToArray(\SimpleXMLElement $element) {
        $attributes = [];
        foreach ($element->attributes() as $key => $value) {
            $attributes[(string)$key] = (string)$value;
        };
        return $attributes;
    }
    
    /**
     * 
     * @param float $lata
     * @param float $lona
     * @param float $latb
     * @param float $lonb
     * @return float
     * 
     */
    protected function haversine($lata, $lona, $latb, $lonb) {
        if (($lata == $latb) && ($lona == $lonb)) {
            return 0;
        }

        $r = 6371; // earth radius, Km
        $lata = deg2rad($lata);
        $lona = deg2rad($lona);
        $latb = deg2rad($latb);
        $lonb = deg2rad($lonb);
        
        return 2 * $r * asin(sqrt(pow(sin(($lata - $latb) / 2), 2) + cos($lata) * cos($latb) * pow(sin(($lona - $lonb) / 2), 2)));
    }
    
    /**
     * Get remote client config
     * 
     * @throws SpeedtestException
     */
    protected function getRemoteConfig() {
        try {
            $client = new GuzzleClient();

            $options = [
                "verify"  => true,
                "timeout" => $this->config->getTimeout(),
                "curl"    => [
                    CURLOPT_USERAGENT       => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36",
                    CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_2_0, // Make http client work with HTTP 2/0
                    CURLOPT_SSLVERSION      => 1,
                    CURLOPT_SSL_VERIFYPEER  => false
                ], 
                "headers" => [
                    "accept-language" => "en-US"
                ],
                // "debug" => true
            ];

            if ($this->config->getProxyType() == "socks5") {
                $options["proxy"] = "socks5://" . $this->config->getProxy();
            } else {
                $options["proxy"] = "http://" . $this->config->getProxy();
            }

            $res = $client->request('GET', 'https://www.speedtest.net/speedtest-config.php', $options);

            $data = $res->getBody()->getContents();
            $xml = simplexml_load_string($data);

            if (empty($xml)) {
                throw new SpeedtestException("Couldn't get remote client config. Reason: empty xml data.");
            }

            $server_config = $xml->{'server-config'};
            $download = $xml->download;
            $upload = $xml->upload;
            $client = $this->xmlAttributesToArray($xml->client);
            $ignore_servers = explode(',', $server_config['ignoreids']);
            $ratio = (int)$upload['ratio'];
            $upload_max = (int)$upload['maxchunkcount'];
            $up_sizes = [32768, 65536, 131072, 262144, 524288, 1048576, 7340032];
            $sizes = [
                'upload' => array_slice($up_sizes, $ratio - 1),
                'download' => [245388, 505544, 1118012, 1986284, 4468241, 7907740, 12407926, 17816816, 24262167, 31625365]
            ];
            
            $size_count = count($sizes['upload']);
            $upload_count = (int)ceil($upload_max / $size_count);
            
            $counts = ['upload' => $upload_count, 'download' => (int)$download['threadsperurl']];
            $threads = ['upload' => (int)$upload['threads'], 'download' => ((int) $server_config['threadcount']) * 2];
            $length = ['upload' => (int)$upload['testlength'], 'download' => (int)$download['testlength']];
            
            $this->config->setClient($client);
            $this->config->setIgnoreServers(
                array_values(array_unique(array_merge($ignore_servers, $this->config->getIgnoreServers())))
                );
            $this->config->setSizes($sizes);
            $this->config->setCounts($counts);
            $this->config->setThreads($threads);
            $this->config->setLength($length);
            $this->config->setUploadMax($upload_count * $size_count);
        } catch (SpeedtestException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new SpeedtestException("Couldn't get remote client config. Reason: " . $e->getMessage());
        }
    }

    /**
     * Get servers
     * 
     * @throws SpeedtestException
     * @return string[]
     */
    public function getServers() {
        $list = [];
        
        try {
            $client = new GuzzleClient();

            $options = [
                "verify"  => true,
                "timeout" => $this->config->getTimeout(),
                "curl"    => [
                    CURLOPT_USERAGENT       => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36",
                    CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_2_0, // Make http client work with HTTP 2/0
                    CURLOPT_SSLVERSION      => 1,
                    CURLOPT_SSL_VERIFYPEER  => false
                ], 
                "headers" => [
                    "accept-language" => "en-US"
                ]
            ];

            if ($this->config->getProxyType() == "socks5") {
                $options["proxy"] = "socks5://" . $this->config->getProxy();
            } else {
                $options["proxy"] = "http://" . $this->config->getProxy();
            }

            $res = $client->request('GET', 'https://c.speedtest.net/speedtest-servers-static.php', $options);

            $data = $res->getBody()->getContents();
            $xml = simplexml_load_string($data);

            if (empty($xml)) {
                throw new SpeedtestException("Couldn't get servers list. Reason: empty xml data.");
            }

            foreach($xml->servers->server as $server) {
                $server = $this->xmlAttributesToArray($server);
                $id = (int)$server['id'];
                
                if((!$this->config->getUseServers() || in_array($id, $this->config->getUseServers()))
                    && !in_array($id, $this->config->getIgnoreServers())) {
                    $server['d'] = $this->haversine(
                        (float)$this->config->getClient()['lat'],
                        (float)$this->config->getClient()['lon'],
                        (float)$server['lat'],
                        (float)$server['lon']
                        );
                    $list[(int)$server['id']] = $server;
                }
            }
        } catch (SpeedtestException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new SpeedtestException("Couldn't get servers list. Reason: " . $e->getMessage());
        }
        
        if(!$list) {
            throw new SpeedtestException("No matched servers");
        }
        
        usort($list, function($a, $b) {
            if ($a['d'] == $b['d']) {
                return 0;
            }
            return ($a['d'] < $b['d']) ? -1 : 1;
        });
        
        $this->servers = $list;
        return $this->servers;
    }
    
    /**
     * Get best server
     * 
     * @param int   $checkCount             Number of server to check
     *                                      100   Default value
     *                                      1     Production value for faster speedtests    
     * @param bool  $skipLatencyRetries     Skip latency check retries for faster speedtests  
     * 
     * @throws SpeedtestException
     * @return string[]
     */
    public function getBestServer($checkCount = 100, $skipLatencyRetries = false) {
        $servers = array_filter($this->servers, function($server) {
            return $server['d'] < 250;
        });

        if ($servers) {
            $this->servers = $servers;
        }

        if ($checkCount == 1) {
            $this->best = $this->servers[0];
            return $this->best;
        }
        
        $latency = PHP_INT_MAX;
        $fastest = null;

        for ($i = 0; $i < min([$checkCount, count($this->servers)]); $i++) {
            $url = $this->servers[$i]['url'];
            $parts = parse_url($url);
            $url = preg_replace('/\?.*/', '', $url);

            $cum = [];

            for ($j = 0; $j < ($skipLatencyRetries ? 1 : 3); $j++) {
                $cum[] = 3600;
                $url = str_replace($parts['path'], $parts['path'] . '/latency.txt?x=' . microtime(true) , $url);
                try {
                    $client = new GuzzleClient();
    
                    $ping_time = 0;
    
                    $options = [
                        "verify"  => true,
                        "timeout" => $this->config->getTimeout(),
                        "curl"    => [
                            CURLOPT_USERAGENT       => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36",
                            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_2_0, // Make http client work with HTTP 2/0
                            CURLOPT_SSLVERSION      => 1,
                            CURLOPT_SSL_VERIFYPEER  => false,
                            CURLOPT_FRESH_CONNECT   => true
                        ], 
                        "headers" => [
                            "accept-language" => "en-US"
                        ],
                        "on_stats" => function (GuzzleTransferStats $stats) use ($ping_time) {
                            $ping_time = $stats->getHandlerStat('starttransfer_time') - $stats->getHandlerStat('pretransfer_time');
                        }
                    ];

                    if ($this->config->getProxyType() == "socks5") {
                        $options["proxy"] = "socks5://" . $this->config->getProxy();
                    } else {
                        $options["proxy"] = "http://" . $this->config->getProxy();
                    }
    
                    $res = $client->request('GET', $url, $options);
    
                    $data = $res->getBody()->getContents();
    
                    if (!empty($res) && ($res->getStatusCode() == 200) && (trim($data) == 'test=test')) {
                        $cum[] = $ping_time * 1000;
                    }
                } catch (SpeedtestException $e) {
                    $cum[] = 3600;
                } catch (\Exception $e) {
                    $cum[] = 3600;
                }
            }

            if ((array_sum($cum) / count($cum)) < $latency) {
                $latency = array_sum($cum) / count($cum);
                $fastest = $this->servers[$i];
            }
        }

        $latency = number_format($latency, 2);

        $fastest['latency'] = $latency;
        $this->progress->getResult()->setLatency($latency);

        $fastest['d'] = number_format($fastest['d'], 2);
        $this->best = $fastest;

        return $this->best;
    }
    
    /**
     * 
     * @param resource $ch
     * @param int $download_size
     * @param int $downloaded
     * @param int $upload_size
     * @param int $uploaded
     */
    protected function progress($ch, $download_size, $downloaded, $upload_size, $uploaded) {
        $this->progress->progress(intval($ch), $this->mode, ($this->mode == 'download') ? $downloaded : $uploaded);
        if($this->config->getCallback()) {
            call_user_func($this->config->getCallback(), $this->progress->getResult());
        }
    }
    
    /**
     * 
     * @param int $threads
     */
    public function download($threads = null) {
        $this->mode = 'download';
        
        $urls = [];
        $parts = parse_url($this->best['url']);
        foreach ($this->config->getSizes()['download'] as $size) {
            $urls[] = str_replace($parts['path'], $parts['path'] . '/download?size=' . $size, $this->best['url']);
        }

        $mh = curl_multi_init();
        $maxConnections = $this->config->isSingle() ? 1 : ($threads ?: $this->config->getThreads()['download']);
        curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, $maxConnections);
        $conn = [];
        foreach ($urls as $url) {
            $ch = curl_init();
            if ($this->config->getSourceAddress()) {
                curl_setopt($ch, CURLOPT_INTERFACE, $this->config->getSourceAddress());
            }
            if (!empty($this->config->getProxy())) {
                if ($this->config->getProxyType() == "socks5") {
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                    $urlParts = parse_url("socks5://" . $this->config->getProxy());
                } else {
                    $urlParts = parse_url("http://" . $this->config->getProxy());
                }
                if ($urlParts == false || !array_key_exists("host", $urlParts)) {
                    throw new SpeedtestException("Invalid pattern for proxy" . $this->config->getProxy() . ". Proxy should match following pattern: http://ip:port, http://username:password@ip:port, socks5://ip:port or socks5://username:password@ip:port");
                }
                curl_setopt($ch, CURLOPT_PROXY, $urlParts["host"]);
                if (isset($urlParts["port"])) {
                    curl_setopt($ch, CURLOPT_PROXY, $urlParts["host"] . ":" . $urlParts["port"]);
                }
                if (isset($urlParts["user"])) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $urlParts["user"] . ":" . $urlParts["pass"]);
                }
            }
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getTimeout());
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'progress']);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36');
            curl_multi_add_handle($mh, $ch);
            $conn[] = $ch;
        }
        
        $this->progress->start();
        
        do {
            $active = false;
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
            // // Debugging
            // while ($info = curl_multi_info_read($mh)) {
            //     echo '<pre>';
            //     if ($info['result'] === 0) {
            //         echo curl_getinfo($info['handle'],CURLINFO_EFFECTIVE_URL) . ' success<br>';
            //     } else {
            //         echo curl_getinfo($info['handle'],CURLINFO_EFFECTIVE_URL) . ' failed<br>';
            //     } 
            // }
        } while ($active && $status == CURLM_OK);

        foreach ($conn as $i => $ch) {
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    /**
     *
     * @param int $threads
     */
    public function upload($threads = null) {
        $this->mode = 'upload';
        
        $datas = [];
        $sizes = $this->config->getSizes()['upload'];
        $requestCount = $this->config->getUploadMax();
        foreach ($sizes as $size) {
            $datas[] = random_bytes($size);
        }
        
        $mh = curl_multi_init();
        $maxConnections = $this->config->isSingle() ? 1 : ($threads ?: $this->config->getThreads()['upload']);
        curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, $maxConnections);
        $conn = [];
        $dataCount = 0;
        for($i = 0; $i < $requestCount; $i++) {
            $data = $datas[$dataCount++];
            if($dataCount == count($datas)) {
                $dataCount = 0;
            }
            $ch = curl_init();
            if ($this->config->getSourceAddress()) {
                curl_setopt($ch, CURLOPT_INTERFACE, $this->config->getSourceAddress());
            }
            if (!empty($this->config->getProxy())) {
                if ($this->config->getProxyType() == "socks5") {
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                    $urlParts = parse_url("socks5://" . $this->config->getProxy());
                } else {
                    $urlParts = parse_url("http://" . $this->config->getProxy());
                }
                if ($urlParts == false || !array_key_exists("host", $urlParts)) {
                    throw new SpeedtestException("Invalid pattern for proxy" . $this->config->getProxy() . ". Proxy should match following pattern: http://ip:port, http://username:password@ip:port, socks5://ip:port or socks5://username:password@ip:port");
                }
                curl_setopt($ch, CURLOPT_PROXY, $urlParts["host"]);
                if (isset($urlParts["port"])) {
                    curl_setopt($ch, CURLOPT_PROXY, $urlParts["host"] . ":" . $urlParts["port"]);
                }
                if (isset($urlParts["user"])) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $urlParts["user"] . ":" . $urlParts["pass"]);
                }
            }
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getTimeout());
            curl_setopt($ch, CURLOPT_URL, $this->best['url']);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'progress']);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-length:' . strlen($data)]);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36');
            curl_multi_add_handle($mh, $ch);
            $conn[] = $ch;
        }
        
        $this->progress->start();
        
        do {
            $active = false;
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
            // // Debugging
            // while ($info = curl_multi_info_read($mh)) {
            //     echo '<pre>';
            //     if ($info['result'] === 0) {
            //         echo curl_getinfo($info['handle'],CURLINFO_EFFECTIVE_URL) . ' success<br>';
            //     } else {
            //         echo curl_getinfo($info['handle'],CURLINFO_EFFECTIVE_URL) . ' failed<br>';
            //     } 
            // }
        } while ($active && $status == CURLM_OK);
        
        foreach ($conn as $ch) {
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    
    /**
     * @return string
     */
    public function share() {
        $results = $this->results();
        $latency = (int)round($results->getLatency(), 0);
        $download = (int)round($results->getDownload() / 1000, 0);
        $upload = (int)round($results->getUpload() / 1000, 0);
        $bytesReceived = (int)$results->getBytesReceived();
        $bytesSent = (int)$results->getBytesSent();
        $hash = md5("$latency-$upload-$download-297aae72");
        
        $apiData = [
            'recommendedserverid' => $this->best['id'],
            'ping' => $latency,
            'screenresolution' => '',
            'promo' => '',
            'download' => $download,
            'screendpi' => '',
            'upload' => $upload,
            'testmethod' => 'https',
            'hash' => $hash,
            'touchscreen' => 'none',
            'startmode' => 'pingselect',
            'accuracy' => '1',
            'bytesreceived' => $bytesReceived,
            'bytessent' => $bytesSent,
            'serverid' => $this->best['id']
        ];
        
        $ch = curl_init();
        if($this->config->getSourceAddress()) {
            curl_setopt($ch, CURLOPT_INTERFACE, $this->config->getSourceAddress());
        }
        if (!empty($this->config->getProxy())) {
            if ($this->config->getProxyType() == "socks5") {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                $urlParts = parse_url("socks5://" . $this->config->getProxy());
            } else {
                $urlParts = parse_url("http://" . $this->config->getProxy());
            }
            if ($urlParts == false || !array_key_exists("host", $urlParts)) {
                throw new SpeedtestException("Invalid pattern for proxy " . $this->config->getProxy() . ". Proxy should match following pattern: http://ip:port, http://username:password@ip:port, socks5://ip:port or socks5://username:password@ip:port");
            }
            curl_setopt($ch, CURLOPT_PROXY, $urlParts["host"]);
            if (isset($urlParts["port"])) {
                curl_setopt($ch, CURLOPT_PROXY, $urlParts["host"] . ":" . $urlParts["port"]);
            }
            if (isset($urlParts["user"])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $urlParts["user"] . ":" . $urlParts["pass"]);
            }
        }
        curl_setopt($ch, CURLOPT_URL, 'https://www.speedtest.net/api/api.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_REFERER, 'http://c.speedtest.net/flash/speedtest.swf');
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getTimeout());
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36');
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        $resultId = null;
        $params = [];
        if($info['http_code'] == 200) {
            parse_str($response, $params);
            if(isset($params['resultid'])) {
                $resultId = $params['resultid'];
            }
        }
        
        if(!$resultId) {
            throw new SpeedtestException("Could not submit results to speedtest.net");
        }
        
        return "https://www.speedtest.net/result/$resultId.png";
    }
    
    /**
     * 
     * @return \NextpostTech\Speedtest\Result
     */
    public function results() {
        return $this->progress->getResult();
    }

    /**
     * @return array
     */
    public function clientInfo()
    {
        return $this->config->getClient();
    }
}

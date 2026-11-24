<?php

namespace Graftype\Speedtest;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\TransferStats as GuzzleTransferStats;

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

class Speedtest
{
    /** Internal relay headers never leave the loopback connection. */
    protected function relayHeaders($url) {
        $relay = $this->config->getRelay();
        return [
            'X-Tls-Key' => $relay['key'],
            'X-Tls-Url' => $url,
            'X-Tls-Proxy' => $this->config->getProxy() ? $this->config->getProxyType() . '://' . $this->config->getProxy() : '',
            'X-Tls-Verify' => $relay['verify'] ? 'true' : 'false',
            'X-Tls-IgMobile' => $relay['mobile'] ? '1' : '0',
            'X-Tls-Follow-Redirects' => 'false',
            'X-Tls-Timeout' => (string)$this->config->getTimeout()
        ];
    }

    protected function request($client, $url, array $options) {
        $relay = $this->config->getRelay();
        if (!$relay) return $client->request('GET', $url, $options);
        $onStats = $options['on_stats'] ?? null;
        $headers = $options['headers'] ?? [];
        $options['proxy'] = '';
        $options['allow_redirects'] = false;
        $options['version'] = 1.1;
        unset($options['curl'][CURLOPT_HTTP_VERSION]);
        for ($redirect = 0; $redirect <= 3; ++$redirect) {
            $options['headers'] = array_merge($headers, $this->relayHeaders($url));
            $options['on_stats'] = function ($stats) use ($onStats, $url) {
                if ($onStats) $onStats(new GuzzleTransferStats(new \GuzzleHttp\Psr7\Request('GET', $url), $stats->getResponse(), $stats->getTransferTime(), $stats->getHandlerErrorData(), $stats->getHandlerStats()));
            };
            $response = $client->request('GET', $relay['url'], $options);
            if (!in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true) || !$response->hasHeader('Location')) return $response;
            $next = \GuzzleHttp\Psr7\UriResolver::resolve(\GuzzleHttp\Psr7\Utils::uriFor($url), \GuzzleHttp\Psr7\Utils::uriFor($response->getHeaderLine('Location')));
            if (!in_array($next->getScheme(), ['http', 'https'], true)) throw new SpeedtestException('Unsupported speed test redirect.');
            $url = (string)$next;
        }
        throw new SpeedtestException('Speed test exceeded the redirect limit.');
    }

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
                    CURLOPT_SSL_VERIFYPEER  => true
                ], 
                "headers" => [
                    "accept-language" => "en-US"
                ],
                // "debug" => true
            ];

            if (!$this->config->getProxy()) {
                $options["proxy"] = null;
            } elseif ($this->config->getProxyType() == "socks5") {
                $options["proxy"] = "socks5://" . $this->config->getProxy();
            } elseif ($this->config->getProxyType() == "socks5h") {
                $options["proxy"] = "socks5h://" . $this->config->getProxy();
            } else {
                $options["proxy"] = $this->config->getProxyType() . "://" . $this->config->getProxy();
            }

            try {
                $res = $this->request($client, 'https://c.speedtest.net/speedtest-config.php', $options);
            } catch (\Exception $e) {
                $res = $this->request($client, 'https://www.speedtest.net/speedtest-config.php', $options);
            }

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
                    CURLOPT_SSL_VERIFYPEER  => true
                ], 
                "headers" => [
                    "accept-language" => "en-US"
                ]
            ];

            if (!$this->config->getProxy()) {
                $options["proxy"] = null;
            } elseif ($this->config->getProxyType() == "socks5") {
                $options["proxy"] = "socks5://" . $this->config->getProxy();
            } elseif ($this->config->getProxyType() == "socks5h") {
                $options["proxy"] = "socks5h://" . $this->config->getProxy();
            } else {
                $options["proxy"] = $this->config->getProxyType() . "://" . $this->config->getProxy();
            }

            $res = $this->request($client, 'https://c.speedtest.net/speedtest-servers-static.php', $options);

            $data = $res->getBody()->getContents();
            $xml = simplexml_load_string($data);

            if (empty($xml)) {
                throw new SpeedtestException("Couldn't get servers list. Reason: empty xml data.");
            }

            foreach($xml->servers->server as $server) {
                $server = $this->xmlAttributesToArray($server);
                $id = (int)$server['id'];
                if ($this->config->getRelay() && $id > 0 && parse_url($server['url'], PHP_URL_SCHEME) === 'http') {
                    // TLSClientProxy requires TLS targets. Ookla's canonical certificate
                    // hostname avoids HTTP-only aliases and their large-POST redirects.
                    $server['url'] = 'https://server-' . $id . '.prod.hosts.ooklaserver.net:8080' . parse_url($server['url'], PHP_URL_PATH);
                }

                
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
            $this->servers = array_values($servers);
        }

        if (!$this->servers) throw new SpeedtestException("No test servers available.");

        
        $latency = PHP_INT_MAX;
        $fastest = null;

        for ($i = 0; $i < min([$checkCount, count($this->servers)]); $i++) {
            $url = $this->servers[$i]['url'];
            $parts = parse_url($url);
            $url = preg_replace('/\?.*/', '', $url);

            $cum = [];
            $candidate = $this->servers[$i];

            for ($j = 0; $j < ($skipLatencyRetries ? 1 : 3); $j++) {
                $url = preg_replace('~/[^/]*$~', '', preg_replace('/[?#].*$/', '', $this->servers[$i]['url'])) . '/latency.txt?x=' . microtime(true);
                try {
                    $client = new GuzzleClient();
    
                    $ping_time = 0;
                    $effectiveLatencyUrl = $url;
    
                    $options = [
                        "verify"  => true,
                        "timeout" => $this->config->getTimeout(),
                        "curl"    => [
                            CURLOPT_USERAGENT       => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36",
                            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_2_0, // Make http client work with HTTP 2/0
                            CURLOPT_SSLVERSION      => 1,
                            CURLOPT_SSL_VERIFYPEER  => true,
                            CURLOPT_FRESH_CONNECT   => true
                        ], 
                        "headers" => [
                            "accept-language" => "en-US"
                        ],
                        "on_stats" => function (GuzzleTransferStats $stats) use (&$ping_time, &$effectiveLatencyUrl) {
                            $ping_time = $stats->getHandlerStat('starttransfer_time') - $stats->getHandlerStat('pretransfer_time');
                            $effectiveLatencyUrl = (string)$stats->getEffectiveUri();
                        }
                    ];

                    if (!$this->config->getProxy()) {
                        $options["proxy"] = null;
                    } elseif ($this->config->getProxyType() == "socks5") {
                        $options["proxy"] = "socks5://" . $this->config->getProxy();
                    } elseif ($this->config->getProxyType() == "socks5h") {
                        $options["proxy"] = "socks5h://" . $this->config->getProxy();
                    } else {
                        $options["proxy"] = $this->config->getProxyType() . "://" . $this->config->getProxy();
                    }
    
                    $res = $this->request($client, $url, $options);
    
                    $data = $res->getBody()->getContents();
    
                    if (!empty($res) && ($res->getStatusCode() == 200) && (trim($data) == 'test=test')) {
                        $cum[] = max(0.001, $ping_time * 1000);
                        // Resolve redirects with the small latency request before sending upload bodies.
                        // Some proxy/server paths close large HTTP POSTs before returning a redirect.
                        $endpoint = basename(parse_url($this->servers[$i]['url'], PHP_URL_PATH));
                        $candidate['url'] = preg_replace('~/[^/]*$~', '', preg_replace('/[?#].*$/', '', $effectiveLatencyUrl)) . '/' . $endpoint;
                    }
                } catch (SpeedtestException $e) {
                    $cum[] = 3600;
                } catch (\Exception $e) {
                    $cum[] = 3600;
                }
            }

            if ($cum && min($cum) < 3600 && (array_sum($cum) / count($cum)) < $latency) {
                $latency = array_sum($cum) / count($cum);
                $fastest = $candidate;
            }
        }

        if (!$fastest) throw new SpeedtestException("No reachable test server. Check the proxy connection.");
        $latency = round($latency, 2);

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
        $this->progress->progress((is_object($ch) ? spl_object_id($ch) : (int)$ch), $this->mode, ($this->mode == 'download') ? $downloaded : $uploaded);
        if($this->config->getCallback()) {
            call_user_func($this->config->getCallback(), $this->progress->getResult());
        }
    }
    
    /**
     * 
     * @param int $threads
     */
    public function download($threads = null) {
        $base = $this->transferBase();
        $urls = [];
        foreach ([350, 500, 750, 1000, 1500, 2000, 2500, 3000, 3500, 4000] as $size) {
            $urls[] = $base . '/random' . $size . 'x' . $size . '.jpg?x=' . microtime(true);
        }
        $this->transfer('download', $urls, $threads);
    }

    public function upload($threads = null) {
        $this->transfer('upload', array_fill(0, max(1, min(20, $this->config->getUploadMax())), $this->best['url']), $threads);
    }

    protected function transferBase() {
        if (empty($this->best['url'])) {
            throw new SpeedtestException('Select a reachable test server before measuring transfer speed.');
        }
        return preg_replace('~/[^/]*$~', '', preg_replace('/[?#].*$/', '', $this->best['url']));
    }

    /** Count only successful HTTP transfers; never report an error page as bandwidth. */
    protected function transfer($mode, array $urls, $threads) {
        $this->transferBase();
        $this->mode = $mode;
        $mh = curl_multi_init();
        $connections = max(1, min(8, $this->config->isSingle() ? 1 : ($threads ?: $this->config->getThreads()[$mode])));
        curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, $connections);
        $handles = [];
        $relay = $this->config->getRelay();
        $bytes = 0;
        $successes = 0;
        $lastError = 'No successful transfers';
        $started = microtime(true);
        $lastRelaySample = 0;
        $deadline = max(1, min(20, $this->config->getLength()[$mode] ?? 10));
        $this->progress->start();
        try {
            foreach ($urls as $i => $url) {
                $ch = curl_init($url);
                $handles[] = $ch;
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) { return strlen($chunk); },
                    CURLOPT_TIMEOUT => $this->config->getTimeout(),
                    CURLOPT_CONNECTTIMEOUT => min(5, $this->config->getTimeout()),
                    CURLOPT_FAILONERROR => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
                    CURLOPT_NOPROGRESS => false,
                    CURLOPT_PROGRESSFUNCTION => [$this, 'progress'],
                    CURLOPT_USERAGENT => 'Speedtest-PHP/1.0.14',
                    CURLOPT_NOSIGNAL => true
                ]);
                if ($this->config->getSourceAddress()) curl_setopt($ch, CURLOPT_INTERFACE, $this->config->getSourceAddress());
                if ($this->config->getProxy()) {
                    curl_setopt($ch, CURLOPT_PROXY, $this->config->getProxyType() . '://' . $this->config->getProxy());
                    // An environment NO_PROXY must not bypass the configured test proxy.
                    curl_setopt($ch, CURLOPT_NOPROXY, '');
                }
                if ($mode === 'upload') {
                    $sizes = $this->config->getSizes()['upload'];
                    $size = min(2097152, $sizes[$i % count($sizes)] ?? 262144);
                    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => str_repeat('0123456789abcdef', (int)ceil($size / 16)), CURLOPT_HTTPHEADER => ['Content-Type: application/octet-stream', 'Expect:']]);
                }
                if ($relay) {
                    $headers = $this->relayHeaders($url);
                    if ($mode === 'upload') $headers = array_merge($headers, ['Content-Type' => 'application/octet-stream', 'Expect' => '']);
                    $lines = [];
                    foreach ($headers as $name => $value) $lines[] = $name . ': ' . $value;
                    curl_setopt_array($ch, [CURLOPT_URL => $relay['url'], CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => $lines, CURLOPT_FOLLOWLOCATION => false, CURLOPT_NOPROGRESS => true]);
                }
                curl_multi_add_handle($mh, $ch);
            }
            do {
                $status = curl_multi_exec($mh, $active);
                while ($info = curl_multi_info_read($mh)) {
                    $ch = $info['handle'];
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    $size = curl_getinfo($ch, $mode === 'download' ? CURLINFO_SIZE_DOWNLOAD : CURLINFO_SIZE_UPLOAD);
                    if ($info['result'] === CURLE_OK && $code >= 200 && $code < 300 && $size > 0 && stripos($type, 'text/html') === false) {
                        $bytes += $size;
                        ++$successes;
                        if ($relay) {
                            // The relay buffers bodies. Count acknowledged upstream transfers,
                            // never the fast loopback leg as proxy upload/download bandwidth.
                            $this->progress->progress(0, $mode, $bytes);
                            if ($this->config->getCallback()) call_user_func($this->config->getCallback(), $this->progress->getResult());
                        }
                    } else {
                        $lastError = 'HTTP ' . $code . ', cURL ' . $info['result'];
                    }
                }
                if ($relay && microtime(true) - $lastRelaySample >= 0.25) {
                    $lastRelaySample = microtime(true);
                    $this->progress->progress(0, $mode, $bytes);
                    if ($this->config->getCallback()) call_user_func($this->config->getCallback(), $this->progress->getResult());
                }
                if ($active && $status === CURLM_OK) curl_multi_select($mh, 0.1);
            } while ($active && $status === CURLM_OK && microtime(true) - $started < $deadline);
        } finally {
            foreach ($handles as $ch) {
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            curl_multi_close($mh);
        }
        $result = $this->progress->getResult();
        $speed = $bytes * 8 / max(0.001, microtime(true) - $started);
        if ($mode === 'download') {
            $result->setDownload($speed);
            $result->setBytesReceived($bytes);
        } else {
            $result->setUpload($speed);
            $result->setBytesSent($bytes);
        }
        if (!$successes || $speed <= 0 || !is_finite($speed)) {
            throw new SpeedtestException(ucfirst($mode) . ' measurement failed (' . $lastError . '). Try another proxy or test server.');
        }
        if ($this->config->getCallback()) call_user_func($this->config->getCallback(), $result);
    }

    /**
     * @return string
     */
    public function share() {
        $results = $this->results();
        $latency = !empty($results->getLatency()) ? (int) round((float) $results->getLatency(), 0) : 0;
        $download = !empty($results->getDownload()) ? (int) round((float) $results->getDownload() / 1000, 0) : 0;
        $upload = !empty($results->getUpload()) ? (int) round((float) $results->getUpload() / 1000, 0) : 0;
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
        if ($this->config->getProxy()) {
            curl_setopt($ch, CURLOPT_PROXY, $this->config->getProxyType() . '://' . $this->config->getProxy());
            curl_setopt($ch, CURLOPT_NOPROXY, '');
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
     * @return \Graftype\Speedtest\Result
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

<?php
// Run against the local fixture; no external services or real proxy traffic.
require getenv('SPEEDTEST_AUTOLOAD') ?: __DIR__ . '/../vendor/autoload.php';
foreach (['Result','Progress','Config','SpeedtestException','Speedtest'] as $class) require_once __DIR__ . '/../src/' . $class . '.php';
class FixtureSpeedtest extends \Graftype\Speedtest\Speedtest {
    protected function getRemoteConfig() {}
    public function server($url) {$this->best=['url'=>$url];}
    public function candidates($servers) {$this->servers=$servers;}
}
function check($ok,$message) {if (!$ok) throw new RuntimeException($message);}
$port=$argv[1];$url='http://127.0.0.1:'.$port;
$config=new \Graftype\Speedtest\Config();
$config->setSizes(['upload'=>[32768,65536]]);$config->setUploadMax(4);$config->setThreads(['upload'=>2,'download'=>3]);$config->setLength(['upload'=>2,'download'=>2]);$config->setTimeout(2);
$test=new FixtureSpeedtest($config);$samples=[];
$config->setCallback(function($r)use(&$samples){$samples[]=[$r->getDownload(),$r->getUpload(),$r->getBytesReceived(),$r->getBytesSent()];});
$test->server($url.'/speedtest/upload.php');$test->download();$test->upload();
check($test->results()->getBytesReceived()===2621460.0,'Download counted wrong bytes');
check($test->results()->getBytesSent()===196608.0,'Upload counted wrong bytes');
check($test->results()->getDownload()>0 && $test->results()->getUpload()>0,'Missing real throughput');
check(count($samples)>10,'Live callbacks missing');
foreach (['failed','html'] as $path) {
 $test->server($url.'/'.$path.'/upload.php');
 try {$test->download();throw new RuntimeException('Accepted '.$path.' as speed');}catch(\Graftype\Speedtest\SpeedtestException $e){check(strpos($e->getMessage(),'measurement failed')!==false,'Wrong failure');}
 check($test->results()->getDownload()==0,'Failure retained stale bandwidth');
}
$test->server($url.'/failed/upload.php');
try {$test->upload();throw new RuntimeException('Accepted rejected upload');}catch(\Graftype\Speedtest\SpeedtestException $e){}
// Send HTTP-proxy requests to the fixture with a deliberately unresolvable target.
$config->setProxy('http://user:p%40ss@127.0.0.1:'.$port);
$test->server('http://unresolvable.invalid/speedtest/upload.php');$test->download();$test->upload();
check($test->results()->getDownload()>0 && $test->results()->getUpload()>0,'Transfer bypassed HTTP proxy');
$config->setProxy('');
$test->candidates([['url'=>$url.'/slow/upload.php','d'=>1],['url'=>$url.'/speedtest/upload.php','d'=>2]]);
$best=$test->getBestServer(2,true);check(is_numeric($best['latency']) && $best['latency']<1000,'Latency broken');
$test->candidates([['url'=>$url.'/failed/upload.php','d'=>1]]);
try {$test->getBestServer(2,true);throw new RuntimeException('Accepted unavailable latency server');}catch(\Graftype\Speedtest\SpeedtestException $e){}
$config->setProxy('socks5h://a:b@host:1080');check($config->getProxyType()==='socks5h','Lost remote DNS');
$config->setProxy('https://a:b@host:443');check($config->getProxyType()==='https','Lost HTTPS proxy');
$config->setProxy('host:8080');check($config->getProxyType()==='http','Proxy type not reset');
echo 'PASS: real local download/upload byte counts, live callbacks, HTTP proxy routing, HTTP/HTML errors, failed upload, latency and proxy configuration on PHP '.PHP_VERSION."\n";

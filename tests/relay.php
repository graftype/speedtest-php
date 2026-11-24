<?php
if(PHP_SAPI!=='cli')exit;
require getenv('SPEEDTEST_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
foreach (['Result','Progress','Config','SpeedtestException','Speedtest'] as $class) require_once dirname(__DIR__).'/src/'.$class.'.php';
function __($s){return $s;}
class RelaySettings {public $enabled=true,$port;function get($key){return ['data.tls_fingerprint.enabled'=>$this->enabled,'data.tls_fingerprint.port'=>$this->port,'data.tls_fingerprint.access_token'=>'fixture-key','data.tls_fingerprint.verify_certificates'=>true][$key]??null;}}
$portFile=tempnam(sys_get_temp_dir(),'pmp-relay-');unlink($portFile);
$process=proc_open(['python3',__DIR__.'/relay.py',$portFile],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
try{
 for($i=0;$i<100&&!is_file($portFile);++$i)usleep(20000);
 if(!is_file($portFile))throw new RuntimeException('Fixture failed to start');
 $settings=new RelaySettings;$settings->port=(int)file_get_contents($portFile);
 $config=new Graftype\Speedtest\Config;$config->setProxy('http://user:secret@proxy.invalid:8080');
 $config->setRelay('http://127.0.0.1:'.$settings->port,'fixture-key');$mode='tls';
 if($mode!=='tls'||!$config->getRelay()['verify'])throw new RuntimeException('Configured TLS mode missing');
 $samples=[];$config->setCallback(function($r)use(&$samples){$samples[]=[$r->getBytesReceived(),$r->getBytesSent()];});
 $s=new Graftype\Speedtest\Speedtest($config);$s->getServers();$best=$s->getBestServer(3,true);
 if(strpos($best['url'],'https://server-12345.prod.hosts.ooklaserver.net:8080/')!==0)throw new RuntimeException('TLS canonical host missing');
 $config->setSizes(['upload'=>[32768,65536]]);$config->setUploadMax(2);
 $s->download();$s->upload();
 if($s->results()->getBytesReceived()!=327680 || $s->results()->getBytesSent()!=98304 || count($samples)<14)throw new RuntimeException('Relay metrics must count completed upstream payloads only');
 $bad=new Graftype\Speedtest\Config;$bad->setProxy('http://user:secret@proxy.invalid:8080');$bad->setRelay('http://127.0.0.1:'.$settings->port,'invalid-key');
 try{new Graftype\Speedtest\Speedtest($bad);throw new RuntimeException('Invalid relay key accepted');}catch(Graftype\Speedtest\SpeedtestException $e){}
 $direct=new Graftype\Speedtest\Config;
 if($direct->getRelay()!==null)throw new RuntimeException('Direct mode must be the default');
 echo "PASS: config, servers, latency and transfers use configured relay; byte accounting excludes loopback; bad key never bypasses TLS; direct mode is the default.\n";
}finally{proc_terminate($process);foreach($pipes as $pipe)fclose($pipe);proc_close($process);@unlink($portFile);}

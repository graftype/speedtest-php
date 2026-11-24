<?php
if (PHP_SAPI !== 'cli') exit;
require getenv('SPEEDTEST_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
foreach (['Result','Progress','Config','SpeedtestException','Speedtest'] as $class) require_once dirname(__DIR__).'/src/'.$class.'.php';
class RedirectSpeedtest extends Graftype\Speedtest\Speedtest {
    protected function getRemoteConfig() {}
    public function candidates($url) {$this->servers=[['url'=>$url,'d'=>1]];}
    public function server($url) {$this->best=['url'=>$url];}
}
$portFile=tempnam(sys_get_temp_dir(),'pmp-redirect-');unlink($portFile);
$process=proc_open(['python3',__DIR__.'/server-redirect.py',$portFile],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
try {
    for($i=0;$i<100&&!is_file($portFile);++$i)usleep(20000);
    if (!is_file($portFile)) throw new RuntimeException('Fixture failed to start');
    $base='http://127.0.0.1:'.trim(file_get_contents($portFile));
    $config=new Graftype\Speedtest\Config();$config->setSizes(['upload'=>[524288]]);$config->setUploadMax(2);$config->setThreads(['upload'=>2]);$config->setLength(['upload'=>3]);$config->setTimeout(3);
    $test=new RedirectSpeedtest($config);$test->server($base.'/legacy/upload.php');
    try {$test->upload();throw new RuntimeException('Expected old endpoint to fail');}catch(Graftype\Speedtest\SpeedtestException $e){if(strpos($e->getMessage(),'cURL 52')===false)throw $e;}
    $test->candidates($base.'/legacy/upload.php');$best=$test->getBestServer(1,true);
    if($best['url']!==$base.'/canonical/upload.php')throw new RuntimeException('Effective server URL was not retained');
    $test->upload();
    if($test->results()->getBytesSent()!=1048576 || $test->results()->getUpload()<=0)throw new RuntimeException('Upload did not complete on canonical server');
    echo "PASS: reproduces empty HTTP reply; latency redirect resolves canonical upload endpoint; both large POSTs complete.\n";
}finally{proc_terminate($process);foreach($pipes as $pipe)fclose($pipe);proc_close($process);@unlink($portFile);}

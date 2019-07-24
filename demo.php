<?php


require '../../../vendor/autoload.php';
use ProcessManager\Process;

class demo extends Process
{
    public function runJob($worker, $index)
    {
        \swoole_set_process_name(sprintf('%s-worker-%d', 'test',$index));
        $i=0;
        while(true){
            $i++;
            echo posix_getpid()."=".$i.PHP_EOL;
            sleep(2);
            $this->isExit($i);

        }
    }
}
if(count($argv)<3){
    exit( "params lost".PHP_EOL);
}
$process_name=$argv[1];
$worker_num=3;
if(isset($argv[3])){
    $worker_num=intval($argv[3]);
}
$cmd=$argv[2];
$config=[
    'process_name'=>$process_name,
    'worker_num'=>$worker_num,
    'out_file'=>'/tmp/out'
];

$process=new demo($config);
$process->run($cmd);

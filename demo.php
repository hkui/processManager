<?php
require '../../../vendor/autoload.php';
use ProcessManager\Process;

define("A","456");
include 'job.php';

class demo extends Process
{
    public function runJob($worker, $index)
    {
        print_r(get_included_files());
        \swoole_set_process_name(sprintf('%s-worker-%d', 'test',$index));

        $job=new job();
        $job->do($this);
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
    'out_file'=>'/tmp/out',
    'max_run_time'=>10  //运行10个后自己退出，然后master补上

];
$process=new demo($config);
echo posix_getpid().PHP_EOL;
$process->run($cmd);

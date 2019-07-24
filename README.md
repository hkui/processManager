# processManager
### 说明
master-worker进程任务管理,以守护进程方式运行,自动重启worker

需要swoole和posix

### 使用
1.使用composer安装即可
```
composer require hkui/process_manager
```
2.例子

demo.php
```php
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
    'process_name'=>$process_name, //worker名称
    'worker_num'=>$worker_num, //开几个worker
    'out_file'=>'/tmp/out' //输出的日志
];

$process=new demo($config);
$process->run($cmd);


```
启动 开3个worker    
```php demo.php test start 3```

查看状态
```
php demo.php test status

Array
(
    [0] => 19456 19457 19458
    [1] => USER       PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
    [2] => hkui     19455  0.0  0.1 144952  5864 ?        Ss   08:29   0:00 test-master
    [3] => hkui     19456  0.0  0.1 147004  7316 ?        S    08:29   0:00  \_ test-worker-0
    [4] => hkui     19457  0.0  0.1 147004  7300 ?        S    08:29   0:00  \_ test-worker-1
    [5] => hkui     19458  0.0  0.1 147004  7296 ?        S    08:29   0:00  \_ test-worker-2
)

```
reload
```
php demo.php test reload
kill 19471 success
kill 19472 success
kill 19473 success
```

### 支持的命令
* start:开启
* status:查看状态
* reload:平滑重启worker
* stop:停止master worker


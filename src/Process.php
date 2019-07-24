<?php
/**
 * Created by PhpStorm.
 * User: 764432054@@qq.cn
 * Date: 2019/7/18
 * Time: 10:10
 */
namespace ProcessManager;

abstract class Process
{
    public $worker_num = 3;
    public $master_pid_file = '/tmp/queue_%s.pid';
    public $process_name = 'queue'; //进程标题
    public $out_file = '/dev/null';  //守护进程日志文件

    public $new_create = true; //子进程结束后是否新建

    public $max_run_time = 100;  //子进程运行多少次会退出 0代表不退出

    

    protected $master_pid;
    protected $wokers = [];
    protected $reboot_worker=true;
    //支持的命令
    protected $cmdSupport = ['start', 'reload', 'stop', 'status'];

    public function __construct(array $config = [])
    {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }
    }

    public function run($cmd)
    {
        if (!in_array($cmd, $this->cmdSupport)) {
            die ("Now support cmds: " . PHP_EOL . join(PHP_EOL, $this->cmdSupport) . PHP_EOL);
        }

        switch ($cmd) {
            case 'start':
                $this->start();
                break;
            case 'reload':
                $this->reload();
                break;
            case 'stop':
                $this->stop();
                break;
            case 'status':
                $this->status();
                break;
        }

    }

    final private function start()
    {
        if (is_file($this->pidFile()) || file_exists($this->pidFile())) {
            die("Already runing! " . $this->pidFile() . PHP_EOL);
        }
        \swoole_process::daemon(false, true);
        \swoole_set_process_name(sprintf('%s-master', $this->process_name));
        $this->resetStd();
        $this->master_pid = posix_getpid();
        $this->setMasterPid($this->master_pid);
        $this->createWorker();
        $this->processWait();
    }


    final private function stop()
    {
        $master_pid = $this->getMasterPid();
        if (!$master_pid) {
            die($this->pidFile() . " invalid" . PHP_EOL);
        }
        //看下master是否还在存活
        if (!$this->isMasterProcessAlive()) {
            echo $master_pid . " already exit!" . PHP_EOL;
            @unlink($this->pidFile());
            exit();

        }
        //查找master的子进程 一定要在master存活时执行，否者master退出后,原来的子进程将变为孤儿被Init进程回收
        exec("ps --ppid {$master_pid}|awk '/[0-9]/{print $1}'|xargs", $output, $status);
        $child_arr = [];
        if ($status == 0) {
            $child_arr = array_filter(explode(' ', current($output)));
        }


        if (posix_kill($master_pid, SIGKILL)) {
            echo 'master ' . $master_pid . ' exit success' . PHP_EOL;
            @unlink($this->pidFile());

            foreach ($child_arr as $id) {
                if (posix_kill($id, SIGTERM)) {
                    echo 'child ' . $id . ' exit success' . PHP_EOL;
                }

            }

            exit();
        }
        echo 'master ' . $master_pid . ' exit Fail! ' . PHP_EOL;


    }

    final private function status()
    {
        $master_pid = $this->getMasterPid();
        if (!$master_pid) {
            die($this->pidFile() . " invalid");
        }
        $show_pids[] = $master_pid;
        exec("ps --ppid {$master_pid}|awk '/[0-9]/{print $1}'|xargs", $output, $status);
        if ($status == 0) {
            $child_arr = explode(' ', current($output));
            $show_pids = array_merge($show_pids, $child_arr);
        }
        $cmdstr = '$1=="USER"||';
        $show_pids = array_filter($show_pids);
        foreach ($show_pids as $id) {
            $cmdstr .= "$2==" . $id . "||";
        }
        $cmdstr = trim($cmdstr, '||');

        $cmd = sprintf("ps aufx|awk '%s {print $0}'", $cmdstr);
        exec($cmd, $output, $status);
        if ($status == 0) {
            print_r($output);
        } else {
            echo 'exec err:' . PHP_EOL;
            echo $cmd . PHP_EOL;
        }
    }

    final private function reload()
    {
        $master_pid = $this->getMasterPid();
        if (!$master_pid) {
            die($this->pidFile() . " invalid");
        }
        exec("ps --ppid {$master_pid}|awk '/[0-9]/{print $1}'|xargs", $output, $status);
        if ($status == 0) {
            $child_arr = explode(' ', current($output));
            foreach ($child_arr as $id) {
                if (posix_kill($id, SIGTERM)) {
                    echo "kill {$id} success" . PHP_EOL;
                }

            }
        }
    }

    /**
     * @param $already_run_time
     * 检测子进程是否需要退出了
     */
    final public function isExit($already_run_time)
    {
        //  运行超过指定次数
        if ($this->new_create && $this->max_run_time > 0) {
            if ($already_run_time >= $this->max_run_time) {
                exit(0);
            }
        }
        // 主进程退出则退出。
        $status = $this->isMasterProcessAlive();
        if (!$status) {
            exit(0);
        }
    }


    public function createWorker()
    {
        for ($i = 0; $i < $this->worker_num; $i++) {
            $pid=$this->CreateProcess($i);
            echo $pid.' forked success'.PHP_EOL;
        }
    }

    public function CreateProcess($index = null)
    {
        $process = new \swoole_process(function (\swoole_process $worker) use ($index) {
            $this->runJob($worker, $index);
        }, false, false);

        $pid = $process->start();
        $this->wokers[$index] = $pid;
        return $pid;
    }

    public function checkMaster(&$worker)
    {
        if (!\swoole_process::kill($this->master_pid, 0)) {
            $worker->exit();
            echo "Master process exited, I [{$worker['pid']}] also quit" . PHP_EOL;
        }
    }

    public function rebootProcess($ret)
    {
        $pid = $ret['pid'];
        $index = array_search($pid, $this->wokers);
        if ($index !== false) {
            $index = intval($index);
            $new_pid = $this->CreateProcess($index);
            echo "[" . date("Y-m-d H:i:s") . "] rebootProcess: {$index}={$new_pid}" . PHP_EOL;

            return;
        }
        throw new \Exception('rebootProcess Error: no pid');
    }

    public function processWait()
    {
        while (1) {
            if (count($this->wokers)) {
                $ret = \swoole_process::wait();
                if ($ret&&$this->reboot_worker) {
                    $this->rebootProcess($ret);
                }
            } else {
                break;
            }
        }
    }

    public function pidFile()
    {
        return sprintf($this->master_pid_file, $this->process_name);
    }

    public function getMasterPid()
    {
        if (file_exists($this->pidFile())) {
            $pid = file_get_contents($this->pidFile());
            if ($pid) {
                return $pid;
            }
            throw new \ErrorException("pid in" . $this->pidFile() . "  err");
        }
        throw new \ErrorException("pid file  " . $this->pidFile() . " not exists");

    }

    public function setMasterPid($pid)
    {
        if (file_exists($this->pidFile())) {
            throw new \ErrorException("pid file" . $this->pidFile() . "  already exists");
        } else {
            if (!file_put_contents($this->pidFile(), $pid)) {
                echo "save pid into " . $this->pidFile() . "  fail".PHP_EOL;
                throw new \ErrorException("save pid into " . $this->pidFile() . "  fail");
            }
        }
    }

    private function resetStd()
    {
        global $stdin, $stdout, $stderr;
        //关闭打开的文件描述符
        if(!fclose(STDIN)){
            exit("fclose stdin err".PHP_EOL);
        }
        if(!fclose(STDOUT)){
            exit("fclose stdout err".PHP_EOL);
        }
        if(!fclose(STDERR)){
            exit("fclose stderr err".PHP_EOL);
        }

        if ($this->out_file != '/dev/null' && !file_exists($this->out_file)) {
            touch($this->out_file);
            chmod($this->out_file, 0755);
            $this->out_file = realpath($this->out_file);
        }

        $stdin = fopen($this->out_file, 'r');
        $stdout = fopen($this->out_file, 'a');
        $stderr = fopen($this->out_file, 'a');
    }

    /**
     * 检测主进程是否存活。
     * @return bool
     */
    final public function isMasterProcessAlive()
    {
        if (posix_kill($this->master_pid, 0)) {
            return true;
        } else {
            return false;
        }
    }


    //实现要执行的任务
    abstract function runJob($worker, $index);

}
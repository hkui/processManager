<?php

class job
{
    public function do($obj){

        $i=0;
        while(true){
            $i++;
            echo posix_getpid()."=".$i."-job-".A.PHP_EOL;
            sleep(2);
            $obj->isExit($i);
        }
    }
}
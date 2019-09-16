<?php

class job1
{

    public function do($obj){

        $i=0;
        while(true){
            $i++;
            echo posix_getpid()."=".$i."--".A.PHP_EOL;

            sleep(2);
            $obj->isExit($i);

        }
    }
}
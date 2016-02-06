<?php
namespace PMVC\PlugIn\supervisor;

class Monitor 
{
    public function __construct($callBack = null)
    {
        $plug = \PMVC\plug('supervisor');
        while(empty($plug['isStopAll']) 
            && count($plug['children'])){

            // Check for exited children
            $pid = pcntl_wait($status, WNOHANG);
            if(isset($plug['children'][$pid])){
                $exitCode = pcntl_wexitstatus($status);
                $plug->log(
                    "Child $pid was stopped with exit code of $exitCode"
                );
                if( !$plug['isStopAll'] 
                    && 1 !==$exitCode 
                ){
                    $callbackId = $plug['children'][$pid];
                    $plug['start']->restore($callbackId);
                }
                $plug->cleanPid($pid);
            }

            if ($callBack) {
                call_user_func($callBack);
            }
            // php will eat up your cpu if you don't have this
            usleep(50000);
            pcntl_signal_dispatch();
        }
    }
}

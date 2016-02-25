<?php
namespace PMVC\PlugIn\supervisor;
class Monitor 
{
    public function __construct(callable $callBack = null)
    {
        $plug = \PMVC\plug(PLUGIN);
        while( empty($plug[IS_STOP_ALL]) || count($plug[CHILDREN]) ){
            if (!empty($plug[MY_PARENT])) {
                break;
            }
            // Check for exited children
            $pid = pcntl_wait($status, WNOHANG);
            if(isset($plug[CHILDREN][$pid])){
                $exitCode = pcntl_wexitstatus($status);
                $plug->log(
                    "Child $pid was stopped with exit code of $exitCode"
                );
                if( !$plug[IS_STOP_ALL] 
                    && 1 !== $exitCode 
                ){
                    $callbackId = $plug[CHILDREN][$pid];
                    $plug['start']->restore($callbackId);
                }
                $plug->cleanPid($pid);
            }
            pcntl_signal_dispatch();
            if (empty($plug[CHILDREN])) {
                $plug[IS_STOP_ALL] = true;
                break;
            }
            if ($callBack && empty($plug[IS_STOP_ALL])) {
                call_user_func($callBack);
            }
            // php will eat up your cpu if you don't have this
            usleep(50000);
            pcntl_signal_dispatch();
        }
    }
}

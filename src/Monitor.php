<?php
namespace PMVC\PlugIn\supervisor;
class Monitor 
{
    public function __construct(callable $callBack = null)
    {
        $plug = \PMVC\plug(PLUGIN);
        trigger_error($plug->log('Monitor starting.'));
        while( empty($plug[IS_STOP_ALL]) || count($plug[CHILDREN]) ){
            if (!empty($plug[MY_PARENT])) {
                break;
            }
            // Check for exited children
            $pid = pcntl_wait($status, WNOHANG | WUNTRACED);
            $callbackId = false;
            if(isset($plug[CHILDREN][$pid])){
                $callbackId = $plug[CHILDREN][$pid];
                $exitCode = pcntl_wexitstatus($status);
                trigger_error($plug->log(
                    'Child '. $pid. ' was stopped with exit code of ['. $exitCode. ']'
                ));
                if( !$plug[IS_STOP_ALL] 
                    && 1 !== $exitCode 
                ){
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
                $callBack($callbackId, $pid);
            }
            // php will eat up your cpu if you don't have this
            usleep(50000);
            pcntl_signal_dispatch();
        }
        if (is_callable($plug[PARENT_SHUTDOWN])) {
            $plug[PARENT_SHUTDOWN]();
        }
        trigger_error($plug->log('Monitor was exited'));
    }
}

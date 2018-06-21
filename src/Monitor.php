<?php
namespace PMVC\PlugIn\supervisor;
class Monitor 
{
    public function __construct(callable $callBack = null)
    {
        $plug = \PMVC\plug(PLUGIN);
        \PMVC\dev(function() use ($plug) {
            return $plug->log('Monitor starting.');
        }, 'debug');
        while( empty($plug[IS_STOP_ALL]) || count($plug[CHILDREN]) ){
            pcntl_signal_dispatch();
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
            // Check for exited children
            $callbackId = false;
            if(isset($plug[CHILDREN][$pid])){
                $callbackId = $plug[CHILDREN][$pid];
                $exitCode = pcntl_wexitstatus($status);
                \PMVC\dev(function() use ($plug, $pid, $exitCode) {
                    return $plug->log(
                        'Child '. $pid. ' was stopped with exit code of ['. $exitCode. ']'
                    );
                }, 'debug');
                if( !$plug[IS_STOP_ALL] 
                    && 1 !== $exitCode 
                ){
                    $plug['start']->restore($callbackId);
                }
                $plug->cleanPid($pid);
            }
            if (!count($plug[CHILDREN])) {
                $plug[IS_STOP_ALL] = true;
                break;
            }
            if ($callBack && empty($plug[IS_STOP_ALL])) {
                $callBack($callbackId, $pid);
            }
            // php will eat up your cpu if you don't have this
            usleep(50000);
        }

        // Call shutdown for normal stop
        $plug->shutdown();

        \PMVC\dev(function() use ($plug) {
            return $plug->log('Monitor was exited.');
        }, 'debug');
    }
}

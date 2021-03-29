<?php
namespace PMVC\PlugIn\supervisor;
class Monitor 
{
    public function __construct(callable $monitorCallback = null)
    {
        $plug = \PMVC\plug(PLUGIN);
        \PMVC\dev(function() use ($plug) {
            return $plug->log('Monitor starting.');
        }, 'debug');
        while( empty($plug[IS_STOP_ALL]) || count($plug[CHILDREN]) ){
            pcntl_signal_dispatch();
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
            // Check for exited children
            $parallel = false;
            if(isset($plug[CHILDREN][$pid])){
                $parallel = $plug[CHILDREN][$pid];
                $exitCode = pcntl_wexitstatus($status);
                $plug->cleanPid($pid, $exitCode);

                if( !$plug[IS_STOP_ALL] 
                    && 1 !== $exitCode 
                    && TYPE_DAEMON === $parallel[TYPE]
                ){
                   // need restore after cleanPid
                   $parallel->restart();
                }
            }
            if (!count($plug[CHILDREN])) {
                $plug[IS_STOP_ALL] = true;
                break;
            }
            if (is_callable($monitorCallback) && empty($plug[IS_STOP_ALL])) {
                $monitorCallback($parallel, $pid);
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

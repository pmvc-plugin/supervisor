<?php

namespace PMVC\PlugIn\supervisor;

class Timeout extends Parallel
{
    public function __construct($pid, $props)
    {
        parent::__construct(
            function () use ($props, $pid) {
                $plug = \PMVC\plug(PLUGIN);
                \PMVC\dev(function () use ($pid, $plug) {
                    return $plug->log('Timeout runing...' . $pid);
                }, 'debug');
                $timeout = $props[TIMEOUT];
                $timeoutFunction = \pmvc\get(
                    $props,
                    TIMEOUT_FUNCTION,
                    function () {
                        return 'sleep';
                    }
                );
                call_user_func($timeoutFunction, $timeout);
                posix_kill($pid, SIGKILL);
                \PMVC\dev(function () use ($pid, $plug) {
                    return $plug->log('Kill by timeout...' . $pid);
                }, 'debug');
                pcntl_signal_dispatch();
            },
            [
                SIGNAL => SIGKILL,  
                TYPE => TYPE_SCRIPT,
            ]
        );
    }
}

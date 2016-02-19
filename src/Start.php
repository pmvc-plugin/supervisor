<?php
namespace PMVC\PlugIn\supervisor;
class Start
{
    public function __invoke($callbackId)
    {
        $plug = \PMVC\plug(PLUGIN);
        $pid = pcntl_fork();
        switch ($pid) {
            case 0: //fork
                $plug[MY_PARENT] = $plug[PID];
                $plug[PID] = posix_setsid();
                $callBack = $plug[CALLBACKS][$callbackId];
                if (TYPE_DAEMON === $callBack[TYPE]) { 
                    while (!$plug[IS_STOP_ME]) {
                        call_user_func_array(
                            $callBack[CALLBACK],
                            $callBack[ARGS]
                        );
                        call_user_func(
                            $callBack[DELAY_FUNCTION],
                            $callBack[DELAY]
                        );
                        pcntl_signal_dispatch();
                    }
                } else {
                    call_user_func_array(
                        $callBack[CALLBACK],
                        $callBack[ARGS]
                    );
                }
                exit(0);
                break;
            case -1: // for fail
                $plug->log("Failed to fork");
                $plug[IS_STOP_ALL] = true;
                break;
            default: // parent process
                $now = microtime(true) * 1000;
                $plug->pid($pid, $callbackId);
                $plug->updateCall($callbackId, array(
                    PID => $pid,
                    START_TIME => $now
                ));
                $plug->log("Child forked with pid $pid");
                break;
        }
    }

    public function restore($callbackId)
    {
        $plug = \PMVC\plug(PLUGIN);
        if (TYPE_DAEMON === $plug[CALLBACKS][$callbackId][TYPE]) {
            $this->__invoke($callbackId);
        }
    }

    public function restart()
    {
        $plug = \PMVC\plug(PLUGIN);
        $plug->log('Restarting children');
        foreach($plug[CHILDREN] as $pid => $callbackId){
            if (TYPE_DAEMON !== $plug[CALLBACKS][$callbackId][TYPE]) {
                continue;
            }
            $plug->log('Stopping child '.$pid);
            $plug['stop']->termOne($pid);
        }
    }
}

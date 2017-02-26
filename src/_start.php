<?php

namespace PMVC\PlugIn\supervisor;

use UnexpectedValueException;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\Start';

class Start
{
    public function __invoke($callbackId)
    {
        $plug = $this->caller;
        $pid = pcntl_fork();
        switch ($pid) {
            case 0: //fork
                $plug[MY_PARENT] = $plug[PID];
                $plug[PID] = posix_setsid();
                $callBack = $plug[CALLBACKS][$callbackId];
                pcntl_signal_dispatch();
                if (TYPE_DAEMON === $callBack[TYPE]) { 
                    trigger_error($plug->log('Start as daemon'));
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
                    exit(1);
                } else {
                    trigger_error($plug->log('Start as script'));
                    call_user_func_array(
                        $callBack[CALLBACK],
                        $callBack[ARGS]
                    );
                    pcntl_signal_dispatch();
                    exit(1);
                }
            case -1: // for fail
                throw new UnexpectedValueException(
                    $plug->log('Fork fail.')
                );
            default: // parent process
                $now = microtime(true) * 1000;
                $plug->pid($pid, $callbackId);
                $plug->updateCallback($callbackId, [ 
                    PID => $pid,
                    START_TIME => $now
                ]);
                return trigger_error($plug->log('Child forked with pid '.$pid));
        }
    }

    public function restore($callbackId)
    {
        $plug = \PMVC\plug(PLUGIN);
        if (!empty($plug[MY_PARENT])) {
            exit;
        } 
        if (TYPE_DAEMON === $plug[CALLBACKS][$callbackId][TYPE]) {
            trigger_error($plug->log('Restore Deamon...'.$callbackId));
            $this($callbackId);
            sleep(3);
        }
    }

    public function restart()
    {
        $plug = \PMVC\plug(PLUGIN);
        trigger_error($plug->log('Restarting children'));
        foreach($plug[CHILDREN] as $pid => $callbackId){
            if (TYPE_DAEMON !== $plug[CALLBACKS][$callbackId][TYPE]) {
                continue;
            }
            $plug['stop']->termOne($pid);
        }
    }
}

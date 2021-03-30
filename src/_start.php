<?php

namespace PMVC\PlugIn\supervisor;

use UnexpectedValueException;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__ . '\Start';

class Start
{
    public function __invoke($parallel)
    {
        $plug = $this->caller;
        $pid = pcntl_fork();
        switch ($pid) {
            case 0: //fork
                $plug[MY_PARENT] = $plug[PID];
                $plug[MY_PARALLEL] = $parallel;
                $parallel->setPid(posix_setsid());
                $plug[PID] = $parallel->getPid();
                pcntl_signal_dispatch();
                if (TYPE_DAEMON === $parallel[TYPE]) {
                    \PMVC\dev(function () use ($plug) {
                        return $plug->log('Start as daemon');
                    }, 'debug');
                    while (!$plug[IS_STOP_ME]) {
                        $parallel->call();
                        // do sleep
                        call_user_func(
                            $parallel[INTERVAL_FUNCTION],
                            $parallel[INTERVAL]
                        );
                    }
                } else {
                    \PMVC\dev(function () use ($plug) {
                        return $plug->log('Start as script');
                    }, 'debug');
                    $parallel->call();
                }
                if ($plug[IS_STOP_ME]) {
                  exit(0); // simulate cancel exit code
                } else {
                  exit(1);
                }
            case -1: // for fail
                throw new UnexpectedValueException($plug->log('Fork fail.'));
            default:
                // parent process
                $plug->pid($pid, $parallel);
                \PMVC\dev(function () use ($plug, $pid, $parallel) {
                    return $plug->log(
                        'Child forked with id ' .
                            $parallel->getId() .
                            ' and pid ' .
                            $pid
                    );
                }, 'debug');
                return $pid;
        }
    }
}

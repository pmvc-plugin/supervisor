<?php
namespace PMVC\PlugIn\supervisor;
class Start
{
    public function __invoke($callbackId)
    {
        $pid = pcntl_fork();
        $plug = \PMVC\plug('supervisor');
        switch ($pid) {
            case 0:
                $plug = \PMVC\plug('supervisor');
                $plug['parent'] = $plug['pid'];
                $plug['pid'] = posix_setsid();
                $callBack = $plug['callbacks'][$callbackId];
                if ('daemon'===$callBack['type']) { 
                    while (!$plug['isStopMe']) {
                        call_user_func_array(
                            $callBack["callback"],
                            $callBack["args"]
                        );
                        call_user_func(
                            $callBack['sleepFunc'],
                            $callBack['delay']
                        );
                        pcntl_signal_dispatch();
                    }
                } else {
                    call_user_func_array(
                        $callBack["callback"],
                        $callBack["args"]
                    );
                }
                exit(0);
                break;
            case -1:
                $plug->log("Failed to fork");
                $plug['isStopAll'] = true;
                break;
            default:
                $now = microtime(true) * 1000;
                $plug->pid($pid, $callbackId);
                $plug->updateCall($callbackId, array(
                    "pid" => $pid,
                    "startTime" => $now
                ));
                $plug->log("Child forked with pid $pid");
                break;
        }
    }

    public function restore($callbackId)
    {
        $plug = \PMVC\plug('supervisor');
        if ('daemon'===$plug['callbacks'][$callbackId]['type']) {
            $this->__invoke($callbackId);
        }
    }

    public function restart()
    {
        //Todo
        $plug = \PMVC\plug('supervisor');
        $plug->log("Restarting children");
    }
}

<?php
namespace PMVC\PlugIn\supervisor;
class Signal 
{
    public function __construct()
    {
        $arr = [SIGHUP, SIGINT, SIGTERM];
        foreach ($arr as $sign) {
            pcntl_signal($sign, $this);
        }
    }

    /**
     * Handles signals.
     * SIGHUP: kill -HUP pid
     * SIGINT: ctrl + c 
     * SIGTERM: kill pid
     */
    public function __invoke($signo)
    {
        $plug = \PMVC\plug('supervisor');
        $name = [ 
            SIGHUP  => 'SIGHUP',
            SIGINT  => 'SIGINT',
            SIGTERM => 'SIGTERM',
        ];
        trigger_error($plug->log('Recieve '.$name[$signo]));
        if (empty($plug[MY_PARENT])) {
            return $this->_handleParent($signo);
        } else {
            return $this->_handleChild($signo);
        }
    }

    private function _handleParent($signo)
    {
        static $term_count = 0;
        $plug = \PMVC\plug('supervisor');
        switch ($signo) {
            case SIGINT:
            case SIGTERM:
                trigger_error($plug->log('Ask start to shutting down...'));
                $term_count++;
                if ($term_count < 5) {
                    $plug->stop($signo);
                } else {
                    $plug->forceStop();
                }
                break;
            case SIGHUP:
                $plug['start']->restart();
                break;
        }
    }

    private function _handleChild($signo)
    {
        $plug = \PMVC\plug('supervisor');
        $plug[IS_STOP_ME] = true;
        if (is_callable($plug[CHILD_SHUTDOWN])) {
            $plug[CHILD_SHUTDOWN]($signo);
        }
    }
}

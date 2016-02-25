<?php
namespace PMVC\PlugIn\supervisor;
class Signal 
{
    public function __construct()
    {
        $arr = array(SIGTERM, SIGINT, SIGHUP);
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
        $name = array (
            SIGINT  => 'SIGINT',
            SIGTERM => 'SIGTERM',
            SIGHUP  => 'SIGHUP'
        );
        $plug->log('Recieve '.$name[$signo]);
        if (!empty($plug['parent'])) {
            $plug['isStopMe'] = true;
            return;
        }
        static $term_count = 0;
        switch ($signo) {
            case SIGINT:
            case SIGTERM:
                $plug->log('Ask start to shutting down...');
                $term_count++;
                if ($term_count < 5) {
                    $plug->stop($signo);
                } else {
                    $plug->stop(SIGKILL);
                }
                break;
            case SIGHUP:
                $plug['start']->restart();
                break;
        }
    }
}

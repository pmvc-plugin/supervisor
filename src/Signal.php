<?php
namespace PMVC\PlugIn\supervisor;


class Signal 
{
    public function __construct()
    {
        $arr = array(SIGTERM, SIGINT, SIGHUP);
        $func = array($this, 'signal');
        foreach ($arr as $sign) {
            pcntl_signal($sign, $func);
        }
    }

    /**
     * Handles signals.
     */
    function signal($signo)
    {
        $plug = \PMVC\plug('supervisor');
        if (!empty($plug['parent'])) {
            $plug['isStopAll'] = true;
            return;
        }
        static $term_count = 0;
        switch ($signo) {
            case SIGINT:
            case SIGTERM:
                $plug->log("Shutting down...");
                $term_count++;
                if ($term_count < 5) {
                    $plug->stop($signo);
                } else {
                    $plug->stop(SIGKILL);
                }
                break;
            case SIGHUP:
                $plug->restart();
                break;
        }
    }
}

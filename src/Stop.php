<?php
namespace PMVC\PlugIn\supervisor;
class Stop
{
    public function __invoke($signal = SIGTERM)
    {
        $plug = \PMVC\plug(PLUGIN);
        if (SIGKILL===$signal) {
            $plug[IS_STOP_ALL] = true;
            return $this->termAll($signal);
        }
        if(empty($plug[IS_STOP_ALL])){
            $plug[IS_STOP_ALL] = true;
            $plug->log("Stopping children");
            $this->termAll($signal);
        }
    }

    public function termAll($signal = SIGTERM)
    {
        $plug = \PMVC\plug(PLUGIN);
        foreach($plug[CHILDREN] as $pid => $child){
            $plug->log("Stopping child $pid");
            $this->termOne($pid, $signal);
        }
    }

    public function termOne($pid, $signal = SIGTERM)
    {
        $plug = \PMVC\plug(PLUGIN);
        if(isset($plug[CHILDREN][$pid])){
            $result = posix_kill($pid, $signal);
        }
    }
}

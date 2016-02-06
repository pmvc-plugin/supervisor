<?php
namespace PMVC\PlugIn\supervisor;
class Stop
{
    public function __invoke($signal = SIGTERM)
    {
        $plug = \PMVC\plug('supervisor');
        if (SIGKILL===$signal) {
            $plug['isStopAll'] = true;
            return $this->termAll($signal);
        }
        if(empty($plug['isStopAll'])){
            $plug['isStopAll'] = true;
            $plug->log("Stopping children");
            $this->termAll($signal);
        }
    }

    public function termAll($signal = SIGTERM)
    {
        $plug = \PMVC\plug('supervisor');
        foreach($plug['children'] as $pid => $child){
            $plug->log("Stopping child $pid");
            $this->termOne($pid, $signal);
        }
    }

    public function termOne($pid, $signal = SIGTERM)
    {
        $plug = \PMVC\plug('supervisor');
        if(isset($plug['children'][$pid])){
            $result = posix_kill($pid, $signal);
        }
    }
}

<?php
namespace PMVC\PlugIn\supervisor;
${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\Stop';
class Stop
{
    public function __invoke($signal = SIGTERM)
    {
        if (SIGKILL===$signal) {
            $this->caller[IS_STOP_ALL] = true;
            trigger_error($this->caller->log('Ask force stopping children'));
            $this->termAll($signal);
        }
        if(empty($this->caller[IS_STOP_ALL])){
            $this->caller[IS_STOP_ALL] = true;
            trigger_error($this->caller->log('Ask stopping children'));
            $this->termAll($signal);
        }
    }

    public function termAll($signal = SIGTERM)
    {
        foreach($this->caller[CHILDREN] as $pid => $child){
            $this->termOne($pid, $signal);
        }
    }

    public function termOne($pid, $signal = SIGTERM)
    {
        if(isset($this->caller[CHILDREN][$pid])){
            trigger_error($this->caller->log('Stopping child '.$pid));
            $result = posix_kill($pid, $signal);
            pcntl_signal_dispatch();
        }
    }
}

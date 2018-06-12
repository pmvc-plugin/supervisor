<?php
namespace PMVC\PlugIn\supervisor;
${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\Stop';
class Stop
{
    public function __invoke($signal = SIGTERM)
    {
        if (SIGKILL===$signal) {
            $this->caller[IS_STOP_ALL] = true;
            \PMVC\dev(function(){
                return $this->caller->log('Ask force stopping children');
            },'debug');
            $this->termAll($signal);
        }
        if(empty($this->caller[IS_STOP_ALL])){
            $this->caller[IS_STOP_ALL] = true;
            \PMVC\dev(function(){
                return $this->caller->log('Ask stopping children');
            },'debug');
            $this->termAll($signal);
        }
    }

    public function termAll($signal = SIGTERM)
    {
        foreach($this->caller[CHILDREN] as $pid => $child){
            $this->termOne($pid, $signal);
        }
        $this->caller->shutdown();
    }

    public function termOne($pid, $signal = SIGTERM)
    {
        if(isset($this->caller[CHILDREN][$pid])){
            \PMVC\dev(function() use ($pid){
                return $this->caller->log('Process stopping child '.$pid);
            },'debug');
            $result = posix_kill($pid, $signal);
            pcntl_signal_dispatch();
        }
    }
}

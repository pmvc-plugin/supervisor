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
            $this->_termAll($signal);
        } elseif (empty($this->caller[IS_STOP_ALL])){
            $this->caller[IS_STOP_ALL] = true;
            \PMVC\dev(function(){
                return $this->caller->log('Ask stopping children');
            },'debug');
            $this->_termAll($signal);
        }
    }

    private function _termAll($signal = SIGTERM)
    {
        foreach($this->caller[CHILDREN] as $pid => $parallel){
            $parallel->stop($signal);
        }

        // Call shutdown for force stop
        $this->caller->shutdown();
    }

}

<?php
namespace PMVC\PlugIn\supervisor;
${_INIT_CONFIG}[_CLASS] = __NAMESPACE__ . '\Stop';
class Stop
{
    public function __invoke($signal = SIGTERM)
    {
        if ($this->caller[MY_PARENT]) {
            $this->caller->kill(); 
            $this->caller->shutdownChildProcess(); 
            return;
        } elseif (SIGKILL === $signal) {
            $this->caller[IS_STOP_ALL] = true;
            \PMVC\dev(function () {
                return $this->caller->log('Ask force stopping children');
            }, DEBUG);
            $this->_termAll($signal);
        } elseif (empty($this->caller[IS_STOP_ALL])) {
            $this->caller[IS_STOP_ALL] = true;
            \PMVC\dev(function () {
                return $this->caller->log('Ask stopping children');
            }, DEBUG);
            $this->_termAll($signal);
        }
    }

    private function _termAll($signal = SIGTERM)
    {
        if (!empty($this->caller[CHILDREN])) {
            foreach ($this->caller[CHILDREN] as $pid => $parallel) {
                \PMVC\dev(function () use ($parallel) {
                    return $this->caller->log(
                        'Child Terminated by stop function [id: ' .
                            $parallel->getId() .
                            '][pid: ' .
                            $parallel->getPid() .
                            ']'
                    );
                }, DEBUG);
                $parallel->stop($signal);
            }
        }

        // Call shutdown for force stop
        $this->caller->shutdown();
    }
}

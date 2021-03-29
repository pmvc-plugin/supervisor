<?php

namespace PMVC\PlugIn\supervisor;

use PMVC\HashMap;

class Parallel extends HashMap
{
    private $_isStarted;
    private $_isRunning;
    private $_isTerminated;
    private $_id;
    private $_pid;

    public function __construct(callable $func, $props)
    {
        parent::__construct($props);
        if (!empty($props[NAME])) {
            $this->_id = $props[NAME];
        } else {
            $this->_id = spl_object_hash($this);
        }
        $this[CALLBACK] = $func;
        $plug = \PMVC\plug(PLUGIN);
        $plug[PARALLELS][$this->_id] = $this;
    }

    public function isStarted()
    {
        return $this->_isStarted;
    }

    public function isTerminated()
    {
        return $this->_isTerminated;
    }

    public function isRunning()
    {
        return $this->_isRunning;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getPid()
    {
        return $this->_pid;
    }

    public function setPid($pid)
    {
        $this->_pid = $pid;
        return $this->_pid;
    }

    private function _time()
    {
        return microtime(true) * 1000;
    }

    public function setRunning($pid)
    {
        $this->setPid($pid);
        $this->_isRunning = $this->_time();
    }

    private function _run()
    {
        $plug = \PMVC\plug(PLUGIN);
        $isSuccess = $plug->start($this);
        if ($isSuccess) {
            $this->setRunning($isSuccess);
        } else {
            $this->_isTerminated = $this->_time();
        }
        return $isSuccess;
    }

    public function call()
    {
        call_user_func_array($this[CALLBACK], $this[ARGS]);
        pcntl_signal_dispatch();
    }

    public function start()
    {
        if ($this->_isStarted) {
            return !!trigger_error(
                'Process [' .
                    $this->_id .
                    ']' .
                    'already starting change to use restart...',
                E_USER_WARNING
            );
        }
        $this->_isStarted = $this->_time();
        return $this->_run();
    }

    public function restart()
    {
        \PMVC\dev(function () {
            $plug = \PMVC\plug(PLUGIN);
            return $plug->log('Restore...' . $this->_id);
        }, 'debug');
        $this->stop();
        $this->_run();
    }

    public function finish()
    {
        $this->_isTerminated = $this->_time();
        pcntl_signal_dispatch();
        \PMVC\dev(function () {
            $plug = \PMVC\plug(PLUGIN);
            return $plug->log('Child Terminated [id: ' . $this->_id. '][pid: '.$this->_pid.']');
        }, 'debug');
    }

    public function stop($signal = SIGTERM)
    {
        $result = posix_kill($this->_pid, $signal);
        if ($result) {
            $this->finish();
        }
    }
}

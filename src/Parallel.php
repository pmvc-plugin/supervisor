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
    private $_exitCode;
    private $_timeout;

    public function __construct(callable $func, $props)
    {
        parent::__construct($props);
        if (!empty($props[NAME])) {
            $this->_id = $props[NAME];
        } else {
            $this->_id = md5(spl_object_hash($this));
        }
        $this[CALLBACK] = $func;
        if ($this[TYPE] === TYPE_DAEMON) {
            if (empty($this[INTERVAL])) {
                $this[INTERVAL] = 1;
            }
            if (empty($this[INTERVAL_FUNCTION])) {
                $this[INTERVAL_FUNCTION] = 'sleep';
            }
        }
        if (!isset($this[ARGS])) {
            $this[ARGS] = [];
        }
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

    public function getExitCode()
    {
        return $this->_exitCode;
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
        if ($this[TIMEOUT]) {
            $this->clearTimeout();
            $this->_timeout = new Timeout($this->getPid(), [
                TIMEOUT => $this[TIMEOUT],
                TIMEOUT_FUNCTION => $this[TIMEOUT_FUNCTION],
            ]);
            $this->_timeout->start();
        }
        $result = call_user_func_array($this[CALLBACK], $this[ARGS]);
        $this->clearTimeout();
        pcntl_signal_dispatch();
        if (is_callable($this[ON_FINISH])) {
            call_user_func($this[ON_FINISH], $this, $result);
        }
    }

    public function start()
    {
        $plug = \PMVC\plug(PLUGIN);
        if (!$plug[MONITOR]) {
            return $plug->process();
        }
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

    public function clearTimeout()
    {
        if (!empty($this->_timeout)) {
            $this->_timeout->stop();
            $this->_timeout = null;
            \PMVC\dev(function () {
                $plug = \PMVC\plug(PLUGIN);
                return $plug->log('Clear Timeout [' . $this->_pid. ']');
            }, 'debug');
        }
    }

    public function finish($exitCode = null)
    {
        $plug = \PMVC\plug(PLUGIN);
        $this->clearTimeout();
        $this->_isTerminated = $this->_time();
        if (!is_null($exitCode)) {
            $this->_exitCode = $exitCode;
            if (is_callable($this[ON_EXIT])) {
                call_user_func($this[ON_EXIT], $this, $this->_exitCode);
            }
        }
        pcntl_signal_dispatch();
        \PMVC\dev(function () use($plug){
            $payload = [
                '[ID: ' . $this->_id . ']',
                '[PID: ' . $this->_pid . ']',
            ];
            if (isset($this->_exitCode)) {
                $payload[] = '[Exit code: ' . $this->_exitCode . ']';
            }
            return $plug->log('Child Handle Finish ' . join('', $payload));
        }, 'debug');
    }

    public function stop($signal = null)
    {
        if (is_null($signal)) {
            $signal = $this[SIGNAL] ? $this[SIGNAL] : SIGTERM;
        }
        $result = posix_kill($this->_pid, $signal);
        if ($result) {
            $this->finish();
        }
    }
}

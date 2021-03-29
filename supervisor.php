<?php

namespace PMVC\PlugIn\supervisor;

use BadMethodCallException;
use UnexpectedValueException;
use LogicException;

\PMVC\l(__DIR__ . '/src/Parallel');

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__ . '\supervisor';

// storage
const PARALLELS = 'parallels';
const CHILDREN = 'children';
const MY_PARENT = 'parent';
const IS_STOP_ALL = 'isStopAll';
const IS_STOP_ME = 'isStopMe';
const PID = 'pid';
const PID_FILE = 'pidFile';
const START_TIME = 'startTime';
const LOG_NUM = 'log';

// parent and child
const TYPE = 'type';
const NAME = 'name';
const TYPE_SCRIPT = 'script';
const TYPE_DAEMON = 'daemon';

// child
const CALLBACK = 'callback';
const TRIGGER = 'trigger';
const QUEUE = 'queue';
const ARGS = 'args';
const INTERVAL = 'interval';
const INTERVAL_FUNCTION = 'intervalFunction';
const PLUGIN = 'supervisor';

// shutdown
const CHILD_SHUTDOWN = 'childShutdown';
const PARENT_SHUTDOWN = 'parentShutdown';
const PARENT_DAEMON_SHUTDOWN = 'parentDaemonShutdown';

class supervisor extends \PMVC\PlugIn
{
    private $_isShutdown = false;
    public function __construct()
    {
        $this[PARALLELS] = [];
        $this[CHILDREN] = [];
        $this[QUEUE] = [];
        $this[MY_PARENT] = null;
        $this[IS_STOP_ALL] = false;
        $this[IS_STOP_ME] = false;
        $this[PID] = getmypid();
        $this[LOG_NUM] = 0;
    }

    public function init()
    {
        \PMVC\l(__DIR__ . '/src/Signal');
        new Signal(); // call it in init to avoid infinity
    }

    private function _runParentAsDaemon()
    {
        if (empty($this[PID_FILE])) {
            return new BadMethodCallException('PID file is not defined');
        }
        $pid = pcntl_fork();
        switch ($pid) {
            case 0:
                $this[PID] = getmypid();
                $this->_createPidFile();
                break;
            case -1: // for fail
                return new UnexpectedValueException($this->log('Fork fail.'));
                break;
            default:
                exit(0);
        }
    }

    public function process(callable $monitorCallBack = null)
    {
        if (empty($this[MY_PARENT])) {
            if (TYPE_DAEMON === $this[TYPE]) {
                $this->_runParentAsDaemon();
            } else {
                unset($this[PARENT_DAEMON_SHUTDOWN]);
                if (!empty($this[PID_FILE])) {
                    $this->_createPidFile();
                }
            }
            foreach ($this[PARALLELS] as $parallelId => $parallel) {
                $trigger = \PMVC\get($parallel, TRIGGER);
                if (strlen($trigger)) {
                    if (!isset($this[QUEUE][$trigger])) {
                        $this[QUEUE][$trigger] = [];
                    }
                    $this[QUEUE][$trigger][] = $parallel;
                } else {
                    $parallel->start();
                }
            }
            \PMVC\l(__DIR__ . '/src/Monitor');
            new Monitor($monitorCallBack);
        }
    }

    public function script(
        callable $callback,
        array $args = [],
        $trigger = null
    ) {
        $parallel = new Parallel($callback, [
            ARGS => $args,
            TYPE => TYPE_SCRIPT,
            TRIGGER => $trigger,
        ]);
        return $parallel->getId();
    }

    public function daemon(
        callable $callback,
        array $args = [],
        $trigger = null,
        $interval = 1,
        $intervalFunction = 'sleep'
    ) {
        $parallel = new Parallel($callback, [
            ARGS => $args,
            TYPE => TYPE_DAEMON,
            TRIGGER => $trigger,
            INTERVAL => $interval,
            INTERVAL_FUNCTION => $intervalFunction,
        ]);
        return $parallel->getId();
    }

    public function forceStop()
    {
        $this->stop(SIGKILL);
    }

    private function _createPidFile()
    {
        $file = \PMVC\realpath($this[PID_FILE]);
        if ($file) {
            throw new LogicException(
                'PID file already exists, can not create. [' . $file . ']'
            );
        }
        file_put_contents($this[PID_FILE], $this[PID]);
    }

    public function shutdown()
    {
        if ($this->_isShutdown) {
            \PMVC\dev(function () {
                return $this->log('Shutdown already running, skip.');
            }, 'debug');
            return;
        }
        $this->_isShutdown = true;
        if (is_callable($this[PARENT_SHUTDOWN])) {
            $this[PARENT_SHUTDOWN]();
        }
        // need avoid cache don't use \PMVC\realpath
        $file = realpath($this[PID_FILE]);
        if (is_file($file)) {
            \PMVC\dev(function () use ($file) {
                return $this->log('Delete pid file. [' . $file . ']');
            }, 'debug');
            $pid = trim(file_get_contents($file));
            if ((int) $pid === $this[PID]) {
                if (is_callable($this[PARENT_DAEMON_SHUTDOWN])) {
                    $this[PARENT_DAEMON_SHUTDOWN]();
                }
                unlink($file);
            }
        }
    }

    public function kill($signo = SIGTERM)
    {
        $file = \PMVC\realpath($this[PID_FILE]);
        if (!$file) {
            throw new BadMethodCallException(
                'PID file is not found. [' .
                    $file .
                    '], Supervisor is not running.'
            );
        }
        $pid = trim(file_get_contents($file));
        if ($pid) {
            $result = $this->killPid($pid, $signo);
        } else {
            throw new LogicException('Get PId failed');
        }
    }

    public function killPid($pid, $signo = SIGTERM)
    {
        if ((int) $pid === $this[PID]) {
            throw new LogicException(
                'Can\'t use kill or killPid function kill self.'
            );
        }
        $result = posix_kill($pid, $signo);
        if ($result) {
            return $result;
        } else {
            throw new LogicException('Kill process failed');
        }
    }

    public function pid($pid, $parallel)
    {
        $this[CHILDREN][$pid] = $parallel;
    }

    public function cleanPid($pid)
    {
        $key = $this[CHILDREN][$pid]->getId();
        if (isset($this[QUEUE][$key])) {
            foreach ($this[QUEUE][$key] as $nextParallel) {
                \PMVC\dev(function () use ($nextParallel) {
                    return $this->log('Start queue: '. $key .' with [' . $nextParallel->getId(). ']');
                }, 'debug');
                $nextParallel->start();
            }
            unset($this[QUEUE][$key]);
        }
        $this[CHILDREN][$pid]->finish();
        unset($this[CHILDREN][$pid]);
    }

    public function log($log)
    {
        $isParent = empty($this['parent']) ? 'Parent' : 'Child';
        $isParent .= ' ' . $this['pid'];
        list($sec, $ms) = explode('.', number_format(microtime(true), 3));
        $message =
            $isParent .
            '-' .
            $this[LOG_NUM] .
            ' [' .
            date('Y-m-d H:i:s') .
            '.' .
            $ms .
            '] ' .
            $log;
        $this[LOG_NUM]++;
        return $message;
    }
}

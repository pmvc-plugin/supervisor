<?php

namespace PMVC\PlugIn\supervisor;

use BadMethodCallException;
use UnexpectedValueException;
use LogicException;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\supervisor';

\PMVC\l(__DIR__.'/src/Signal.php');

// storage
const CALLBACKS = 'callbacks';
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
const TYPE_SCRIPT = 'script';
const TYPE_DAEMON = 'daemon';

// child
const CALLBACK = 'callback'; 
const TRIGGER = 'trigger'; 
const QUEUE = 'queue'; 
const ARGS = 'args';
const DELAY = 'delay';
const DELAY_FUNCTION = 'delayFunction';
const PLUGIN = 'supervisor';

// shutdown
const CHILD_SHUTDOWN = 'childShutdown';
const PARENT_SHUTDOWN = 'parentShutdown';
const PARENT_INTO_DAEMON_SHUTDOWN = 'parentIntoDaemonShutdown';

class supervisor extends \PMVC\PlugIn
{
    public function __construct()
    {
        $this[CALLBACKS] = [];
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
        new Signal(); // call it in init to avoid infinity
    }

    private function _runParentAsDaemon()
    {
        if (empty($this[PID_FILE])) {
            return new BadMethodCallException(
                'PID file is not defined'
            );
        }
        $pid = pcntl_fork();
        switch ($pid) {
            case 0:
                $this[PID] = getmypid();
                $this->_createPidFile();
                break;
            case -1: // for fail
                return new UnexpectedValueException(
                    $this->log('Fork fail.')
                );
                break;
            default:
                if (is_callable($this[PARENT_INTO_DAEMON_SHUTDOWN])) {
                    $this[PARENT_INTO_DAEMON_SHUTDOWN]();
                }
                exit(0);
        }
    }

    public function process(callable $monitorCallBack = null)
    {
        if (empty($this[MY_PARENT])) {
            if (TYPE_DAEMON === $this[TYPE]) {
                $this->_runParentAsDaemon();
            } else {
                if (!empty($this[PID_FILE])) {
                    $this->_createPidFile();
                }
            }
            \PMVC\l(__DIR__.'/src/Monitor.php');
            foreach ($this[CALLBACKS] as $callbackId=>$callback) {
                $trigger = \PMVC\get($callback, TRIGGER); 
                if (strlen($trigger)) {
                    if (!isset($this[QUEUE][$trigger])) {
                        $this[QUEUE][$trigger] = [];
                    }
                    $this[QUEUE][$trigger][] = $callbackId; 
                } else {
                    $this->start($callbackId);
                }
            }
            new Monitor($monitorCallBack);
        }
    }

    public function script (
        callable $callback, 
        array $args = [],
        $trigger = null
    )
    {
        $this[CALLBACKS][] = [ 
            CALLBACK => $callback,
            ARGS     => $args,
            TYPE     => TYPE_SCRIPT,
            TRIGGER  => $trigger
        ];
        return count($this[CALLBACKS]) - 1;
    }

    public function daemon ( 
        callable $callback, 
        array $args = [],
        $delay = 1,
        $delayFunction = 'sleep'
    )
    {
        $this[CALLBACKS][] = [ 
            CALLBACK => $callback,
            ARGS => $args,
            TYPE => TYPE_DAEMON,
            DELAY => $delay,
            DELAY_FUNCTION => $delayFunction
        ];
        return count($this[CALLBACKS]) - 1;
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
                'PID file already exists, can\'t create. ['.$file.']'
            );
        }
        file_put_contents($this[PID_FILE], $this[PID]);
    }

    public function kill($signo=SIGTERM)
    {
        $file = \PMVC\realpath($this[PID_FILE]);
        if (!$file) {
            throw new BadMethodCallException(
                'PID file is not found. ['.$file.'], Supervisor is not running.'
            );
        }
        $pid = trim(file_get_contents($file));
        if ($pid) {
            $result = $this->killPid($pid, $signo); 
            if ($result) {
                \PMVC\dev(function() use ($file) {
                    return $this->log('Delete pid file. ['.$file.']');
                }, 'debug');
                unlink($file);
            }
        } else {
            throw new LogicException(
                'Get PId failed'
            );
        }
    }

    public function killPid($pid, $signo=SIGTERM)
    {
        if ((int)$pid === $this[PID]) {
            throw new LogicException(
                'Can\'t use kill or killPid function kill self.'
            );
        }
        $result = posix_kill($pid, $signo);
        if ($result) {
            return $result;
        } else {
            throw new LogicException(
                'Kill process failed'
            );
        }
    }

    public function updateCallback($callbackId, $arr)
    {
        $this[CALLBACKS][$callbackId] = $arr + $this[CALLBACKS][$callbackId];
    }

    public function pid($pid, $callbackId)
    {
        $this[CHILDREN][$pid] = $callbackId;
    }

    public function cleanPid($pid)
    {
        $key = $this[CHILDREN][$pid];
        if (isset($this[QUEUE][$key])) {
            foreach ($this[QUEUE][$key] as $next) {
                \PMVC\dev(function() use ($next) {
                    return $this->log('Start queue: '.$next);
                }, 'debug');
                $this->start($next);
            }
            unset($this[QUEUE][$key]);
        }
        unset($this[CHILDREN][$pid]);
    }

    public function log($log)
    {
        $isParent = (empty($this['parent'])) ? 'Parent' : 'Child';
        $isParent.=' '.$this['pid'];
        list($sec, $ms) = explode('.', number_format(microtime(true), 3));
        $message = $isParent.'-'.$this[LOG_NUM].' ['.date('Y-m-d H:i:s').'.'.$ms.'] '.$log;
        $this[LOG_NUM]++;
        return $message;
    }
}

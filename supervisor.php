<?php

namespace PMVC\PlugIn\supervisor;

use UnexpectedValueException;
use LogicException;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__ . '\supervisor';

\PMVC\l(__DIR__ . '/src/Parallel');
\PMVC\l(__DIR__ . '/src/Timeout');

// storage
const PARALLELS = 'parallels';
const CHILDREN = 'children';
const MONITOR = 'monitor';
const MY_PARENT = 'parent';
const MY_PARALLEL = 'myParallel';
const PID = 'pid';
const PID_FILE = 'pidFile';
const LOG_NUM = 'log';
const RESULT = 'result';
const DEBUG = 'debug';

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
const TIMEOUT = 'timeout';
const TIMEOUT_FUNCTION = 'timeoutFunction';
const PLUGIN = 'supervisor';

// shutdown
const SIGNAL = 'signal';
const ON_FINISH = 'onFinish';
const ON_EXIT = 'onExit';
const IS_STOP_ALL = 'isStopAll';
const IS_STOP_ME = 'isStopMe';
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
        if ($this[DEBUG]) {
            $this->enableDebugMode($this[DEBUG]);
        }
    }

    private function _runParentAsDaemon()
    {
        if (empty($this[PID_FILE])) {
            return new UnexpectedValueException(
                $this->log('PID file is not defined')
            );
        }
        $pid = pcntl_fork();
        switch ($pid) {
            case 0:
                $this[PID] = getmypid();
                $this->_createPidFile();
                break;
            case -1: // for fail
                return new LogicException($this->log('Fork fail.'));
                break;
            default:
                exit(0);
        }
    }

    public function process(callable $monitorCallBack = null)
    {
        if (empty($this[MY_PARENT]) && empty($this[MONITOR])) {
            $this[MONITOR] = true;
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
                if (!strlen($trigger)) {
                    $parallel->start();
                }
            }
            \PMVC\l(__DIR__ . '/src/Monitor');
            $this[MONITOR] = new Monitor($monitorCallBack);
        }
    }

    public function pushQueue($triggerId, $parallel)
    {
        if (!isset($this[QUEUE][$triggerId])) {
            $this[QUEUE][$triggerId] = [];
        }
        $this[QUEUE][$triggerId][$parallel->getId()] = $parallel;
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
        $interval = null,
        $intervalFunction = null
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
                $this->log(
                    'PID file already exists, can not create. [' . $file . ']'
                )
            );
        }
        file_put_contents($this[PID_FILE], $this[PID]);
    }

    private function _getPidFromFile($throw)
    {
        $file = $this[PID_FILE];
        if (is_file($file)) {
            $pid = trim(file_get_contents($file));
            return (int) $pid;
        } else {
            if ($throw) {
                throw new UnexpectedValueException(
                    $this->log('PID file is not found. [' . $file . ']')
                );
            }
        }
    }

    private function _getParentPid($throw)
    {
        $pid = !empty($this[MY_PARENT])
            ? $this[MY_PARENT]
            : $this->_getPidFromFile($throw);
        return $pid;
    }

    /**
     * Run from child will kill child and parent
     *
     * Run from parent will killall
     */
    public function shutdown()
    {
        if ($this->_isShutdown) {
            \PMVC\dev(function () {
                return $this->log('Shutdown already running, skip.');
            }, DEBUG);
            return;
        }
        $this->_isShutdown = true;
        if (empty($this[MY_PARENT])) {
            $this->stop();
            if (is_callable($this[PARENT_SHUTDOWN])) {
                $this[PARENT_SHUTDOWN]();
            }
            $pid = $this->_getPidFromFile(false);
            if ($pid === $this[PID]) {
                if (is_callable($this[PARENT_DAEMON_SHUTDOWN])) {
                    $this[PARENT_DAEMON_SHUTDOWN]();
                }
                unlink($this[PID_FILE]);
                \PMVC\dev(function () {
                    $file = \PMVC\realpath($this[PID_FILE]);
                    return $this->log('Delete pid file. [' . $file . ']');
                }, DEBUG);
            }
        } else {
            // run from child process
            $this->kill();
            $this->shutdownChildProcess();
        }
    }

    public function shutdownChildProcess()
    {
        if ($this[MY_PARENT] && !$this[IS_STOP_ME]) {
            $this[IS_STOP_ME] = true;
            $this[MY_PARALLEL]->finish();
        }
    }

    /**
     * can not kill itself, need call from different pid.
     */
    public function kill($signo = SIGTERM)
    {
        $pid = $this->_getParentPid(true);
        if ($pid) {
            return $this->killPid($pid, $signo);
        }
    }

    public function killPid($pid, $signo = SIGTERM)
    {
        if ((int) $pid === $this[PID]) {
            throw new LogicException(
                $this->log('Can\'t use kill or killPid function kill self.')
            );
        }
        $result = posix_kill($pid, $signo);
        if ($result) {
            pcntl_signal_dispatch();
            return $result;
        } else {
            throw new LogicException($this->log('Kill process failed'));
        }
    }

    /**
     * can not run by itself
     */
    public function execGetStatus()
    {
        $this->kill(SIGUSR2);
    }

    public function getStatus()
    {
        \PMVC\v($plug[CHILDREN]);
    }

    public function addChildren($pid, $parallel)
    {
        $this[CHILDREN][$pid] = $parallel;
    }

    public function cleanPid($pid, $exitCode)
    {
        $parallel = $this[CHILDREN][$pid];
        $key = $parallel->getId();
        if (isset($this[QUEUE][$key])) {
            foreach ($this[QUEUE][$key] as $nextParallel) {
                $nextParallel->start();
                \PMVC\dev(function () use ($nextParallel, $key) {
                    return $this->log(
                        'Start queue: ' .
                            $key .
                            ' with [' .
                            $nextParallel->getId() .
                            ']'
                    );
                }, DEBUG);
            }
            unset($this[QUEUE][$key]);
        }
        $parallel->finish($exitCode);
        unset($this[CHILDREN][$pid]);
        \PMVC\dev(function () use ($parallel) {
            return $this->log(
                'Handle Clean Id [id: ' .
                    $parallel->getId() .
                    '][pid: ' .
                    $parallel->getPid() .
                    '][exit Code: ' .
                    $parallel->getExitCode() .
                    ']'
            );
        }, DEBUG);
    }

    public function enableDebugMode($level = null)
    {
        if (is_null($level) || true === $level) {
            $level = DEBUG;
        }
        \PMVC\initPlugIn([
            DEBUG => ['output' => 'debug_cli', 'level' => $level],
            'dev' => null,
        ]);
    }

    public function log($log)
    {
        $isParent = empty($this[MY_PARENT])
            ? 'Parent ' . $this[PID]
            : 'Child ' . $this[MY_PARALLEL]->getPid();
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

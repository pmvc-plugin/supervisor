<?php
namespace PMVC\PlugIn\supervisor;
use SplFixedArray;
${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\supervisor';
\PMVC\l(__DIR__.'/src/Signal.php');

// storage
const CALLBACKS = 'callbacks';
const CHILDREN = 'children';
const MY_PARENT = 'parent';
const IS_STOP_ALL = 'isStopAll';
const IS_STOP_ME = 'isStopMe';
const PID = 'pid';
const START_TIME = 'startTime';
const MONITOR = 'monitor';
const LOG_NUM = 'log';
// child
const CALLBACK = 'callback'; 
const QUEUE = 'queue'; 
const ARGS = 'args';
const TYPE = 'type';
const TYPE_SCRIPT = 'script';
const TYPE_DAEMON = 'daemon';
const DELAY = 'delay';
const DELAY_FUNCTION = 'delayFunction';
const PLUGIN = 'supervisor';

class supervisor extends \PMVC\PlugIn
{
    private $num;
    public function __construct()
    {
        $this[CALLBACKS] = new SplFixedArray(1);
        $this[CHILDREN] = array();
        $this[MY_PARENT] = null;
        $this[IS_STOP_ALL] = false;
        $this[IS_STOP_ME] = false;
        $this[MONITOR] = true;
        $this[PID] = posix_getpid();
        $this[LOG_NUM] = 0;
        $this->num = 0;
        new Signal();
    }

    public function process(callable $callBack = null)
    {
        if (empty($this[MY_PARENT])) {
            \PMVC\l(__DIR__.'/src/Monitor.php');
            new Monitor($callBack); 
        }
    }

    public function script (
        callable $callback, 
        array $args = array(),
        $trigger = null
    )
    {
        $this[CALLBACKS][$this->num] = array(
            CALLBACK => $callback,
            ARGS => $args,
            TYPE => TYPE_SCRIPT 
        );
        return $this->_increase($trigger);
    }

    public function daemon ( 
        callable $callback, 
        array $args = array(),
        $delay = 1,
        $delayFunction = 'sleep'
    )
    {
        $this[CALLBACKS][$this->num] = array(
            CALLBACK => $callback,
            ARGS => $args,
            TYPE => TYPE_DAEMON,
            DELAY => $delay,
            DELAY_FUNCTION => $delayFunction
        );
        return $this->_increase();
    }

    public function forceStop()
    {
        $this->stop(SIGKILL);
    }

    private function _increase($trigger=null)
    {
        if (is_null($trigger) || empty($this[CALLBACKS][$trigger])) {
            $this->start($this->num);
        } else {
            if (!isset($this[QUEUE][$trigger])) {
                $this[QUEUE][$trigger] = array();
            }
            $this[QUEUE][$trigger][] = $this->num; 
        }
        $size = $this->num + 2;
        $this[CALLBACKS]->setSize($size);
        return $this->num++;
    }

    public function updateCall($callbackId, $arr)
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
                $this->log('Start queue: '.$next);
                $this->start($next);
            }
            unset($this[QUEUE][$key]);
        }
        unset($this[CHILDREN][$pid]);
    }

    /**
     * Force Start Monitor When child not empty 
     * http://stackoverflow.com/questions/230245/destruct-visibility-for-php
     */
    public function __destruct()
    {
        if (!empty($this[CHILDREN]) && $this[MONITOR]) {
            $this->process();
        }
    }

    public function log($log)
    {
        $isParent = (empty($this['parent'])) ? 'Parent' : 'Child';
        $isParent.=' '.$this['pid'];
        list($sec, $ms) = explode('.', number_format(microtime(true), 3));
        echo $isParent.'-'.$this[LOG_NUM].' ['.date('Y-m-d H:i:s').'.'.$ms.'] '.$log."\n";
        $this[LOG_NUM]++;
    }
}

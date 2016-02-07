<?php
namespace PMVC\PlugIn\supervisor;
use SplFixedArray;
${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\supervisor';

class supervisor extends \PMVC\PlugIn
{
    private $num = 0;
    public function init()
    {
        $this['callbacks'] = new SplFixedArray(1);
        $this['children'] = array();
        $this['parent'] = null;
        $this['isStopAll'] = false;
        $this['isStopMe'] = false;
        $this['pid'] = posix_getpid();
        new Signal();
        $this->start = new Start();
        $this->stop = new Stop();
    }

    public function process(callable $callBack = null)
    {
        new Monitor($callBack); 
    }

    public function script (
        callable $callback, 
        array $args = array()
    )
    {
        $this['callbacks'][$this->num] = array(
            'callback' => $callback,
            'args' => $args,
            'type' => 'script' 
        );
        $this->_increase();
    }

    public function daemon ( 
        callable $callback, 
        array $args = array()
    )
    {
        $this['callbacks'][$this->num] = array(
            'callback' => $callback,
            'args' => $args,
            'type' => 'daemon'
        );
        $this->_increase();
    }

    private function _increase()
    {
        $this->start($this->num);
        $this->num++;
        $size = $this->num + 1;
        $this['callbacks']->setSize($size);
    }

    public function updateCall($callbackId, $arr)
    {
        $this['callbacks'][$callbackId] = $arr + $this['callbacks'][$callbackId];
    }

    public function pid($pid, $callbackId)
    {
        $this['children'][$pid] = $callbackId;
    }

    public function cleanPid($pid)
    {
        unset($this['children'][$pid]);
    }

    public function log($log)
    {
        list($sec, $ms) = explode('.', number_format(microtime(true), 3));
        echo '['.date('Y-m-d H:i:s').'.'.$ms.'] '.$log."\n";
    }
}

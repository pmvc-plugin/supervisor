<?php

namespace PMVC\PlugIn\supervisor;

class Signal
{
    /**
     * Handles signals.
     * SIGHUP: kill -HUP pid
     * SIGINT: ctrl + c
     * SIGTERM: kill pid
     */
    private $_signals = [
        SIGHUP => 'SIGHUP',
        SIGINT => 'SIGINT',
        SIGTERM => 'SIGTERM',
        SIGUSR2 => 'SIGUSR2',
    ];

    public function __construct()
    {
        $arr = array_keys($this->_signals);
        foreach ($arr as $sign) {
            \pcntl_signal($sign, $this);
        }
    }

    public function __invoke($signo)
    {
        $plug = \PMVC\plug('supervisor');
        \PMVC\dev(function () use ($plug, $signo) {
            return $plug->log('Recieve ' . $this->_signals[$signo]);
        }, DEBUG);
        if (empty($plug[MY_PARENT])) {
            return $this->_handleParent($signo);
        } else {
            return $this->_handleChild($signo);
        }
    }

    private function _handleParent($signo)
    {
        static $term_count = 0;
        $plug = \PMVC\plug('supervisor');
        switch ($signo) {
            case SIGUSR2:
                $plug->getStatus();
                break;
            case SIGHUP:
                $this->restartAll();
                break;
            case SIGINT:
            case SIGTERM:
            default:
                \PMVC\dev(function () use ($plug) {
                    return $plug->log('Ask start to shutting down...');
                }, DEBUG);
                $term_count++;
                if ($term_count < 5) {
                    $plug->stop($signo);
                } else {
                    $plug->forceStop();
                }
                break;
        }
    }

    private function _handleChild($signo)
    {
        $plug = \PMVC\plug('supervisor');
        switch ($signo) {
            case SIGUSR2:
                break;
            default:
                $plug->shutdownChildProcess();
                break;
        }
    }

    private function restartAll()
    {
        $plug = \PMVC\plug(PLUGIN);
        \PMVC\dev(function () use ($plug) {
            return $plug->log('Restarting children');
        }, DEBUG);
        foreach ($plug[CHILDREN] as $pid => $parallel) {
            if (TYPE_DAEMON !== $parallel[TYPE]) {
                continue;
            }
            $parallel->restart();
        }
    }
}

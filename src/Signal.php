<?php

namespace PMVC\PlugIn\supervisor;

class Signal
{
    public function __construct()
    {
        $arr = [SIGHUP, SIGINT, SIGTERM];
        foreach ($arr as $sign) {
            \pcntl_signal($sign, $this);
        }
    }

    /**
     * Handles signals.
     * SIGHUP: kill -HUP pid
     * SIGINT: ctrl + c
     * SIGTERM: kill pid
     */
    public function __invoke($signo)
    {
        $plug = \PMVC\plug('supervisor');
        \PMVC\dev(function () use ($plug, $signo) {
            $name = [
                SIGHUP => 'SIGHUP',
                SIGINT => 'SIGINT',
                SIGTERM => 'SIGTERM',
            ];
            return $plug->log('Recieve ' . $name[$signo]);
        }, 'debug');
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
            case SIGHUP:
                $this->restartAll();
                break;
            case SIGINT:
            case SIGTERM:
            default:
                \PMVC\dev(function () use ($plug) {
                    return $plug->log('Ask start to shutting down...');
                }, 'debug');
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
            default:
                if (!$plug[IS_STOP_ME]) {
                  $plug[IS_STOP_ME] = true;
                  $plug[MY_PARALLEL]->finish();
                }
                break;
        }
    }

    private function restartAll()
    {
        $plug = \PMVC\plug(PLUGIN);
        \PMVC\dev(function () use ($plug) {
            return $plug->log('Restarting children');
        }, 'debug');
        foreach ($plug[CHILDREN] as $pid => $parallel) {
            if (TYPE_DAEMON !== $parallel[TYPE]) {
                continue;
            }
            $parallel->restart();
        }
    }
}

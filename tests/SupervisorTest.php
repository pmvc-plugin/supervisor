<?php
namespace PMVC\PlugIn\supervisor;

use PMVC\TestCase;


class SupervisorTest extends TestCase
{
    private $_plug = 'supervisor';

    protected function pmvc_setup()
    {
        \PMVC\unplug($this->_plug);
//        $plug = \PMVC\plug($this->_plug);
//        $plug->enableDebugMode();
    }

    function testPlugin()
    {
        ob_start();
        print_r(\PMVC\plug($this->_plug));
        $output = ob_get_contents();
        ob_end_clean();
        $this->haveString($this->_plug, $output);
    }

    function testScript()
    {
        $plug = \PMVC\plug($this->_plug);
        $s = 'helloScript';
        $runId = $plug->script(new fakeChild(), [$s . '0', 0]);
        $self = $this;
        $plug->process(function () use ($plug, $self, $s, $runId) {
            $plug->stop();
            $self->assertEquals($s.'0', $plug[PARALLELS][$runId]['args'][0]);
        });
    }

    function testDaemon()
    {
        $s = 'helloDaemon';
        $plug = \PMVC\plug($this->_plug);

        $runId = $plug->daemon(new fakeDaemon(), [$s . '1', 1]);
        $plug->process(function () use ($plug, $runId) {
            if ($plug[PARALLELS][$runId]->isRunning()) {
              $this->assertEquals('daemon', $plug[PARALLELS][$runId]['type']);
              /**
               * After fork, this is not easy to know child already run atleast once,
               * So use sleep here to let child have chance run atlease once. 
               */
              usleep(1500);
              $plug[PARALLELS][$runId]->stop();
            }
        });
    }

    function testTrigger()
    {
        $plug = \PMVC\plug($this->_plug);
        $s = 'helloTrigger';
        $self = $this;
        $childKey = $plug->script(new fakeChild(), [$s . '2', 2]);
        $second = $plug->script(new fakeChild(), [$s . '3', 3], $childKey);
        $plug->process(function ($callbackId, $pid) use (
            $plug,
            $self,
            $second,
            $childKey
        ) {
            static $i = 0;
            if (!$i) {
                $self->assertTrue(
                    empty($plug[PARALLELS][$second]->isStarted()),
                    'Test first'
                );
            } else {
                if ($plug[PARALLELS][$childKey]->isStarted()) {
                    $self->assertFalse(
                        empty($plug[PARALLELS][$second]->isStarted()),
                        'Test second failed. trigger_id: ' .
                            $childKey .
                            ' parallel_id: ' .
                            $second
                    );
                }
            }
            $i++;
        });
    }
}

class fakeChild
{
    function __invoke($s, $exit)
    {
        echo $s . "\n";
        exit($exit);
    }
}

/**
 * fakeDaemon without exit
 */
class fakeDaemon
{
    function __invoke($s, $exit)
    {
        echo $s . "\n";
    }
}

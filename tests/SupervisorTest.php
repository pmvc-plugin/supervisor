<?php
namespace PMVC\PlugIn\supervisor;

use PMVC_TestCase;

class SupervisorTest extends PMVC_TestCase
{
    private $_plug = 'supervisor';

    protected function teardown(): void
    {
        \PMVC\unplug($this->_plug);
    }

    function testPlugin()
    {
        ob_start();
        print_r(\PMVC\plug($this->_plug));
        $output = ob_get_contents();
        ob_end_clean();
        $this->haveString($this->_plug,$output);
    }

    function testScript()
    {
        $plug = \PMVC\plug($this->_plug);
        $s = 'helloScript';
        $runId = @$plug->script(new fakeChild(), array($s, 0));
        $self = $this;
        @$plug->process(function() use($plug, $self, $s, $runId){
            $plug->stop();
            $self->assertEquals($s,$plug[PARALLELS][$runId]['args'][0]);
        });
    }

    function testDaemon()
    {
        $s = 'helloDaemon';
        $plug = \PMVC\plug($this->_plug);
        $runId = @$plug->daemon(new fakeDaemon(), [$s, 1]);
        @$plug->process(function() use ($plug, $runId) {
            $plug->forceStop();
            $this->assertEquals('daemon',$plug[PARALLELS][$runId]['type']);
        });
    }

    function testTrigger()
    {
        $plug = \PMVC\plug($this->_plug);
        $s = 'helloTrigger';
        $self = $this;
        $childKey = $plug->script(new fakeChild(), array($s.'3', 3));
        $second = $plug->script(new fakeChild(), array($s.'4', 4), $childKey);
        $third = $plug->script(new fakeChild(), array($s.'5', 5), $second);
        $plug->process(function($callbackId, $pid) use($plug, $self, $second){
            static $i = 0;
            if (!$i) {
                $self->assertTrue(empty($plug[PARALLELS][$second]->isStarted()), 'Test first');
            } else {
                $self->assertFalse(empty($plug[PARALLELS][$second]->isStarted()), 'Test second');
            }
            $i++;
        });
    }
}

class fakeChild
{
    function __invoke($s, $exit)
    {
        echo $s."\n";
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
        echo $s."\n";
    }
}

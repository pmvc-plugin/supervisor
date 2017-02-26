<?php
PMVC\Load::plug();
PMVC\addPlugInFolders(['../']);
class SupervisorTest extends PHPUnit_Framework_TestCase
{
    private $_plug = 'supervisor';

    function tearDown()
    {
        \PMVC\unplug($this->_plug);
    }

    function testPlugin()
    {
        ob_start();
        print_r(PMVC\plug($this->_plug));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertContains($this->_plug,$output);
    }

    function testScript()
    {
        $plug = PMVC\plug($this->_plug);
        $s = 'helloScript';
        @$plug->script(new fakeChild(), array($s, 0));
        $self = $this;
        @$plug->process(function() use($plug, $self, $s){
            $plug->stop();
            $self->assertEquals($s,$plug['callbacks'][0]['args'][0]);
        });
    }

    function testDaemon()
    {
        $s = 'helloDaemon';
        $plug = PMVC\plug($this->_plug);
        @$plug->daemon(new fakeDaemon(), array($s, 1));
        $self = $this;
        @$plug->process(function() use($plug, $self){
            $plug->stop();
            $self->assertEquals('daemon',$plug['callbacks'][0]['type']);
        });
    }

    function testTrigger()
    {
        $plug = PMVC\plug($this->_plug);
        $s = 'helloTrigger';
        $self = $this;
        @$childKey = $plug->script(new fakeChild(), array($s.'3', 3));
        @$second = $plug->script(new fakeChild(), array($s.'4', 4), $childKey);
        @$third = $plug->script(new fakeChild(), array($s.'5', 5), $second);
        @$plug->process(function($callbackId, $pid) use($plug, $self, $second){
            static $i = 0;
            if (!$i) {
                $self->assertTrue(empty($plug['callbacks'][$second]['startTime']), 'Test first');
            } else {
                $self->assertFalse(empty($plug['callbacks'][$second]['startTime']), 'Test second');
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

<?php
PMVC\Load::plug();
PMVC\addPlugInFolder('../');
class SupervisorTest extends PHPUnit_Framework_TestCase
{
    private $_plug = 'supervisor';

    function tearDown()
    {
        if (\PMVC\exists($this->_plug,'plugin')) {
            \PMVC\unplug($this->_plug);
        }
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
        $s = 'hello1';
        $plug->script(new fakeChild(), array($s, 0));
        $self = $this;
        $plug->process(function() use($plug, $self, $s){
            $plug->stop();
            $self->assertEquals($s,$plug['callbacks'][0]['args'][0]);
        });
    }

    function testDaemon()
    {
        $s = 'hello2';
        $plug = PMVC\plug($this->_plug);
        $plug->daemon(new fakeDaemon(), array($s, 1));
        $self = $this;
        $plug->process(function() use($plug, $self){
            $plug->stop();
            $self->assertEquals('daemon',$plug['callbacks'][0]['type']);
        });
    }

    function testTrigger()
    {
        $plug = PMVC\plug($this->_plug);
        $s = 'hello';
        $self = $this;
        $childKey = $plug->script(new fakeChild(), array($s.'3', 3));
        $second = $plug->script(new fakeChild(), array($s.'4', 4), $childKey);
        $third = $plug->script(new fakeChild(), array($s.'5', 5), $second);
        $plug->process(function() use($plug, $self, $second){
            static $i = 0;
            if (!$i) {
                $self->assertTrue(empty($plug['callbacks'][$second]['startTime']));
            } else {
                $self->assertFalse(empty($plug['callbacks'][$second]['startTime']));
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

class fakeDaemon
{
    function __invoke($s, $exit)
    {
        echo "Daemon \n";
        echo $s."\n";
    }
}

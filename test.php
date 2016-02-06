<?php
PMVC\Load::plug();
PMVC\addPlugInFolder('../');
class SupervisorTest extends PHPUnit_Framework_TestCase
{
    private $_plug = 'supervisor';

    function setup()
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
        $s = 'hello';
        $plug->script(new fakeChild(), array($s, 1));
        $self = $this;
        $plug->process(function() use($plug, $self, $s){
            $plug->stop();
            $self->assertEquals($s,$plug['callbacks'][0]['args'][0]);
        });
    }

    function testDaemon()
    {
        $s = 'hello';
        $plug = PMVC\plug($this->_plug);
        $plug->daemon(new fakeDaemon(), array($s, 1));
        $self = $this;
        $plug->process(function() use($plug, $self){
                $plug->stop();
                $self->assertEquals('daemon',$plug['callbacks'][0]['type']);
        });
    }

    function testChildExitByItSelf()
    {
        $plug = PMVC\plug($this->_plug);
    }
}

class fakeChild
{
    function __invoke($s, $exit)
    {
        echo $s;
        exit($exit);
    }
}

class fakeDaemon
{
    function __invoke($s, $exit)
    {
        echo "aaaaaaaaaaaaaaaaaaaaa";
    }
}

<?php
include("vendor/autoload.php");
PMVC\Load::plug();
PMVC\addPlugInFolder('../');
$s = 'hello';
$plug = PMVC\plug('supervisor');
$plug->daemon(new fakeDaemon(), array($s, 1));
$plug->process(function() use($plug){
    static $i = 0;
    if ($i) {
        //$plug->stop();
    } else {
        $i++;
    }
});
class fakeDaemon
{
    function __invoke($s, $exit)
    {
        echo "aaaaaaaaaaaaaaaaaaaaa";
        exit();
    }
}

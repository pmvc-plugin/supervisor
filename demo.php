<?php
include("vendor/autoload.php");
PMVC\Load::plug();
PMVC\addPlugInFolder('../');
$s = 'hello'."\n";
$plug = PMVC\plug('supervisor');
$plug->daemon(new fakeDaemon(), array($s, 0), 3);
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
        $plug = PMVC\plug('supervisor');
        echo $plug['pid'].'--'.$s;
//        exit($exit);
    }
}

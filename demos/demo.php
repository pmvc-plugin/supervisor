<?php
include("../vendor/autoload.php");

PMVC\Load::plug(
    [ 
        'error'=>['all'],
        'debug'=>['output'=>'debug_cli'],
        'dev'=>null
    ],
    ['../../']
);

/**
 * Enable Debug mode
 * composer require pmvc-plugin/error pmvc-plugin/debug_cli
 */
 \PMVC\plug('debug')->setLevel('debug');


$plug = PMVC\plug('supervisor');

/**
 * Run with many time
 */
$plug->daemon(new fakeCommand(), ['This is deamon', 0], 3);

/**
 * Run only once 
 */
$plug->script(new fakeCommand(), ['This is script', 0]);

$plug->process(function() use($plug){
    static $i = 0;
    if ($i) {
        //$plug->stop();
    } else {
        $i++;
    }
});

class fakeCommand
{
    function __invoke($s, $exit)
    {
        $plug = PMVC\plug('supervisor');
        echo $plug['pid'].'--'.$s."\n";
    }
}


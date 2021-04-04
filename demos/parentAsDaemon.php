<?php
include("../vendor/autoload.php");

use PMVC\PlugIn\supervisor as sv;

\PMVC\Load::plug();
\PMVC\addPlugInFolders(['../../']);
\PMVC\initPlugIn(['supervisor'=>null],true); // for load constant


/**
 * Enable Debug mode
 * composer require pmvc-plugin/error pmvc-plugin/debug_cli
 */
\PMVC\initPlugin([ 'error'=>['all'],  'debug'=>['output'=>'debug_cli'],  'dispatcher'=>null, 'dev'=>null,]);
\PMVC\plug('debug')->setLevel('debug');


if (!in_array(\PMVC\value($GLOBALS, ['argv','1']), ['start', 'stop'])) {
    echo 'run command as "php parentAsDaemon.php [stop|start]"'."\n";
    exit();
}


$plug = PMVC\plug('supervisor', [
    sv\TYPE=> sv\TYPE_DAEMON,
    sv\PID_FILE=>'./pid',
    sv\PARENT_DAEMON_SHUTDOWN=>function () {
        echo 'Stop by "php parentAsDaemon.php stop"'."\n";
    }
]);

if ('stop' === \PMVC\value($GLOBALS, ['argv','1'])) {
    $plug->kill();
    exit();
}

/**
 * Run with many time
 */
$plug->daemon(new fakeCommand(), ['This is deamon', 0]);

/**
 * Run only once 
 */
$plug->script(new fakeCommand(), ['This is script', 0]);

$plug->process();

class fakeCommand
{
    function __invoke($s, $exit)
    {
        $plug = PMVC\plug('supervisor');
        echo $plug['pid'].'--'.$s."\n";
    }
}


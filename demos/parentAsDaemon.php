<?php
include '../vendor/autoload.php';

use PMVC\PlugIn\supervisor as sv;

\PMVC\Load::plug(['supervisor' => ['debug' => true]], ['../../']);

$plug = PMVC\plug('supervisor', [
    sv\TYPE => sv\TYPE_DAEMON,
    sv\PID_FILE => './pid',
    sv\PARENT_DAEMON_SHUTDOWN => function () {
        echo 'Stop by "php parentAsDaemon.php stop"' . "\n";
    },
]);

function start()
{
    $plug = \PMVC\plug('supervisor');
    /**
     * Run with many time
     */
    $plug->daemon(new fakeCommand(), ['This is deamon', 0]);

    /**
     * Run only once
     */
    $plug->script(new fakeCommand(), ['This is script', 0]);

    $plug->process();
}

switch (\PMVC\value($GLOBALS, ['argv', '1'])) {
    case 'start':
        start();
        break;
    case 'stop':
        $plug->kill();
        break;
    case 'status':
        $plug->execGetStatus();
        break;
    default:
        echo 'run command as "php parentAsDaemon.php [stop|start|status]"' .
            PHP_EOL;
        break;
}
exit();

class fakeCommand
{
    function __invoke($s, $exit)
    {
        $plug = PMVC\plug('supervisor');
        echo $plug['pid'] . '--' . $s . "\n";
    }
}

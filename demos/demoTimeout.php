<?php
include("../vendor/autoload.php");

use PMVC\PlugIn\supervisor\Parallel;

/**
 * Enable Debug mode
 * composer require pmvc-plugin/error pmvc-plugin/debug_cli
 */
PMVC\Load::plug(
    [ 
        'supervisor'=>['debug' => 'debug'],
    ],
    ['../../',  __DIR__.'/../vendor/pmvc-plugin/', ]
);

$parallel = new Parallel(function(){
  echo "this is daemon timeout <!--" .PHP_EOL;
  sleep(10);
  echo "this is daemon timeout -->".PHP_EOL;
}, [
  'type' => 'daemon',
  'timeout' => 3,
  'onExit'  => function($parallel, $exitCode) {
    \PMVC\v([
      'exitCode' => $exitCode,
      'pid' => $parallel->getPid()
    ]);
  }, 
  'onFinish'=> function($parallel, $result) {
    \PMVC\v([
      'result' => $result,
      'pid' => $parallel->getPid()
    ]);
  }
]);

$parallel->start();

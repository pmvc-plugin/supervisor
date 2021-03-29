<?php
include("../vendor/autoload.php");

use PMVC\PlugIn\supervisor\Parallel;

/**
 * Enable Debug mode
 * composer require pmvc-plugin/error pmvc-plugin/debug_cli
 */
PMVC\Load::plug(
    [ 
        'error'=>['all'],
        'debug'=>['output'=>'debug_cli', 'level'=> 'debug, trace'],
        'dev'=>null,
        'supervisor'=>null,
    ],
    ['../../']
);

$parallel = new Parallel(function(){
  echo "this is daemon timeout";
  sleep(10);
}, [
  'type' => 'daemon',
  'timeout' => 3 
]);

$parallel->start();

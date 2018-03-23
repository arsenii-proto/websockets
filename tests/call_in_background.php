<?php

$phpBinary = (new PhpExecutableFinder)->find();

$cmd = '';

if ( (strtoupper(substr(php_uname(), 0, 7)) === 'WINDOWS') ) {
    $cmd = "start /B ";
}

$cmd .= "{$phpBinary} ". base_path('/'. ( defined('ARTISAN_BINARY') ? ARTISAN_BINARY : 'artisan' ) ) ." websockets:start";

if (! (strtoupper(substr(php_uname(), 0, 7)) === 'WINDOWS') ) {
    $cmd = "({$cmd}) > /dev/null &";
}

$output = [];
//exec($cmd, $output);
    
dd($cmd,  $output);
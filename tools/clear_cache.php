<?php

if (php_sapi_name() !== 'cli' or !defined('STDIN')) {
    exit();    
}

$files = glob('../cache/*.cache');
if ($files) {    
    $c = count($files);
    array_map('unlink', $files);
    echo "$c file(s) deleted.";
}
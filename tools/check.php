<?php

if (php_sapi_name() !== 'cli' or !defined('STDIN')) {
    exit();    
}

// Simple check to make sure XML's are properly formatted.
$files = glob(__DIR__ . '/../data/*.xml');

foreach ($files as $file) {    
    simplexml_load_file($file);
}    

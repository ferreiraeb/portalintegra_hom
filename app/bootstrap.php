<?php
date_default_timezone_set('America/Sao_Paulo');
$config = require __DIR__ . '/config/config.php';

session_name($config['app']['session_name']);
session_start();

require __DIR__ . '/src/Support/helpers.php';
spl_autoload_register(function($class) {
    $base = __DIR__ . '/src/';
    $path = $base . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) require $path;
});
?>
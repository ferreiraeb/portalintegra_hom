<?php
date_default_timezone_set('America/Sao_Paulo');
$config = require __DIR__ . '/config/config.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $sessionDir = __DIR__ . '/storage/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0770, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    $fwdProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $fwdProto === 'https'
        || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';

    $cookiePath = '/';
    $basePath = parse_url((string)($config['app']['base_url'] ?? '/'), PHP_URL_PATH);
    if (is_string($basePath) && $basePath !== '' && $basePath !== '/') {
        $cookiePath = rtrim($basePath, '/') . '/';
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '28800');

    session_name($config['app']['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath,
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require __DIR__ . '/src/Support/helpers.php';
spl_autoload_register(function($class) {
    $base = __DIR__ . '/src/';
    $path = $base . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) require $path;
});

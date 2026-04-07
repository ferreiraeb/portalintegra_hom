#!/usr/bin/env php
<?php
/**
 * CLI Worker: Atualiza preços Massey Ferguson em segundo plano.
 * Disparado automaticamente por AgroMasseyController::ajaxUpdateAllStart().
 *
 * Uso: php run_update_massey.php --op={GUID}
 */

// No CLI não há cookies nem sessão HTTP — evita avisos desnecessários
ini_set('session.use_cookies',      '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid',    '0');

// Garante que o CWD seja a raiz do projeto (mesmo nível de bootstrap.php)
chdir(__DIR__ . '/..');

require_once __DIR__ . '/../bootstrap.php';

$opts = getopt('', ['op:']);
$op   = trim($opts['op'] ?? '');

if (!preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
    fwrite(STDERR, "Uso: php run_update_massey.php --op={GUID}\n");
    exit(1);
}

$ctrl = new \Controllers\AgroMasseyController();
try {
    $ctrl->runUpdateAllBackground($op);
    echo "Concluído: op={$op}\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}


#!/usr/bin/env php
<?php
/**
 * CLI Worker: sincroniza VIEW_METADADOS_COMPOSICAO_REMUNERACAO para cache local.
 * Disparado por RhController::ajaxRefreshStart().
 *
 * Uso: php sync_composicao_remuneracao.php
 */

ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid', '0');

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../bootstrap.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

$ctrl = new \Controllers\RhController();
try {
    $ctrl->runRefreshBackground();
    echo "Concluído: composição remuneração sincronizada.\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}

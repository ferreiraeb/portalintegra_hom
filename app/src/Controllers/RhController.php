<?php
namespace Controllers;

use Database\Connection;
use Database\OracleConnection;
use Support\ListTable;

/**
 * Módulo RH — Composição de Remuneração.
 *
 * Fonte: Oracle VIEW_METADADOS_COMPOSICAO_REMUNERACAO.
 * Cache: SQL Server Portal_Integra (RH_ComposicaoRemuneracao + RH_ComposicaoJob).
 *
 * Listagem/export leem colunas reais no SQL Server.
 */
class RhController
{
    private const PERM = 'rh.composicao_remuneracao';

    /** Linhas do cursor Oracle acumuladas por transação */
    public const STREAM_FLUSH_ROWS = 2000;

    public const REFRESH_POLL_INTERVAL_MS = 5000;
    public const REFRESH_STALE_TICKS = 180;
    private const STALE_RESTART_SECONDS = 600;
    /** Só marca worker morto se processo sumiu E heartbeat parou há N segundos. */
    private const DEAD_WORKER_GRACE_SECONDS = 180;

    private const LOAD_INDEXES = [
        'IX_RH_Comp_Sync_Row',
        'IX_RH_Comp_Sync_Contrato',
        'IX_RH_Comp_Sync_Nome',
    ];

    /**
     * Colunas da view Oracle → tipos SQL Server.
     * t: n=nvarchar (len), d=date, i=int, m=decimal(18,4)
     */
    private const COL_DEFS = [
        'CONTRATO'                          => ['t' => 'n', 'len' => 80],
        'TIPOCONTRATO'                      => ['t' => 'n', 'len' => 40],
        'VINCULOEMPREGATICIO'               => ['t' => 'n', 'len' => 40],
        'NOME'                              => ['t' => 'n', 'len' => 250],
        'CARGO'                             => ['t' => 'n', 'len' => 200],
        'SEXO'                              => ['t' => 'n', 'len' => 5],
        'NASCIMENTO'                        => ['t' => 'd'],
        'CPF'                               => ['t' => 'n', 'len' => 20],
        'UNIDADE'                           => ['t' => 'n', 'len' => 40],
        'CODIGOEMPRESA'                     => ['t' => 'n', 'len' => 40],
        'NOMEEMPRESA'                       => ['t' => 'n', 'len' => 200],
        'CODIGOESTABELECIMENTO'             => ['t' => 'n', 'len' => 40],
        'NOMEESTABELECIMENTO'               => ['t' => 'n', 'len' => 200],
        'CNPJ'                              => ['t' => 'n', 'len' => 20],
        'NOMECARGOGERENTE'                  => ['t' => 'n', 'len' => 200],
        'NOMEGERENTE'                       => ['t' => 'n', 'len' => 250],
        'CODIGOCLASSIFICACAOCONTABIL'       => ['t' => 'n', 'len' => 40],
        'DESCRICAOCLASSIFICACAOCONTABIL'    => ['t' => 'n', 'len' => 200],
        'CODIGOCENTRODECUSTO'               => ['t' => 'n', 'len' => 40],
        'DESCRICAOCENTROCUSTO1'             => ['t' => 'n', 'len' => 200],
        'CODIGOSITUACAO'                    => ['t' => 'n', 'len' => 20],
        'FERIASNOMES'                       => ['t' => 'n', 'len' => 5],
        'DATAADMISSAO'                      => ['t' => 'd'],
        'MESESCASA'                         => ['t' => 'i'],
        'MESESCARGO'                        => ['t' => 'i'],
        'DATAULTTRANSFERENCIA'              => ['t' => 'd'],
        'DATAULTIMOREAJUSTE'                => ['t' => 'd'],
        'MOTIVOALTERACAOSALARIO'            => ['t' => 'n', 'len' => 200],
        'HORASCONTRATUAIS'                  => ['t' => 'm'],
        'DATALANCAMENTO'                    => ['t' => 'd'],
        'SALARIO_CONTRATUAL_HIST'           => ['t' => 'm'],
        'SALARIO_CONTRATUAL_FOLHA'          => ['t' => 'm'],
        'DECIMO_TERCEIRO'                   => ['t' => 'm'],
        'FERIAS'                            => ['t' => 'm'],
        'INSS'                              => ['t' => 'm'],
        'GARANTIA_MINIMA'                   => ['t' => 'm'],
        'FGTS'                              => ['t' => 'm'],
        'AJUDA_DE_CUSTO'                    => ['t' => 'm'],
        'ADIC_TEMPO_CASA'                   => ['t' => 'm'],
        'INSALUBRIDADE'                     => ['t' => 'm'],
        'PERICULOSIDADE'                    => ['t' => 'm'],
        'HORA_EXTRA_50'                     => ['t' => 'm'],
        'HORA_EXTRA_60'                     => ['t' => 'm'],
        'HORA_EXTRA_100'                    => ['t' => 'm'],
        'REPOUSO_HE'                        => ['t' => 'm'],
        'HORA_EXTRA_M'                      => ['t' => 'm'],
        'ADICIONAL_NOTURNO'                 => ['t' => 'm'],
        'ADICIONAL_NOTURNO_M'               => ['t' => 'm'],
        'COMISSAO'                          => ['t' => 'm'],
        'COMISSAO_M'                        => ['t' => 'm'],
        'REPOUSO_COMISSAO'                  => ['t' => 'm'],
        'REPOUSO_COMISSAO_M'                => ['t' => 'm'],
        'PREMIACAO'                         => ['t' => 'm'],
        'PREMIACAO_M'                       => ['t' => 'm'],
        'BONIFICACAO'                       => ['t' => 'm'],
        'BONIFICACAO_M'                     => ['t' => 'm'],
        'META_DIRETORIA'                    => ['t' => 'm'],
        'META_DIRETORIA_M'                  => ['t' => 'm'],
        'TREINAMENTO'                       => ['t' => 'm'],
        'TREINAMENTO_M'                     => ['t' => 'm'],
        'SALARIO_DOENCA_SOBRE_COMISSAO'     => ['t' => 'm'],
        'SALARIO_DOENCA_SOBRE_COMISSAO_M'   => ['t' => 'm'],
        'ASS_MEDICA_COLABORADOR_EMPREGADO'  => ['t' => 'm'],
        'ASS_ODONTOLOGICA'                  => ['t' => 'm'],
        'ASS_ODONTOLOGICA_DEPENDENTES'      => ['t' => 'm'],
        'ALIMENTACAO'                       => ['t' => 'm'],
        'TRANSPORTE'                        => ['t' => 'm'],
        'AUXILIO_MOBILIDADE'                => ['t' => 'm'],
        'TOTAL_BRUTO_DESLIGAMENTO'          => ['t' => 'm'],
        'MULTA_FGTS'                        => ['t' => 'm'],
        'FGTS_RESCISAO'                     => ['t' => 'm'],
        'DATARESCISAO'                      => ['t' => 'd'],
        'CODIGOMOTIVORESCISAO'              => ['t' => 'n', 'len' => 40],
        'MOTIVORESCISAO'                    => ['t' => 'n', 'len' => 200],
        'CPF_LIDER'                         => ['t' => 'n', 'len' => 20],
    ];

    /** @var array<int, \PDOStatement> */
    private array $insertStmts = [];

    private function viewName(): string
    {
        global $config;
        $name = trim((string)($config['db']['oracle']['composicao_remuneracao_view']
            ?? 'SIRH.VIEW_METADADOS_COMPOSICAO_REMUNERACAO'));
        if ($name === '' || !preg_match('/^[A-Za-z0-9_$.]+$/', $name)) {
            throw new \RuntimeException('Nome da view de composição remuneração inválido na config.');
        }
        return $name;
    }

    /** @return list<string> */
    private function dataColumnNames(): array
    {
        return array_keys(self::COL_DEFS);
    }

    public function index(): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require(self::PERM, 1);

        $action = (string)($_GET['action'] ?? '');
        switch ($action) {
            case 'refresh_start':
                $this->ajaxRefreshStart();
                return;
            case 'refresh_status':
                $this->ajaxRefreshStatus();
                return;
            case 'export':
                $this->exportCsv();
                return;
            default:
                $this->composicaoRemuneracao();
        }
    }

    public function composicaoRemuneracao(): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require(self::PERM, 1);

        $this->ensureSchema();

        $meta     = $this->jobToMeta($this->readLatestJob());
        $active   = $this->readActiveDoneJob();
        $columns  = $this->columnsFromJob($active ?? $meta);
        $erro     = null;
        $hasCache = $active !== null;
        $isRunning = (($meta['status'] ?? '') === 'running');

        $cols = $this->buildListCols($columns);
        $lt   = new ListTable(base_url('rh/composicao-remuneracao'), $cols, 'rhcomp');
        $lt->setPerPageOptions([10, 25, 50, 100, 250, 500]);
        $lt->readRequest('CONTRATO', 'asc');

        $pageRows = [];
        $total = 0;
        $from = 0;
        $to = 0;

        if ($hasCache) {
            try {
                $page = $this->pageFetch(
                    (string)$active['SyncId'],
                    $columns,
                    $lt
                );
                $pageRows = $page['rows'];
                $total    = $page['total'];
                $perPage  = $lt->getPerPage();
                $p        = $lt->getPage();
                $offset   = ($p - 1) * $perPage;
                $from = $total > 0 ? $offset + 1 : 0;
                $to   = min($offset + $perPage, $total);
            } catch (\Throwable $e) {
                $erro = 'Falha ao ler cache SQL: ' . $e->getMessage();
            }
        }

        $displayMeta = $active ? $this->jobToMeta($active) : $meta;
        if ($isRunning && $active) {
            $displayMeta['status'] = 'running';
            $displayMeta['message'] = $meta['message'] ?? $displayMeta['message'];
            $displayMeta['progress'] = $meta['progress'] ?? 0;
            $displayMeta['done'] = $meta['done'] ?? 0;
            $displayMeta['total'] = $meta['total'] ?? 0;
        }

        render_page('RH/composicao_remuneracao.php', [
            'lt'         => $lt,
            'rows'       => $pageRows,
            'total'      => $total,
            'from'       => $from,
            'to'         => $to,
            'erro'       => $erro,
            'meta'       => $displayMeta,
            'hasCache'   => $hasCache,
            'isRunning'  => $isRunning,
            'pollMs'     => self::REFRESH_POLL_INTERVAL_MS,
            'staleTicks' => self::REFRESH_STALE_TICKS,
        ]);
    }

    /** Worker CLI: um cursor Oracle → SQL Server (streaming, sem OFFSET). */
    public function runRefreshBackground(): void
    {
        set_time_limit(0);
        ignore_user_abort(true);
        ini_set('memory_limit', '256M');

        $pdoSql = Connection::get();
        try {
            $pdoSql->exec('SET NOCOUNT ON');
        } catch (\Throwable $ignored) {
        }

        $job = $this->readRunningJob();
        if (!$job) {
            throw new \RuntimeException('Nenhum job RH em andamento para processar.');
        }
        $syncId = (string)$job['SyncId'];

        $view = $this->viewName();
        $this->updateJob($syncId, [
            'Status'    => 'running',
            'Step'      => 'init',
            'Message'   => 'Abrindo cursor Oracle...',
            'Progress'  => 1,
            'Total'     => 0,
            'Done'      => 0,
            'WorkerPid' => getmypid(),
        ]);

        try {
            $this->ensureSchema();
        } catch (\Throwable $e) {
            $this->updateJob($syncId, [
                'Status'     => 'error',
                'Step'       => 'error',
                'Message'    => 'Falha na atualização.',
                'Progress'   => 0,
                'Error'      => $this->safeUtf8($e->getMessage()),
                'FinishedAt' => $this->now(),
                'WorkerPid'  => null,
            ]);
            throw $e;
        }

        $indexesDisabled = false;
        $tsvPath = null;
        $csvPartPath = null;
        $csvFp = null;
        try {
            $pdoOra = OracleConnection::get();
            $this->applyOraclePrefetch($pdoOra);

            $stDel = $pdoSql->prepare(
                'DELETE FROM dbo.RH_ComposicaoRemuneracao WHERE SyncId = CAST(? AS uniqueidentifier)'
            );
            $stDel->execute([$syncId]);

            $bcpBin = $this->bcpBinary();
            $useBcp = $bcpBin !== null;
            $tsvFp = null;
            $csvPartPath = null;
            $csvFp = null;
            if ($useBcp) {
                $tsvPath = sys_get_temp_dir() . '/rh_comp_' . preg_replace('/[^a-f0-9-]/i', '', $syncId) . '.tsv';
                $tsvFp = fopen($tsvPath, 'wb');
                if ($tsvFp === false) {
                    throw new \RuntimeException('Não foi possível criar arquivo temporário para bcp.');
                }
                $csvPartPath = $this->snapshotCsvPath($syncId) . '.part';
                $csvFp = fopen($csvPartPath, 'wb');
                if ($csvFp === false) {
                    throw new \RuntimeException('Não foi possível criar CSV de exportação.');
                }
                fwrite($csvFp, "\xEF\xBB\xBF");
                fwrite($csvFp, $this->csvLine($this->dataColumnNames()));
            } else {
                $this->disableLoadIndexes($pdoSql);
                $indexesDisabled = true;
            }

            $this->updateJob($syncId, [
                'Step'     => 'streaming',
                'Message'  => $useBcp
                    ? 'Lendo Oracle (arquivo temporário para carga bulk)...'
                    : 'Lendo Oracle e gravando no SQL Server (OPENJSON)...',
                'Progress' => 2,
            ]);

            $stmt = $pdoOra->query('SELECT * FROM ' . $view);
            if ($stmt === false) {
                throw new \RuntimeException('Falha ao abrir cursor Oracle.');
            }
            $this->applyOraclePrefetch($pdoOra, $stmt);

            $columns = [];
            $buffer  = [];
            $done    = 0;
            $rowNum  = 0;
            $flushEvery = self::STREAM_FLUSH_ROWS;
            $tsvBuf = '';
            $tsvBufRows = 0;

            while (($raw = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $row = $this->normalizeRow($raw);

                if (empty($columns)) {
                    $columns = $this->filterKnownColumns(array_keys($row));
                    $this->updateJob($syncId, [
                        'ColumnsJson' => json_encode($columns, JSON_UNESCAPED_UNICODE),
                    ]);
                }

                if ($useBcp) {
                    $rowNum++;
                    $csvFields = [];
                    $tsvParts = [$syncId, (string)$rowNum];
                    foreach ($this->dataColumnNames() as $col) {
                        $coerced = $this->coerceValue($col, $row[$col] ?? null);
                        $tsvParts[] = ($coerced === null)
                            ? ''
                            : str_replace(["\t", "\n", "\r"], ' ', (string)$coerced);
                        $csvFields[] = $this->formatExportCell(
                            $coerced,
                            self::COL_DEFS[$col]['t'] ?? 'n'
                        );
                    }
                    $tsvBuf .= implode("\t", $tsvParts) . "\n";
                    $tsvBufRows++;
                    fwrite($csvFp, $this->csvLine($csvFields));
                    $done++;
                    if ($tsvBufRows >= 200) {
                        fwrite($tsvFp, $tsvBuf);
                        $tsvBuf = '';
                        $tsvBufRows = 0;
                    }
                    if ($done % $flushEvery === 0) {
                        $pct = min(85, max(3, (int)floor($done / 1100)));
                        $this->updateJob($syncId, [
                            'Step'     => 'streaming',
                            'Done'     => $done,
                            'Total'    => 0,
                            'Progress' => $pct,
                            'Message'  => sprintf(
                                '%s registros lidos do Oracle...',
                                number_format($done, 0, ',', '.')
                            ),
                        ]);
                    }
                    continue;
                }

                $buffer[] = $row;
                if (count($buffer) >= $flushEvery) {
                    $this->insertSqlBatches($pdoSql, $syncId, $buffer, $rowNum);
                    $rowNum += count($buffer);
                    $done   += count($buffer);
                    $buffer  = [];
                    $pct = min(95, max(3, (int)floor($done / 1000)));
                    $this->updateJob($syncId, [
                        'Step'     => 'streaming',
                        'Done'     => $done,
                        'Total'    => 0,
                        'Progress' => $pct,
                        'Message'  => sprintf(
                            '%s registros sincronizados no SQL Server...',
                            number_format($done, 0, ',', '.')
                        ),
                    ]);
                }
            }

            if ($useBcp) {
                if ($tsvBuf !== '') {
                    fwrite($tsvFp, $tsvBuf);
                }
                fclose($tsvFp);
                $tsvFp = null;

                $this->disableLoadIndexes($pdoSql);
                $indexesDisabled = true;

                $this->updateJob($syncId, [
                    'Step'     => 'writing',
                    'Done'     => $done,
                    'Progress' => 90,
                    'Message'  => sprintf(
                        'Atualizando dados no SQL Server (%s registros)...',
                        number_format($done, 0, ',', '.')
                    ),
                ]);
                $this->runBcpLoad($bcpBin, $tsvPath, $syncId, $done);
            } elseif (!empty($buffer)) {
                $this->insertSqlBatches($pdoSql, $syncId, $buffer, $rowNum);
                $done += count($buffer);
                $buffer = [];
            }

            if (empty($columns)) {
                $columns = $this->dataColumnNames();
            }

            $this->updateJob($syncId, [
                'Step'     => 'activating',
                'Done'     => $done,
                'Message'  => 'Ativando cache e recriando índices...',
                'Progress' => 99,
            ]);

            $this->activateSync($pdoSql, $syncId);
            $this->rebuildLoadIndexes($pdoSql);
            $indexesDisabled = false;

            if ($csvFp) {
                fclose($csvFp);
                $csvFp = null;
                $finalCsv = $this->snapshotCsvPath($syncId);
                if (is_file($csvPartPath)) {
                    @unlink($finalCsv);
                    if (!@rename($csvPartPath, $finalCsv)) {
                        @copy($csvPartPath, $finalCsv);
                        @unlink($csvPartPath);
                    }
                    $this->purgeOldSnapshotCsvs($syncId);
                }
            } else {
                // Fallback OPENJSON: gera CSV uma vez via bcp out (rápido).
                try {
                    $this->buildSnapshotCsvBcp($syncId, $columns);
                } catch (\Throwable $ignored) {
                }
            }

            $fetchedAt = $this->now();
            $this->updateJob($syncId, [
                'Status'      => 'done',
                'Step'        => 'done',
                'Message'     => sprintf(
                    'Atualização concluída — %s registros.',
                    number_format($done, 0, ',', '.')
                ),
                'Progress'    => 100,
                'Done'        => $done,
                'Total'       => $done,
                'IsActive'    => 1,
                'FinishedAt'  => $fetchedAt,
                'FetchedAt'   => $fetchedAt,
                'Error'       => null,
                'WorkerPid'   => null,
                'ColumnsJson' => json_encode($columns, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            if ($indexesDisabled) {
                try {
                    $this->rebuildLoadIndexes($pdoSql);
                } catch (\Throwable $ignored) {
                }
            }
            $this->updateJob($syncId, [
                'Status'     => 'error',
                'Step'       => 'error',
                'Message'    => 'Falha na atualização.',
                'Progress'   => 0,
                'Error'      => $this->safeUtf8($e->getMessage()),
                'FinishedAt' => $this->now(),
                'WorkerPid'  => null,
            ]);
            try {
                $st = Connection::get()->prepare(
                    'DELETE FROM dbo.RH_ComposicaoRemuneracao WHERE SyncId = CAST(? AS uniqueidentifier)'
                );
                $st->execute([$syncId]);
            } catch (\Throwable $ignored) {
            }
            throw $e;
        } finally {
            if (!empty($csvFp) && is_resource($csvFp)) {
                fclose($csvFp);
            }
            if (!empty($csvPartPath) && is_file($csvPartPath)) {
                @unlink($csvPartPath);
            }
            if (!empty($tsvPath) && is_file($tsvPath)) {
                @unlink($tsvPath);
            }
        }
    }

    /* ─── AJAX ───────────────────────────────────────────────────────────── */

    private function ajaxRefreshStart(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        session_write_close();

        try {
            $this->ensureSchema();
            $meta = $this->jobToMeta($this->readLatestJob());

            if (($meta['status'] ?? '') === 'running') {
                $pid = (int)($meta['worker_pid'] ?? 0);
                $alive = $pid > 0 && $this->isProcessAlive($pid);
                $updated = strtotime((string)($meta['updated_at'] ?? '')) ?: 0;
                $staleSec = $updated > 0 ? (time() - $updated) : PHP_INT_MAX;

                if ($alive && $staleSec < self::STALE_RESTART_SECONDS) {
                    echo json_encode([
                        'ok'      => true,
                        'async'   => true,
                        'already' => true,
                        'message' => 'Atualização já em andamento.',
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }
            }

            $syncId = $this->uuidv4();
            $now = $this->now();
            $pdo = Connection::get();
            $st = $pdo->prepare("
                INSERT INTO dbo.RH_ComposicaoJob
                    (SyncId, Status, Step, Message, Progress, Total, Done, IsActive, StartedAt, UpdatedAt)
                VALUES
                    (CAST(? AS uniqueidentifier), 'running', 'queued',
                     N'Iniciando worker em background...', 0, 0, 0, 0, ?, ?)
            ");
            $st->execute([$syncId, $now, $now]);

            $execAvailable = function_exists('exec');
            if ($execAvailable) {
                $disabled = array_map('trim', explode(',', strtolower(ini_get('disable_functions') ?: '')));
                if (in_array('exec', $disabled, true)) {
                    $execAvailable = false;
                }
            }

            $script = realpath(__DIR__ . '/../../cli/sync_composicao_remuneracao.php');
            if (!$execAvailable || !$script) {
                try {
                    $this->runRefreshBackground();
                    echo json_encode(['ok' => true, 'async' => false], JSON_UNESCAPED_UNICODE);
                } catch (\Throwable $e) {
                    http_response_code(500);
                    echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                }
                return;
            }

            $phpBin = PHP_BINARY;
            if (stripos(basename($phpBin), 'php') === false) {
                $phpBin = 'php';
            }

            $log = $this->storageDir() . '/worker_last.log';
            $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            if ($isWin) {
                $cmd = 'start /B "" ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($script);
                pclose(popen($cmd, 'r'));
            } else {
                $cmd = escapeshellarg($phpBin)
                     . ' ' . escapeshellarg($script)
                     . ' >> ' . escapeshellarg($log)
                     . ' 2>&1 &';
                exec($cmd);
            }

            echo json_encode(['ok' => true, 'async' => true, 'sync_id' => $syncId], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private function ajaxRefreshStatus(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        session_write_close();

        try {
            $this->ensureSchema();
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }

        $job = $this->readLatestJob();
        $meta = $this->jobToMeta($job);
        $status = (string)($meta['status'] ?? 'idle');

        if ($status === 'running') {
            $pid = (int)($meta['worker_pid'] ?? 0);
            $updated = strtotime((string)($meta['updated_at'] ?? '')) ?: 0;
            $staleSec = $updated > 0 ? (time() - $updated) : PHP_INT_MAX;
            $alive = $pid > 0 && $this->isProcessAlive($pid);

            if (
                ($pid > 0 && !$alive && $staleSec >= self::DEAD_WORKER_GRACE_SECONDS)
                || ($pid <= 0 && $staleSec >= self::DEAD_WORKER_GRACE_SECONDS)
            ) {
                $this->updateJob((string)$job['SyncId'], [
                    'Status'     => 'error',
                    'Step'       => 'error',
                    'Message'    => 'Worker interrompido (processo não encontrado).',
                    'Error'      => $pid <= 0
                        ? 'O worker não chegou a iniciar. Tente novamente.'
                        : 'O processo de sincronização foi interrompido. Tente novamente.',
                    'FinishedAt' => $this->now(),
                    'WorkerPid'  => null,
                ]);
                try {
                    $this->rebuildLoadIndexes(Connection::get());
                } catch (\Throwable $ignored) {
                }
                $job = $this->readLatestJob();
                $meta = $this->jobToMeta($job);
                $status = 'error';
            }
        }

        $workerAlive = false;
        $pid = (int)($meta['worker_pid'] ?? 0);
        if ($pid > 0) {
            $workerAlive = $this->isProcessAlive($pid);
        }
        if (($meta['status'] ?? '') === 'running' && !$workerAlive) {
            $updated = strtotime((string)($meta['updated_at'] ?? '')) ?: 0;
            $staleSec = $updated > 0 ? (time() - $updated) : 0;
            if ($pid <= 0 || $staleSec < self::DEAD_WORKER_GRACE_SECONDS) {
                $workerAlive = true;
            }
        }

        echo json_encode([
            'ok'           => true,
            'status'       => $status,
            'step'         => $meta['step'] ?? '',
            'message'      => $meta['message'] ?? '',
            'progress'     => (int)($meta['progress'] ?? 0),
            'total'        => (int)($meta['total'] ?? 0),
            'done'         => (int)($meta['done'] ?? 0),
            'worker_alive' => $workerAlive,
            'started_at'   => $meta['started_at'] ?? null,
            'updated_at'   => $meta['updated_at'] ?? null,
            'finished_at'  => $meta['finished_at'] ?? null,
            'fetched_at'   => $meta['fetched_at'] ?? null,
            'row_count'    => (int)($meta['row_count'] ?? 0),
            'error'        => $meta['error'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
    }

    /* ─── Export CSV (streamed) ──────────────────────────────────────────── */

    private function exportCsv(): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require(self::PERM, 1);
        $this->ensureSchema();

        @set_time_limit(0);
        ignore_user_abort(true);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $active = $this->readActiveDoneJob();
        if (!$active) {
            http_response_code(400);
            exit('Não há dados em cache. Atualize os dados antes de exportar.');
        }

        $columns = $this->columnsFromJob($active);
        if (empty($columns)) {
            http_response_code(400);
            exit('Cache sem colunas definidas.');
        }

        $cols = $this->buildListCols($columns);
        $lt   = new ListTable(base_url('rh/composicao-remuneracao'), $cols, 'rhcomp');
        $lt->setPerPageOptions([1000]);
        $lt->readRequest('CONTRATO', 'asc');

        $filename = 'composicao_remuneracao_' . date('Ymd_His') . '.csv';
        $syncId = (string)$active['SyncId'];
        $hasFilters = $lt->hasFilters();

        // Snapshot completo (sem filtro): arquivo pré-gerado — download quase imediato.
        if (!$hasFilters) {
            $snap = $this->snapshotCsvPath($syncId);
            if (!is_file($snap) || filesize($snap) < 10) {
                try {
                    $this->buildSnapshotCsvBcp($syncId, $columns);
                } catch (\Throwable $e) {
                    // Cai no stream PHP abaixo.
                }
            }
            if (is_file($snap) && filesize($snap) > 10) {
                $this->sendSnapshotCsv($snap, $filename);
                exit;
            }
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Accel-Buffering: no');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fwrite($out, $this->csvLine($columns));

        $pdo = Connection::get();
        if (defined('PDO::SQLSRV_ATTR_CLIENT_BUFFERING')) {
            try {
                $pdo->setAttribute(\PDO::SQLSRV_ATTR_CLIENT_BUFFERING, false);
            } catch (\Throwable $ignored) {
            }
        }

        [$where, $params] = $this->buildFilterSql($syncId, $columns, $lt);
        $sortCol = strtoupper($lt->getSort());
        if ($sortCol === '' || !in_array($sortCol, $columns, true)) {
            $sortCol = 'CONTRATO';
        }
        $dir = strtolower($lt->getDir()) === 'desc' ? 'DESC' : 'ASC';

        $orderSql = (!$hasFilters && $sortCol === 'CONTRATO' && $dir === 'ASC')
            ? 'ORDER BY [RowNum] ASC'
            : $this->orderBySql($sortCol, $dir);
        $selectSql = $this->selectListSql($columns);

        $types = [];
        foreach ($columns as $col) {
            $types[] = self::COL_DEFS[strtoupper((string)$col)]['t'] ?? 'n';
        }
        $colCount = count($columns);

        $sql = "
            SELECT {$selectSql}
            FROM dbo.RH_ComposicaoRemuneracao
            WHERE {$where}
            {$orderSql}
        ";
        $st = $pdo->prepare($sql);
        if (defined('PDO::SQLSRV_ATTR_CLIENT_BUFFERING')) {
            try {
                $st->setAttribute(\PDO::SQLSRV_ATTR_CLIENT_BUFFERING, false);
            } catch (\Throwable $ignored) {
            }
        }
        $st->execute($params);

        $buf = '';
        $n = 0;
        while ($r = $st->fetch(\PDO::FETCH_NUM)) {
            $line = [];
            for ($i = 0; $i < $colCount; $i++) {
                $line[] = $this->formatExportCell($r[$i] ?? null, $types[$i]);
            }
            $buf .= $this->csvLine($line);
            if (++$n % 500 === 0) {
                fwrite($out, $buf);
                $buf = '';
                flush();
            }
        }
        if ($buf !== '') {
            fwrite($out, $buf);
        }
        fclose($out);
        exit;
    }

    private function snapshotCsvPath(string $syncId): string
    {
        $safe = preg_replace('/[^a-f0-9-]/i', '', $syncId) ?: 'unknown';
        return $this->storageDir() . '/composicao_' . $safe . '.csv';
    }

    private function purgeOldSnapshotCsvs(string $keepSyncId): void
    {
        $keep = $this->snapshotCsvPath($keepSyncId);
        foreach (glob($this->storageDir() . '/composicao_*.csv') ?: [] as $f) {
            if (is_file($f) && realpath($f) !== realpath($keep)) {
                @unlink($f);
            }
        }
        foreach (glob($this->storageDir() . '/composicao_*.csv.part') ?: [] as $f) {
            @unlink($f);
        }
    }

    private function sendSnapshotCsv(string $path, string $filename): void
    {
        $size = filesize($path);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }
        readfile($path);
    }

    /**
     * Gera snapshot CSV via bcp out (rápido). Usado se o arquivo ainda não existe
     * (cache antigo) ou no fallback sem dual-write.
     */
    private function buildSnapshotCsvBcp(string $syncId, array $columns): void
    {
        $bcpBin = $this->bcpBinary();
        if ($bcpBin === null) {
            throw new \RuntimeException('bcp indisponível.');
        }
        $this->ensureBcpView(Connection::get());

        global $config;
        $db = $config['db'] ?? [];
        $server = (string)($db['server'] ?? '');
        $database = (string)($db['database'] ?? '');
        $user = (string)($db['username'] ?? '');
        $pass = (string)($db['password'] ?? '');
        if ($server === '' || $database === '' || $user === '') {
            throw new \RuntimeException('Config db incompleta para bcp.');
        }

        $final = $this->snapshotCsvPath($syncId);
        $dataFile = $final . '.bcp';
        $errFile = $dataFile . '.err';
        $outFile = $dataFile . '.out';
        $errOut = $dataFile . '.stderr';
        @unlink($dataFile);

        $cmd = [
            $bcpBin,
            $database . '.dbo.VW_RH_ComposicaoBcp',
            'out',
            $dataFile,
            '-S', $server,
            '-U', $user,
            '-P', $pass,
            '-c',
            '-C', '65001',
            '-t', ';',
            '-r', "\n",
            '-q',
            '-e', $errFile,
        ];
        if (!empty($db['trust_server_certificate'])) {
            $cmd[] = '-u';
        }
        if (!empty($db['encrypt'])) {
            $cmd[] = '-Ym';
        }

        $desc = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $outFile, 'w'],
            2 => ['file', $errOut, 'w'],
        ];
        $proc = proc_open($cmd, $desc, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Falha ao iniciar bcp out.');
        }
        $code = 1;
        while (true) {
            $st = proc_get_status($proc);
            if (empty($st['running'])) {
                $code = (int)($st['exitcode'] ?? 1);
                break;
            }
            usleep(200000);
        }
        proc_close($proc);

        $combined = '';
        foreach ([$outFile, $errOut, $errFile] as $f) {
            if (is_file($f)) {
                $combined .= "\n" . (string)file_get_contents($f);
                @unlink($f);
            }
        }
        $combined = $this->safeUtf8(trim($combined));
        $copiedOk = (bool)preg_match('/\b(\d+)\s+rows copied\b/i', $combined, $m)
            && (int)$m[1] > 0
            && !preg_match('/\bError\s*=/i', $combined);
        if (($code !== 0 && !$copiedOk) || !is_file($dataFile)) {
            @unlink($dataFile);
            throw new \RuntimeException('bcp out falhou: ' . mb_substr($combined, 0, 800));
        }

        $fp = fopen($final, 'wb');
        if ($fp === false) {
            @unlink($dataFile);
            throw new \RuntimeException('Não foi possível gravar CSV snapshot.');
        }
        fwrite($fp, "\xEF\xBB\xBF");
        fwrite($fp, $this->csvLine($columns ?: $this->dataColumnNames()));
        $in = fopen($dataFile, 'rb');
        if ($in) {
            stream_copy_to_stream($in, $fp);
            fclose($in);
        }
        fclose($fp);
        @unlink($dataFile);
        $this->purgeOldSnapshotCsvs($syncId);
    }

    /** Linha CSV ; com aspas só quando necessário (bem mais leve que fputcsv). */
    private function csvLine(array $fields): string
    {
        $parts = [];
        foreach ($fields as $f) {
            if ($f === null) {
                $parts[] = '';
                continue;
            }
            $s = (string)$f;
            if ($s !== '' && strpbrk($s, ";\"\r\n") !== false) {
                $parts[] = '"' . str_replace('"', '""', $s) . '"';
            } else {
                $parts[] = $s;
            }
        }
        return implode(';', $parts) . "\r\n";
    }

    /** Formatação leve para export (sem parseDecimal pesado por célula). */
    private function formatExportCell($val, string $type): string
    {
        if ($val === null || $val === '') {
            return '';
        }
        if ($val instanceof \DateTimeInterface) {
            return $val->format('Y-m-d');
        }
        if ($type === 'd') {
            $s = (string)$val;
            return strlen($s) >= 10 ? substr($s, 0, 10) : $s;
        }
        if ($type === 'm') {
            $s = trim((string)$val);
            if ($s === '') {
                return '';
            }
            // DECIMAL do SQL → "8476.0000" → "8476,00"
            if (preg_match('/^-?\d+(\.\d+)?$/', $s)) {
                $neg = $s[0] === '-';
                if ($neg) {
                    $s = substr($s, 1);
                }
                $parts = explode('.', $s, 2);
                $int = $parts[0];
                $frac = isset($parts[1]) ? substr(str_pad($parts[1], 2, '0'), 0, 2) : '00';
                return ($neg ? '-' : '') . $int . ',' . $frac;
            }
            $n = $this->parseDecimalOrNull($s);
            return $n === null ? $s : number_format((float)$n, 2, ',', '');
        }
        if ($type === 'i') {
            return (string)(int)$val;
        }
        return (string)$val;
    }

    /* ─── SQL page fetch ─────────────────────────────────────────────────── */

    private function pageFetch(string $syncId, array $columns, ListTable $lt): array
    {
        $pdo = Connection::get();
        [$where, $params] = $this->buildFilterSql($syncId, $columns, $lt);

        $stCount = $pdo->prepare("SELECT COUNT(1) FROM dbo.RH_ComposicaoRemuneracao WHERE {$where}");
        $stCount->execute($params);
        $total = (int)$stCount->fetchColumn();

        $sortCol = strtoupper($lt->getSort());
        if ($sortCol === '' || (!empty($columns) && !in_array($sortCol, $columns, true))) {
            $sortCol = 'CONTRATO';
        }
        $dir = strtolower($lt->getDir()) === 'desc' ? 'DESC' : 'ASC';
        $orderSql = $this->orderBySql($sortCol, $dir);
        $selectSql = $this->selectListSql($columns);

        $perPage = $lt->getPerPage();
        $page = $lt->getPage();
        $offset = max(0, ($page - 1) * $perPage);
        $fetch = (int)$perPage;

        $sql = "
            SELECT {$selectSql}
            FROM dbo.RH_ComposicaoRemuneracao
            WHERE {$where}
            {$orderSql}
            OFFSET {$offset} ROWS FETCH NEXT {$fetch} ROWS ONLY
        ";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = [];
        while ($r = $st->fetch(\PDO::FETCH_ASSOC)) {
            $rows[] = $this->normalizeFetchedRow($r, $columns);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    private function normalizeFetchedRow(array $r, array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            if (array_key_exists($col, $r)) {
                $out[$col] = $r[$col];
            } else {
                $out[$col] = $r[strtolower($col)] ?? null;
            }
        }
        return $out;
    }

    private function selectListSql(array $columns): string
    {
        $allowed = array_fill_keys($this->dataColumnNames(), true);
        $parts = [];
        foreach ($columns as $c) {
            $c = strtoupper((string)$c);
            if (isset($allowed[$c])) {
                $parts[] = '[' . $c . ']';
            }
        }
        if (empty($parts)) {
            foreach ($this->dataColumnNames() as $c) {
                $parts[] = '[' . $c . ']';
            }
        }
        return implode(', ', $parts);
    }

    private function buildFilterSql(string $syncId, array $columns, ListTable $lt): array
    {
        $where = ['SyncId = CAST(? AS uniqueidentifier)'];
        $params = [$syncId];
        $fv = $lt->getFilterValues();
        $allowed = array_fill_keys($this->dataColumnNames(), true);

        foreach ($lt->cols() as $colKey => $col) {
            if (empty($col['param']) || empty($col['filter'])) {
                continue;
            }
            $val = trim((string)($fv[$col['param']] ?? ''));
            if ($val === '') {
                continue;
            }
            $colU = strtoupper((string)$colKey);
            if (!isset($allowed[$colU])) {
                continue;
            }
            $expr = '[' . $colU . ']';
            $type = self::COL_DEFS[$colU]['t'];

            if (($col['filter'] ?? '') === 'select') {
                $where[] = "{$expr} = ?";
                $params[] = $val;
                continue;
            }

            if ($colU === 'CPF' || $colU === 'CPF_LIDER' || $colU === 'CNPJ') {
                $digits = preg_replace('/\D+/', '', $val) ?? '';
                if ($digits === '') {
                    continue;
                }
                $where[] = "{$expr} LIKE ?";
                $params[] = '%' . $digits . '%';
                continue;
            }

            if ($type === 'd') {
                if (preg_match('/^\d{4}$/', $val)) {
                    $where[] = "YEAR({$expr}) = ?";
                    $params[] = (int)$val;
                } else {
                    $where[] = "CONVERT(varchar(10), {$expr}, 103) LIKE ?";
                    $params[] = '%' . $val . '%';
                }
                continue;
            }

            if ($type === 'm' || $type === 'i') {
                $digits = preg_replace('/\D+/', '', $val) ?? '';
                if ($digits === '') {
                    continue;
                }
                $where[] = "REPLACE(REPLACE(CAST({$expr} AS nvarchar(40)), '.', ''), ',', '') LIKE ?";
                $params[] = '%' . $digits . '%';
                continue;
            }

            $where[] = "UPPER(CAST({$expr} AS nvarchar(4000))) LIKE UPPER(?)";
            $params[] = '%' . $val . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    private function isDateFilterColumn(string $colU): bool
    {
        $t = self::COL_DEFS[$colU]['t'] ?? '';
        if ($t === 'd') {
            return true;
        }
        return str_contains($colU, 'DATA')
            || str_contains($colU, 'DATE')
            || $colU === 'NASCIMENTO';
    }

    private function orderBySql(string $sortCol, string $dir): string
    {
        $dir = $dir === 'DESC' ? 'DESC' : 'ASC';
        $allowed = array_fill_keys($this->dataColumnNames(), true);
        if ($sortCol === 'CONTRATO') {
            return "ORDER BY [CONTRATO] {$dir}, [NOME] ASC, [DATALANCAMENTO] DESC, [RowNum] ASC";
        }
        if ($sortCol === 'NOME') {
            return "ORDER BY [NOME] {$dir}, [CONTRATO] ASC, [DATALANCAMENTO] DESC, [RowNum] ASC";
        }
        if ($sortCol === 'DATALANCAMENTO') {
            return "ORDER BY [DATALANCAMENTO] {$dir}, [CONTRATO] ASC, [NOME] ASC, [RowNum] ASC";
        }
        if (!isset($allowed[$sortCol])) {
            return 'ORDER BY [CONTRATO] ASC, [NOME] ASC, [DATALANCAMENTO] DESC, [RowNum] ASC';
        }
        return "ORDER BY [{$sortCol}] {$dir}, [RowNum] ASC";
    }

    /* ─── Oracle / SQL insert ────────────────────────────────────────────── */

    private function normalizeRow(array $row): array
    {
        $norm = [];
        foreach ($row as $k => $v) {
            $key = strtoupper((string)$k);
            if ($v instanceof \DateTimeInterface) {
                $norm[$key] = $v->format('Y-m-d H:i:s');
            } elseif (is_resource($v)) {
                $norm[$key] = stream_get_contents($v) ?: '';
            } else {
                $norm[$key] = $v;
            }
        }
        return $norm;
    }

    /** @param list<string> $keys */
    private function filterKnownColumns(array $keys): array
    {
        $allowed = array_fill_keys($this->dataColumnNames(), true);
        $out = [];
        foreach ($keys as $k) {
            $k = strtoupper((string)$k);
            if (isset($allowed[$k])) {
                $out[] = $k;
            }
        }
        return $out ?: $this->dataColumnNames();
    }

    private function insertSqlBatches(\PDO $pdo, string $syncId, array $rows, int $startRowNum): void
    {
        if (empty($rows)) {
            return;
        }
        $cols = $this->dataColumnNames();
        $payload = [];
        $rowNum = $startRowNum;
        foreach ($rows as $row) {
            $rowNum++;
            $obj = ['RowNum' => $rowNum];
            foreach ($cols as $col) {
                $obj[$col] = $this->coerceValue($col, $row[$col] ?? null);
            }
            $payload[] = $obj;
        }
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        try {
            $this->insertOpenJsonStatement($pdo)->execute([$syncId, $json]);
            if ($ownTx) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function insertOpenJsonStatement(\PDO $pdo): \PDOStatement
    {
        if (!isset($this->insertStmts[0])) {
            $cols = $this->dataColumnNames();
            $insertCols = '[SyncId],[RowNum],[' . implode('],[', $cols) . ']';
            $selectCols = 'CAST(? AS uniqueidentifier), [RowNum], [' . implode('],[', $cols) . ']';
            $with = ["[RowNum] INT '$.RowNum'"];
            foreach (self::COL_DEFS as $name => $def) {
                $sqlType = match ($def['t']) {
                    'd' => 'DATE',
                    'i' => 'INT',
                    'm' => 'DECIMAL(18,4)',
                    default => 'NVARCHAR(' . (int)($def['len'] ?? 200) . ')',
                };
                $with[] = "[{$name}] {$sqlType} '$.{$name}'";
            }
            $sql = "INSERT INTO dbo.RH_ComposicaoRemuneracao ({$insertCols})
                    SELECT {$selectCols}
                    FROM OPENJSON(?) WITH (" . implode(', ', $with) . ')';
            $this->insertStmts[0] = $pdo->prepare($sql);
        }
        return $this->insertStmts[0];
    }

    private function tsvRow(string $syncId, int $rowNum, array $row): string
    {
        $parts = [$syncId, (string)$rowNum];
        foreach ($this->dataColumnNames() as $col) {
            $v = $this->coerceValue($col, $row[$col] ?? null);
            if ($v === null) {
                $parts[] = '';
                continue;
            }
            $s = (string)$v;
            $parts[] = str_replace(["\t", "\n", "\r"], ' ', $s);
        }
        return implode("\t", $parts) . "\n";
    }

    private function bcpBinary(): ?string
    {
        foreach (['/opt/mssql-tools18/bin/bcp', '/opt/mssql-tools/bin/bcp'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        return null;
    }

    private function runBcpLoad(string $bcpBin, string $tsvPath, string $syncId, int $rowCount): void
    {
        global $config;
        $db = $config['db'] ?? [];
        $server = (string)($db['server'] ?? '');
        $database = (string)($db['database'] ?? '');
        $user = (string)($db['username'] ?? '');
        $pass = (string)($db['password'] ?? '');
        if ($server === '' || $database === '' || $user === '') {
            throw new \RuntimeException('Config db incompleta para bcp.');
        }
        if (!is_file($tsvPath) || filesize($tsvPath) === 0) {
            throw new \RuntimeException('Arquivo TSV vazio — nada para carregar.');
        }

        $this->ensureBcpView(Connection::get());

        $errFile = $tsvPath . '.err';
        $outFile = $tsvPath . '.out';
        $errOut  = $tsvPath . '.stderr';
        @unlink($errFile);
        @unlink($outFile);
        @unlink($errOut);

        $cmd = [
            $bcpBin,
            $database . '.dbo.VW_RH_ComposicaoBcp',
            'in',
            $tsvPath,
            '-S', $server,
            '-U', $user,
            '-P', $pass,
            '-c',
            '-C', '65001',
            '-t', "\t",
            '-r', "\n",
            '-k',
            '-q',
            '-b', '5000',
            '-e', $errFile,
        ];
        if (!empty($db['trust_server_certificate'])) {
            $cmd[] = '-u';
        }
        if (!empty($db['encrypt'])) {
            $cmd[] = '-Ym';
        }

        $desc = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $outFile, 'w'],
            2 => ['file', $errOut, 'w'],
        ];
        $proc = proc_open($cmd, $desc, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Falha ao iniciar bcp.');
        }

        $t0 = microtime(true);
        $lastBeat = 0.0;
        $code = 1;
        while (true) {
            $st = proc_get_status($proc);
            $running = !empty($st['running']);
            $now = microtime(true);
            if ($now - $lastBeat >= 3.0) {
                $elapsed = max(1, (int)round($now - $t0));
                // Progresso estimado 90→98 enquanto bcp roda (sem contador nativo confiável).
                $pct = min(98, 90 + (int)floor($elapsed / 15));
                $this->updateJob($syncId, [
                    'Step'     => 'writing',
                    'Done'     => $rowCount,
                    'Progress' => $pct,
                    'Message'  => sprintf(
                        'Atualizando dados no SQL Server (%s registros, %ds)...',
                        number_format($rowCount, 0, ',', '.'),
                        $elapsed
                    ),
                ]);
                $lastBeat = $now;
            }
            if (!$running) {
                // exitcode só é válido na primeira leitura com running=false;
                // proc_close() depois disso costuma retornar -1.
                $code = (int)($st['exitcode'] ?? 1);
                break;
            }
            usleep(500000);
        }
        proc_close($proc);

        $out = is_file($outFile) ? (string)file_get_contents($outFile) : '';
        $err = is_file($errOut) ? (string)file_get_contents($errOut) : '';
        $errExtra = is_file($errFile) ? trim((string)file_get_contents($errFile)) : '';
        @unlink($outFile);
        @unlink($errOut);
        @unlink($errFile);

        $combined = $this->safeUtf8(trim($err . "\n" . $out . "\n" . $errExtra));
        $copiedOk = (bool)preg_match('/\b(\d+)\s+rows copied\b/i', $combined, $m)
            && (int)$m[1] > 0
            && !preg_match('/\bError\s*=/i', $combined);

        if ($code !== 0 && !$copiedOk) {
            $msg = preg_replace('/-P\s+\S+/', '-P ***', $combined) ?? $combined;
            $msg = preg_replace('/Password:\s*\S+/i', 'Password: ***', $msg) ?? $msg;
            throw new \RuntimeException('Carga bulk falhou: ' . mb_substr($msg, 0, 1500));
        }
    }

    private function safeUtf8(string $s): string
    {
        if ($s === '') {
            return '';
        }
        if (mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        $converted = @mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? '';
    }

    private function coerceValue(string $col, $raw)
    {
        $def = self::COL_DEFS[$col] ?? ['t' => 'n', 'len' => 200];
        return match ($def['t']) {
            'd' => $this->parseDateOrNull($raw),
            'i' => $this->parseIntOrNull($raw),
            'm' => $this->parseDecimalOrNull($raw),
            default => $this->strOrNull($raw, (int)($def['len'] ?? 200)),
        };
    }

    private function activateSync(\PDO $pdo, string $syncId): void
    {
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE dbo.RH_ComposicaoJob SET IsActive = 0 WHERE IsActive = 1');
            $st = $pdo->prepare('UPDATE dbo.RH_ComposicaoJob SET IsActive = 1 WHERE SyncId = CAST(? AS uniqueidentifier)');
            $st->execute([$syncId]);

            $stDel = $pdo->prepare('
                DELETE FROM dbo.RH_ComposicaoRemuneracao
                WHERE SyncId <> CAST(? AS uniqueidentifier)
            ');
            $stDel->execute([$syncId]);

            $stOld = $pdo->prepare("
                ;WITH old AS (
                    SELECT SyncId,
                           ROW_NUMBER() OVER (ORDER BY StartedAt DESC) AS rn
                    FROM dbo.RH_ComposicaoJob
                    WHERE SyncId <> CAST(? AS uniqueidentifier)
                )
                DELETE FROM dbo.RH_ComposicaoJob
                WHERE SyncId IN (SELECT SyncId FROM old WHERE rn > 5)
            ");
            $stOld->execute([$syncId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function strOrNull($v, int $max): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = (string)$v;
        if (mb_strlen($s, 'UTF-8') > $max) {
            $s = mb_substr($s, 0, $max, 'UTF-8');
        }
        return $s;
    }

    private function parseDateOrNull($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $s = trim((string)$v);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            $ts = strtotime($s);
            return $ts !== false ? date('Y-m-d', $ts) : null;
        }
        return null;
    }

    private function parseIntOrNull($v): ?int
    {
        $d = $this->parseDecimalOrNull($v);
        if ($d === null) {
            return null;
        }
        return (int)round((float)$d);
    }

    private function parseDecimalOrNull($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (string)$v;
        }
        $s = str_replace(' ', '', trim((string)$v));
        if ($s === '') {
            return null;
        }
        if (preg_match('/^-?\d{1,3}(\.\d{3})+,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})+\.\d+$/', $s)) {
            $s = str_replace(',', '', $s);
        } elseif (preg_match('/^-?\d+,\d+$/', $s)) {
            $s = str_replace(',', '.', $s);
        }
        if (!is_numeric($s)) {
            return null;
        }
        return $s;
    }

    /* ─── Job helpers ────────────────────────────────────────────────────── */

    private function readLatestJob(): ?array
    {
        $pdo = Connection::get();
        $st = $pdo->query('
            SELECT TOP 1 *
            FROM dbo.RH_ComposicaoJob
            ORDER BY StartedAt DESC
        ');
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function readRunningJob(): ?array
    {
        $pdo = Connection::get();
        $st = $pdo->query("
            SELECT TOP 1 *
            FROM dbo.RH_ComposicaoJob
            WHERE Status IN ('running', 'queued')
            ORDER BY StartedAt DESC
        ");
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function readActiveDoneJob(): ?array
    {
        $pdo = Connection::get();
        $st = $pdo->query("
            SELECT TOP 1 *
            FROM dbo.RH_ComposicaoJob
            WHERE IsActive = 1 AND Status = 'done'
            ORDER BY FetchedAt DESC, FinishedAt DESC
        ");
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function updateJob(string $syncId, array $fields): void
    {
        $fields['UpdatedAt'] = $this->now();
        $sets = [];
        $params = [];
        foreach ($fields as $col => $val) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $col)) {
                continue;
            }
            if ($col === 'WorkerPid' && $val === null) {
                $sets[] = "[{$col}] = NULL";
                continue;
            }
            if ($col === 'Error' && $val === null) {
                $sets[] = "[{$col}] = NULL";
                continue;
            }
            $sets[] = "[{$col}] = ?";
            if (is_string($val)) {
                $params[] = $this->safeUtf8($val);
            } else {
                $params[] = $val;
            }
        }
        if (empty($sets)) {
            return;
        }
        $params[] = $syncId;
        $sql = 'UPDATE dbo.RH_ComposicaoJob SET ' . implode(', ', $sets)
             . ' WHERE SyncId = CAST(? AS uniqueidentifier)';
        $st = Connection::get()->prepare($sql);
        $st->execute($params);
    }

    private function jobToMeta(?array $job): array
    {
        if (!$job) {
            return [
                'status'     => 'idle',
                'step'       => '',
                'message'    => '',
                'progress'   => 0,
                'total'      => 0,
                'done'       => 0,
                'columns'    => $this->dataColumnNames(),
                'row_count'  => 0,
                'fetched_at' => null,
                'worker_pid' => null,
            ];
        }
        $columns = [];
        if (!empty($job['ColumnsJson'])) {
            $decoded = json_decode((string)$job['ColumnsJson'], true);
            if (is_array($decoded)) {
                $columns = $decoded;
            }
        }
        return [
            'status'      => strtolower((string)($job['Status'] ?? 'idle')),
            'step'        => (string)($job['Step'] ?? ''),
            'message'     => (string)($job['Message'] ?? ''),
            'progress'    => (int)($job['Progress'] ?? 0),
            'total'       => (int)($job['Total'] ?? 0),
            'done'        => (int)($job['Done'] ?? 0),
            'columns'     => $this->filterKnownColumns($columns ?: $this->dataColumnNames()),
            'row_count'   => (int)($job['Done'] ?? 0),
            'fetched_at'  => $this->fmtDt($job['FetchedAt'] ?? null),
            'started_at'  => $this->fmtDt($job['StartedAt'] ?? null),
            'updated_at'  => $this->fmtDt($job['UpdatedAt'] ?? null),
            'finished_at' => $this->fmtDt($job['FinishedAt'] ?? null),
            'error'       => $job['Error'] ?? null,
            'worker_pid'  => isset($job['WorkerPid']) ? (int)$job['WorkerPid'] : null,
            'sync_id'     => (string)($job['SyncId'] ?? ''),
        ];
    }

    private function columnsFromJob(?array $jobOrMeta): array
    {
        $fixed = $this->dataColumnNames();
        if (!$jobOrMeta) {
            return $fixed;
        }
        $fromJob = [];
        if (isset($jobOrMeta['ColumnsJson'])) {
            $decoded = json_decode((string)$jobOrMeta['ColumnsJson'], true);
            if (is_array($decoded)) {
                $fromJob = $decoded;
            }
        } elseif (is_array($jobOrMeta['columns'] ?? null)) {
            $fromJob = $jobOrMeta['columns'];
        }
        return $this->filterKnownColumns($fromJob ?: $fixed);
    }

    private function fmtDt($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }
        $ts = strtotime((string)$v);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : (string)$v;
    }

    /* ─── Schema ─────────────────────────────────────────────────────────── */

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $pdo = Connection::get();

        $needsReset = false;
        try {
            $st = $pdo->query("
                SELECT
                    COL_LENGTH('dbo.RH_ComposicaoRemuneracao', 'Payload') AS payload_len,
                    COL_LENGTH('dbo.RH_ComposicaoRemuneracao', 'SALARIO_CONTRATUAL_HIST') AS typed_len,
                    OBJECT_ID('dbo.RH_ComposicaoRemuneracao', 'U') AS tbl
            ");
            $info = $st ? $st->fetch(\PDO::FETCH_ASSOC) : null;
            if ($info) {
                if (!empty($info['payload_len'])) {
                    $needsReset = true;
                } elseif (!empty($info['tbl']) && empty($info['typed_len'])) {
                    $needsReset = true;
                }
            }
        } catch (\Throwable $e) {
            $needsReset = false;
        }

        if ($needsReset) {
            $pdo->exec("
IF OBJECT_ID('dbo.RH_ComposicaoRemuneracao', 'U') IS NOT NULL
    DROP TABLE dbo.RH_ComposicaoRemuneracao;
IF OBJECT_ID('dbo.RH_ComposicaoJob', 'U') IS NOT NULL
    DROP TABLE dbo.RH_ComposicaoJob;
");
        }

        $pdo->exec("
IF OBJECT_ID('dbo.RH_ComposicaoJob', 'U') IS NULL
CREATE TABLE dbo.RH_ComposicaoJob (
    SyncId       UNIQUEIDENTIFIER NOT NULL PRIMARY KEY,
    Status       VARCHAR(20)  NOT NULL,
    Step         VARCHAR(40)  NULL,
    Message      NVARCHAR(4000) NULL,
    Progress     INT NOT NULL CONSTRAINT DF_RH_CompJob_Progress DEFAULT (0),
    Total        INT NOT NULL CONSTRAINT DF_RH_CompJob_Total DEFAULT (0),
    Done         INT NOT NULL CONSTRAINT DF_RH_CompJob_Done DEFAULT (0),
    IsActive     BIT NOT NULL CONSTRAINT DF_RH_CompJob_IsActive DEFAULT (0),
    ColumnsJson  NVARCHAR(MAX) NULL,
    WorkerPid    INT NULL,
    Error        NVARCHAR(MAX) NULL,
    StartedAt    DATETIME2(0) NOT NULL,
    UpdatedAt    DATETIME2(0) NOT NULL,
    FinishedAt   DATETIME2(0) NULL,
    FetchedAt    DATETIME2(0) NULL
);
");

        $stExists = $pdo->query("SELECT OBJECT_ID('dbo.RH_ComposicaoRemuneracao', 'U')");
        $exists = $stExists && $stExists->fetchColumn();
        if (!$exists) {
            $pdo->exec($this->createRemuneracaoTableSql());
            $pdo->exec('CREATE INDEX IX_RH_Comp_Sync_Row ON dbo.RH_ComposicaoRemuneracao (SyncId, RowNum)');
            $pdo->exec('CREATE INDEX IX_RH_Comp_Sync_Contrato ON dbo.RH_ComposicaoRemuneracao (SyncId, CONTRATO)');
            $pdo->exec('CREATE INDEX IX_RH_Comp_Sync_Nome ON dbo.RH_ComposicaoRemuneracao (SyncId, NOME)');
        }

        $pdo->exec("
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_RH_CompJob_Active' AND object_id = OBJECT_ID('dbo.RH_ComposicaoJob'))
CREATE INDEX IX_RH_CompJob_Active ON dbo.RH_ComposicaoJob (IsActive, Status, FinishedAt DESC);
");

        $this->repairDisabledIndexesIfIdle($pdo);
        $done = true;
    }

    private function ensureBcpView(\PDO $pdo): void
    {
        $cols = '[SyncId], [RowNum], [' . implode('], [', $this->dataColumnNames()) . ']';
        $pdo->exec(
            "CREATE OR ALTER VIEW dbo.VW_RH_ComposicaoBcp AS SELECT {$cols} FROM dbo.RH_ComposicaoRemuneracao"
        );
    }

    private function createRemuneracaoTableSql(): string
    {
        $lines = [
            '[Id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY',
            '[SyncId] UNIQUEIDENTIFIER NOT NULL',
            '[RowNum] INT NOT NULL',
        ];
        foreach (self::COL_DEFS as $name => $def) {
            $col = match ($def['t']) {
                'd' => "[{$name}] DATE NULL",
                'i' => "[{$name}] INT NULL",
                'm' => "[{$name}] DECIMAL(18,4) NULL",
                default => "[{$name}] NVARCHAR(" . (int)($def['len'] ?? 200) . ") NULL",
            };
            $lines[] = $col;
        }
        $lines[] = '[CreatedAt] DATETIME2(0) NOT NULL CONSTRAINT DF_RH_Comp_CreatedAt DEFAULT (SYSDATETIME())';
        return "CREATE TABLE dbo.RH_ComposicaoRemuneracao (\n    " . implode(",\n    ", $lines) . "\n);";
    }

    private function disableLoadIndexes(\PDO $pdo): void
    {
        foreach (self::LOAD_INDEXES as $ix) {
            try {
                $pdo->exec("ALTER INDEX [{$ix}] ON dbo.RH_ComposicaoRemuneracao DISABLE");
            } catch (\Throwable $ignored) {
            }
        }
    }

    private function rebuildLoadIndexes(\PDO $pdo): void
    {
        foreach (self::LOAD_INDEXES as $ix) {
            try {
                $pdo->exec("ALTER INDEX [{$ix}] ON dbo.RH_ComposicaoRemuneracao REBUILD");
            } catch (\Throwable $ignored) {
            }
        }
    }

    private function repairDisabledIndexesIfIdle(\PDO $pdo): void
    {
        try {
            $st = $pdo->query("
                SELECT COUNT(1)
                FROM dbo.RH_ComposicaoJob
                WHERE Status IN ('running', 'queued')
            ");
            $running = $st ? (int)$st->fetchColumn() : 0;
            if ($running > 0) {
                return;
            }
            $stIx = $pdo->query("
                SELECT i.name
                FROM sys.indexes i
                WHERE i.object_id = OBJECT_ID('dbo.RH_ComposicaoRemuneracao')
                  AND i.is_disabled = 1
                  AND i.name IS NOT NULL
            ");
            if (!$stIx) {
                return;
            }
            while ($row = $stIx->fetch(\PDO::FETCH_ASSOC)) {
                $name = (string)($row['name'] ?? '');
                if ($name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                    continue;
                }
                $pdo->exec("ALTER INDEX [{$name}] ON dbo.RH_ComposicaoRemuneracao REBUILD");
            }
        } catch (\Throwable $ignored) {
        }
    }

    private function applyOraclePrefetch(\PDO $pdo, $stmt = null): void
    {
        $attrs = [\PDO::ATTR_PREFETCH];
        if (defined('PDO::OCI_ATTR_PREFETCH')) {
            $attrs[] = \PDO::OCI_ATTR_PREFETCH;
        }
        foreach ($attrs as $attr) {
            try {
                $pdo->setAttribute($attr, 1000);
            } catch (\Throwable $ignored) {
            }
            if ($stmt instanceof \PDOStatement) {
                try {
                    $stmt->setAttribute($attr, 1000);
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    private function isProcessAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $out = [];
            exec('tasklist /FI "PID eq ' . $pid . '" 2>NUL', $out);
            return count($out) > 1;
        }
        return true;
    }

    private function storageDir(): string
    {
        $dir = __DIR__ . '/../../storage/rh';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function uuidv4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    /* ─── List helpers ───────────────────────────────────────────────────── */

    private function buildListCols(array $columns): array
    {
        $cols = [];
        foreach ($columns as $col) {
            $col = strtoupper((string)$col);
            if (!isset(self::COL_DEFS[$col])) {
                continue;
            }
            $param = 'f_' . strtolower(preg_replace('/[^A-Za-z0-9_]/', '_', $col));
            $def = [
                'label'    => $this->humanizeColumn($col),
                'sortable' => true,
                'filter'   => 'text',
                'param'    => $param,
                'sql_col'  => $col,
                'th_class' => $this->isDateFilterColumn($col) ? 'col-rh-date' : '',
                'render'   => function ($val) use ($col) {
                    return e($this->formatCellValue($val, $col, true));
                },
            ];

            if ($col === 'SEXO') {
                $def['filter']  = 'select';
                $def['options'] = ['' => 'Todos', 'M' => 'M', 'F' => 'F'];
            }

            $cols[$col] = $def;
        }
        if (empty($cols)) {
            return $this->buildListCols($this->dataColumnNames());
        }
        return $cols;
    }

    private function humanizeColumn(string $col): string
    {
        static $map = [
            'CONTRATO'                          => 'Contrato',
            'TIPOCONTRATO'                      => 'Tipo de contrato',
            'VINCULOEMPREGATICIO'               => 'Vínculo empregatício',
            'NOME'                              => 'Nome',
            'CARGO'                             => 'Cargo',
            'SEXO'                              => 'Sexo',
            'NASCIMENTO'                        => 'Nascimento',
            'CPF'                               => 'CPF',
            'UNIDADE'                           => 'Unidade',
            'CODIGOEMPRESA'                     => 'Cód. empresa',
            'NOMEEMPRESA'                       => 'Empresa',
            'EMPRESA'                           => 'Empresa',
            'CODIGOESTABELECIMENTO'             => 'Cód. estabelecimento',
            'NOMEESTABELECIMENTO'               => 'Estabelecimento',
            'CNPJ'                              => 'CNPJ',
            'NOMECARGOGERENTE'                  => 'Cargo do gerente',
            'NOMEGERENTE'                       => 'Gerente',
            'CODIGOCLASSIFICACAOCONTABIL'       => 'Cód. classificação contábil',
            'DESCRICAOCLASSIFICACAOCONTABIL'    => 'Classificação contábil',
            'CODIGOCENTRODECUSTO'               => 'Cód. centro de custo',
            'DESCRICAOCENTROCUSTO1'             => 'Centro de custo',
            'CODIGOSITUACAO'                    => 'Cód. situação',
            'FERIASNOMES'                       => 'Férias no mês',
            'DATAADMISSAO'                      => 'Admissão',
            'MESESCASA'                         => 'Meses de casa',
            'MESESCARGO'                        => 'Meses no cargo',
            'DATAULTTRANSFERENCIA'              => 'Últ. transferência',
            'DATAULTIMOREAJUSTE'                => 'Últ. reajuste',
            'MOTIVOALTERACAOSALARIO'            => 'Motivo alteração salarial',
            'HORASCONTRATUAIS'                  => 'Horas contratuais',
            'DATALANCAMENTO'                    => 'Data lançamento',
            'SALARIO_CONTRATUAL_HIST'           => 'Salário contratual (hist.)',
            'SALARIO_CONTRATUAL_FOLHA'          => 'Salário contratual (folha)',
            'DECIMO_TERCEIRO'                   => '13º salário',
            'FERIAS'                            => 'Férias',
            'INSS'                              => 'INSS',
            'GARANTIA_MINIMA'                   => 'Garantia mínima',
            'FGTS'                              => 'FGTS',
            'AJUDA_DE_CUSTO'                    => 'Ajuda de custo',
            'ADIC_TEMPO_CASA'                   => 'Adic. tempo de casa',
            'INSALUBRIDADE'                     => 'Insalubridade',
            'PERICULOSIDADE'                    => 'Periculosidade',
            'HORA_EXTRA_50'                     => 'Hora extra 50%',
            'HORA_EXTRA_60'                     => 'Hora extra 60%',
            'HORA_EXTRA_100'                    => 'Hora extra 100%',
            'REPOUSO_HE'                        => 'Repouso HE',
            'HORA_EXTRA_M'                      => 'Hora extra (R$)',
            'ADICIONAL_NOTURNO'                 => 'Adicional noturno',
            'ADICIONAL_NOTURNO_M'               => 'Adicional noturno (R$)',
            'COMISSAO'                          => 'Comissão',
            'COMISSAO_M'                        => 'Comissão (R$)',
            'REPOUSO_COMISSAO'                  => 'Repouso comissão',
            'REPOUSO_COMISSAO_M'                => 'Repouso comissão (R$)',
            'PREMIACAO'                         => 'Premiação',
            'PREMIACAO_M'                       => 'Premiação (R$)',
            'BONIFICACAO'                       => 'Bonificação',
            'BONIFICACAO_M'                     => 'Bonificação (R$)',
            'META_DIRETORIA'                    => 'Meta diretoria',
            'META_DIRETORIA_M'                  => 'Meta diretoria (R$)',
            'TREINAMENTO'                       => 'Treinamento',
            'TREINAMENTO_M'                     => 'Treinamento (R$)',
            'SALARIO_DOENCA_SOBRE_COMISSAO'     => 'Salário-doença s/ comissão',
            'SALARIO_DOENCA_SOBRE_COMISSAO_M'   => 'Salário-doença s/ comissão (R$)',
            'ASS_MEDICA_COLABORADOR_EMPREGADO'  => 'Assist. médica (colaborador)',
            'ASS_ODONTOLOGICA'                  => 'Assist. odontológica',
            'ASS_ODONTOLOGICA_DEPENDENTES'      => 'Assist. odontológica (dependentes)',
            'ALIMENTACAO'                       => 'Alimentação',
            'TRANSPORTE'                        => 'Transporte',
            'AUXILIO_MOBILIDADE'                => 'Auxílio mobilidade',
            'TOTAL_BRUTO_DESLIGAMENTO'          => 'Total bruto desligamento',
            'MULTA_FGTS'                        => 'Multa FGTS',
            'FGTS_RESCISAO'                     => 'FGTS rescisão',
            'DATARESCISAO'                      => 'Rescisão',
            'CODIGOMOTIVORESCISAO'              => 'Cód. motivo rescisão',
            'MOTIVORESCISAO'                    => 'Motivo rescisão',
            'CPF_LIDER'                         => 'CPF do líder',
            'CODIGO'                            => 'Código',
            'VALOR'                             => 'Valor',
            'EVENTO'                            => 'Evento',
        ];

        $colU = strtoupper($col);
        if (isset($map[$colU])) {
            return $map[$colU];
        }

        $s = str_replace('_', ' ', $colU);
        return mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function formatCellValue($val, string $col, bool $forDisplay): string
    {
        if ($val === null || $val === '') {
            return '';
        }
        $colU = strtoupper($col);
        $type = self::COL_DEFS[$colU]['t'] ?? 'n';

        if ($val instanceof \DateTimeInterface) {
            return $forDisplay ? $val->format('d/m/Y') : $val->format('Y-m-d');
        }

        if ($type === 'd') {
            $s = (string)$val;
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
                $ts = strtotime($s);
                if ($ts !== false) {
                    return $forDisplay ? date('d/m/Y', $ts) : date('Y-m-d', $ts);
                }
            }
            return $s;
        }

        if ($type === 'm') {
            $n = $this->parseDecimalOrNull($val);
            if ($n === null) {
                return (string)$val;
            }
            return number_format((float)$n, 2, ',', '');
        }

        if ($type === 'i') {
            $n = $this->parseIntOrNull($val);
            return $n === null ? (string)$val : (string)$n;
        }

        return (string)$val;
    }

    private function now(): string
    {
        return (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
    }
}

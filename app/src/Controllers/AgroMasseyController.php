<?php
namespace Controllers;

use Database\Connection;

class AgroMasseyController
{
    // Marca fixa: MASSEY (96)
    private const MARCA_NOME = 'Massey Ferguson';
    private const MARCA_ID   = 96;

    // Defaults exibidos como texto
    private const DEF_OVERPRICE_TXT   = '15%';
    private const DEF_INDICECUSTO_TXT = '70%';

    // Parsing LP%
    private const MIN_LINE_LEN = 73; // até col 82 (0-based substr(72,10))

    // Performance de importação
    private const BATCH_ROWS         = 300;    // 300*6=1800 params < 2100 (limite do SQL Server)
    private const COMMIT_EVERY       = 30000;  // commit parcial a cada 30k linhas
    private const PROGRESS_EVERY     = 5000;   // atualiza JobProgress a cada 5k linhas
    private const IMPORT_CHUNK_LINES = 5000;   // linhas por chunk HTTP (import faseado)
    private const UPDATE_CHUNK_SIZE  = 100;    // produtos por chunk HTTP (update faseado)

    // Polling de atualização de preços — alinhado com o layout global (adminlte.php)
    public const UPDATE_POLL_INTERVAL_MS = 5000;  // intervalo entre ticks de polling (ms)
    public const UPDATE_STALE_TICKS      = 12;    // 12 × 5 s = 60 s sem progresso → timeout

    // Paginação: tamanhos disponíveis
    private const PAGE_SIZES = [25, 100, 1000, 5000];

    public function index()
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('agro.tabela_massey', 2);

        $action = (string)($_GET['action'] ?? '');

        switch ($action) {
            case 'upload':        return $this->ajaxUpload();
            case 'process':       return $this->ajaxProcess();
            case 'status':        return $this->ajaxStatus();
            case 'results':       return $this->renderResults();
            case 'export':        return $this->exportFromDb();
            case 'update':        return $this->updateFromDb();
            case 'update_all':       return $this->ajaxUpdateAll();       // batch SP (recomendado)
            case 'update_all_start': return $this->ajaxUpdateAllStart();  // inicia worker async
            case 'update_chunk':     return $this->ajaxUpdateChunk();     // fallback chunked
            case 'ping':          echo 'pong'; return;
            default:              return $this->renderMain();
        }
    }

    /* ================== RENDERIZADORES (main/results) ================== */

    private function renderMain()
    {
        return render_page('agro/tabela_massey.php', [
            'erro'             => null,
            'rows'             => [],
            'soSistema'        => [],
            'soPlanilha'       => [],
            'matchMeta'        => ['total'=>0,'p'=>1,'pp'=>25,'qsPattern'=>'','tabActive'=>true],
            'sysMeta'          => ['total'=>0,'p'=>1,'pp'=>25,'qsPattern'=>'','tabActive'=>false],
            'planMeta'         => ['total'=>0,'p'=>1,'pp'=>25,'qsPattern'=>'','tabActive'=>false],
            'filters'          => ['f_emp'=>'','f_cod'=>'','f_ref'=>'','f_desc'=>'','f_ncm'=>'','f_ncmid'=>''],
            'uiOver'           => self::DEF_OVERPRICE_TXT,
            'uiIndice'         => self::DEF_INDICECUSTO_TXT,
            'marcaNome'        => self::MARCA_NOME,
            'base'             => function_exists('base_url') ? base_url('agro/tabela-massey') : ''
        ]);
    }

    /**
     * results: agora com paginação e filtros por aba (MATCH/ONLY_SYS/ONLY_PLAN)
     * Querystring esperada: ?action=results&op=GUID&tab=match|sys|plan
     *  &ppM=25|100|1000|5000 &pM=1..N  (match)
     *  &ppS=... &pS=...                 (sys)
     *  &ppP=... &pP=...                 (plan)
     *  Filtros (aplicam por aba): f_emp, f_cod, f_ref, f_desc, f_ncm, f_ncmid
     */
    private function renderResults()
    {
        $op = (string)($_GET['op'] ?? '');
        if ($op === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
            return $this->renderMain();
        }

        // Aba ativa (padrão: match)
        $tab = (string)($_GET['tab'] ?? 'match');
        if (!in_array($tab, ['match','sys','plan'], true)) $tab = 'match';

        // Tamanhos de página e páginas correntes (cada aba mantém seu estado)
        $ppM = $this->readPageSize($_GET['ppM'] ?? 25);
        $ppS = $this->readPageSize($_GET['ppS'] ?? 25);
        $ppP = $this->readPageSize($_GET['ppP'] ?? 25);

        $pM  = max(1, (int)($_GET['pM'] ?? 1));
        $pS  = max(1, (int)($_GET['pS'] ?? 1));
        $pP  = max(1, (int)($_GET['pP'] ?? 1));

        // Filtros (mesmos nomes para todas as abas)
        $filters = $this->readFilters($_GET);

        // Carregar páginas a partir do banco (com total filtrado)
        $match = $this->pageFetch($op, 'MATCH',     $filters, $ppM, $pM);
        $sys   = $this->pageFetch($op, 'ONLY_SYS',  $filters, $ppS, $pS);
        $plan  = $this->pageFetch($op, 'ONLY_PLAN', $filters, $ppP, $pP);

        // QS auxiliar para montar links mantendo estado (por aba)
        $commonQS = $this->buildFiltersQS($filters); // f_emp=...&f_cod=... etc.

        $matchQS  = "action=results&op={$op}&tab=match&ppM={$ppM}&pM=%d" . ($commonQS ? "&{$commonQS}" : '');
        $sysQS    = "action=results&op={$op}&tab=sys&ppS={$ppS}&pS=%d"   . ($commonQS ? "&{$commonQS}" : '');
        $planQS   = "action=results&op={$op}&tab=plan&ppP={$ppP}&pP=%d"  . ($commonQS ? "&{$commonQS}" : '');

        return render_page('agro/tabela_massey.php', [
            'erro'             => null,
            'rows'             => $match['rows'],
            'soSistema'        => $sys['rows'],
            'soPlanilha'       => $plan['rows'],

            'matchMeta'        => ['total'=>$match['total'], 'p'=>$pM, 'pp'=>$ppM, 'qsPattern'=>$matchQS, 'tabActive'=>$tab==='match'],
            'sysMeta'          => ['total'=>$sys['total'],   'p'=>$pS, 'pp'=>$ppS, 'qsPattern'=>$sysQS,   'tabActive'=>$tab==='sys'],
            'planMeta'         => ['total'=>$plan['total'],  'p'=>$pP, 'pp'=>$ppP, 'qsPattern'=>$planQS,  'tabActive'=>$tab==='plan'],

            'filters'          => $filters,
            'uiOver'           => self::DEF_OVERPRICE_TXT,
            'uiIndice'         => self::DEF_INDICECUSTO_TXT,
            'marcaNome'        => self::MARCA_NOME,
            'base'             => function_exists('base_url') ? base_url('agro/tabela-massey') : ''
        ]);
    }

    /** Lê page size seguro */
    private function readPageSize($v): int
    {
        $v = (int)$v;
        return in_array($v, self::PAGE_SIZES, true) ? $v : 25;
    }

    /** Lê filtros do GET e padroniza */
    private function readFilters(array $src): array
    {
        $get = function($k) use ($src) {
            return isset($src[$k]) ? trim((string)$src[$k]) : '';
        };
        return [
            'f_emp'   => $get('f_emp'),
            'f_cod'   => $get('f_cod'),
            'f_ref'   => $get('f_ref'),
            'f_desc'  => $get('f_desc'),
            'f_ncm'   => $get('f_ncm'),
            'f_ncmid' => $get('f_ncmid'),
        ];
    }

    /** Monta QS de filtros para reaproveitar nos links */
    private function buildFiltersQS(array $filt): string
    {
        $parts = [];
        foreach ($filt as $k=>$v) {
            if ($v !== '') $parts[] = $k . '=' . urlencode($v);
        }
        return implode('&', $parts);
    }

    /**
     * Busca página filtrada (SQL Server, OFFSET/FETCH). Retorna:
     * ['rows'=>array, 'total'=>int]
     */
   /**
 * Busca página filtrada (SQL Server, paginação segura).
 * Retorna: ['rows'=>array, 'total'=>int]
 */
private function pageFetch(string $op, string $tipo, array $filters, int $pp, int $p): array
{
    $pdo = Connection::get();

    // WHERE base
    $where  = "OpID = CAST(? AS uniqueidentifier) AND Tipo = ?";
    $params = [$op, $tipo];

    // Filtros (UPPER(col) LIKE UPPER(?)) – contém
    if ($filters['f_emp']   !== '') { $where .= " AND UPPER(ISNULL(Empresa,'')) LIKE UPPER(?)";             $params[] = '%'.$filters['f_emp'].'%'; }
    if ($filters['f_cod']   !== '') { $where .= " AND CONVERT(varchar(50), ISNULL(ProdutoCodigo,'')) LIKE ?"; $params[] = '%'.$filters['f_cod'].'%'; }
    if ($filters['f_ref']   !== '') { $where .= " AND UPPER(ISNULL(ProdutoReferencia,'')) LIKE UPPER(?)";   $params[] = '%'.$filters['f_ref'].'%'; }
    if ($filters['f_desc']  !== '') { $where .= " AND UPPER(ISNULL(ProdutoDescricao,'')) LIKE UPPER(?)";    $params[] = '%'.$filters['f_desc'].'%'; }
    if ($filters['f_ncm']   !== '') { $where .= " AND CONVERT(varchar(50), ISNULL(NCMCodigo,'')) LIKE ?";   $params[] = '%'.$filters['f_ncm'].'%'; }
    if ($filters['f_ncmid'] !== '') { $where .= " AND UPPER(ISNULL(NCMIdentificador,'')) LIKE UPPER(?)";    $params[] = '%'.$filters['f_ncmid'].'%'; }

    // Total filtrado
    $sqlCount = "SELECT COUNT(1) FROM [Portal_Integra].dbo.MF_Result WHERE {$where}";
    $st = $pdo->prepare($sqlCount);
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    // Tamanho de página e página corrente (tipados e seguros)
    $allowed = [25,100,1000,5000];
    $pp = in_array($pp, $allowed, true) ? $pp : 25;
    $totalPages = max(1, (int)ceil(($total > 0 ? $total : 1) / $pp));
    $p = min(max(1, (int)$p), $totalPages);

    // Cálculo de offset/fetch (inteiros)
    $offset = max(0, ($p - 1) * $pp);
    $fetch  = max(1, (int)$pp); // evitar 0

    // IMPORTANTE:
    // SQL Server (via ODBC) exige literal/variável inteira para OFFSET/FETCH.
    // Vamos INTERPOLAR offset/fetch (já validados) diretamente no SQL.
    // Os demais filtros continuam parametrizados (segurança).

    $sqlData = "
        SELECT Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
               NCMCodigo, NCMIdentificador, ValorPlanilha, Overprice, IndiceParaCusto,
               PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao
        FROM [Portal_Integra].dbo.MF_Result
        WHERE {$where}
        ORDER BY ProdutoDescricao, ProdutoReferencia
        OFFSET {$offset} ROWS FETCH NEXT {$fetch} ROWS ONLY;
    ";

    $st = $pdo->prepare($sqlData);
    $st->execute($params);
    $rows = $st->fetchAll();

    return ['rows'=>$rows, 'total'=>$total];
}

    /* ================== AJAX ENDPOINTS (upload/process/status) ================== */

    private function ajaxUpload()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            if (!isset($_FILES['arquivo']) || !is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
                throw new \RuntimeException('Arquivo não enviado.');
            }
            $overTxt   = (string)($_POST['overprice']    ?? self::DEF_OVERPRICE_TXT);
            $indiceTxt = (string)($_POST['indice_custo'] ?? self::DEF_INDICECUSTO_TXT);
            $over   = $this->percentToFraction($overTxt,   0.15);
            $indice = $this->percentToFraction($indiceTxt, 0.70);

            $op = $this->uuidv4();
            $destDir = $this->ensureStorageDir();
            $dest = $destDir . DIRECTORY_SEPARATOR . $op . '.lp';
            if (!@move_uploaded_file($_FILES['arquivo']['tmp_name'], $dest)) {
                throw new \RuntimeException('Falha ao mover arquivo para armazenamento temporário.');
            }

            $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
            $pdo = Connection::get();

            $upd = $pdo->prepare("
                UPDATE [Portal_Integra].dbo.JobProgress
                   SET Step='INIT', Status='PENDING', Message=NULL, Total=NULL, Done=NULL,
                       MarcaId=?, MarcaNome=?, Overprice=?, IndiceParaCusto=?, UploadedFilePath=?,
                       StartedAt=?, UpdatedAt=?, FinishedAt=NULL
                 WHERE OpID = CAST(? AS uniqueidentifier)
            ");
            $upd->execute([ self::MARCA_ID, self::MARCA_NOME, $over, $indice, $dest, $now, $now, $op ]);

            if ($upd->rowCount() === 0) {
                $ins = $pdo->prepare("
                    INSERT INTO [Portal_Integra].dbo.JobProgress
                    (OpID, MarcaId, MarcaNome, Step, Status, Message, Total, Done, Overprice, IndiceParaCusto, UploadedFilePath, StartedAt, UpdatedAt, FinishedAt)
                    VALUES (CAST(? AS uniqueidentifier), ?, ?, 'INIT', 'PENDING', NULL, NULL, NULL, ?, ?, ?, ?, ?, NULL)
                ");
                $ins->execute([ $op, self::MARCA_ID, self::MARCA_NOME, $over, $indice, $dest, $now, $now ]);
            }

            echo json_encode(['ok'=>true, 'op'=>$op], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        return;
    }

    private function ajaxProcess(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        session_write_close(); // libera lock de sessão imediatamente
        set_time_limit(120);

        try {
            $op = (string)($_GET['op'] ?? '');
            if ($op === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
                throw new \RuntimeException('OpID inválido.');
            }

            $phase = (string)($_GET['phase'] ?? 'import');

            if ($phase === 'finalize') {
                set_time_limit(600); // queries de cruzamento podem levar vários minutos
                $this->doProcessFinalize($op);
                return;
            }

            // ── phase = import ──────────────────────────────────────────────
            $byteOffset = max(0, (int)($_GET['byte_offset'] ?? 0));
            $prevDone   = max(0, (int)($_GET['done'] ?? 0));

            $pdo = Connection::get();
            $st  = $pdo->prepare("
                SELECT UploadedFilePath, Total
                  FROM [Portal_Integra].dbo.JobProgress
                 WHERE OpID = CAST(? AS uniqueidentifier)
            ");
            $st->execute([$op]);
            $job = $st->fetch();
            if (!$job) throw new \RuntimeException('Operação não encontrada.');

            $filePath = (string)$job['UploadedFilePath'];

            if ($byteOffset === 0) {
                // Primeiro chunk: limpa dados anteriores, conta total de linhas
                $this->clearOpData($op);
                $total = $this->countFileLines($filePath);
                $this->jobStep($op, 'IMPORT', 'RUNNING', 'Importando para staging ...', $total, 0);
            } else {
                $total = (int)($job['Total'] ?? 0);
                if ($total <= 0) $total = 1;
            }

            [$linesConsumed, $newByteOffset, $isEof] = $this->importFileChunk($op, $filePath, $byteOffset);
            $doneSoFar = $prevDone + $linesConsumed;

            if ($isEof) {
                // Conta linhas realmente inseridas para a mensagem final
                $stc = $pdo->prepare("SELECT COUNT(1) FROM [Portal_Integra].dbo.MF_Import WHERE OpID = CAST(? AS uniqueidentifier)");
                $stc->execute([$op]);
                $importedRows = (int)$stc->fetchColumn();
                $this->jobStep($op, 'IMPORT', 'OK', "Linhas importadas: {$importedRows}", $importedRows, $importedRows);
                echo json_encode([
                    'ok'       => true,
                    'phase'    => 'import',
                    'finished' => true,
                    'done'     => $doneSoFar,
                    'total'    => $total,
                ], JSON_UNESCAPED_UNICODE);
            } else {
                $this->jobStep($op, 'IMPORT', 'RUNNING', "Importando ... {$doneSoFar}/{$total}", $total, $doneSoFar);
                echo json_encode([
                    'ok'          => true,
                    'phase'       => 'import',
                    'finished'    => false,
                    'byte_offset' => $newByteOffset,
                    'done'        => $doneSoFar,
                    'total'       => $total,
                ], JSON_UNESCAPED_UNICODE);
            }

        } catch (\Throwable $e) {
            $op = (string)($_GET['op'] ?? '');
            if ($op !== '') {
                try { $this->jobStep($op, 'ERROR', 'ERROR', $e->getMessage(), null, null, true); } catch (\Throwable $ignored) {}
            }
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Fase de finalização: MATCH / ONLY_PLAN / ONLY_SYS.
     * Chamada via action=process&phase=finalize após todos os chunks de import.
     */
    private function doProcessFinalize(string $op): void
    {
        global $config;
        $dealerDb = $config['db']['dealernet_database'] ?? '';
        if ($dealerDb === '') throw new \RuntimeException('DealerNet não configurado (db.dealernet_database).');

        $pdo = Connection::get();
        $st  = $pdo->prepare("SELECT Overprice, IndiceParaCusto FROM [Portal_Integra].dbo.JobProgress WHERE OpID = CAST(? AS uniqueidentifier)");
        $st->execute([$op]);
        $job = $st->fetch();
        if (!$job) throw new \RuntimeException('Operação não encontrada.');

        $over   = (float)$job['Overprice'];
        $indice = (float)$job['IndiceParaCusto'];

        $this->jobStep($op, 'MATCH', 'RUNNING', 'Gerando itens conciliados (MATCH) ...', null, null);
        $matchCount = $this->sqlInsertMatch($op, $dealerDb, $over, $indice);
        $this->jobStep($op, 'MATCH', 'OK', "Itens conciliados: {$matchCount}", $matchCount, $matchCount);

        $this->jobStep($op, 'ONLY_PLAN', 'RUNNING', 'Gerando itens apenas na planilha ...', null, null);
        $onlyPlan = $this->sqlInsertOnlyPlan($op, $dealerDb);
        $this->jobStep($op, 'ONLY_PLAN', 'OK', "Itens apenas planilha: {$onlyPlan}", $onlyPlan, $onlyPlan);

        $this->jobStep($op, 'ONLY_SYS', 'RUNNING', 'Gerando itens apenas no DealerNet ...', null, null);
        $onlySys = $this->sqlInsertOnlySys($op, $dealerDb);
        $this->jobStep($op, 'ONLY_SYS', 'OK', "Itens apenas DealerNet: {$onlySys}", $onlySys, $onlySys);

        $this->jobStep($op, 'DONE', 'OK', 'Processo concluído.', null, null, true);

        echo json_encode([
            'ok'        => true,
            'phase'     => 'finalize',
            'finished'  => true,
            'match'     => $matchCount,
            'only_plan' => $onlyPlan,
            'only_sys'  => $onlySys,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Processa um chunk de linhas do arquivo LP para MF_Import.
     * Usa fseek() (O(1)) para não re-ler o arquivo do início a cada chunk.
     *
     * @return array [$linesConsumed, $newByteOffset, $isEof]
     */
    private function importFileChunk(string $op, string $filePath, int $byteOffset): array
    {
        if (!is_readable($filePath)) {
            throw new \RuntimeException("Arquivo temporário não pode ser lido: {$filePath}");
        }

        $fh = fopen($filePath, 'r');
        if ($fh === false) {
            throw new \RuntimeException("Não foi possível abrir o arquivo para leitura.");
        }

        // Primeiro chunk: pula a linha de cabeçalho. Chunks seguintes: seek direto.
        if ($byteOffset === 0) {
            fgets($fh); // descarta cabeçalho
        } else {
            fseek($fh, $byteOffset);
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $params        = [];
            $inBatch       = 0;
            $linesConsumed = 0;
            $isEof         = false;

            $flush = function () use (&$params, &$inBatch, $pdo) {
                if ($inBatch === 0) return;
                $placeholders = implode(',', array_fill(0, $inBatch, '(CAST(? AS uniqueidentifier), ?, ?, ?, ?, ?)'));
                $sql = "INSERT INTO [Portal_Integra].dbo.MF_Import
                        (OpID, RowNum, ProdutoReferencia, ProdutoDescricao, ValorPlanilha, RefNorm)
                        VALUES {$placeholders}";
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $params  = [];
                $inBatch = 0;
            };

            for ($i = 0; $i < self::IMPORT_CHUNK_LINES; $i++) {
                $line = fgets($fh);
                if ($line === false) {
                    $isEof = true;
                    break;
                }
                $linesConsumed++;
                $line = rtrim($line, "\r\n");

                if ($line === '' || strlen($line) < self::MIN_LINE_LEN) continue;

                $ref = trim(substr($line, 1, 25));
                if ($ref === '') continue;

                $desc = trim(substr($line, 26, 33));
                $val  = $this->toNumber(substr($line, 72, 10), 0.0);
                $norm = $this->normalizeRefStrict($ref);

                $params[] = $op;
                $params[] = $linesConsumed; // RowNum aproximado (posição no chunk)
                $params[] = $ref;
                $params[] = $desc;
                $params[] = $val;
                $params[] = $norm;
                $inBatch++;

                if ($inBatch >= self::BATCH_ROWS) {
                    $flush();
                }
            }

            // Detecta EOF mesmo quando o chunk terminou exatamente no fim do arquivo
            if (!$isEof && feof($fh)) {
                $isEof = true;
            }

            $flush();
            $pdo->commit();

            $newByteOffset = (int)ftell($fh);
            fclose($fh);

            return [$linesConsumed, $newByteOffset, $isEof];

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            fclose($fh);
            throw $e;
        }
    }

    private function ajaxStatus()
    {
        header('Content-Type: application/json; charset=UTF-8');
        session_write_close(); // libera lock de sessão — não precisa de escrita aqui
        try {
            $op = (string)($_GET['op'] ?? '');
            if ($op === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
                throw new \RuntimeException('OpID inválido.');
            }
            $pdo = Connection::get();
            $st = $pdo->prepare("SELECT Step, Status, Message, Total, Done, StartedAt, UpdatedAt, FinishedAt FROM [Portal_Integra].dbo.JobProgress WHERE OpID = CAST(? AS uniqueidentifier)");
            $st->execute([$op]);
            $row = $st->fetch();
            if (!$row) throw new \RuntimeException('Operação não encontrada.');
            echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        return;
    }

    /* ================== EXPORT/UPDATE A PARTIR DO BANCO (inalterados) ================== */

    private function exportFromDb(): void
    {
        $op = (string)($_GET['op'] ?? '');
        if ($op === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Informe ?action=export&op={GUID} (após processar).";
            return;
        }

        $pdo = Connection::get();
        $st = $pdo->prepare("
            SELECT Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
                   NCMCodigo, NCMIdentificador, ValorPlanilha, Overprice, IndiceParaCusto,
                   PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao
            FROM [Portal_Integra].dbo.MF_Result
            WHERE OpID = CAST(? AS uniqueidentifier) AND Tipo='MATCH'
            ORDER BY ProdutoDescricao, ProdutoReferencia
        ");
        $st->execute([$op]);
        $dados = $st->fetchAll();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="massey_result.csv"');
        echo "\xEF\xBB\xBF";
        if (empty($dados)) { echo "Sem dados.\n"; return; }
        $headers = array_keys($dados[0]);
        $f = fopen('php://output','w');
        fputcsv($f, $headers, ';');
        foreach ($dados as $r) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $this->csvFormat($h, $r[$h] ?? '');
            }
            fputcsv($f, $line, ';');
        }
        fclose($f);
    }

    private function updateFromDb(): void
    {
        global $config;
        $dealerDb = $config['db']['dealernet_database'] ?? '';
        if ($dealerDb === '') {
            $_SESSION['massey_flash'] = "DealerNet não configurado (db.dealernet_database).";
            redirect('agro/tabela-massey'); return;
        }

        $op = (string)($_GET['op'] ?? '');
        if ($op === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
            $_SESSION['massey_flash'] = "OpID inválido para atualizar valores.";
            redirect('agro/tabela-massey'); return;
        }

        $pdo = Connection::get();

        $st = $pdo->prepare("
            SELECT Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
                   NCMCodigo, NCMIdentificador, ValorPlanilha, Overprice, IndiceParaCusto,
                   PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao
            FROM [Portal_Integra].dbo.MF_Result
            WHERE OpID = CAST(? AS uniqueidentifier) AND Tipo='MATCH'
            ORDER BY ProdutoDescricao, ProdutoReferencia
        ");
        $st->execute([$op]);
        $resultado = $st->fetchAll();

        if (empty($resultado)) {
            $_SESSION['massey_flash'] = "Sem dados (MATCH) para atualizar.";
            redirect("agro/tabela-massey?action=results&op={$op}");
            return;
        }

        // 1) Permissão
        $hasExec = 0;
        try {
            $check = $pdo->query("SELECT HAS_PERMS_BY_NAME('{$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS','OBJECT','EXECUTE') AS HasExec");
            $hasExec = (int)$check->fetchColumn();
        } catch (\Throwable $e) { $hasExec = 1; }
        if ($hasExec !== 1) {
            $_SESSION['massey_flash'] = "Sem permissão para executar {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS. Solicite GRANT EXECUTE ao DBA.";
            redirect("agro/tabela-massey?action=results&op={$op}");
            return;
        }

        // 2) Auditoria
        $opGuid = $this->uuidv4();
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("
            INSERT INTO [Portal_Integra].dbo.Atualizacao_Precos_DealerNet
            (IDUnico, dataHoraProcessamento, Marca_Codigo, Marca_Empresa,
             Marca, Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
             NCMCodigo, NCMIdentificador, [ValorPlanilha(US$)], Markup,
             FatorImportacao, CotacaoDolar, Tipo,
             PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao)
            VALUES
            ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
            ");
            $now  = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

            foreach ($resultado as $r) {
                $ins->execute([
                    $opGuid,
                    $now,
                    self::MARCA_ID,
                    (string)($r['Empresa'] ?? ''),
                    (string)self::MARCA_NOME,
                    (string)($r['Empresa'] ?? ''),
                    (int)($r['ProdutoCodigo'] ?? 0),
                    (string)($r['ProdutoReferencia'] ?? ''),
                    (string)($r['ProdutoDescricao'] ?? ''),
                    ($r['NCMCodigo'] ?? null) !== null ? (int)$r['NCMCodigo'] : null,
                    (string)($r['NCMIdentificador'] ?? ''),
                    (float)($r['ValorPlanilha'] ?? 0),
                    (float)($r['Overprice'] ?? 0),
                    (float)($r['IndiceParaCusto'] ?? 0),
                    1.0,
                    'Massey-BRL',
                    (float)($r['PrecoPublico'] ?? 0),
                    (float)($r['PrecoSugerido'] ?? 0),
                    (float)($r['PrecoGarantia'] ?? 0),
                    (float)($r['PrecoReposicao'] ?? 0),
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['massey_flash'] = "Falha ao registrar auditoria: ".$e->getMessage();
            redirect("agro/tabela-massey?action=results&op={$op}");
            return;
        }

        // 3) Exec
        $ok=0; $fail=0; $errs=[]; $hoje=(new \DateTime('today', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $execSql = "
        EXEC {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS
             @Marca_Codigo   = ?,
             @Produto_Codigo = ?,
             @PrecoPublico   = ?,
             @PrecoSugerido  = ?,
             @PrecoGarantia  = ?,
             @PrecoReposicao = ?,
             @DataVigencia   = ?,
             @DataValidade   = ?,
             @Empresa_Codigo = ?,
             @Usuario_Codigo = ?
        ";
        $stmtExec = $pdo->prepare($execSql);
        foreach ($resultado as $r) {
            try {
                $stmtExec->execute([
                    self::MARCA_ID,
                    (int)$r['ProdutoCodigo'],
                    (float)$r['PrecoPublico'],
                    (float)$r['PrecoSugerido'],
                    (float)$r['PrecoGarantia'],
                    (float)$r['PrecoReposicao'],
                    $hoje,
                    null,
                    null,
                    813
                ]);
                $ok++;
            } catch (\Throwable $e) {
                $fail++; $errs[] = "Produto {$r['ProdutoCodigo']}: ".$e->getMessage();
            }
        }

        $_SESSION['massey_flash'] = ($fail>0)
          ? "Operação registrada (ID={$opGuid}). Atualizações OK: {$ok}. Falhas: {$fail}.\n".implode("\n",$errs)
          : "Operação concluída. ID={$opGuid}. Itens atualizados: {$ok}.";

        redirect("agro/tabela-massey?action=results&op={$op}");
    }

    /* ================== AJAX: UPDATE ALL — batch SP (recomendado) ================== */

    /**
     * Atualiza preços de TODOS os produtos MATCH em uma única chamada à SP em lote.
     * A SP lê MF_Result diretamente via OpID e faz UPDATE + MERGE set-based.
     *
     * Requer: PRC_VALENCE_DN_ATUALIZAR_PRECOS_LOTE no banco DealerNet
     *         e SELECT em [Portal_Integra].dbo.MF_Result para o usuário da SP.
     */
    private function ajaxUpdateAll(): void
    {
        // DESIGN: uma chamada HTTP por batch de 1 000 produtos.
        // O JS faz o loop; cada request aqui roda ≤ 10 s → zero risco de timeout.
        // mod_php: sem proxy timeout; set_time_limit cobre folga generosa para 1 batch.
        header('Content-Type: application/json; charset=UTF-8');
        session_write_close();
        ignore_user_abort(true);  // batch atual termina mesmo que o browser desconecte
        set_time_limit(60);       // 1 batch de 1 000 produtos não deveria levar > 10 s

        try {
            global $config;
            $dealerDb = $config['db']['dealernet_database'] ?? '';
            if ($dealerDb === '') throw new \RuntimeException('DealerNet não configurado (db.dealernet_database).');

            $op = (string)($_GET['op'] ?? '');
            if ($op === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
                throw new \RuntimeException('OpID inválido.');
            }

            // Parâmetros de paginação passados pelo JS a cada chamada
            $offset         = max(0, (int)($_GET['offset']          ?? 0));
            $total          = max(0, (int)($_GET['total']            ?? 0));
            $auditOp        = preg_replace('/[^0-9a-fA-F\-]/', '', (string)($_GET['audit_op'] ?? ''));
            $accFechados    = max(0, (int)($_GET['acc_fechados']     ?? 0));
            $accAtualizados = max(0, (int)($_GET['acc_atualizados']  ?? 0));

            $pdo  = Connection::get();
            $now  = (new \DateTime('now',   new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
            $hoje = (new \DateTime('today', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

            // ── Primeira chamada (offset = 0): auditoria + contagem + mutex ──
            if ($offset === 0) {
                $auditOp = $this->uuidv4();

                // Auditoria em lote via método centralizado
                $this->insertAuditBatch($op, $auditOp, $now);

                // Conta produtos únicos para barra de progresso no JS
                $stCount = $pdo->prepare("
                    SELECT COUNT(DISTINCT ProdutoCodigo)
                    FROM [Portal_Integra].dbo.MF_Result
                    WHERE OpID = CAST(? AS uniqueidentifier)
                      AND Tipo = 'MATCH' AND ProdutoCodigo IS NOT NULL
                ");
                $stCount->execute([$op]);
                $total = (int)$stCount->fetchColumn();
                if ($total === 0) {
                    throw new \RuntimeException('Sem dados (MATCH com ProdutoCodigo) para atualizar.');
                }

                // Mutex: rejeita se já em andamento.
                // Permite re-início se UpdatedAt > 5 min (estado preso por crash/reload).
                $stMark = $pdo->prepare("
                    UPDATE [Portal_Integra].dbo.JobProgress
                       SET Step = 'UPDATE_RUNNING', Status = 'RUNNING',
                           Message = 'Iniciando atualização de preços...', UpdatedAt = ?
                     WHERE OpID = CAST(? AS uniqueidentifier)
                       AND (
                           Step IS NULL
                           OR Step <> 'UPDATE_RUNNING'
                           OR UpdatedAt < DATEADD(MINUTE, -5, GETDATE())
                       )
                ");
                $stMark->execute([$now, $op]);
                if ($stMark->rowCount() === 0) {
                    throw new \RuntimeException(
                        'Uma atualização de preços já está em andamento para esta operação. Aguarde a conclusão.'
                    );
                }
            }

            // ── Executa UM batch e retorna ao JS ────────────────────────────
            $batchSize = 1000;
            $stSP = $pdo->prepare("
                EXEC {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_LOTE
                     @OpID           = ?,
                     @Marca_Codigo   = ?,
                     @DataVigencia   = ?,
                     @DataValidade   = ?,
                     @Usuario_Codigo = ?,
                     @Offset         = ?,
                     @BatchSize      = ?
            ");
            try {
                $stSP->execute([$op, self::MARCA_ID, $hoje, null, 813, $offset, $batchSize]);
                $result = $stSP->fetch() ?: [];
                try { do { $stSP->fetchAll(); } while ($stSP->nextRowset()); } catch (\Throwable $ignored) {}
            } catch (\Throwable $spEx) {
                $this->jobStep($op, 'UPDATE_ERROR', 'ERROR',
                    "Erro no batch offset={$offset}: " . $spEx->getMessage());
                throw $spEx;
            }

            $rowsProcessed  = (int)($result['RowsProcessed']    ?? 0);
            $batchFechados  = (int)($result['PrecosFechados']    ?? 0);
            $batchAtu       = (int)($result['PrecosAtualizados'] ?? 0);

            $newOffset     = $offset + $rowsProcessed;
            $totalFechados = $accFechados    + $batchFechados;
            $totalAtu      = $accAtualizados + $batchAtu;
            $finished      = ($rowsProcessed === 0);

            // Atualiza JobProgress — detectável pelo polling em caso de reload
            if ($finished) {
                $this->jobStep($op, 'UPDATE_DONE', 'OK',
                    "{$totalAtu} preços atualizados, {$totalFechados} vigências fechadas. Auditoria: {$auditOp}",
                    $total ?: null, $total ?: null);
            } else {
                $pct = $total > 0 ? (int)round($newOffset / $total * 100) : 0;
                $this->jobStep($op, 'UPDATE_RUNNING', 'RUNNING',
                    "{$newOffset}/{$total} produtos processados ({$pct}%)",
                    $total ?: null, $newOffset);
            }

            echo json_encode([
                'ok'                 => true,
                'finished'           => $finished,
                'offset'             => $newOffset,
                'total'              => $total,
                'audit_op'           => $auditOp,
                'batch_fechados'     => $batchFechados,
                'batch_atualizados'  => $batchAtu,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /* ================== AJAX: UPDATE ALL START — inicia worker CLI async ================== */

    /**
     * Inicia a atualização de preços em background via processo CLI.
     * Retorna imediatamente: {ok, async}.
     *   async=true  → worker CLI foi iniciado; frontend faz polling de action=status.
     *   async=false → exec() desabilitado; frontend usa JS-batch (action=update_all).
     */
    private function ajaxUpdateAllStart(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        session_write_close();
        try {
            $op = (string)($_GET['op'] ?? '');
            if ($op === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
                throw new \RuntimeException('OpID inválido.');
            }

            $pdo = Connection::get();
            $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

            // Mutex: rejeita se já em andamento (mesma lógica de ajaxUpdateAll)
            $stMark = $pdo->prepare("
                UPDATE [Portal_Integra].dbo.JobProgress
                   SET Step = 'UPDATE_QUEUED', Status = 'RUNNING',
                       Message = 'Aguardando worker em background...', UpdatedAt = ?
                 WHERE OpID = CAST(? AS uniqueidentifier)
                   AND (
                       Step IS NULL
                       OR Step NOT IN ('UPDATE_RUNNING', 'UPDATE_QUEUED')
                       OR UpdatedAt < DATEADD(MINUTE, -5, GETDATE())
                   )
            ");
            $stMark->execute([$now, $op]);
            if ($stMark->rowCount() === 0) {
                throw new \RuntimeException(
                    'Uma atualização de preços já está em andamento para esta operação. Aguarde a conclusão.'
                );
            }

            // Verifica se exec() está disponível e não está na disable_functions
            $execAvailable = function_exists('exec');
            if ($execAvailable) {
                $disabled = array_map('trim', explode(',', strtolower(ini_get('disable_functions') ?: '')));
                if (in_array('exec', $disabled, true)) {
                    $execAvailable = false;
                }
            }

            if (!$execAvailable) {
                // Fallback: JS vai usar action=update_all (JS-loop)
                echo json_encode(['ok' => true, 'async' => false], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Localiza o script CLI
            $script = realpath(__DIR__ . '/../../cli/run_update_massey.php');
            if (!$script) {
                echo json_encode(['ok' => true, 'async' => false], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Em mod_php PHP_BINARY aponta para o Apache — usa 'php' do PATH
            $phpBin = PHP_BINARY;
            if (stripos(basename($phpBin), 'php') === false) {
                $phpBin = 'php';
            }

            // Spawna em background (Linux/Docker: &)
            $cmd = escapeshellarg($phpBin)
                 . ' ' . escapeshellarg($script)
                 . ' --op=' . escapeshellarg($op)
                 . ' > /dev/null 2>&1 &';
            exec($cmd);

            echo json_encode(['ok' => true, 'async' => true], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /* ================== PUBLIC: runUpdateAllBackground — chamado pelo CLI ================== */

    /**
     * Executa todos os batches de atualização de preços em loop contínuo.
     * Método público para ser chamado pelo worker CLI (run_update_massey.php).
     * Atualiza JobProgress a cada batch para que o frontend possa fazer polling.
     *
     * @throws \RuntimeException em caso de erro irrecuperável
     */
    public function runUpdateAllBackground(string $op): void
    {
        global $config;
        $dealerDb = $config['db']['dealernet_database'] ?? '';
        if ($dealerDb === '') {
            $this->jobStep($op, 'UPDATE_ERROR', 'ERROR', 'DealerNet não configurado (db.dealernet_database).');
            throw new \RuntimeException('DealerNet não configurado.');
        }

        $pdo  = Connection::get();
        $now  = (new \DateTime('now',   new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        $hoje = (new \DateTime('today', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

        // ── Auditoria em lote via método centralizado ────────────────────────
        $auditOp = $this->uuidv4();
        try {
            $this->insertAuditBatch($op, $auditOp, $now);
        } catch (\Throwable $e) {
            $this->jobStep($op, 'UPDATE_ERROR', 'ERROR', 'Falha ao registrar auditoria: ' . $e->getMessage());
            throw new \RuntimeException('Falha ao registrar auditoria: ' . $e->getMessage());
        }

        // ── Conta total de produtos únicos ───────────────────────────────────
        $stCount = $pdo->prepare("
            SELECT COUNT(DISTINCT ProdutoCodigo)
            FROM [Portal_Integra].dbo.MF_Result
            WHERE OpID = CAST(? AS uniqueidentifier)
              AND Tipo = 'MATCH' AND ProdutoCodigo IS NOT NULL
        ");
        $stCount->execute([$op]);
        $total = (int)$stCount->fetchColumn();
        if ($total === 0) {
            $this->jobStep($op, 'UPDATE_ERROR', 'ERROR', 'Sem dados (MATCH com ProdutoCodigo) para atualizar.');
            throw new \RuntimeException('Sem dados para atualizar.');
        }

        $this->jobStep($op, 'UPDATE_RUNNING', 'RUNNING', "Iniciando... 0/{$total}", $total, 0);

        // ── Loop de batches ──────────────────────────────────────────────────
        $batchSize     = 1000;
        $offset        = 0;
        $totalFechados = 0;
        $totalAtu      = 0;

        $stSP = $pdo->prepare("
            EXEC {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_LOTE
                 @OpID           = ?,
                 @Marca_Codigo   = ?,
                 @DataVigencia   = ?,
                 @DataValidade   = ?,
                 @Usuario_Codigo = ?,
                 @Offset         = ?,
                 @BatchSize      = ?
        ");

        while (true) {
            try {
                $stSP->execute([$op, self::MARCA_ID, $hoje, null, 813, $offset, $batchSize]);
                $result = $stSP->fetch() ?: [];
                try { do { $stSP->fetchAll(); } while ($stSP->nextRowset()); } catch (\Throwable $ignored) {}
            } catch (\Throwable $spEx) {
                $this->jobStep($op, 'UPDATE_ERROR', 'ERROR',
                    "Erro no batch offset={$offset}: " . $spEx->getMessage());
                throw $spEx;
            }

            $rowsProcessed  = (int)($result['RowsProcessed']    ?? 0);
            $batchFechados  = (int)($result['PrecosFechados']    ?? 0);
            $batchAtu       = (int)($result['PrecosAtualizados'] ?? 0);

            $offset        += $rowsProcessed;
            $totalFechados += $batchFechados;
            $totalAtu      += $batchAtu;

            if ($rowsProcessed === 0) {
                // Todos os batches processados
                $this->jobStep($op, 'UPDATE_DONE', 'OK',
                    "{$totalAtu} preços atualizados, {$totalFechados} vigências fechadas. Auditoria: {$auditOp}",
                    $total, $total);
                break;
            }

            $pct = $total > 0 ? (int)round($offset / $total * 100) : 0;
            $this->jobStep($op, 'UPDATE_RUNNING', 'RUNNING',
                "{$offset}/{$total} produtos processados ({$pct}%)",
                $total, $offset);
        }
    }

    /* ================== AJAX: UPDATE CHUNK (Atualizar Valores faseado — fallback) ================== */

    /**
     * Atualiza preços em chunks para evitar lock e timeout.
     * GET params: op, offset, limit, total (passado pelo JS após 1ª chamada), audit_op
     * Retorna: {ok, done, total, finished, audit_op, proc_ok, proc_fail, errors}
     */
    private function ajaxUpdateChunk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        session_write_close();
        set_time_limit(120);

        try {
            global $config;
            $dealerDb = $config['db']['dealernet_database'] ?? '';
            if ($dealerDb === '') throw new \RuntimeException('DealerNet não configurado (db.dealernet_database).');

            $op = (string)($_GET['op'] ?? '');
            if ($op === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
                throw new \RuntimeException('OpID inválido.');
            }

            $offset  = max(0, (int)($_GET['offset']  ?? 0));
            $limit   = min(200, max(1, (int)($_GET['limit'] ?? self::UPDATE_CHUNK_SIZE)));
            $total   = max(0, (int)($_GET['total']   ?? 0));
            $auditOp = (string)($_GET['audit_op']    ?? '');

            $pdo = Connection::get();

            // Na primeira chamada: conta o total de registros MATCH e gera GUID de auditoria
            if ($offset === 0 || $total === 0) {
                $stc = $pdo->prepare("SELECT COUNT(1) FROM [Portal_Integra].dbo.MF_Result WHERE OpID = CAST(? AS uniqueidentifier) AND Tipo = 'MATCH'");
                $stc->execute([$op]);
                $total = (int)$stc->fetchColumn();
                if ($total === 0) {
                    echo json_encode(['ok' => false, 'msg' => 'Sem dados (MATCH) para atualizar.'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                $auditOp = $this->uuidv4();
            }
            if ($auditOp === '') $auditOp = $this->uuidv4();

            // Busca o chunk de produtos MATCH
            $st = $pdo->prepare("
                SELECT Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
                       NCMCodigo, NCMIdentificador, ValorPlanilha, Overprice, IndiceParaCusto,
                       PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao
                FROM [Portal_Integra].dbo.MF_Result
                WHERE OpID = CAST(? AS uniqueidentifier) AND Tipo = 'MATCH'
                ORDER BY ProdutoCodigo
                OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY
            ");
            $st->execute([$op]);
            $chunk = $st->fetchAll();

            if (empty($chunk)) {
                echo json_encode([
                    'ok' => true, 'done' => $offset, 'total' => $total,
                    'finished' => true, 'audit_op' => $auditOp,
                    'proc_ok' => 0, 'proc_fail' => 0, 'errors' => [],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 1) Grava registros de auditoria para o chunk
            $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
            $ins = $pdo->prepare("
                INSERT INTO [Portal_Integra].dbo.Atualizacao_Precos_DealerNet
                (IDUnico, dataHoraProcessamento, Marca_Codigo, Marca_Empresa,
                 Marca, Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
                 NCMCodigo, NCMIdentificador, [ValorPlanilha(US$)], Markup,
                 FatorImportacao, CotacaoDolar, Tipo,
                 PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $pdo->beginTransaction();
            try {
                foreach ($chunk as $r) {
                    $ins->execute([
                        $auditOp, $now,
                        self::MARCA_ID,
                        (string)($r['Empresa'] ?? ''),
                        self::MARCA_NOME,
                        (string)($r['Empresa'] ?? ''),
                        (int)($r['ProdutoCodigo'] ?? 0),
                        (string)($r['ProdutoReferencia'] ?? ''),
                        (string)($r['ProdutoDescricao'] ?? ''),
                        ($r['NCMCodigo'] ?? null) !== null ? (int)$r['NCMCodigo'] : null,
                        (string)($r['NCMIdentificador'] ?? ''),
                        (float)($r['ValorPlanilha']    ?? 0), // [ValorPlanilha(US$)]
                        (float)($r['Overprice']        ?? 0), // Markup
                        (float)($r['IndiceParaCusto']  ?? 0), // FatorImportacao
                        1.0,                                   // CotacaoDolar  (DECIMAL — câmbio BRL=1)
                        'Massey-BRL',                          // Tipo          (NVARCHAR)
                        (float)($r['PrecoPublico']     ?? 0),
                        (float)($r['PrecoSugerido']    ?? 0),
                        (float)($r['PrecoGarantia']    ?? 0),
                        (float)($r['PrecoReposicao']   ?? 0),
                    ]);
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw new \RuntimeException('Falha ao registrar auditoria: ' . $e->getMessage());
            }

            // 2) Executa a stored procedure para cada produto do chunk
            $hoje    = (new \DateTime('today', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
            $execSql = "
                EXEC {$dealerDb}.dbo.PRC_VALENCE_DN_ATUALIZAR_PRECOS_PRODUTOS
                     @Marca_Codigo   = ?,
                     @Produto_Codigo = ?,
                     @PrecoPublico   = ?,
                     @PrecoSugerido  = ?,
                     @PrecoGarantia  = ?,
                     @PrecoReposicao = ?,
                     @DataVigencia   = ?,
                     @DataValidade   = ?,
                     @Empresa_Codigo = ?,
                     @Usuario_Codigo = ?
            ";

            $ok = 0; $fail = 0; $errs = [];

            foreach ($chunk as $r) {
                try {
                    $stExec = $pdo->prepare($execSql);
                    $stExec->execute([
                        self::MARCA_ID,
                        (int)$r['ProdutoCodigo'],
                        (float)$r['PrecoPublico'],
                        (float)$r['PrecoSugerido'],
                        (float)$r['PrecoGarantia'],
                        (float)$r['PrecoReposicao'],
                        $hoje, null, null, 813,
                    ]);
                    // Consome todos os result sets para manter estado limpo do driver PDO
                    try {
                        do { $stExec->fetchAll(); } while ($stExec->nextRowset());
                    } catch (\Throwable $ignored) {}
                    $ok++;
                } catch (\Throwable $e) {
                    $fail++;
                    if (count($errs) < 5) {
                        $errs[] = "Produto {$r['ProdutoCodigo']}: " . $e->getMessage();
                    }
                }
            }

            $newDone  = $offset + count($chunk);
            $finished = (count($chunk) < $limit) || ($newDone >= $total);

            echo json_encode([
                'ok'        => true,
                'done'      => $newDone,
                'total'     => $total,
                'finished'  => $finished,
                'audit_op'  => $auditOp,
                'proc_ok'   => $ok,
                'proc_fail' => $fail,
                'errors'    => $errs,
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /* ================== ETAPAS (SQL/IO) ================== */

    private function clearOpData(string $op): void
    {
        $pdo = Connection::get();
        $st = $pdo->prepare("DELETE FROM [Portal_Integra].dbo.MF_Result WHERE OpID = CAST(? AS uniqueidentifier)");
        $st->execute([$op]);
        $st = $pdo->prepare("DELETE FROM [Portal_Integra].dbo.MF_Import WHERE OpID = CAST(? AS uniqueidentifier)");
        $st->execute([$op]);
    }

    private function countFileLines(string $filePath): int
    {
        if (!is_readable($filePath)) return 0;
        $fh = new \SplFileObject($filePath, 'r');
        $fh->setFlags(\SplFileObject::DROP_NEW_LINE);
        $count = 0;
        foreach ($fh as $line) { if ($line !== false) { $count++; } }
        if ($count > 0) $count--;
        return max(0, $count);
    }

    private function importFileToStaging(string $op, string $filePath, int $totalLines = 0): int
    {
        if (!is_readable($filePath)) {
            throw new \RuntimeException("Arquivo temporário não pode ser lido: {$filePath}");
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $fh = new \SplFileObject($filePath, 'r');
            $fh->setFlags(\SplFileObject::DROP_NEW_LINE);

            $params    = [];
            $inBatch   = 0;
            $imported  = 0;
            $rownum    = 0;
            $lastCommit= 0;

            $flush = function() use (&$params,&$inBatch,&$imported,&$lastCommit,$pdo) {
                if ($inBatch === 0) return;
                $values = [];
                for ($i = 0; $i < $inBatch; $i++) {
                    $values[] = "(CAST(? AS uniqueidentifier), ?, ?, ?, ?, ?)";
                }
                $sql = "INSERT INTO [Portal_Integra].dbo.MF_Import
                        (OpID, RowNum, ProdutoReferencia, ProdutoDescricao, ValorPlanilha, RefNorm)
                        VALUES ".implode(',', $values);
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $params = [];
                $imported += $inBatch;
                $lastCommit+= $inBatch;
                $inBatch   = 0;
            };

            foreach ($fh as $line) {
                if ($line === false) break;
                if ($rownum === 0) { $rownum++; continue; }
                if ($line === '') { $rownum++; continue; }
                if (strlen($line) < self::MIN_LINE_LEN) { $rownum++; continue; }

                $ref  = trim((string)rtrim(substr($line, 1, 25)));
                if ($ref === '') { $rownum++; continue; }
                $desc = trim((string)rtrim(substr($line, 26, 33)));
                $valT = substr($line, 72, 10);
                $val  = $this->toNumber($valT, 0.0);
                $norm = $this->normalizeRefStrict($ref);

                $params[] = $op;
                $params[] = $rownum;
                $params[] = $ref;
                $params[] = $desc;
                $params[] = $val;
                $params[] = $norm;
                $inBatch++;

                if ($inBatch >= self::BATCH_ROWS) {
                    $flush();
                }

                if ($totalLines > 0 && ($rownum % self::PROGRESS_EVERY) === 0) {
                    $done = min($rownum - 1, $totalLines);
                    $this->jobStep($op, 'IMPORT', 'RUNNING', "Importando... {$done}/{$totalLines}", $totalLines, $done);
                }

                if ($lastCommit >= self::COMMIT_EVERY) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                    $lastCommit = 0;
                }

                $rownum++;
            }

            $flush();
            if ($pdo->inTransaction()) $pdo->commit();

            if ($totalLines > 0) {
                $this->jobStep($op, 'IMPORT', 'RUNNING', "Importação quase concluída ...", $totalLines, $imported);
            }

            return $imported;

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private function sqlInsertMatch(string $op, string $dealerDb, float $over, float $indice): int
    {
        $pdo = Connection::get();
        $pfx = '[' . preg_replace('/[^A-Za-z0-9_.]/', '', $dealerDb) . '].dbo.';
        $normSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(PM.ProdutoMarca_Referencia, PM.ProdutoMarca_ReferenciaAlfanumerico),' ',''),'-',''),'.',''),'/',''),'_',''), '\\', ''))";

        $st = $pdo->prepare("DELETE FROM [Portal_Integra].dbo.MF_Result WHERE OpID = CAST(? AS uniqueidentifier) AND Tipo = 'MATCH'");
        $st->execute([$op]);

        $sql = "
        INSERT INTO [Portal_Integra].dbo.MF_Result
        (OpID, Tipo, Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
         NCMCodigo, NCMIdentificador, ValorPlanilha, Overprice, IndiceParaCusto,
         PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao)
        SELECT
          CAST(? AS uniqueidentifier) AS OpID,
          'MATCH' AS Tipo,
          MAX(E.Empresa_Nome) AS Empresa,
          PM.Produto_Codigo,
          COALESCE(PM.ProdutoMarca_Referencia, PM.ProdutoMarca_ReferenciaAlfanumerico) AS ProdutoReferencia,
          P.Produto_Descricao AS ProdutoDescricao,
          N.NCM_Codigo,
          N.NCM_Identificador,
          I.ValorPlanilha,
          ? AS Overprice,
          ? AS IndiceParaCusto,
          CAST(I.ValorPlanilha * (1.0 + ?) AS decimal(18,4)) AS PrecoPublico,
          CAST(I.ValorPlanilha AS decimal(18,4)) AS PrecoSugerido,
          CAST(I.ValorPlanilha * ? AS decimal(18,4)) AS PrecoGarantia,
          CAST(I.ValorPlanilha * ? AS decimal(18,4)) AS PrecoReposicao
        FROM {$pfx}ProdutoMarca PM
        INNER JOIN {$pfx}Produto P ON P.Produto_Codigo = PM.Produto_Codigo
        LEFT  JOIN {$pfx}NCM N     ON N.NCM_Codigo     = P.Produto_NCMCod
        LEFT  JOIN {$pfx}EmpresaMarca EM ON EM.EmpresaMarca_MarcaCod = PM.ProdutoMarca_MarcaCod
        LEFT  JOIN {$pfx}Empresa E       ON E.Empresa_Codigo = EM.Empresa_Codigo
        INNER JOIN [Portal_Integra].dbo.MF_Import I
                ON I.OpID = CAST(? AS uniqueidentifier)
               AND I.RefNorm = {$normSql}
        WHERE PM.ProdutoMarca_MarcaCod = ?
        GROUP BY PM.Produto_Codigo, COALESCE(PM.ProdutoMarca_Referencia, PM.ProdutoMarca_ReferenciaAlfanumerico),
                 P.Produto_Descricao, N.NCM_Codigo, N.NCM_Identificador, I.ValorPlanilha
        ";
        $st = $pdo->prepare($sql);
        $st->execute([ $op, $over, $indice, $over, $indice, $indice, $op, self::MARCA_ID ]);

        return (int)$pdo->query("SELECT @@ROWCOUNT")->fetchColumn();
    }

    private function sqlInsertOnlyPlan(string $op, string $dealerDb): int
    {
        $pdo = Connection::get();
        $pfx = '[' . preg_replace('/[^A-Za-z0-9_.]/', '', $dealerDb) . '].dbo.';
        $normSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(PM.ProdutoMarca_Referencia, PM.ProdutoMarca_ReferenciaAlfanumerico),' ',''),'-',''),'.',''),'/',''),'_',''), '\\', ''))";

        $st = $pdo->prepare("DELETE FROM [Portal_Integra].dbo.MF_Result WHERE OpID = CAST(? AS uniqueidentifier) AND Tipo = 'ONLY_PLAN'");
        $st->execute([$op]);

        $sql = "
        INSERT INTO [Portal_Integra].dbo.MF_Result
        (OpID, Tipo, Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
         NCMCodigo, NCMIdentificador, ValorPlanilha, Overprice, IndiceParaCusto,
         PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao)
        SELECT
          CAST(? AS uniqueidentifier) AS OpID,
          'ONLY_PLAN' AS Tipo,
          NULL AS Empresa,
          NULL AS ProdutoCodigo,
          I.ProdutoReferencia,
          I.ProdutoDescricao,
          NULL AS NCMCodigo,
          NULL AS NCMIdentificador,
          CAST(I.ValorPlanilha AS decimal(18,4)) AS ValorPlanilha,
          0, 0, NULL, NULL, NULL, NULL
        FROM [Portal_Integra].dbo.MF_Import I
        WHERE I.OpID = CAST(? AS uniqueidentifier)
          AND NOT EXISTS (
            SELECT 1
            FROM {$pfx}ProdutoMarca PM
            WHERE PM.ProdutoMarca_MarcaCod = ?
              AND I.RefNorm = {$normSql}
          )
        ";
        $st = $pdo->prepare($sql);
        $st->execute([ $op, $op, self::MARCA_ID ]);

        return (int)$pdo->query("SELECT @@ROWCOUNT")->fetchColumn();
    }

    private function sqlInsertOnlySys(string $op, string $dealerDb): int
    {
        $pdo = Connection::get();
        $pfx = '[' . preg_replace('/[^A-Za-z0-9_.]/', '', $dealerDb) . '].dbo.';
        $normSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(PM.ProdutoMarca_Referencia, PM.ProdutoMarca_ReferenciaAlfanumerico),' ',''),'-',''),'.',''),'/',''),'_',''), '\\', ''))";

        $st = $pdo->prepare("DELETE FROM [Portal_Integra].dbo.MF_Result WHERE OpID = CAST(? AS uniqueidentifier) AND Tipo = 'ONLY_SYS'");
        $st->execute([$op]);

        $sql = "
        INSERT INTO [Portal_Integra].dbo.MF_Result
        (OpID, Tipo, Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
         NCMCodigo, NCMIdentificador, ValorPlanilha, Overprice, IndiceParaCusto,
         PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao)
        SELECT
          CAST(? AS uniqueidentifier) AS OpID,
          'ONLY_SYS' AS Tipo,
          MAX(E.Empresa_Nome) AS Empresa,
          PM.Produto_Codigo,
          COALESCE(PM.ProdutoMarca_Referencia, PM.ProdutoMarca_ReferenciaAlfanumerico),
          P.Produto_Descricao,
          N.NCM_Codigo,
          N.NCM_Identificador,
          NULL, 0, 0, NULL, NULL, NULL, NULL
        FROM {$pfx}ProdutoMarca PM
        INNER JOIN {$pfx}Produto P ON P.Produto_Codigo = PM.Produto_Codigo
        LEFT  JOIN {$pfx}NCM N     ON N.NCM_Codigo     = P.Produto_NCMCod
        LEFT  JOIN {$pfx}EmpresaMarca EM ON EM.EmpresaMarca_MarcaCod = PM.ProdutoMarca_MarcaCod
        LEFT  JOIN {$pfx}Empresa E       ON E.Empresa_Codigo = EM.Empresa_Codigo
        WHERE PM.ProdutoMarca_MarcaCod = ?
          AND NOT EXISTS (
            SELECT 1
            FROM [Portal_Integra].dbo.MF_Import I
            WHERE I.OpID = CAST(? AS uniqueidentifier)
              AND I.RefNorm = {$normSql}
          )
        GROUP BY PM.Produto_Codigo, COALESCE(PM.ProdutoMarca_Referencia, PM.ProdutoMarca_ReferenciaAlfanumerico),
                 P.Produto_Descricao, N.NCM_Codigo, N.NCM_Identificador
        ";
        $st = $pdo->prepare($sql);
        $st->execute([ $op, self::MARCA_ID, $op ]);

        return (int)$pdo->query("SELECT @@ROWCOUNT")->fetchColumn();
    }

    /* ================== HELPERS GERAIS ================== */

    /**
     * Insere o lote de auditoria via INSERT … SELECT direto de MF_Result.
     * Chamado por ajaxUpdateAll() e runUpdateAllBackground().
     *
     * @throws \RuntimeException em caso de falha no banco
     */
    private function insertAuditBatch(string $op, string $auditOp, string $now): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO [Portal_Integra].dbo.Atualizacao_Precos_DealerNet
                (IDUnico, dataHoraProcessamento, Marca_Codigo, Marca_Empresa,
                 Marca, Empresa, ProdutoCodigo, ProdutoReferencia, ProdutoDescricao,
                 NCMCodigo, NCMIdentificador, [ValorPlanilha(US$)], Markup,
                 FatorImportacao, CotacaoDolar, Tipo,
                 PrecoPublico, PrecoSugerido, PrecoGarantia, PrecoReposicao)
                SELECT
                    CAST(? AS uniqueidentifier), CAST(? AS datetime), CAST(? AS int),
                    ISNULL(Empresa, N''), ?, ISNULL(Empresa, N''),
                    TRY_CAST(ProdutoCodigo AS int), ProdutoReferencia, ProdutoDescricao,
                    TRY_CAST(NCMCodigo AS int), NCMIdentificador,
                    ISNULL(CAST(ValorPlanilha   AS decimal(18,4)), 0),
                    ISNULL(CAST(Overprice        AS decimal(18,4)), 0),
                    ISNULL(CAST(IndiceParaCusto  AS decimal(18,4)), 0),
                    CAST(1 AS decimal(18,4)), N'Massey-BRL',
                    ISNULL(CAST(PrecoPublico    AS decimal(18,4)), 0),
                    ISNULL(CAST(PrecoSugerido   AS decimal(18,4)), 0),
                    ISNULL(CAST(PrecoGarantia   AS decimal(18,4)), 0),
                    ISNULL(CAST(PrecoReposicao  AS decimal(18,4)), 0)
                FROM [Portal_Integra].dbo.MF_Result
                WHERE OpID = CAST(? AS uniqueidentifier) AND Tipo = 'MATCH'
            ")->execute([$auditOp, $now, self::MARCA_ID, self::MARCA_NOME, $op]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw new \RuntimeException('Falha ao registrar auditoria: ' . $e->getMessage());
        }
    }

    private function ensureStorageDir(): string
    {
        $dir = __DIR__ . '/../../storage/massey';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return realpath($dir) ?: $dir;
    }

    private function jobStep(string $op, string $step, string $status, string $msg=null, $total=null, $done=null, bool $finish=false): void
    {
        $pdo = Connection::get();
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        $finished = $finish ? $now : null;

        $sql = "
        UPDATE [Portal_Integra].dbo.JobProgress
           SET Step = ?, Status = ?, Message = ?,
               Total = ?, Done = ?,
               UpdatedAt = ?,
               FinishedAt = CASE WHEN ? IS NULL THEN FinishedAt ELSE ? END
         WHERE OpID = CAST(? AS uniqueidentifier)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([ $step, $status, $msg, $total, $done, $now, $finished, $finished, $op ]);
    }

    /** pt-BR / en-US */
    private function toNumber(string $s, float $def=0.0): float
    {
        if ($s === '' || $s === null) return $def;
        $s = preg_replace('/[^\d.,+\-]/', '', (string)$s);
        if ($s === '' || $s === '.' || $s === ',' || $s === '-' || $s === '+') return $def;

        $hasDot   = strpos($s, '.') !== false;
        $hasComma = strpos($s, ',') !== false;

        if ($hasDot && $hasComma) {
            $lastDot   = strrpos($s, '.');
            $lastComma = strrpos($s, ',');
            if ($lastComma !== false && $lastComma > $lastDot) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($hasComma && !$hasDot) {
            $s = str_replace(',', '.', $s);
        }

        if ($s === '' || $s === '.' || $s === '-' || $s === '+') return $def;
        return (float)$s;
    }

    private function percentToFraction(string $txt, float $default): float
    {
        $s = trim($txt);
        $hasPercent = strpos($s, '%') !== false;
        $s = preg_replace('/[^\d.,+\-]/','', $s);
        $s = str_replace(',', '.', $s);
        if ($s === '' || $s === '.' || $s === '-' || $s === '+') return $default;
        $num = (float)$s;
        if ($hasPercent || $num > 1.0) return $num / 100.0;
        return $num;
    }

    private function normalizeRefStrict(string $s): string
    {
        $u = strtoupper(trim($s));
        return preg_replace('/[^A-Z0-9]/', '', $u) ?? '';
    }

    private function csvFormat(string $col, $val): string
    {
        static $raw = ['ProdutoCodigo','ProdutoReferencia','NCMIdentificador','NCMCodigo'];
        if (in_array($col, $raw, true)) return (string)$val;
        if (is_numeric($val)) return number_format((float)$val, 2, ',', '.');
        return (string)$val;
    }

    private function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
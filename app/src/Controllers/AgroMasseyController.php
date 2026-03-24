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
    private const BATCH_ROWS      = 300;    // 300*6=1800 params < 2100 (limite do SQL Server)
    private const COMMIT_EVERY    = 30000;  // commit parcial a cada 30k linhas
    private const PROGRESS_EVERY  = 5000;   // atualiza JobProgress a cada 5k linhas

    // Paginação: tamanhos disponíveis
    private const PAGE_SIZES = [25, 100, 1000, 5000];

    public function index()
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('agro.tabela_massey', 2);

        $action = (string)($_GET['action'] ?? '');

        switch ($action) {
            case 'upload':   return $this->ajaxUpload();
            case 'process':  return $this->ajaxProcess();
            case 'status':   return $this->ajaxStatus();
            case 'results':  return $this->renderResults();
            case 'export':   return $this->exportFromDb();
            case 'update':   return $this->updateFromDb();
            case 'ping':     echo 'pong'; return;
            default:         return $this->renderMain();
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
            'matchMeta'        => ['total'=>0,'p'=>1,'pp'=>25,'qs'=>''],
            'sysMeta'          => ['total'=>0,'p'=>1,'pp'=>25,'qs'=>''],
            'planMeta'         => ['total'=>0,'p'=>1,'pp'=>25,'qs'=>''],
            'filters'          => [],
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

    private function ajaxProcess()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $op = (string)($_GET['op'] ?? '');
            if ($op === '' || !preg_match('/^[0-9a-fA-F-]{36}$/', $op)) {
                throw new \RuntimeException('OpID inválido.');
            }

            global $config;
            $dealerDb = $config['db']['dealernet_database'] ?? '';
            if ($dealerDb === '') throw new \RuntimeException('DealerNet não configurado.');

            $pdo = Connection::get();

            // Parâmetros do job
            $st = $pdo->prepare("SELECT Overprice, IndiceParaCusto, UploadedFilePath FROM [Portal_Integra].dbo.JobProgress WHERE OpID = CAST(? AS uniqueidentifier)");
            $st->execute([$op]);
            $job = $st->fetch();
            if (!$job) throw new \RuntimeException('Operação não encontrada.');
            $over = (float)$job['Overprice'];
            $indice = (float)$job['IndiceParaCusto'];
            $filePath = (string)$job['UploadedFilePath'];

            // 1) IMPORT com total/progresso
            $this->jobStep($op, 'IMPORT', 'RUNNING', 'Contando linhas do arquivo ...', null, 0);
            $totalLines = $this->countFileLines($filePath);
            $this->jobStep($op, 'IMPORT', 'RUNNING', "Importando para staging ...", $totalLines, 0);

            $this->clearOpData($op);
            $totalImported = $this->importFileToStaging($op, $filePath, $totalLines);
            $this->jobStep($op, 'IMPORT', 'OK', "Linhas importadas: {$totalImported}", $totalImported, $totalImported);

            // 2) MATCH
            $this->jobStep($op, 'MATCH', 'RUNNING', 'Gerando itens conciliados (MATCH) ...', null, null);
            $matchCount = $this->sqlInsertMatch($op, $dealerDb, $over, $indice);
            $this->jobStep($op, 'MATCH', 'OK', "Itens conciliados: {$matchCount}", $matchCount, $matchCount);

            // 3) ONLY_PLAN
            $this->jobStep($op, 'ONLY_PLAN', 'RUNNING', 'Gerando itens apenas na planilha ...', null, null);
            $onlyPlan = $this->sqlInsertOnlyPlan($op, $dealerDb);
            $this->jobStep($op, 'ONLY_PLAN', 'OK', "Itens apenas planilha: {$onlyPlan}", $onlyPlan, $onlyPlan);

            // 4) ONLY_SYS
            $this->jobStep($op, 'ONLY_SYS', 'RUNNING', 'Gerando itens apenas no DealerNet ...', null, null);
            $onlySys = $this->sqlInsertOnlySys($op, $dealerDb);
            $this->jobStep($op, 'ONLY_SYS', 'OK', "Itens apenas DealerNet: {$onlySys}", $onlySys, $onlySys);

            // DONE
            $this->jobStep($op, 'DONE', 'OK', 'Processo concluído.', null, null, true);

            echo json_encode(['ok'=>true, 'op'=>$op], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $op = (string)($_GET['op'] ?? '');
            if ($op) { try { $this->jobStep($op, 'ERROR', 'ERROR', $e->getMessage(), null, null, true); } catch (\Throwable $e2) {} }
            http_response_code(500);
            echo json_encode(['ok'=>false,'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        return;
    }

    private function ajaxStatus()
    {
        header('Content-Type: application/json; charset=UTF-8');
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
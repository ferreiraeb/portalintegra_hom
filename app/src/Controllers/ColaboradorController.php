<?php
namespace Controllers;

use Database\OracleConnection;

class ColaboradorController
{
    private const VIEW = 'SIRH.VW_RH_COLABORADORES';

    private const COLUNAS = [
        'NOMECOMPLETO'           => 'Nome',
        'CODPESSOA'              => 'Cód. Pessoa',
        'CODCONTRATO'            => 'Cód. Contrato',
        'CPF'                    => 'CPF',
        'SEXO'                   => 'Sexo',
        'NASCIMENTO'             => 'Nascimento',
        'CARGO'                  => 'Cargo',
        'EMPRESA'                => 'Empresa',
        'UNIDADE'                => 'Unidade',
        'CLASSIFICACAOGERENCIAL' => 'Classificação Gerencial',
        'CENTROCUSTO'            => 'Centro de Custo',
        'SETOR'                  => 'Setor',
        'LIDER'                  => 'Líder',
        'SITUACAOCONTRATO'       => 'Situação',
        'DATAADMISSAO'           => 'Admissão',
        'DATARESCISAO'           => 'Rescisão',
        'STATUS'                 => 'Status',
    ];

    public function calendarioAniversarios(): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('hr.colaboradores', 1);

        $mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
        $ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

        if ($mes < 1) {
            $mes = 12;
            $ano--;
        } elseif ($mes > 12) {
            $mes = 1;
            $ano++;
        }

        if ($ano < 1970) {
            $ano = 1970;
        } elseif ($ano > 2100) {
            $ano = 2100;
        }

        $prevMes = $mes - 1;
        $prevAno = $ano;
        if ($prevMes < 1) {
            $prevMes = 12;
            $prevAno--;
        }

        $nextMes = $mes + 1;
        $nextAno = $ano;
        if ($nextMes > 12) {
            $nextMes = 1;
            $nextAno++;
        }

        $erro                = null;
        $aniversariantes     = [];
        $aniversariantesSem  = [];
        $aniversariantesDia  = [];
        $porDia              = [];

        $hoje         = new \DateTimeImmutable('today');
        $inicioSemana = $hoje->modify('-' . ((int)$hoje->format('N') - 1) . ' days');
        $fimSemana    = $inicioSemana->modify('+6 days');

        try {
            $service             = new \Services\AniversarioService();
            $aniversariantes     = $service->getAniversariantesDoMes($mes);
            $aniversariantesSem  = $service->getAniversariantesDaSemana($hoje);
            $aniversariantesDia  = $service->getAniversariantesDoDia($hoje);
            foreach ($aniversariantes as $row) {
                $dia = (int)($row['DIA_ANIV'] ?? 0);
                if ($dia < 1) {
                    continue;
                }
                $porDia[$dia][] = $row;
            }
        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        }

        $primeiroDia = new \DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes));
        $diasNoMes   = (int)$primeiroDia->format('t');
        $offset      = (int)$primeiroDia->format('N') - 1; // segunda = 0

        $hojeDia  = ($hoje->format('n') === (string)$mes && $hoje->format('Y') === (string)$ano)
            ? (int)$hoje->format('j')
            : null;

        $mesesPt = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        $di = (int)$inicioSemana->format('j');
        $mi = (int)$inicioSemana->format('n');
        $df = (int)$fimSemana->format('j');
        $mf = (int)$fimSemana->format('n');
        $semanaLabel = $this->formatSemanaLabel($inicioSemana, $fimSemana);

        $diaLabel = $this->formatDiaLabel($hoje);

        render_page('colaboradores/calendario.php', [
            'mes'                 => $mes,
            'ano'                 => $ano,
            'mesLabel'            => $mesesPt[$mes] ?? (string)$mes,
            'mesesPt'             => $mesesPt,
            'prevMes'             => $prevMes,
            'prevAno'             => $prevAno,
            'nextMes'             => $nextMes,
            'nextAno'             => $nextAno,
            'diasNoMes'           => $diasNoMes,
            'offset'              => $offset,
            'hojeDia'             => $hojeDia,
            'porDia'              => $porDia,
            'aniversariantes'     => $aniversariantes,
            'aniversariantesSem'  => $aniversariantesSem,
            'aniversariantesDia'  => $aniversariantesDia,
            'semanaLabel'         => $semanaLabel,
            'diaLabel'            => $diaLabel,
            'semanaAnoRef'        => (int)$hoje->format('Y'),
            'erro'                => $erro,
            'isGlobalAdmin'       => \Security\Permission::isAdmin(),
        ]);
    }

    /** GET ?ref=AAAA-MM-DD — aniversariantes da semana e do dia para a data informada. */
    public function calendarioAniversariantesJson(): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('hr.colaboradores', 1);
        header('Content-Type: application/json; charset=utf-8');

        $ref = trim((string)($_GET['ref'] ?? ''));
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $ref);
        if (!$parsed || $parsed->format('Y-m-d') !== $ref) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Data inválida. Use AAAA-MM-DD.']);
            return;
        }

        try {
            $service = new \Services\AniversarioService();
            $semana  = $service->getAniversariantesDaSemana($parsed);
            $dia     = $service->getAniversariantesDoDia($parsed);

            $inicioSemana = $parsed->modify('-' . ((int)$parsed->format('N') - 1) . ' days');
            $fimSemana    = $inicioSemana->modify('+6 days');

            echo json_encode([
                'ok'          => true,
                'ref'         => $ref,
                'semanaLabel' => $this->formatSemanaLabel($inicioSemana, $fimSemana),
                'diaLabel'    => $this->formatDiaLabel($parsed),
                'semana'      => $this->serializeAniversariantesJson($semana),
                'dia'         => $this->serializeAniversariantesJson($dia),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** @return array<int, string> */
    private function mesesPtMap(): array
    {
        return [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
    }

    private function formatSemanaLabel(\DateTimeImmutable $inicio, \DateTimeImmutable $fim): string
    {
        $mesesPt = $this->mesesPtMap();
        $di      = (int)$inicio->format('j');
        $mi      = (int)$inicio->format('n');
        $df      = (int)$fim->format('j');
        $mf      = (int)$fim->format('n');

        if ($mi === $mf) {
            return "{$di}–{$df} de " . ($mesesPt[$mi] ?? '');
        }

        return "{$di} de " . ($mesesPt[$mi] ?? '') . " – {$df} de " . ($mesesPt[$mf] ?? '');
    }

    private function formatDiaLabel(\DateTimeImmutable $date): string
    {
        $mesesPt = $this->mesesPtMap();
        return (int)$date->format('j') . ' de ' . ($mesesPt[(int)$date->format('n')] ?? '');
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function serializeAniversariantesJson(array $rows): array
    {
        return array_map(function (array $row): array {
            return [
                'codpessoa' => (string)($row['CODPESSOA'] ?? ''),
                'nome'      => (string)($row['NOMECOMPLETO'] ?? ''),
                'dia_aniv'  => (int)($row['DIA_ANIV'] ?? 0),
                'mes_aniv'  => (int)($row['MES_ANIV'] ?? 0),
            ];
        }, $rows);
    }

    /** POST — dispara e-mails de aniversário (grupo + individual) para destinatários informados. */
    public function enviarEmailAniversario(): void
    {
        \Security\Auth::requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        if (!\Security\Permission::isAdmin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Acesso negado.']);
            return;
        }

        if (!is_post()) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
            return;
        }

        if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'CSRF token inválido. Recarregue a página e tente novamente.']);
            return;
        }

        global $config;

        if (!($config['birthday']['enabled'] ?? true)) {
            echo json_encode(['ok' => false, 'error' => 'Envio de aniversários desabilitado (birthday.enabled = false).']);
            return;
        }

        $recipients = $this->parseEmailList((string)($_POST['emails'] ?? ''));
        if (empty($recipients)) {
            echo json_encode(['ok' => false, 'error' => 'Informe ao menos um e-mail válido.']);
            return;
        }

        try {
            $aniversarioService = new \Services\AniversarioService();
            $aniversariantes    = $aniversarioService->getAniversariantesDoDia();

            if (empty($aniversariantes)) {
                echo json_encode(['ok' => false, 'error' => 'Nenhum aniversariante hoje.']);
                return;
            }

            $mailService          = new \Services\MailService($config['mail'] ?? []);
            $birthdayEmailService = new \Services\BirthdayEmailService(
                $mailService,
                $config['birthday'] ?? [],
                dirname(__DIR__, 2)
            );

            $birthdayEmailService->sendGroupEmail($aniversariantes, false, $recipients);
            $sentIndividuals = $birthdayEmailService->sendIndividualEmails($aniversariantes, false, $recipients);

            $nomes = array_map(fn(array $r) => (string)($r['NOMECOMPLETO'] ?? ''), $aniversariantes);

            echo json_encode([
                'ok'      => true,
                'message' => sprintf(
                    'E-mails enviados para %s: resumo de grupo e %d e-mail(s) individual(is) (%d aniversariante(s): %s).',
                    implode(', ', $recipients),
                    count($sentIndividuals),
                    count($aniversariantes),
                    implode(', ', $nomes)
                ),
                'group_recipients'    => $recipients,
                'individual_count'    => count($sentIndividuals),
                'aniversariante_count'=> count($aniversariantes),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** @return string[] */
    private function parseEmailList(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $valid = [];
        foreach ($parts as $part) {
            $email = trim($part);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            }
        }
        return array_values(array_unique($valid));
    }

    public function organograma(): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('hr.colaboradores', 1);

        $action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));
        if ($action !== '') {
            $this->organogramaAction($action);
            return;
        }

        if (($_GET['format'] ?? '') !== 'json') {
            render_page('colaboradores/organograma.php', [
                'canProposeEdit' => \Security\Permission::level('hr.organograma_proposta') >= 1,
                'csrfToken'      => csrf_token(),
            ]);
            return;
        }

        $this->organogramaJsonTree();
    }

    private function organogramaAction(string $action): void
    {
        switch ($action) {
            case 'drafts':
                $this->orgDraftList();
                return;
            case 'draft_save':
                $this->orgDraftSave();
                return;
            case 'draft_load':
                $this->orgDraftLoad();
                return;
            case 'draft_delete':
                $this->orgDraftDelete();
                return;
            case 'export_proposta':
                $this->orgExportProposta();
                return;
            default:
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['error' => 'Ação inválida.']);
        }
    }

    private function requireOrgProposta(): void
    {
        \Security\Permission::require('hr.organograma_proposta', 1);
    }

    private function orgCurrentUserId(): int
    {
        return (int)($_SESSION['user']['id'] ?? 0);
    }

    private function orgEnsureCpfMap(): void
    {
        if (!isset($_SESSION['org_cpf_map']) || !is_array($_SESSION['org_cpf_map'])) {
            $_SESSION['org_cpf_map'] = [];
        }
    }

    private function orgJsonOut($data, int $code = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data);
    }

    private function orgDraftList(): void
    {
        $this->requireOrgProposta();
        try {
            $pdo = \Database\Connection::get();
            $st  = $pdo->prepare(
                "SELECT id, created_at, updated_at, base_snapshot_at, title
                 FROM dbo.org_chart_drafts
                 WHERE user_id = :uid
                 ORDER BY updated_at DESC, id DESC"
            );
            $st->execute([':uid' => $this->orgCurrentUserId()]);
            $fetched = $st->fetchAll();

            // Conta ocorrências do mesmo rótulo base (data/hora) em ordem de criação,
            // para: "16/08/2026 17:37", "16/08/2026 17:37 (1)", "16/08/2026 17:37 (2)"
            $byIdAsc = $fetched;
            usort($byIdAsc, static function ($a, $b) {
                return ((int)$a['id']) <=> ((int)$b['id']);
            });
            $seqByBase = [];
            $labelById = [];
            foreach ($byIdAsc as $row) {
                $base = $this->orgFmtDt($row['updated_at'] ?? $row['created_at'] ?? null);
                if ($base === '') {
                    $base = '#' . (int)$row['id'];
                }
                $n = $seqByBase[$base] ?? 0;
                $seqByBase[$base] = $n + 1;
                $labelById[(int)$row['id']] = $n === 0 ? $base : ($base . ' (' . $n . ')');
            }

            $rows = [];
            foreach ($fetched as $row) {
                $id = (int)$row['id'];
                $rows[] = [
                    'id'               => $id,
                    'created_at'       => $this->orgFmtDt($row['created_at'] ?? null),
                    'updated_at'       => $this->orgFmtDt($row['updated_at'] ?? null),
                    'base_snapshot_at' => $this->orgFmtDt($row['base_snapshot_at'] ?? null),
                    'title'            => $row['title'] !== null ? (string)$row['title'] : null,
                    'label'            => $labelById[$id] ?? $this->orgFmtDt($row['updated_at'] ?? null),
                ];
            }
            $this->orgJsonOut(['drafts' => $rows]);
        } catch (\Throwable $e) {
            $this->orgJsonOut(['error' => $e->getMessage()], 500);
        }
    }

    private function orgDraftSave(): void
    {
        $this->requireOrgProposta();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->orgJsonOut(['error' => 'Método não permitido.'], 405);
            return;
        }
        check_csrf();

        $draftId = (int)($_POST['draft_id'] ?? 0);
        $payloadRaw = (string)($_POST['payload'] ?? '');
        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded) || !isset($decoded['changes']) || !is_array($decoded['changes'])) {
            $this->orgJsonOut(['error' => 'Payload inválido.'], 400);
            return;
        }

        $changes = $this->orgNormalizeChanges($decoded['changes']);
        $cycleError = $this->orgValidateNoCycles($changes);
        if ($cycleError !== null) {
            $this->orgJsonOut(['error' => $cycleError], 400);
            return;
        }

        $baseSnapshotRaw = trim((string)($decoded['base_snapshot_at'] ?? ''));
        $baseSnapshotSql = $this->orgToSqlDateTime($baseSnapshotRaw);
        $title = isset($decoded['title']) ? mb_substr(trim((string)$decoded['title']), 0, 200) : null;
        if ($title === '') {
            $title = null;
        }

        $store = json_encode([
            'changes'          => $changes,
            'base_snapshot_at' => $baseSnapshotSql,
            'title'            => $title,
        ], JSON_UNESCAPED_UNICODE);

        $uid = $this->orgCurrentUserId();

        try {
            $pdo = \Database\Connection::get();

            if ($draftId > 0) {
                $chk = $pdo->prepare(
                    "SELECT id FROM dbo.org_chart_drafts WHERE id = :id AND user_id = :uid"
                );
                $chk->execute([':id' => $draftId, ':uid' => $uid]);
                if (!$chk->fetch()) {
                    $this->orgJsonOut(['error' => 'Rascunho não encontrado.'], 404);
                    return;
                }
                $upd = $pdo->prepare(
                    "UPDATE dbo.org_chart_drafts
                     SET payload = :payload,
                         base_snapshot_at = CONVERT(datetime, :base_at, 120),
                         title = :title,
                         updated_at = GETDATE()
                     WHERE id = :id AND user_id = :uid"
                );
                $upd->execute([
                    ':payload' => $store,
                    ':base_at' => $baseSnapshotSql,
                    ':title'   => $title,
                    ':id'      => $draftId,
                    ':uid'     => $uid,
                ]);
                $this->orgJsonOut([
                    'ok'         => true,
                    'draft_id'   => $draftId,
                    'updated_at' => date('d/m/Y H:i'),
                ]);
                return;
            }

            $ins = $pdo->prepare(
                "INSERT INTO dbo.org_chart_drafts (user_id, base_snapshot_at, title, payload, created_at, updated_at)
                 OUTPUT INSERTED.id
                 VALUES (:uid, CONVERT(datetime, :base_at, 120), :title, :payload, GETDATE(), GETDATE())"
            );
            $ins->execute([
                ':uid'     => $uid,
                ':base_at' => $baseSnapshotSql,
                ':title'   => $title,
                ':payload' => $store,
            ]);
            $row = $ins->fetch(\PDO::FETCH_ASSOC);
            $newId = (int)($row['id'] ?? 0);

            $this->orgJsonOut([
                'ok'         => true,
                'draft_id'   => $newId,
                'updated_at' => date('d/m/Y H:i'),
            ]);
        } catch (\Throwable $e) {
            $this->orgJsonOut(['error' => $e->getMessage()], 500);
        }
    }

    private function orgDraftLoad(): void
    {
        $this->requireOrgProposta();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->orgJsonOut(['error' => 'ID inválido.'], 400);
            return;
        }
        try {
            $pdo = \Database\Connection::get();
            $st  = $pdo->prepare(
                "SELECT id, created_at, updated_at, base_snapshot_at, title, payload
                 FROM dbo.org_chart_drafts
                 WHERE id = :id AND user_id = :uid"
            );
            $st->execute([':id' => $id, ':uid' => $this->orgCurrentUserId()]);
            $row = $st->fetch();
            if (!$row) {
                $this->orgJsonOut(['error' => 'Rascunho não encontrado.'], 404);
                return;
            }
            $payload = json_decode((string)$row['payload'], true);
            if (!is_array($payload)) {
                $payload = ['changes' => []];
            }
            $this->orgJsonOut([
                'id'               => (int)$row['id'],
                'created_at'       => $this->orgFmtDt($row['created_at'] ?? null),
                'updated_at'       => $this->orgFmtDt($row['updated_at'] ?? null),
                'base_snapshot_at' => $this->orgFmtDt($row['base_snapshot_at'] ?? null),
                'title'            => $row['title'] !== null ? (string)$row['title'] : null,
                'payload'          => $payload,
            ]);
        } catch (\Throwable $e) {
            $this->orgJsonOut(['error' => $e->getMessage()], 500);
        }
    }

    private function orgDraftDelete(): void
    {
        $this->requireOrgProposta();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->orgJsonOut(['error' => 'Método não permitido.'], 405);
            return;
        }
        check_csrf();
        $id = (int)($_POST['draft_id'] ?? 0);
        if ($id <= 0) {
            $this->orgJsonOut(['error' => 'ID inválido.'], 400);
            return;
        }
        try {
            $pdo = \Database\Connection::get();
            $st  = $pdo->prepare(
                "DELETE FROM dbo.org_chart_drafts WHERE id = :id AND user_id = :uid"
            );
            $st->execute([':id' => $id, ':uid' => $this->orgCurrentUserId()]);
            if ($st->rowCount() === 0) {
                $this->orgJsonOut(['error' => 'Rascunho não encontrado.'], 404);
                return;
            }
            $this->orgJsonOut(['ok' => true]);
        } catch (\Throwable $e) {
            $this->orgJsonOut(['error' => $e->getMessage()], 500);
        }
    }

    private function orgExportProposta(): void
    {
        $this->requireOrgProposta();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: text/plain; charset=UTF-8');
            http_response_code(405);
            echo 'Método não permitido.';
            return;
        }
        check_csrf();

        $payloadRaw = (string)($_POST['payload'] ?? '');
        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded) || !isset($decoded['changes']) || !is_array($decoded['changes'])) {
            header('Content-Type: text/plain; charset=UTF-8');
            http_response_code(400);
            echo 'Payload inválido.';
            return;
        }

        $changes = $this->orgNormalizeChanges($decoded['changes']);
        if ($changes === []) {
            header('Content-Type: text/plain; charset=UTF-8');
            http_response_code(400);
            echo 'Nenhuma alteração para exportar.';
            return;
        }

        $cycleError = $this->orgValidateNoCycles($changes);
        if ($cycleError !== null) {
            header('Content-Type: text/plain; charset=UTF-8');
            http_response_code(400);
            echo $cycleError;
            return;
        }

        $this->orgEnsureCpfMap();
        $hashes = [];
        foreach ($changes as $ch) {
            $hashes[$ch['id']] = true;
            if ($ch['fromPid'] !== null) {
                $hashes[$ch['fromPid']] = true;
            }
            if ($ch['toPid'] !== null) {
                $hashes[$ch['toPid']] = true;
            }
        }

        $cpfByHash = [];
        foreach (array_keys($hashes) as $h) {
            if (isset($_SESSION['org_cpf_map'][$h])) {
                $cpfByHash[$h] = (string)$_SESSION['org_cpf_map'][$h];
            }
        }

        $infoByCpf = $this->orgLookupPeopleByCpfs(array_values(array_unique(array_values($cpfByHash))));

        $exporter = (string)($_SESSION['user']['nome'] ?? $_SESSION['user']['login'] ?? '');
        $exportAt = date('d/m/Y H:i');
        $baseEm   = trim((string)($decoded['base_snapshot_at'] ?? ''));
        $rascunhoEm = trim((string)($decoded['draft_updated_at'] ?? ''));

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="organograma_proposta_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";

        $f = fopen('php://output', 'w');
        fputcsv($f, [
            'Nome colaborador',
            'CPF colaborador',
            'Cargo',
            'Empresa',
            'Unidade',
            'Líder oficial (nome)',
            'Líder oficial (CPF)',
            'Novo líder proposto (nome)',
            'Novo líder proposto (CPF)',
            'Exportado por',
            'Exportado em',
            'Base em',
            'Rascunho em',
        ], ';');

        foreach ($changes as $ch) {
            $cpf = $cpfByHash[$ch['id']] ?? '';
            $info = $infoByCpf[$cpf] ?? [];
            $fromCpf = $ch['fromPid'] !== null ? ($cpfByHash[$ch['fromPid']] ?? '') : '';
            $toCpf   = $ch['toPid'] !== null ? ($cpfByHash[$ch['toPid']] ?? '') : '';
            $fromInfo = $fromCpf !== '' ? ($infoByCpf[$fromCpf] ?? []) : [];
            $toInfo   = $toCpf !== '' ? ($infoByCpf[$toCpf] ?? []) : [];

            $nome = (string)($ch['nome'] ?? $info['nome'] ?? '');
            $fromNome = (string)($ch['fromNome'] ?? $fromInfo['nome'] ?? ($ch['fromPid'] === null ? '(sem líder)' : ''));
            $toNome   = (string)($ch['toNome'] ?? $toInfo['nome'] ?? ($ch['toPid'] === null ? '(sem líder)' : ''));

            fputcsv($f, [
                $nome,
                $this->orgFormatCpf($cpf),
                (string)($ch['cargo'] ?? $info['cargo'] ?? ''),
                (string)($ch['empresa'] ?? $info['empresa'] ?? ''),
                (string)($ch['unidade'] ?? $info['unidade'] ?? ''),
                $fromNome,
                $this->orgFormatCpf($fromCpf),
                $toNome,
                $this->orgFormatCpf($toCpf),
                $exporter,
                $exportAt,
                $baseEm,
                $rascunhoEm,
            ], ';');
        }
        fclose($f);
    }

    /** @param list<array<string,mixed>> $raw */
    private function orgNormalizeChanges(array $raw): array
    {
        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string)($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $fromPid = array_key_exists('fromPid', $item) && $item['fromPid'] !== null && $item['fromPid'] !== ''
                ? (string)$item['fromPid'] : null;
            $toPid = array_key_exists('toPid', $item) && $item['toPid'] !== null && $item['toPid'] !== ''
                ? (string)$item['toPid'] : null;
            if ($fromPid === $toPid) {
                continue;
            }
            $out[] = [
                'id'       => $id,
                'fromPid'  => $fromPid,
                'toPid'    => $toPid,
                'nome'     => isset($item['nome']) ? (string)$item['nome'] : null,
                'fromNome' => isset($item['fromNome']) ? (string)$item['fromNome'] : null,
                'toNome'   => isset($item['toNome']) ? (string)$item['toNome'] : null,
                'cargo'    => isset($item['cargo']) ? (string)$item['cargo'] : null,
                'empresa'  => isset($item['empresa']) ? (string)$item['empresa'] : null,
                'unidade'  => isset($item['unidade']) ? (string)$item['unidade'] : null,
            ];
        }
        return $out;
    }

    /** @param list<array{id:string,fromPid:?string,toPid:?string}> $changes */
    private function orgValidateNoCycles(array $changes): ?string
    {
        foreach ($changes as $ch) {
            if ($ch['toPid'] === $ch['id']) {
                return 'Um colaborador não pode ser líder de si mesmo.';
            }
        }

        $parent = $this->orgOfficialParentMapByHash();
        foreach ($changes as $ch) {
            $parent[$ch['id']] = $ch['toPid'];
        }

        foreach ($changes as $ch) {
            if ($ch['toPid'] === null) {
                continue;
            }
            // Walk up from proposed leader; if we hit the node, cycle
            $seen = [];
            $cur  = $ch['toPid'];
            $guard = 0;
            while ($cur !== null && $guard++ < 2000) {
                if ($cur === $ch['id']) {
                    return 'Não é permitido selecionar alguém da própria subárvore como líder (ciclo).';
                }
                if (isset($seen[$cur])) {
                    return 'A alteração criaria um ciclo na hierarquia.';
                }
                $seen[$cur] = true;
                $cur = $parent[$cur] ?? null;
            }
        }

        return null;
    }

    /** @return array<string,?string> hash => parent hash|null */
    private function orgOfficialParentMapByHash(): array
    {
        $this->orgEnsureCpfMap();
        $map = [];
        try {
            $pdo = OracleConnection::get();
            $sql = "SELECT v.CPF, v.CPFLIDER
                    FROM " . self::VIEW . " v
                    WHERE v.STATUS = 'ATIVO'
                      AND (v.CPFLIDER IS NULL OR EXISTS (
                          SELECT 1 FROM " . self::VIEW . " a
                          WHERE a.CPF = v.CPFLIDER AND a.STATUS = 'ATIVO'
                      ))";
            $st = $pdo->query($sql);
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $row = array_change_key_case($row, CASE_UPPER);
                $hash = md5((string)($row['CPF'] ?? ''));
                $_SESSION['org_cpf_map'][$hash] = (string)($row['CPF'] ?? '');
                $pid = null;
                if (($row['CPFLIDER'] ?? '') !== '') {
                    $pid = md5((string)$row['CPFLIDER']);
                    $_SESSION['org_cpf_map'][$pid] = (string)$row['CPFLIDER'];
                }
                $map[$hash] = $pid;
            }
        } catch (\Throwable $e) {
            // Fall back to empty — client still validates
        }
        return $map;
    }

    /** @param list<string> $cpfs @return array<string,array{nome:string,cargo:string,empresa:string,unidade:string}> */
    private function orgLookupPeopleByCpfs(array $cpfs): array
    {
        $cpfs = array_values(array_filter(array_unique($cpfs), fn($c) => $c !== ''));
        if ($cpfs === []) {
            return [];
        }
        $out = [];
        try {
            $pdo = OracleConnection::get();
            // Oracle IN limit — chunk
            foreach (array_chunk($cpfs, 500) as $chunk) {
                $placeholders = [];
                $binds = [];
                foreach ($chunk as $i => $cpf) {
                    $k = ':c' . $i;
                    $placeholders[] = $k;
                    $binds[$k] = $cpf;
                }
                $in = implode(',', $placeholders);
                $sql = "SELECT CPF, NOMECOMPLETO, CARGO, EMPRESA, UNIDADE
                        FROM " . self::VIEW . "
                        WHERE CPF IN ({$in}) AND STATUS = 'ATIVO'";
                $st = $pdo->prepare($sql);
                $st->execute($binds);
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $row = array_change_key_case($row, CASE_UPPER);
                    $cpf = (string)($row['CPF'] ?? '');
                    $out[$cpf] = [
                        'nome'    => (string)($row['NOMECOMPLETO'] ?? ''),
                        'cargo'   => (string)($row['CARGO'] ?? ''),
                        'empresa' => (string)($row['EMPRESA'] ?? ''),
                        'unidade' => (string)($row['UNIDADE'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // keep empty — CSV still has names from payload when present
        }
        return $out;
    }

    private function orgFormatCpf(string $cpf): string
    {
        $v = preg_replace('/\D+/', '', $cpf) ?? $cpf;
        if (strlen($v) === 11) {
            return substr($v, 0, 3) . '.' . substr($v, 3, 3) . '.'
                 . substr($v, 6, 3) . '-' . substr($v, 9, 2);
        }
        return $cpf;
    }

    private function orgFmtDt($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }
        $ts = strtotime((string)$value);
        return $ts !== false ? date('d/m/Y H:i', $ts) : (string)$value;
    }

    /**
     * Converte string de data do client para yyyy-mm-dd HH:mm:ss (estilo 120),
     * evitando ambiguidade DMY do SQL Server com datas tipo 2026-08-16.
     */
    private function orgToSqlDateTime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sP',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
        ];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $value);
            if ($dt instanceof \DateTime) {
                $errors = \DateTime::getLastErrors();
                if (empty($errors['warning_count']) && empty($errors['error_count'])) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function organogramaJsonTree(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->orgEnsureCpfMap();

        $cpfHash      = trim($_GET['cpf']       ?? '');
        $search       = trim($_GET['search']    ?? '');
        $expandAll    = isset($_GET['expand_all']);
        $ancestorHash = trim($_GET['ancestors'] ?? '');
        $orphans      = isset($_GET['orphans']);
        $view         = self::VIEW;

        try {
            $pdo   = OracleConnection::get();
            $binds = [];

            if ($orphans) {
                $sql = "SELECT v.NOMECOMPLETO, v.CARGO, v.EMPRESA, v.UNIDADE,
                               l.NOMECOMPLETO  AS LIDER_NOME,
                               l.CARGO         AS LIDER_CARGO,
                               l.STATUS        AS LIDER_STATUS,
                               TO_CHAR(l.DATARESCISAO, 'DD/MM/YYYY') AS LIDER_RESCISAO
                        FROM {$view} v
                        JOIN (
                            SELECT CPF, NOMECOMPLETO, CARGO, STATUS, DATARESCISAO,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY CPF
                                       ORDER BY DATARESCISAO DESC NULLS LAST
                                   ) AS RN
                            FROM {$view}
                            WHERE STATUS = 'INATIVO' OR DATARESCISAO IS NOT NULL
                        ) l ON l.CPF = v.CPFLIDER AND l.RN = 1
                        WHERE v.STATUS     = 'ATIVO'
                          AND v.CPFLIDER  IS NOT NULL
                          AND NOT EXISTS (
                              SELECT 1 FROM {$view} a
                              WHERE a.CPF = v.CPFLIDER AND a.STATUS = 'ATIVO'
                          )
                        ORDER BY v.NOMECOMPLETO";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $out  = [];
                foreach ($rows as $row) {
                    $row   = array_change_key_case($row, CASE_UPPER);
                    $out[] = [
                        'nome'           => (string)($row['NOMECOMPLETO']   ?? ''),
                        'cargo'          => (string)($row['CARGO']          ?? ''),
                        'empresa'        => (string)($row['EMPRESA']        ?? ''),
                        'unidade'        => (string)($row['UNIDADE']        ?? ''),
                        'lider_nome'     => (string)($row['LIDER_NOME']     ?? ''),
                        'lider_cargo'    => (string)($row['LIDER_CARGO']    ?? ''),
                        'lider_status'   => (string)($row['LIDER_STATUS']   ?? ''),
                        'lider_rescisao' => (string)($row['LIDER_RESCISAO'] ?? ''),
                    ];
                }
                echo json_encode($out);
                return;

            } elseif ($expandAll) {
                $sql = "SELECT v.CPF, v.CPFLIDER, v.NOMECOMPLETO, v.CARGO, v.EMPRESA, v.UNIDADE,
                               CASE WHEN EXISTS (
                                   SELECT 1 FROM {$view} c WHERE c.CPFLIDER = v.CPF AND c.STATUS = 'ATIVO'
                               ) THEN 1 ELSE 0 END AS HAS_CHILDREN
                        FROM {$view} v
                        WHERE v.STATUS = 'ATIVO'
                          AND (v.CPFLIDER IS NULL OR EXISTS (
                              SELECT 1 FROM {$view} a WHERE a.CPF = v.CPFLIDER AND a.STATUS = 'ATIVO'
                          ))
                        ORDER BY v.NOMECOMPLETO";
                $mode = 'expand_all';

            } elseif ($ancestorHash !== '') {
                $rawCpf = $_SESSION['org_cpf_map'][$ancestorHash] ?? null;
                if ($rawCpf === null) {
                    echo json_encode(['error' => 'Hash não encontrado. Recarregue a página.']);
                    return;
                }
                $sql = "SELECT v.CPF, v.CPFLIDER, v.NOMECOMPLETO, v.CARGO, v.EMPRESA, v.UNIDADE,
                               CASE WHEN EXISTS (
                                   SELECT 1 FROM {$view} c WHERE c.CPFLIDER = v.CPF AND c.STATUS = 'ATIVO'
                               ) THEN 1 ELSE 0 END AS HAS_CHILDREN
                        FROM {$view} v
                        WHERE v.STATUS = 'ATIVO'
                        START WITH v.CPF = :cpf
                        CONNECT BY v.CPF = PRIOR v.CPFLIDER
                        ORDER BY LEVEL DESC";
                $binds[':cpf'] = $rawCpf;
                $mode = 'ancestors';

            } elseif ($search !== '') {
                $sql = "SELECT v.CPF, v.CPFLIDER, v.NOMECOMPLETO, v.CARGO, v.EMPRESA, v.UNIDADE,
                               CASE WHEN EXISTS (
                                   SELECT 1 FROM {$view} c WHERE c.CPFLIDER = v.CPF AND c.STATUS = 'ATIVO'
                               ) THEN 1 ELSE 0 END AS HAS_CHILDREN,
                               CASE WHEN v.CPFLIDER IS NOT NULL AND NOT EXISTS (
                                   SELECT 1 FROM {$view} a WHERE a.CPF = v.CPFLIDER AND a.STATUS = 'ATIVO'
                               ) THEN 1 ELSE 0 END AS IS_ORPHAN
                        FROM {$view} v
                        WHERE v.STATUS = 'ATIVO'
                          AND (UPPER(v.NOMECOMPLETO) LIKE UPPER(:s1)
                            OR UPPER(v.CARGO)        LIKE UPPER(:s2))
                        ORDER BY v.NOMECOMPLETO
                        FETCH FIRST 50 ROWS ONLY";
                $binds[':s1'] = '%' . $search . '%';
                $binds[':s2'] = '%' . $search . '%';
                $mode = 'search';

            } elseif ($cpfHash !== '') {
                $rawCpf = $_SESSION['org_cpf_map'][$cpfHash] ?? null;
                if ($rawCpf === null) {
                    echo json_encode(['error' => 'Hash não encontrado. Recarregue a página.']);
                    return;
                }
                $sql = "SELECT v.CPF, v.NOMECOMPLETO, v.CARGO, v.EMPRESA, v.UNIDADE,
                               CASE WHEN EXISTS (
                                   SELECT 1 FROM {$view} c WHERE c.CPFLIDER = v.CPF AND c.STATUS = 'ATIVO'
                               ) THEN 1 ELSE 0 END AS HAS_CHILDREN
                        FROM {$view} v
                        WHERE v.CPFLIDER = :cpf
                          AND v.STATUS = 'ATIVO'
                        ORDER BY v.NOMECOMPLETO";
                $binds[':cpf'] = $rawCpf;
                $mode = 'children';

            } else {
                $sql = "SELECT v.CPF, v.NOMECOMPLETO, v.CARGO, v.EMPRESA, v.UNIDADE,
                               CASE WHEN EXISTS (
                                   SELECT 1 FROM {$view} c WHERE c.CPFLIDER = v.CPF AND c.STATUS = 'ATIVO'
                               ) THEN 1 ELSE 0 END AS HAS_CHILDREN
                        FROM {$view} v
                        WHERE v.CPFLIDER IS NULL
                          AND v.STATUS = 'ATIVO'
                        ORDER BY v.NOMECOMPLETO";
                $mode = 'roots';
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($binds);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $nodes = [];
            foreach ($rows as $row) {
                $row  = array_change_key_case($row, CASE_UPPER);
                $hash = md5((string)($row['CPF'] ?? ''));

                $_SESSION['org_cpf_map'][$hash] = (string)($row['CPF'] ?? '');

                $node = [
                    'id'          => $hash,
                    'pid'         => ($mode === 'children')
                                        ? $cpfHash
                                        : (in_array($mode, ['search', 'expand_all', 'ancestors'], true) && ($row['CPFLIDER'] ?? '') !== ''
                                            ? md5((string)$row['CPFLIDER'])
                                            : null),
                    'nome'        => (string)($row['NOMECOMPLETO'] ?? ''),
                    'cargo'       => (string)($row['CARGO']        ?? ''),
                    'empresa'     => (string)($row['EMPRESA']      ?? ''),
                    'unidade'     => (string)($row['UNIDADE']      ?? ''),
                    'hasChildren' => (bool)(int)($row['HAS_CHILDREN'] ?? 0),
                    'isOrphan'    => (bool)(int)($row['IS_ORPHAN']    ?? 0),
                ];

                if (in_array($mode, ['search', 'expand_all', 'ancestors'], true)) {
                    if (($row['CPFLIDER'] ?? '') !== '') {
                        $leaderHash = md5((string)$row['CPFLIDER']);
                        $_SESSION['org_cpf_map'][$leaderHash] = (string)$row['CPFLIDER'];
                    }
                }

                $nodes[] = $node;
            }

            echo json_encode($nodes);

        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function colaboradores(): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('hr.colaboradores', 1);

        if (($_GET['export'] ?? '') === '1') {
            $this->exportColaboradoresCsv();
            return;
        }

        // Atributos
        $cols = [
            'NOMECOMPLETO' => [
                'label' => 'Nome', 'sortable' => true, 'filter' => 'text', 'param' => 'f_nome',
                'th_class' => 'col-colab-nome',
            ],
            'usuario_email' => [
                'label'    => 'E-mail / UPN (Usuário)',
                'sortable' => true,
                'filter'   => 'text',
                'param'    => 'f_usuario_email',
                'render'   => fn($val) => ($val !== null && $val !== '') ? e((string)$val) : '—',
            ],
            'linha_corporativa' => [
                'label'    => 'Linha corporativa',
                'sortable' => true,
                'filter'   => null,
                'render'   => fn($val) => $this->renderLinhaCorporativaCell($val),
            ],
            'STATUS' => [
                'label' => 'Status', 'sortable' => true, 'filter' => 'select', 'param' => 'f_status',
                'options' => ['' => 'Todos', 'ATIVO' => 'Ativo', 'INATIVO' => 'Inativo'],
                'default' => 'ATIVO',
                'render' => fn($val) => '<span class="badge badge-' . ((string)$val === 'ATIVO' ? 'success' : 'secondary') . '">'
                                      . e((string)$val) . '</span>',
            ],
            'SITUACAOCONTRATO' => [
                'label' => 'Situação', 'sortable' => true, 'filter' => 'select', 'param' => 'f_situacao',
                'options' => [
                    '' => 'Todas',
                    'TRABALHANDO' => 'Trabalhando',
                    'FÉRIAS'      => 'Férias',
                    'LICENÇA'     => 'Licença',
                    'DESLIGADO'   => 'Desligado',
                ],
                'render' => function ($val) {
                    if ($val === '' || $val === null) return '';
                    $key = mb_strtoupper((string)$val, 'UTF-8');
                    $cls = [
                        'TRABALHANDO' => 'success',
                        'FÉRIAS'      => 'info',
                        'FERIAS'      => 'info',
                        'LICENÇA'     => 'warning',
                        'LICENCA'     => 'warning',
                        'DESLIGADO'   => 'secondary',
                    ][$key] ?? 'secondary';
                    return '<span class="badge badge-' . $cls . '">' . e((string)$val) . '</span>';
                },
            ],
            'CPF' => [
                'label' => 'CPF', 'sortable' => true, 'filter' => 'text', 'param' => 'f_cpf',
                'render' => function ($val) {
                    $v = (string)$val;
                    if (strlen($v) === 11) {
                        return e(substr($v, 0, 3) . '.' . substr($v, 3, 3) . '.' . substr($v, 6, 3) . '-' . substr($v, 9, 2));
                    }
                    return e($v);
                },
            ],
            'SEXO' => [
                'label' => 'Sexo', 'sortable' => true, 'filter' => 'select', 'param' => 'f_sexo',
                'options' => ['' => 'Todos', 'M' => 'M', 'F' => 'F'],
            ],
            'NASCIMENTO' => [
                'label' => 'Nascimento', 'sortable' => true, 'filter' => 'text', 'param' => 'f_nascimento',
                'render' => function ($val) {
                    if ($val === null || $val === '') return '';
                    if ($val instanceof \DateTime) return e($val->format('d/m/Y'));
                    $ts = strtotime((string)$val);
                    if ($ts === false) return e((string)$val);
                    // Oracle YY-format bug: anos de 2 digitos considerados 20xx em vez de 19xx
                    if ((int)date('Y', $ts) > (int)date('Y')) $ts = strtotime('-100 years', $ts);
                    return e(date('d/m/Y', $ts));
                },
            ],
            'LIDER' => [
                'label' => 'Líder', 'sortable' => true, 'filter' => 'text', 'param' => 'f_lider',
            ],
            'CARGO' => [
                'label' => 'Cargo', 'sortable' => true, 'filter' => 'text', 'param' => 'f_cargo',
            ],
            'EMPRESA' => [
                'label' => 'Empresa', 'sortable' => true, 'filter' => 'text', 'param' => 'f_empresa',
            ],
            'UNIDADE' => [
                'label' => 'Unidade', 'sortable' => true, 'filter' => 'text', 'param' => 'f_unidade',
            ],
            'SETOR' => [
                'label' => 'Setor', 'sortable' => true, 'filter' => 'text', 'param' => 'f_setor',
            ],
            'CENTROCUSTO' => [
                'label' => 'Centro de Custo', 'sortable' => true, 'filter' => 'text', 'param' => 'f_centrocusto',
            ],
            'DATAADMISSAO' => [
                'label' => 'Admissão', 'sortable' => true, 'filter' => 'text', 'param' => 'f_dataadmissao',
                'render' => function ($val) {
                    if ($val === null || $val === '') return '';
                    if ($val instanceof \DateTime) return e($val->format('d/m/Y'));
                    $ts = strtotime((string)$val);
                    return $ts !== false ? e(date('d/m/Y', $ts)) : e((string)$val);
                },
            ],
            'DATARESCISAO' => [
                'label' => 'Rescisão', 'sortable' => true, 'filter' => 'text', 'param' => 'f_datarescisao',
                'render' => function ($val) {
                    if ($val === null || $val === '') return '';
                    if ($val instanceof \DateTime) return e($val->format('d/m/Y'));
                    $ts = strtotime((string)$val);
                    return $ts !== false ? e(date('d/m/Y', $ts)) : e((string)$val);
                },
            ],
            'CODPESSOA' => [
                'label' => 'Cód. Pessoa', 'sortable' => true, 'filter' => 'text', 'param' => 'f_codpessoa',
            ],
            'CODCONTRATO' => [
                'label' => 'Cód. Contrato', 'sortable' => true, 'filter' => 'text', 'param' => 'f_codcontrato',
            ],
            'CLASSIFICACAOGERENCIAL' => [
                'label' => 'Classificação Gerencial', 'sortable' => true, 'filter' => 'text', 'param' => 'f_classificacao',
            ],
        ];

        $lt = new \Support\ListTable(base_url('colaboradores'), $cols, 'colab');
        $lt->setPerPageOptions([10, 25, 50, 100, 1000]);
        $lt->readRequest('NOMECOMPLETO');

        $fv      = $lt->getFilterValues();
        $sort    = $lt->getSort();
        $dir     = strtoupper($lt->getDir());
        $offset  = ($lt->getPage() - 1) * $lt->getPerPage();
        $perPage = $lt->getPerPage();

        // Sort guard: campos enriquecidos via SQL Server usam fallback Oracle + sort em memória
        $sortForOracle = in_array($sort, ['usuario_email', 'linha_corporativa'], true)
            ? 'NOMECOMPLETO'
            : $sort;

        $erro          = null;
        $colaboradores = [];
        $total         = 0;

        try {
            $pdo = OracleConnection::get();

            $conditions = [];
            $binds      = [];

            ['conditions' => $conditions, 'binds' => $binds] = $this->buildColabConditions($fv);
            $where = empty($conditions) ? '1=1' : implode(' AND ', $conditions);

            $stmtCount = $pdo->prepare(
                "SELECT COUNT(*) AS TOTAL FROM " . self::VIEW . " WHERE {$where}"
            );
            $stmtCount->execute($binds);
            $total = (int)($stmtCount->fetch()['TOTAL'] ?? 0);

            if ($sortForOracle === 'NASCIMENTO') {
                $orderExpr = "CASE WHEN NASCIMENTO > SYSDATE"
                           . " THEN ADD_MONTHS(NASCIMENTO, -1200)"
                           . " ELSE NASCIMENTO END";
            } else {
                $orderExpr = $sortForOracle;
            }
            $nullsClause = in_array($sortForOracle, ['NASCIMENTO', 'DATAADMISSAO', 'DATARESCISAO'], true)
                ? ' NULLS LAST'
                : '';

            $sql = "SELECT * FROM " . self::VIEW . "
                    WHERE {$where}
                    ORDER BY {$orderExpr} {$dir}{$nullsClause}
                    OFFSET :offset_rows ROWS FETCH NEXT :fetch_rows ROWS ONLY";

            $binds[':offset_rows'] = $offset;
            $binds[':fetch_rows']  = $perPage;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($binds);
            $rows = $stmt->fetchAll();

            $colaboradores = array_map(
                fn($row) => array_change_key_case($row, CASE_UPPER),
                $rows
            );

            // Enriquece com e-mail ou UPN do usuário via SQL Server (uma query para a página inteira)
            $cpfs = array_values(array_filter(array_column($colaboradores, 'CPF')));
            $usuarioEmailMap = $this->buildUsuarioContatoMap($cpfs);

            $codpessoas = array_values(array_filter(array_map(
                fn($row) => (string)($row['CODPESSOA'] ?? ''),
                $colaboradores
            )));
            $linhaCorporativaMap = $this->buildLinhaCorporativaMap($codpessoas);

            $colaboradores = array_map(function ($row) use ($usuarioEmailMap, $linhaCorporativaMap) {
                $row['usuario_email'] = $usuarioEmailMap[(string)($row['CPF'] ?? '')] ?? null;
                $row['linha_corporativa'] = $linhaCorporativaMap[(string)($row['CODPESSOA'] ?? '')] ?? null;
                return $row;
            }, $colaboradores);

            // Ordenação em memória quando o sort é por campo cross-DB
            if ($sort === 'usuario_email') {
                usort($colaboradores, function ($a, $b) use ($dir) {
                    $cmp = strcasecmp((string)($a['usuario_email'] ?? ''), (string)($b['usuario_email'] ?? ''));
                    return $dir === 'DESC' ? -$cmp : $cmp;
                });
            } elseif ($sort === 'linha_corporativa') {
                usort($colaboradores, function ($a, $b) use ($dir) {
                    $cmp = strcasecmp((string)($a['linha_corporativa'] ?? ''), (string)($b['linha_corporativa'] ?? ''));
                    return $dir === 'DESC' ? -$cmp : $cmp;
                });
            }

        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        }

        $from = $total > 0 ? ($offset + 1) : 0;
        $to   = min($total, $offset + $perPage);

        render_page('colaboradores/index.php', [
            'lt'            => $lt,
            'colaboradores' => $colaboradores,
            'total'         => $total,
            'from'          => $from,
            'to'            => $to,
            'erro'          => $erro,
        ]);
    }

    // Helpers

    /**
     * Mapa CPF (employeeNumber) => contato do usuário vinculado.
     * Usa e-mail quando disponível; caso contrário, UPN.
     *
     * @param  string[] $cpfs
     * @return array<string, string>
     */
    private function buildUsuarioContatoMap(array $cpfs): array
    {
        $map = [];
        if (empty($cpfs)) {
            return $map;
        }

        try {
            $sqlSrv       = \Database\Connection::get();
            $placeholders = implode(',', array_map(fn($i) => ":cpf{$i}", array_keys($cpfs)));
            $stSrv        = $sqlSrv->prepare(
                "SELECT employeeNumber, email, upn FROM users
                 WHERE employeeNumber IN ({$placeholders})
                   AND (
                       NULLIF(LTRIM(RTRIM(email)), '') IS NOT NULL
                       OR NULLIF(LTRIM(RTRIM(upn)), '') IS NOT NULL
                   )
                 ORDER BY is_active DESC"
            );
            foreach ($cpfs as $i => $cpf) {
                $stSrv->bindValue(":cpf{$i}", $cpf);
            }
            $stSrv->execute();
            foreach ($stSrv->fetchAll() as $row) {
                $cpfKey = (string)$row['employeeNumber'];
                if (isset($map[$cpfKey])) {
                    continue;
                }
                $email = trim((string)($row['email'] ?? ''));
                $upn   = trim((string)($row['upn'] ?? ''));
                $map[$cpfKey] = $email !== '' ? $email : $upn;
            }
        } catch (\Throwable $e) {
            // SQL Server indisponível: coluna exibirá "—" sem interromper a tela
        }

        return $map;
    }

    /**
     * Mapa CODPESSOA => texto da linha corporativa (primeira linha + indicador +N).
     *
     * @param  string[] $codpessoas
     * @return array<string, string>
     */
    private function buildLinhaCorporativaMap(array $codpessoas): array
    {
        $map = [];
        $codpessoas = array_values(array_unique(array_filter(array_map('strval', $codpessoas))));
        if (empty($codpessoas)) {
            return $map;
        }

        try {
            $sqlSrv = \Database\Connection::get();
            $placeholders = implode(',', array_map(fn($i) => ":cp{$i}", array_keys($codpessoas)));
            $st = $sqlSrv->prepare("
                SELECT e.colaborador_codpessoa,
                       COALESCE(l_direct.numero_linha, l_linked.numero_linha) AS numero_linha
                FROM   dbo.emprestimos e
                INNER JOIN dbo.itens i ON i.id = e.item_id
                INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
                LEFT JOIN dbo.item_linha_telefonica l_direct
                       ON l_direct.item_id = i.id AND t.tabela_detalhe = 'item_linha_telefonica'
                LEFT JOIN dbo.item_equipamento_ti eq
                       ON eq.item_id = i.id AND t.tabela_detalhe = 'item_equipamento_ti'
                LEFT JOIN dbo.item_linha_telefonica l_linked ON l_linked.item_id = eq.linha_item_id
                WHERE  e.colaborador_codpessoa IN ({$placeholders})
                  AND  e.status IN ('ativo', 'reservado')
                  AND  COALESCE(l_direct.numero_linha, l_linked.numero_linha) IS NOT NULL
                ORDER BY e.colaborador_codpessoa, e.data_entrega DESC
            ");
            foreach ($codpessoas as $i => $cp) {
                $st->bindValue(":cp{$i}", $cp);
            }
            $st->execute();

            $grouped = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $cod = (string)$row['colaborador_codpessoa'];
                $num = trim((string)($row['numero_linha'] ?? ''));
                if ($num === '') {
                    continue;
                }
                $grouped[$cod][] = $num;
            }

            foreach ($grouped as $cod => $numeros) {
                $map[$cod] = $this->formatLinhaCorporativaPlain($numeros);
            }
        } catch (\Throwable $e) {
            // SQL Server indisponível: coluna exibirá "—" sem interromper a tela
        }

        return $map;
    }

    /** @param string[] $numeros */
    private function formatLinhaCorporativaPlain(array $numeros): string
    {
        $numeros = array_values(array_unique(array_filter(array_map('trim', $numeros))));
        if (empty($numeros)) {
            return '';
        }

        $first = $numeros[0];
        $extra = count($numeros) - 1;

        return $extra > 0 ? "{$first} (+{$extra})" : $first;
    }

    private function renderLinhaCorporativaCell(mixed $val): string
    {
        if ($val === null || $val === '') {
            return '—';
        }

        $s = (string)$val;
        if (preg_match('/^(.+?) \(\+(\d+)\)$/', $s, $m)) {
            return e($m[1]) . ' <span class="text-muted">(+' . e($m[2]) . ')</span>';
        }

        return e($s);
    }

    /** Constrói as condições WHERE + binds para a view de colaboradores. */
    private function buildColabConditions(array $fv): array
    {
        $conditions = [];
        $binds      = [];

        if ($fv['f_status'] ?? '' !== '') {
            $conditions[] = "STATUS = :f_status";
            $binds[':f_status'] = $fv['f_status'];
        }
        if ($fv['f_nome'] ?? '' !== '') {
            $conditions[] = "UPPER(NOMECOMPLETO) LIKE UPPER(:f_nome)";
            $binds[':f_nome'] = '%' . $fv['f_nome'] . '%';
        }
        if ($fv['f_codpessoa'] ?? '' !== '') {
            $conditions[] = "UPPER(TO_CHAR(CODPESSOA)) LIKE UPPER(:f_codpessoa)";
            $binds[':f_codpessoa'] = '%' . $fv['f_codpessoa'] . '%';
        }
        if ($fv['f_codcontrato'] ?? '' !== '') {
            $conditions[] = "UPPER(TO_CHAR(CODCONTRATO)) LIKE UPPER(:f_codcontrato)";
            $binds[':f_codcontrato'] = '%' . $fv['f_codcontrato'] . '%';
        }
        if ($fv['f_cpf'] ?? '' !== '') {
            $conditions[] = "CPF LIKE :f_cpf";
            $binds[':f_cpf'] = '%' . $fv['f_cpf'] . '%';
        }
        if ($fv['f_sexo'] ?? '' !== '') {
            $conditions[] = "SEXO = :f_sexo";
            $binds[':f_sexo'] = $fv['f_sexo'];
        }
        if ($fv['f_nascimento'] ?? '' !== '') {
            if (preg_match('/^\d{4}$/', $fv['f_nascimento'])) {
                $conditions[] = "EXTRACT(YEAR FROM NASCIMENTO) = :f_nascimento";
                $binds[':f_nascimento'] = (int)$fv['f_nascimento'];
            } else {
                $conditions[] = "TO_CHAR(NASCIMENTO, 'DD/MM/YYYY') LIKE :f_nascimento";
                $binds[':f_nascimento'] = '%' . $fv['f_nascimento'] . '%';
            }
        }
        if ($fv['f_cargo'] ?? '' !== '') {
            $conditions[] = "UPPER(CARGO) LIKE UPPER(:f_cargo)";
            $binds[':f_cargo'] = '%' . $fv['f_cargo'] . '%';
        }
        if ($fv['f_empresa'] ?? '' !== '') {
            $conditions[] = "UPPER(EMPRESA) LIKE UPPER(:f_empresa)";
            $binds[':f_empresa'] = '%' . $fv['f_empresa'] . '%';
        }
        if ($fv['f_unidade'] ?? '' !== '') {
            $conditions[] = "UPPER(UNIDADE) LIKE UPPER(:f_unidade)";
            $binds[':f_unidade'] = '%' . $fv['f_unidade'] . '%';
        }
        if ($fv['f_classificacao'] ?? '' !== '') {
            $conditions[] = "UPPER(CLASSIFICACAOGERENCIAL) LIKE UPPER(:f_classificacao)";
            $binds[':f_classificacao'] = '%' . $fv['f_classificacao'] . '%';
        }
        if ($fv['f_centrocusto'] ?? '' !== '') {
            $conditions[] = "UPPER(CENTROCUSTO) LIKE UPPER(:f_centrocusto)";
            $binds[':f_centrocusto'] = '%' . $fv['f_centrocusto'] . '%';
        }
        if ($fv['f_setor'] ?? '' !== '') {
            $conditions[] = "UPPER(SETOR) LIKE UPPER(:f_setor)";
            $binds[':f_setor'] = '%' . $fv['f_setor'] . '%';
        }
        if ($fv['f_lider'] ?? '' !== '') {
            $conditions[] = "UPPER(LIDER) LIKE UPPER(:f_lider)";
            $binds[':f_lider'] = '%' . $fv['f_lider'] . '%';
        }
        if ($fv['f_situacao'] ?? '' !== '') {
            $conditions[] = "SITUACAOCONTRATO = :f_situacao";
            $binds[':f_situacao'] = $fv['f_situacao'];
        }
        if ($fv['f_dataadmissao'] ?? '' !== '') {
            if (preg_match('/^\d{4}$/', $fv['f_dataadmissao'])) {
                $conditions[] = "EXTRACT(YEAR FROM DATAADMISSAO) = :f_dataadmissao";
                $binds[':f_dataadmissao'] = (int)$fv['f_dataadmissao'];
            } else {
                $conditions[] = "TO_CHAR(DATAADMISSAO, 'DD/MM/YYYY') LIKE :f_dataadmissao";
                $binds[':f_dataadmissao'] = '%' . $fv['f_dataadmissao'] . '%';
            }
        }
        if ($fv['f_datarescisao'] ?? '' !== '') {
            if (preg_match('/^\d{4}$/', $fv['f_datarescisao'])) {
                $conditions[] = "EXTRACT(YEAR FROM DATARESCISAO) = :f_datarescisao";
                $binds[':f_datarescisao'] = (int)$fv['f_datarescisao'];
            } else {
                $conditions[] = "TO_CHAR(DATARESCISAO, 'DD/MM/YYYY') LIKE :f_datarescisao";
                $binds[':f_datarescisao'] = '%' . $fv['f_datarescisao'] . '%';
            }
        }

        if ($fv['f_usuario_email'] ?? '' !== '') {
            $matchedCpfs = [];
            try {
                $sqlSrv  = \Database\Connection::get();
                $stEmail = $sqlSrv->prepare(
                    "SELECT employeeNumber FROM users
                     WHERE (email LIKE :q OR upn LIKE :q)
                       AND employeeNumber IS NOT NULL"
                );
                $stEmail->execute([':q' => '%' . $fv['f_usuario_email'] . '%']);
                $matchedCpfs = array_column($stEmail->fetchAll(), 'employeeNumber');
            } catch (\Throwable $e) { /* SQL Server indisponível → sem resultados */ }

            if (!empty($matchedCpfs)) {
                $cpfPh = implode(',', array_map(fn($i) => ":email_cpf{$i}", array_keys($matchedCpfs)));
                $conditions[] = "CPF IN ({$cpfPh})";
                foreach ($matchedCpfs as $i => $cpf) { $binds[":email_cpf{$i}"] = $cpf; }
            } else {
                $conditions[] = "1=0";
            }
        }

        return ['conditions' => $conditions, 'binds' => $binds];
    }

    /** GET ?export=1 — exporta os colaboradores filtrados como CSV. */
    private function exportColaboradoresCsv(): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('hr.colaboradores', 1);

        $cols = [
            'NOMECOMPLETO'           => ['label' => 'Nome',                    'sortable' => true,  'filter' => 'text',   'param' => 'f_nome'],
            'usuario_email'          => ['label' => 'E-mail / UPN (Usuário)',  'sortable' => true,  'filter' => 'text',   'param' => 'f_usuario_email', 'render' => fn($v) => $v],
            'linha_corporativa'      => ['label' => 'Linha corporativa',       'sortable' => true,  'filter' => null,     'render' => fn($v) => $v],
            'STATUS'                 => ['label' => 'Status',                  'sortable' => true,  'filter' => 'select', 'param' => 'f_status',
                                         'options' => ['' => 'Todos', 'ATIVO' => 'Ativo', 'INATIVO' => 'Inativo'], 'default' => 'ATIVO'],
            'SITUACAOCONTRATO'       => ['label' => 'Situação',                'sortable' => true,  'filter' => 'select', 'param' => 'f_situacao',
                                         'options' => ['' => 'Todas', 'Trabalhando' => 'Trabalhando', 'Férias' => 'Férias', 'Licença' => 'Licença', 'Desligado' => 'Desligado']],
            'CPF'                    => ['label' => 'CPF',                     'sortable' => true,  'filter' => 'text',   'param' => 'f_cpf'],
            'SEXO'                   => ['label' => 'Sexo',                    'sortable' => true,  'filter' => 'select', 'param' => 'f_sexo', 'options' => ['' => 'Todos', 'M' => 'M', 'F' => 'F']],
            'NASCIMENTO'             => ['label' => 'Nascimento',              'sortable' => true,  'filter' => 'text',   'param' => 'f_nascimento'],
            'LIDER'                  => ['label' => 'Líder',                   'sortable' => true,  'filter' => 'text',   'param' => 'f_lider'],
            'CARGO'                  => ['label' => 'Cargo',                   'sortable' => true,  'filter' => 'text',   'param' => 'f_cargo'],
            'EMPRESA'                => ['label' => 'Empresa',                 'sortable' => true,  'filter' => 'text',   'param' => 'f_empresa'],
            'UNIDADE'                => ['label' => 'Unidade',                 'sortable' => true,  'filter' => 'text',   'param' => 'f_unidade'],
            'SETOR'                  => ['label' => 'Setor',                   'sortable' => true,  'filter' => 'text',   'param' => 'f_setor'],
            'CENTROCUSTO'            => ['label' => 'Centro de Custo',         'sortable' => true,  'filter' => 'text',   'param' => 'f_centrocusto'],
            'DATAADMISSAO'           => ['label' => 'Admissão',                'sortable' => true,  'filter' => 'text',   'param' => 'f_dataadmissao'],
            'DATARESCISAO'           => ['label' => 'Rescisão',                'sortable' => true,  'filter' => 'text',   'param' => 'f_datarescisao'],
            'CODPESSOA'              => ['label' => 'Cód. Pessoa',             'sortable' => true,  'filter' => 'text',   'param' => 'f_codpessoa'],
            'CODCONTRATO'            => ['label' => 'Cód. Contrato',           'sortable' => true,  'filter' => 'text',   'param' => 'f_codcontrato'],
            'CLASSIFICACAOGERENCIAL' => ['label' => 'Classificação Gerencial', 'sortable' => true,  'filter' => 'text',   'param' => 'f_classificacao'],
        ];

        $lt = new \Support\ListTable(base_url('colaboradores'), $cols, 'colab');
        $lt->readRequest('NOMECOMPLETO');

        $fv            = $lt->getFilterValues();
        $sort          = $lt->getSort();
        $dir           = strtoupper($lt->getDir());
        $sortForOracle = in_array($sort, ['usuario_email', 'linha_corporativa'], true)
            ? 'NOMECOMPLETO'
            : $sort;

        ['conditions' => $conditions, 'binds' => $binds] = $this->buildColabConditions($fv);
        $where = empty($conditions) ? '1=1' : implode(' AND ', $conditions);

        if ($sortForOracle === 'NASCIMENTO') {
            $orderExpr = "CASE WHEN NASCIMENTO > SYSDATE"
                       . " THEN ADD_MONTHS(NASCIMENTO, -1200)"
                       . " ELSE NASCIMENTO END";
        } else {
            $orderExpr = $sortForOracle;
        }
        $nullsClause = in_array($sortForOracle, ['NASCIMENTO', 'DATAADMISSAO', 'DATARESCISAO'], true)
            ? ' NULLS LAST' : '';

        try {
            $pdo  = OracleConnection::get();
            $stmt = $pdo->prepare(
                "SELECT * FROM " . self::VIEW . "
                 WHERE {$where}
                 ORDER BY {$orderExpr} {$dir}{$nullsClause}"
            );
            $stmt->execute($binds);
            $rows = $stmt->fetchAll();

            $colaboradores = array_map(
                fn($row) => array_change_key_case($row, CASE_UPPER),
                $rows
            );

            // Enriquece com e-mail ou UPN do usuário (SQL Server)
            $cpfs = array_values(array_filter(array_column($colaboradores, 'CPF')));
            $usuarioEmailMap = $this->buildUsuarioContatoMap($cpfs);

            $codpessoas = array_values(array_filter(array_map(
                fn($row) => (string)($row['CODPESSOA'] ?? ''),
                $colaboradores
            )));
            $linhaCorporativaMap = $this->buildLinhaCorporativaMap($codpessoas);

            $colaboradores = array_map(function ($row) use ($usuarioEmailMap, $linhaCorporativaMap) {
                $row['usuario_email'] = $usuarioEmailMap[(string)($row['CPF'] ?? '')] ?? '';
                $row['linha_corporativa'] = $linhaCorporativaMap[(string)($row['CODPESSOA'] ?? '')] ?? '';
                return $row;
            }, $colaboradores);

            if ($sort === 'usuario_email') {
                usort($colaboradores, function ($a, $b) use ($dir) {
                    $cmp = strcasecmp((string)($a['usuario_email'] ?? ''), (string)($b['usuario_email'] ?? ''));
                    return $dir === 'DESC' ? -$cmp : $cmp;
                });
            } elseif ($sort === 'linha_corporativa') {
                usort($colaboradores, function ($a, $b) use ($dir) {
                    $cmp = strcasecmp((string)($a['linha_corporativa'] ?? ''), (string)($b['linha_corporativa'] ?? ''));
                    return $dir === 'DESC' ? -$cmp : $cmp;
                });
            }

        } catch (\Throwable $e) {
            header('Content-Type: text/plain; charset=UTF-8');
            http_response_code(500);
            echo 'Erro ao exportar: ' . $e->getMessage();
            return;
        }

        $csvHeaders = [
            'NOMECOMPLETO'           => 'Nome',
            'usuario_email'          => 'E-mail / UPN (Usuário)',
            'linha_corporativa'      => 'Linha corporativa',
            'STATUS'                 => 'Status',
            'SITUACAOCONTRATO'       => 'Situação',
            'CPF'                    => 'CPF',
            'SEXO'                   => 'Sexo',
            'NASCIMENTO'             => 'Nascimento',
            'LIDER'                  => 'Líder',
            'CARGO'                  => 'Cargo',
            'EMPRESA'                => 'Empresa',
            'UNIDADE'                => 'Unidade',
            'SETOR'                  => 'Setor',
            'CENTROCUSTO'            => 'Centro de Custo',
            'DATAADMISSAO'           => 'Admissão',
            'DATARESCISAO'           => 'Rescisão',
            'CODPESSOA'              => 'Cód. Pessoa',
            'CODCONTRATO'            => 'Cód. Contrato',
            'CLASSIFICACAOGERENCIAL' => 'Classificação Gerencial',
        ];
        $dateCols = ['NASCIMENTO', 'DATAADMISSAO', 'DATARESCISAO'];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="colaboradores.csv"');
        echo "\xEF\xBB\xBF";

        $f = fopen('php://output', 'w');
        fputcsv($f, array_values($csvHeaders), ';');

        foreach ($colaboradores as $row) {
            $line = [];
            foreach (array_keys($csvHeaders) as $col) {
                $val = $row[$col] ?? '';
                if (in_array($col, $dateCols, true)) {
                    if ($val === null || $val === '') {
                        $val = '';
                    } elseif ($val instanceof \DateTime) {
                        $val = $val->format('d/m/Y');
                    } else {
                        $ts = strtotime((string)$val);
                        if ($ts !== false) {
                            if ($col === 'NASCIMENTO' && (int)date('Y', $ts) > (int)date('Y')) {
                                $ts = strtotime('-100 years', $ts);
                            }
                            $val = date('d/m/Y', $ts);
                        }
                    }
                } elseif ($col === 'CPF') {
                    $v = (string)$val;
                    if (strlen($v) === 11) {
                        $val = substr($v, 0, 3) . '.' . substr($v, 3, 3) . '.'
                             . substr($v, 6, 3) . '-' . substr($v, 9, 2);
                    }
                }
                $line[] = (string)($val ?? '');
            }
            fputcsv($f, $line, ';');
        }
        fclose($f);
    }

    public function showColaborador(string $codpessoa): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('hr.colaboradores', 1);

        $data = $this->loadColaboradorShowData($codpessoa);
        if (!$data['colaborador'] && !$data['erro']) {
            http_response_code(404);
            exit('Colaborador não encontrado.');
        }

        if (($_GET['export'] ?? '') === 'pdf') {
            $this->exportColaboradorPdf($codpessoa, $data);
            return;
        }

        render_page('colaboradores/show.php', $data);
    }

    /** @return array{colaborador: ?array, itens: array, erro: ?string} */
    private function loadColaboradorShowData(string $codpessoa): array
    {
        $colaborador = null;
        $erro        = null;

        try {
            $pdo  = OracleConnection::get();
            $stmt = $pdo->prepare(
                "SELECT * FROM " . self::VIEW . "
                 WHERE CODPESSOA = :codpessoa
                 ORDER BY CASE WHEN STATUS = 'ATIVO' THEN 0 ELSE 1 END,
                          DATAADMISSAO DESC NULLS LAST,
                          CODCONTRATO DESC
                 FETCH FIRST 1 ROW ONLY"
            );
            $stmt->execute([':codpessoa' => $codpessoa]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $colaborador = array_change_key_case($row, CASE_UPPER);
            }
        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        }

        $itens = [];
        try {
            $pdo2  = \Database\Connection::get();
            $stmt2 = $pdo2->prepare("
                SELECT e.id AS emprestimo_id,
                       e.status,
                       e.data_entrega,
                       e.data_prevista_devolucao,
                       e.quantidade,
                       i.id AS item_id,
                       i.descricao AS item_descricao,
                       t.nome AS tipo_nome,
                       c.nome AS categoria_nome
                FROM   dbo.emprestimos e
                INNER JOIN dbo.itens i       ON i.id = e.item_id
                INNER JOIN dbo.tipos_item t  ON t.id = i.tipo_item_id
                INNER JOIN dbo.categorias c  ON c.id = t.categoria_id
                WHERE  e.colaborador_codpessoa = :codpessoa
                  AND  e.status IN ('ativo', 'reservado')
                ORDER BY e.data_entrega DESC
            ");
            $stmt2->execute([':codpessoa' => $codpessoa]);
            $itens = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // não bloqueia a exibição do colaborador
        }

        return [
            'colaborador' => $colaborador,
            'itens'       => $itens,
            'erro'        => $erro,
        ];
    }

    /** @param array{colaborador: ?array, itens: array, erro: ?string} $data */
    private function exportColaboradorPdf(string $codpessoa, array $data): void
    {
        if ($data['erro']) {
            http_response_code(500);
            exit('Erro ao carregar colaborador: ' . $data['erro']);
        }

        $c = $data['colaborador'];
        if (!$c) {
            http_response_code(404);
            exit('Colaborador não encontrado.');
        }

        $statusLabels = ['ativo' => 'Em uso', 'reservado' => 'Reservado'];
        $pdf          = new \Support\SimplePdf();
        $pdf->title('Colaborador');
        $pdf->line('Nome: ' . (string)($c['NOMECOMPLETO'] ?? ''));
        $pdf->line('CPF: ' . $this->formatCpfForPdf($c['CPF'] ?? ''));
        $pdf->line('Cargo: ' . (string)($c['CARGO'] ?? ''));
        $pdf->line('Empresa: ' . (string)($c['EMPRESA'] ?? ''));
        $pdf->line('Unidade: ' . (string)($c['UNIDADE'] ?? ''));
        $pdf->line('Líder: ' . (string)($c['LIDER'] ?? ''));
        $pdf->line('Setor: ' . (string)($c['SETOR'] ?? ''));

        $pdf->section('Itens atribuídos');
        if (empty($data['itens'])) {
            $pdf->line('Nenhum item atribuído no momento.');
        } else {
            foreach ($data['itens'] as $item) {
                $status = $statusLabels[$item['status'] ?? ''] ?? (string)($item['status'] ?? '');
                $pdf->line('- ' . (string)($item['item_descricao'] ?? ''));
                $pdf->line('  Tipo: ' . (string)($item['tipo_nome'] ?? '')
                    . ' | Categoria: ' . (string)($item['categoria_nome'] ?? '')
                    . ' | Situação: ' . $status);
                $entrega = $this->formatDateForPdf($item['data_entrega'] ?? null);
                $prev    = $this->formatDateForPdf($item['data_prevista_devolucao'] ?? null);
                if ($entrega !== '' || $prev !== '') {
                    $pdf->line('  Desde: ' . ($entrega !== '' ? $entrega : '-')
                        . ' | Prev. devolução: ' . ($prev !== '' ? $prev : '-'));
                }
                $pdf->blank();
            }
        }

        $nomeArquivo = 'colaborador_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$codpessoa) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        header('Content-Length: ' . strlen($bytes = $pdf->render()));
        echo $bytes;
        exit;
    }

    private function formatCpfForPdf(mixed $cpf): string
    {
        $v = preg_replace('/\D/', '', (string)$cpf);
        if (strlen($v) === 11) {
            return substr($v, 0, 3) . '.' . substr($v, 3, 3) . '.' . substr($v, 6, 3) . '-' . substr($v, 9, 2);
        }
        return (string)$cpf;
    }

    private function formatDateForPdf(mixed $val): string
    {
        if ($val === null || $val === '') {
            return '';
        }
        if ($val instanceof \DateTimeInterface) {
            return $val->format('d/m/Y');
        }
        $ts = strtotime((string)$val);
        return $ts === false ? (string)$val : date('d/m/Y', $ts);
    }

    public function autocompleteColaboradores(): void
    {
        \Security\Auth::requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        $q    = trim($_GET['q'] ?? '');
        $view = self::VIEW;

        try {
            $pdo        = OracleConnection::get();
            $conditions = ["STATUS = 'ATIVO'"];
            $binds      = [];

            if ($q !== '') {
                $parts = [
                    'UPPER(NOMECOMPLETO) LIKE UPPER(:q1)',
                    'UPPER(TO_CHAR(CODPESSOA)) LIKE UPPER(:q2)',
                    'CPF LIKE :q3',
                ];
                $binds[':q1'] = '%' . $q . '%';
                $binds[':q2'] = '%' . $q . '%';
                $binds[':q3'] = '%' . $q . '%';

                $digits = preg_replace('/\D/', '', $q);
                if ($digits !== '' && $digits !== $q) {
                    $parts[] = "REPLACE(REPLACE(CPF, '.', ''), '-', '') LIKE :q_digits";
                    $binds[':q_digits'] = '%' . $digits . '%';
                }

                $conditions[] = '(' . implode(' OR ', $parts) . ')';
            }

            $where = implode(' AND ', $conditions);
            $sql   = "SELECT NOMECOMPLETO, CPF, CODPESSOA
                      FROM {$view}
                      WHERE {$where}
                      ORDER BY NOMECOMPLETO
                      FETCH FIRST 20 ROWS ONLY";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($binds);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $row = array_change_key_case($row, CASE_UPPER);
                $cpf = (string)($row['CPF'] ?? '');
                if (strlen($cpf) === 11) {
                    $cpfFormatado = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.'
                                  . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
                } else {
                    $cpfFormatado = $cpf;
                }
                $nome      = (string)($row['NOMECOMPLETO'] ?? '');
                $codpessoa = (string)($row['CODPESSOA']    ?? '');
                $result[]  = [
                    'codpessoa' => $codpessoa,
                    'nome'      => $nome,
                    'cpf'       => $cpfFormatado,
                    'label'     => $nome . ' - ' . $cpfFormatado,
                ];
            }

            echo json_encode($result);

        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

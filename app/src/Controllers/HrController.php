<?php
namespace Controllers;

use Database\OracleConnection;

class HrController
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

    public function organograma(): void
    {
        \Security\Auth::requireAuth();
        \Security\Permission::require('hr.colaboradores', 1);

        if (($_GET['format'] ?? '') !== 'json') {
            render_page('hr/organograma.php', []);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');

        // Session map
        if (!isset($_SESSION['org_cpf_map']) || !is_array($_SESSION['org_cpf_map'])) {
            $_SESSION['org_cpf_map'] = [];
        }

        $cpfHash      = trim($_GET['cpf']       ?? '');
        $search       = trim($_GET['search']    ?? '');
        $expandAll    = isset($_GET['expand_all']);
        $ancestorHash = trim($_GET['ancestors'] ?? '');
        $orphans      = isset($_GET['orphans']);
        $view         = self::VIEW;

        try {
            $pdo   = OracleConnection::get();
            $binds = [];

            // colaboradores ativos sem líder
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

            // encontra o root de um usuário
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

            // filhos de um nódulo
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

            // roots
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

                // Persist hash
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
                'label'    => 'E-mail (Usuário)',
                'sortable' => true,
                'filter'   => 'text',
                'param'    => 'f_usuario_email',
                'render'   => fn($val) => ($val !== null && $val !== '') ? e((string)$val) : '—',
            ],
            'STATUS' => [
                'label' => 'Status', 'sortable' => true, 'filter' => 'select', 'param' => 'f_status',
                'options' => ['' => 'Todos', 'ATIVO' => 'Ativo', 'INATIVO' => 'Inativo'],
                'default' => 'ATIVO',
                'render' => fn($val) => '<span class="badge badge-' . ((string)$val === 'ATIVO' ? 'success' : 'secondary') . '">'
                                      . e((string)$val) . '</span>',
            ],
            'SITUACAOCONTRATO' => [
                'label' => 'Situação', 'sortable' => true, 'filter' => 'text', 'param' => 'f_situacao',
                'render' => fn($val) => ($val !== '' && $val !== null)
                    ? '<span class="badge badge-info">' . e((string)$val) . '</span>'
                    : '',
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

        $lt = new \Support\ListTable(base_url('hr/colaboradores'), $cols, 'hr');
        $lt->readRequest('NOMECOMPLETO');

        $fv      = $lt->getFilterValues();
        $sort    = $lt->getSort();
        $dir     = strtoupper($lt->getDir());
        $offset  = ($lt->getPage() - 1) * $lt->getPerPage();
        $perPage = $lt->getPerPage();

        // Sort guard: usuario_email vem do SQL Server; usa fallback para a query Oracle
        $sortForOracle = ($sort === 'usuario_email') ? 'NOMECOMPLETO' : $sort;

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

            // Enriquece com e-mail do usuário via SQL Server (uma query para a página inteira)
            $cpfs = array_values(array_filter(array_column($colaboradores, 'CPF')));
            $usuarioEmailMap = [];
            if (!empty($cpfs)) {
                try {
                    $sqlSrv = \Database\Connection::get();
                    $placeholders = implode(',', array_map(fn($i) => ":cpf{$i}", array_keys($cpfs)));
                    $stSrv = $sqlSrv->prepare(
                        "SELECT employeeNumber, email FROM users WHERE employeeNumber IN ({$placeholders}) AND email IS NOT NULL"
                    );
                    foreach ($cpfs as $i => $cpf) {
                        $stSrv->bindValue(":cpf{$i}", $cpf);
                    }
                    $stSrv->execute();
                    foreach ($stSrv->fetchAll() as $row) {
                        $usuarioEmailMap[(string)$row['employeeNumber']] = (string)$row['email'];
                    }
                } catch (\Throwable $e) {
                    // SQL Server indisponível: coluna exibirá "—" sem interromper a tela
                }
            }

            $colaboradores = array_map(function ($row) use ($usuarioEmailMap) {
                $row['usuario_email'] = $usuarioEmailMap[(string)($row['CPF'] ?? '')] ?? null;
                return $row;
            }, $colaboradores);

            // Ordenação em memória quando o sort é pelo campo cross-DB
            if ($sort === 'usuario_email') {
                usort($colaboradores, function ($a, $b) use ($dir) {
                    $cmp = strcasecmp((string)($a['usuario_email'] ?? ''), (string)($b['usuario_email'] ?? ''));
                    return $dir === 'DESC' ? -$cmp : $cmp;
                });
            }

        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        }

        $from = $total > 0 ? ($offset + 1) : 0;
        $to   = min($total, $offset + $perPage);

        render_page('hr/colaboradores.php', [
            'lt'            => $lt,
            'colaboradores' => $colaboradores,
            'total'         => $total,
            'from'          => $from,
            'to'            => $to,
            'erro'          => $erro,
        ]);
    }

    // Helpers

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
            $conditions[] = "UPPER(SITUACAOCONTRATO) LIKE UPPER(:f_situacao)";
            $binds[':f_situacao'] = '%' . $fv['f_situacao'] . '%';
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
                     WHERE email LIKE :q AND employeeNumber IS NOT NULL"
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
            'usuario_email'          => ['label' => 'E-mail (Usuário)',        'sortable' => true,  'filter' => 'text',   'param' => 'f_usuario_email', 'render' => fn($v) => $v],
            'STATUS'                 => ['label' => 'Status',                  'sortable' => true,  'filter' => 'select', 'param' => 'f_status',
                                         'options' => ['' => 'Todos', 'ATIVO' => 'Ativo', 'INATIVO' => 'Inativo'], 'default' => 'ATIVO'],
            'SITUACAOCONTRATO'       => ['label' => 'Situação',                'sortable' => true,  'filter' => 'text',   'param' => 'f_situacao'],
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

        $lt = new \Support\ListTable(base_url('hr/colaboradores'), $cols, 'hr');
        $lt->readRequest('NOMECOMPLETO');

        $fv            = $lt->getFilterValues();
        $sort          = $lt->getSort();
        $dir           = strtoupper($lt->getDir());
        $sortForOracle = ($sort === 'usuario_email') ? 'NOMECOMPLETO' : $sort;

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

            // Enriquece com e-mail do usuário (SQL Server)
            $cpfs = array_values(array_filter(array_column($colaboradores, 'CPF')));
            $usuarioEmailMap = [];
            if (!empty($cpfs)) {
                try {
                    $sqlSrv       = \Database\Connection::get();
                    $placeholders = implode(',', array_map(fn($i) => ":cpf{$i}", array_keys($cpfs)));
                    $stSrv        = $sqlSrv->prepare(
                        "SELECT employeeNumber, email FROM users
                         WHERE employeeNumber IN ({$placeholders}) AND email IS NOT NULL"
                    );
                    foreach ($cpfs as $i => $cpf) { $stSrv->bindValue(":cpf{$i}", $cpf); }
                    $stSrv->execute();
                    foreach ($stSrv->fetchAll() as $row) {
                        $usuarioEmailMap[(string)$row['employeeNumber']] = (string)$row['email'];
                    }
                } catch (\Throwable $e) { /* SQL Server indisponível */ }
            }

            $colaboradores = array_map(function ($row) use ($usuarioEmailMap) {
                $row['usuario_email'] = $usuarioEmailMap[(string)($row['CPF'] ?? '')] ?? '';
                return $row;
            }, $colaboradores);

        } catch (\Throwable $e) {
            header('Content-Type: text/plain; charset=UTF-8');
            http_response_code(500);
            echo 'Erro ao exportar: ' . $e->getMessage();
            return;
        }

        $csvHeaders = [
            'NOMECOMPLETO'           => 'Nome',
            'usuario_email'          => 'E-mail (Usuário)',
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

        if (!$colaborador && !$erro) {
            http_response_code(404);
            exit('Colaborador não encontrado.');
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

        render_page('hr/colaborador_show.php', [
            'colaborador' => $colaborador,
            'itens'       => $itens,
            'erro'        => $erro,
        ]);
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

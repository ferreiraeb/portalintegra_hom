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

        // Atributos
        $cols = [
            'NOMECOMPLETO' => [
                'label' => 'Nome', 'sortable' => true, 'filter' => 'text', 'param' => 'f_nome',
            ],
            'CODPESSOA' => [
                'label' => 'Cód. Pessoa', 'sortable' => true, 'filter' => 'text', 'param' => 'f_codpessoa',
            ],
            'CODCONTRATO' => [
                'label' => 'Cód. Contrato', 'sortable' => true, 'filter' => 'text', 'param' => 'f_codcontrato',
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
            'CARGO' => [
                'label' => 'Cargo', 'sortable' => true, 'filter' => 'text', 'param' => 'f_cargo',
            ],
            'EMPRESA' => [
                'label' => 'Empresa', 'sortable' => true, 'filter' => 'text', 'param' => 'f_empresa',
            ],
            'UNIDADE' => [
                'label' => 'Unidade', 'sortable' => true, 'filter' => 'text', 'param' => 'f_unidade',
            ],
            'CLASSIFICACAOGERENCIAL' => [
                'label' => 'Classificação Gerencial', 'sortable' => true, 'filter' => 'text', 'param' => 'f_classificacao',
            ],
            'CENTROCUSTO' => [
                'label' => 'Centro de Custo', 'sortable' => true, 'filter' => 'text', 'param' => 'f_centrocusto',
            ],
            'SETOR' => [
                'label' => 'Setor', 'sortable' => true, 'filter' => 'text', 'param' => 'f_setor',
            ],
            'LIDER' => [
                'label' => 'Líder', 'sortable' => true, 'filter' => 'text', 'param' => 'f_lider',
            ],
            'SITUACAOCONTRATO' => [
                'label' => 'Situação', 'sortable' => true, 'filter' => 'text', 'param' => 'f_situacao',
                'render' => fn($val) => ($val !== '' && $val !== null)
                    ? '<span class="badge badge-info">' . e((string)$val) . '</span>'
                    : '',
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
            'STATUS' => [
                'label' => 'Status', 'sortable' => true, 'filter' => 'select', 'param' => 'f_status',
                'options' => ['' => 'Todos', 'ATIVO' => 'Ativo', 'INATIVO' => 'Inativo'],
                'default' => 'ATIVO',
                'render' => fn($val) => '<span class="badge badge-' . ((string)$val === 'ATIVO' ? 'success' : 'secondary') . '">'
                                      . e((string)$val) . '</span>',
            ],
        ];

        $lt = new \Support\ListTable(base_url('hr/colaboradores'), $cols, 'hr');
        $lt->readRequest('NOMECOMPLETO');

        $fv      = $lt->getFilterValues();
        $sort    = $lt->getSort();
        $dir     = strtoupper($lt->getDir());
        $offset  = ($lt->getPage() - 1) * $lt->getPerPage();
        $perPage = $lt->getPerPage();

        $erro          = null;
        $colaboradores = [];
        $total         = 0;

        try {
            $pdo = OracleConnection::get();

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

            $where = empty($conditions) ? '1=1' : implode(' AND ', $conditions);

            $stmtCount = $pdo->prepare(
                "SELECT COUNT(*) AS TOTAL FROM " . self::VIEW . " WHERE {$where}"
            );
            $stmtCount->execute($binds);
            $total = (int)($stmtCount->fetch()['TOTAL'] ?? 0);

            if ($sort === 'NASCIMENTO') {
                $orderExpr = "CASE WHEN NASCIMENTO > SYSDATE"
                           . " THEN ADD_MONTHS(NASCIMENTO, -1200)"
                           . " ELSE NASCIMENTO END";
            } else {
                $orderExpr = $sort;
            }
            $nullsClause = in_array($sort, ['NASCIMENTO', 'DATAADMISSAO', 'DATARESCISAO'], true)
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
}

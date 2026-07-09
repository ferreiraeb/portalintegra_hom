<?php
namespace Controllers;

use Database\Connection;
use Models\Categoria;
use Models\TipoItem;
use Security\AlmoxPermission;
use Services\EmprestimoService;
use Services\ItemService;
use PDO;

class EmprestimoController
{
    private PDO $pdo;
    private EmprestimoService $service;
    private ItemService $itemService;

    public function __construct()
    {
        $this->pdo         = Connection::get();
        $this->service     = new EmprestimoService($this->pdo);
        $this->itemService = new ItemService($this->pdo);
    }

    public function index(): void
    {
        \Security\Auth::requireAuth();

        $accessibleCatIds = AlmoxPermission::accessibleCategoryIds(1);

        $statusOptions = [
            ''            => 'Todos',
            'ativo'       => 'Ativo',
            'reservado'   => 'Reservado',
            'devolvido'   => 'Devolvido',
            'extraviado'  => 'Extraviado',
            'transferido' => 'Transferido',
            'cancelado'   => 'Cancelado',
        ];

        $cols = [
            'item_descricao' => [
                'label'    => 'Item',
                'sortable' => true,
                'filter'   => 'text',
                'param'    => 'f_item',
                'sql_col'  => 'i.descricao',
            ],
            'tipo_nome' => [
                'label'    => 'Tipo',
                'sortable' => true,
                'filter'   => 'text',
                'param'    => 'f_tipo',
                'sql_col'  => 't.nome',
            ],
            'colaborador_nome' => [
                'label'      => 'Colaborador',
                'sortable'   => true,
                'filter'     => 'text',
                'param'      => 'f_colab',
                'sql_col'    => 'e.colaborador_nome',
                'skip_where' => true,
            ],
            'quantidade' => [
                'label'    => 'Qtd',
                'sortable' => true,
                'filter'   => null,
                'sql_col'  => 'e.quantidade',
            ],
            'data_entrega' => [
                'label'    => 'Data Entrega',
                'sortable' => true,
                'filter'   => 'text',
                'param'    => 'f_data_entrega',
                'sql_col'  => 'CONVERT(NVARCHAR(10), e.data_entrega, 23)',
            ],
            'data_prevista_devolucao' => [
                'label'    => 'Prev. Devolução',
                'sortable' => true,
                'filter'   => 'text',
                'param'    => 'f_prev_dev',
                'sql_col'  => 'CONVERT(NVARCHAR(10), e.data_prevista_devolucao, 23)',
            ],
            'status' => [
                'label'    => 'Status',
                'sortable' => true,
                'filter'   => 'select',
                'param'    => 'f_status',
                'sql_col'  => 'e.status',
                'options'  => $statusOptions,
            ],
            '_acoes' => [
                'label'    => 'Ações',
                'sortable' => false,
                'filter'   => null,
                'th_class' => 'text-right',
            ],
        ];

        $lt = new \Support\ListTable(base_url('emprestimos'), $cols, 'emprestimos');
        $lt->readRequest('e.created_at', 'desc');

        $editableCatIds = AlmoxPermission::accessibleCategoryIds(2);
        $canCreate = $editableCatIds === null || count($editableCatIds) > 0;

        if ($accessibleCatIds !== null && empty($accessibleCatIds)) {
            render_page('emprestimos/index.php', [
                'emprestimos' => [],
                'lt'          => $lt,
                'total'       => 0,
                'from'        => 0,
                'to'          => 0,
                'canCreate'   => false,
            ]);
            return;
        }

        $extra = [];
        if ($accessibleCatIds !== null) {
            $catBinds = [];
            $catPh    = [];
            foreach ($accessibleCatIds as $i => $catId) {
                $catPh[] = ':cat' . $i;
                $catBinds[':cat' . $i] = $catId;
            }
            $extra['t.categoria_id IN (' . implode(',', $catPh) . ')'] = $catBinds;
        }

        $fColab = trim($lt->getFilterValues()['f_colab'] ?? '');
        if ($fColab !== '') {
            $extra['(e.colaborador_nome LIKE :f_colab OR e.colaborador_codpessoa LIKE :f_colab2)'] = [
                ':f_colab'  => '%' . $fColab . '%',
                ':f_colab2' => '%' . $fColab . '%',
            ];
        }

        $w       = $lt->buildWhere($extra);
        $sort    = $lt->getSort() ?: 'e.created_at';
        $dir     = strtoupper($lt->getDir());
        $offset  = ($lt->getPage() - 1) * $lt->getPerPage();
        $perPage = $lt->getPerPage();

        $fromSql = "
            FROM dbo.emprestimos e
            INNER JOIN dbo.itens i ON i.id = e.item_id
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
        ";

        $sqlCount = "SELECT COUNT(*) {$fromSql} {$w['sql']}";
        $stmt = $this->pdo->prepare($sqlCount);
        foreach ($w['binds'] as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT e.id, e.item_id, e.quantidade, e.colaborador_codpessoa, e.colaborador_nome,
                       e.data_entrega, e.data_prevista_devolucao, e.data_devolucao, e.status, e.created_at,
                       i.descricao AS item_descricao,
                       t.nome AS tipo_nome,
                       t.categoria_id
                {$fromSql}
                {$w['sql']}
                ORDER BY {$sort} {$dir}
                OFFSET :off ROWS FETCH NEXT :lim ROWS ONLY";

        $stmt = $this->pdo->prepare($sql);
        foreach ($w['binds'] as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $emprestimos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $catIds = array_unique(array_column($emprestimos, 'categoria_id'));
        AlmoxPermission::preloadForCategories($catIds);

        foreach ($emprestimos as &$emp) {
            $emp['nivel_usuario'] = AlmoxPermission::categoryLevel((int)$emp['categoria_id']);
        }
        unset($emp);

        $from = $total > 0 ? ($offset + 1) : 0;
        $to   = min($total, $offset + $perPage);

        render_page('emprestimos/index.php', [
            'emprestimos' => $emprestimos,
            'lt'          => $lt,
            'total'       => $total,
            'from'        => $from,
            'to'          => $to,
            'canCreate'   => $canCreate,
        ]);
    }

    public function create(): void
    {
        \Security\Auth::requireAuth();
        $editableCatIds = AlmoxPermission::accessibleCategoryIds(2);
        $canCreate = $editableCatIds === null || count($editableCatIds) > 0;
        if (!$canCreate) { http_response_code(403); exit('Acesso negado.'); }

        $modo   = $_GET['modo'] ?? 'emprestar';
        $itemId = (int)($_GET['item_id'] ?? 0);

        // Verifica permissão e disponibilidade do item pré-selecionado
        $preItem = null;
        $erroPreItem = null;
        if ($itemId) {
            $preItem = $this->findItemById($itemId);
            if ($preItem) {
                $catId = $this->getCategoriaIdByItem($itemId);
                AlmoxPermission::requireCategory($catId, 2);
                if (!$this->service->itemDisponivelParaEmprestimo($itemId)) {
                    $erroPreItem = 'Este item não possui unidades disponíveis para empréstimo.';
                    $preItem = null;
                }
            }
        }

        render_page('emprestimos/form.php', [
            'modo'               => in_array($modo, ['emprestar', 'reservar']) ? $modo : 'emprestar',
            'emprestimo'         => $preItem ? ['item_id' => $itemId] : null,
            'erro'               => $erroPreItem,
            'tiposPorCategoria'  => $this->tiposPorCategoria(),
            'itemPreSelecionado' => $preItem,
            'itemSelecionado'    => null,
        ]);
    }

    public function autocompleteItens(): void
    {
        \Security\Auth::requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        $tipoId = (int)($_GET['tipo_item_id'] ?? 0);
        $q      = trim($_GET['q'] ?? '');

        if (!$tipoId) {
            echo json_encode([]);
            return;
        }

        $tipo = TipoItem::find($tipoId);
        if (!$tipo || !$tipo->ativo) {
            echo json_encode([]);
            return;
        }

        if (AlmoxPermission::categoryLevel($tipo->categoria_id) < 2) {
            echo json_encode([]);
            return;
        }

        $result = $this->itemService->findForAutocomplete($tipo, $q);
        echo json_encode($result);
    }

    public function store(): void
    {
        \Security\Auth::requireAuth();
        check_csrf();

        $data   = $this->collectPost();
        $tipo   = in_array($_POST['tipo'] ?? '', ['ativo', 'reservado']) ? $_POST['tipo'] : 'ativo';
        $modo   = $tipo === 'reservado' ? 'reservar' : 'emprestar';
        $locked = !empty($_POST['_item_locked']);

        // Verifica permissão sobre a categoria do item
        if ($data['item_id']) {
            $catId = $this->getCategoriaIdByItem($data['item_id']);
            AlmoxPermission::requireCategory($catId, 2);
        }

        $err = $this->validate($data, $tipo);
        if ($err) {
            render_page('emprestimos/form.php', [
                'modo'               => $modo,
                'emprestimo'         => $data,
                'erro'               => $err,
                'tiposPorCategoria'  => $this->tiposPorCategoria(),
                'itemPreSelecionado' => $locked && $data['item_id'] ? $this->findItemById($data['item_id']) : null,
                'itemSelecionado'    => !$locked && $data['item_id'] ? $this->findItemById($data['item_id']) : null,
            ]);
            return;
        }

        try {
            if ($tipo === 'reservado') {
                $id = $this->service->reservar(
                    $data['item_id'],
                    $data['colaborador_codpessoa'],
                    $data['colaborador_nome'],
                    $data
                );
            } else {
                $id = $this->service->emprestar(
                    $data['item_id'],
                    $data['colaborador_codpessoa'],
                    $data['colaborador_nome'],
                    $data
                );
            }
            redirect('emprestimos/' . $id);
        } catch (\RuntimeException $e) {
            render_page('emprestimos/form.php', [
                'modo'               => $modo,
                'emprestimo'         => $data,
                'erro'               => $e->getMessage(),
                'tiposPorCategoria'  => $this->tiposPorCategoria(),
                'itemPreSelecionado' => $locked && $data['item_id'] ? $this->findItemById($data['item_id']) : null,
                'itemSelecionado'    => !$locked && $data['item_id'] ? $this->findItemById($data['item_id']) : null,
            ]);
        }
    }

    public function show(int $id): void
    {
        \Security\Auth::requireAuth();
        $emp = $this->findOrFail($id);
        $catId = $this->getCategoriaIdByItem((int)$emp['item_id']);
        AlmoxPermission::requireCategory($catId, 1);
        $nivelUsuario = AlmoxPermission::categoryLevel($catId);
        render_page('emprestimos/show.php', [
            'emprestimo'   => $emp,
            'nivelUsuario' => $nivelUsuario,
        ]);
    }

    public function devolver(int $id): void
    {
        \Security\Auth::requireAuth();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();

        $emp = $this->findOrFail($id);
        AlmoxPermission::requireCategory($this->getCategoriaIdByItem((int)$emp['item_id']), 2);

        try {
            $this->service->devolver($id, trim($_POST['observacao'] ?? '') ?: null);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        redirect('emprestimos/' . $id);
    }

    public function ativar(int $id): void
    {
        \Security\Auth::requireAuth();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();

        $emp = $this->findOrFail($id);
        AlmoxPermission::requireCategory($this->getCategoriaIdByItem((int)$emp['item_id']), 2);

        try {
            $this->service->ativarReserva($id);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        redirect('emprestimos/' . $id);
    }

    public function cancelarReserva(int $id): void
    {
        \Security\Auth::requireAuth();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();

        $emp = $this->findOrFail($id);
        AlmoxPermission::requireCategory($this->getCategoriaIdByItem((int)$emp['item_id']), 2);

        try {
            $this->service->cancelarReserva($id);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        redirect('emprestimos?tab=reservas');
    }

    // Helpers
    private function findOrFail(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*,
                   i.descricao AS item_descricao,
                   t.nome AS tipo_nome
            FROM dbo.emprestimos e
            INNER JOIN dbo.itens i ON i.id = e.item_id
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); exit('Empréstimo não encontrado.'); }
        return $row;
    }

    private function collectPost(): array
    {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $qtd    = max(1, (int)($_POST['quantidade'] ?? 1));
        if ($itemId && $this->isItemDeterminado($itemId)) {
            $qtd = 1;
        }

        return [
            'item_id'                 => $itemId,
            'colaborador_codpessoa'   => trim($_POST['colaborador_codpessoa'] ?? ''),
            'colaborador_nome'        => trim($_POST['colaborador_nome'] ?? ''),
            'quantidade'              => $qtd,
            'data_entrega'            => trim($_POST['data_entrega'] ?? date('Y-m-d')),
            'data_prevista_devolucao' => trim($_POST['data_prevista_devolucao'] ?? '') ?: null,
            'observacao'              => trim($_POST['observacao'] ?? '') ?: null,
            'criado_por'              => $_SESSION['user']['id'] ?? null,
        ];
    }

    private function validate(array $data, string $tipo): ?string
    {
        if (!$data['item_id'])                    return 'Selecione um item.';
        if ($data['colaborador_codpessoa'] === '') return 'Informe o código do colaborador.';
        if ($data['colaborador_nome'] === '')      return 'Informe o nome do colaborador.';
        if ($tipo === 'ativo' && $data['data_entrega'] === '') return 'Informe a data de entrega.';
        return null;
    }

    private function findItemById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.id, i.descricao, i.tipo_item_id, t.nome AS tipo_nome, t.is_determinado
            FROM dbo.itens i
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, TipoItem[]> */
    private function tiposPorCategoria(): array
    {
        $tipos  = TipoItem::findAll(['ativo' => 1], 'nome');
        $cats   = Categoria::findAll(['ativo' => 1], 'nome');
        $catMap = [];
        foreach ($cats as $c) {
            $catMap[$c->id] = $c->nome;
        }

        $accessibleCatIds = AlmoxPermission::accessibleCategoryIds(2);

        $grupos = [];
        foreach ($tipos as $t) {
            if ($accessibleCatIds !== null && !in_array($t->categoria_id, $accessibleCatIds, true)) {
                continue;
            }
            $nomeCat = $catMap[$t->categoria_id] ?? 'Sem categoria';
            $grupos[$nomeCat][] = $t;
        }
        ksort($grupos);
        return $grupos;
    }

    /** Resolve o categoria_id a partir de um item_id. */
    private function getCategoriaIdByItem(int $itemId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT t.categoria_id
            FROM dbo.itens i
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
            WHERE i.id = ?
        ");
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['categoria_id'] : 0;
    }

    private function isItemDeterminado(int $itemId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT t.is_determinado
            FROM dbo.itens i
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
            WHERE i.id = ?
        ");
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (bool)(int)$row['is_determinado'] : false;
    }
}


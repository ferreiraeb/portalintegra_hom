<?php
namespace Controllers;

use Database\Connection;
use Security\AlmoxPermission;
use Services\EmprestimoService;
use PDO;

class EmprestimoController
{
    private PDO $pdo;
    private EmprestimoService $service;

    public function __construct()
    {
        $this->pdo     = Connection::get();
        $this->service = new EmprestimoService($this->pdo);
    }

    public function index(): void
    {
        \Security\Auth::requireAuth();

        $filtroStatus = trim($_GET['status'] ?? '');
        $filtroColab  = trim($_GET['colaborador'] ?? '');

        // Categorias acessíveis para filtrar empréstimos
        $accessibleCatIds = AlmoxPermission::accessibleCategoryIds(1);

        $params = [];
        $where  = [];
        if ($filtroStatus !== '') {
            $where[]  = 'e.status = ?';
            $params[] = $filtroStatus;
        }
        if ($filtroColab !== '') {
            $where[]  = "(e.colaborador_nome LIKE ? OR e.colaborador_codpessoa LIKE ?)";
            $params[] = "%{$filtroColab}%";
            $params[] = "%{$filtroColab}%";
        }

        // Restrição por categoria acessível
        if ($accessibleCatIds !== null) {
            if (empty($accessibleCatIds)) {
                // Sem acesso a nenhuma categoria retorna lista vazia
                render_page('emprestimos/index.php', [
                    'emprestimos'  => [],
                    'filtroStatus' => $filtroStatus,
                    'filtroColab'  => $filtroColab,
                    'canCreate'    => false,
                ]);
                return;
            }
            $placeholders = implode(',', array_fill(0, count($accessibleCatIds), '?'));
            $where[]  = "t.categoria_id IN ($placeholders)";
            $params   = array_merge($params, $accessibleCatIds);
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare("
            SELECT e.id, e.item_id, e.quantidade, e.colaborador_codpessoa, e.colaborador_nome,
                   e.data_entrega, e.data_prevista_devolucao, e.data_devolucao, e.status, e.created_at,
                   i.descricao AS item_descricao,
                   t.nome AS tipo_nome,
                   t.categoria_id
            FROM dbo.emprestimos e
            INNER JOIN dbo.itens i ON i.id = e.item_id
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
            {$whereSql}
            ORDER BY e.created_at DESC
        ");
        $stmt->execute($params);
        $emprestimos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pré-carrega níveis para renderização
        $catIds = array_unique(array_column($emprestimos, 'categoria_id'));
        AlmoxPermission::preloadForCategories($catIds);

        // Adiciona nivel_usuario a cada empréstimo
        foreach ($emprestimos as &$emp) {
            $emp['nivel_usuario'] = AlmoxPermission::categoryLevel((int)$emp['categoria_id']);
        }
        unset($emp);

        // Pode criar empréstimos se tiver nivel >= 2 em alguma categoria
        $editableCatIds = AlmoxPermission::accessibleCategoryIds(2);
        $canCreate = $editableCatIds === null || count($editableCatIds) > 0;

        render_page('emprestimos/index.php', [
            'emprestimos'  => $emprestimos,
            'filtroStatus' => $filtroStatus,
            'filtroColab'  => $filtroColab,
            'canCreate'    => $canCreate,
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

        // Verifica permissão sobre o item pré-selecionado
        if ($itemId) {
            $preItem = $this->findItemById($itemId);
            if ($preItem) {
                $catId = $this->getCategoriaIdByItem($itemId);
                AlmoxPermission::requireCategory($catId, 2);
            }
        }

        render_page('emprestimos/form.php', [
            'modo'               => in_array($modo, ['emprestar', 'reservar']) ? $modo : 'emprestar',
            'emprestimo'         => $itemId ? ['item_id' => $itemId] : null,
            'erro'               => null,
            'itensDisponiveis'   => $this->itensDisponiveis(),
            'itemPreSelecionado' => $itemId ? $this->findItemById($itemId) : null,
        ]);
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
                'itensDisponiveis'   => $this->itensDisponiveis(),
                'itemPreSelecionado' => $locked && $data['item_id'] ? $this->findItemById($data['item_id']) : null,
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
                'itensDisponiveis'   => $this->itensDisponiveis(),
                'itemPreSelecionado' => $locked && $data['item_id'] ? $this->findItemById($data['item_id']) : null,
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
        return [
            'item_id'                 => (int)($_POST['item_id'] ?? 0),
            'colaborador_codpessoa'   => trim($_POST['colaborador_codpessoa'] ?? ''),
            'colaborador_nome'        => trim($_POST['colaborador_nome'] ?? ''),
            'quantidade'              => max(1, (int)($_POST['quantidade'] ?? 1)),
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
            SELECT i.id, i.descricao, t.nome AS tipo_nome
            FROM dbo.itens i
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function itensDisponiveis(): array
    {
        $accessibleCatIds = AlmoxPermission::accessibleCategoryIds(2);

        $catFilter = '';
        $extraParams = [];
        if ($accessibleCatIds !== null) {
            if (empty($accessibleCatIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleCatIds), '?'));
            $catFilter    = "AND t.categoria_id IN ($placeholders)";
            $extraParams  = $accessibleCatIds;
        }

        $stmt = $this->pdo->prepare("
            SELECT i.id, i.descricao, i.quantidade_total,
                   t.nome AS tipo_nome
            FROM dbo.itens i
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
            WHERE i.status IN ('disponivel','em_uso','reservado')
              AND i.status NOT IN ('bloqueado','baixado','extraviado')
              $catFilter
            ORDER BY t.nome, i.descricao
        ");
        $stmt->execute($extraParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
}


<?php
namespace Controllers;

use Database\Connection;
use Models\Categoria;
use Models\Item;
use Models\TipoItem;
use Security\AlmoxPermission;
use Services\ItemService;

class ItemController
{
    private ItemService $itemService;

    public function __construct()
    {
        $this->itemService = new ItemService(Connection::get());
    }

    public function index(): void
    {
        \Security\Auth::requireAuth();

        $filtroCategoria = (int)($_GET['categoria_id'] ?? 0);
        $filtroTipo      = (int)($_GET['tipo_item_id'] ?? 0);
        $filtroStatus    = trim($_GET['status'] ?? '');

        // Categorias acessíveis (nivel >= 1)
        $accessibleCatIds = AlmoxPermission::accessibleCategoryIds(1);

        $where = [];
        if ($filtroTipo)   $where['tipo_item_id'] = $filtroTipo;
        if ($filtroStatus) $where['status']       = $filtroStatus;

        $itens = Item::findAll($where, 'descricao');

        $todosOsTipos = TipoItem::findAll([], 'nome');
        $tipoMap = [];
        foreach ($todosOsTipos as $t) { $tipoMap[$t->id] = $t; }

        $todasAsCategorias = Categoria::findAll([], 'nome');
        $catMap = [];
        foreach ($todasAsCategorias as $c) { $catMap[$c->id] = $c->nome; }

        // Filtra por categoria
        if ($filtroCategoria) {
            $itens = array_filter($itens, function(Item $i) use ($tipoMap, $filtroCategoria) {
                $t = $tipoMap[$i->tipo_item_id] ?? null;
                return $t && $t->categoria_id === $filtroCategoria;
            });
        }

        // Restringe às categorias acessíveis (nivel >= 1)
        if ($accessibleCatIds !== null) {
            $itens = array_filter($itens, function(Item $i) use ($tipoMap, $accessibleCatIds) {
                $t = $tipoMap[$i->tipo_item_id] ?? null;
                return $t && in_array($t->categoria_id, $accessibleCatIds, true);
            });
        }

        // Pré-carrega níveis para renderização dos botões
        $allCatIds = array_unique(array_filter(array_map(
            fn(Item $i) => ($tipoMap[$i->tipo_item_id] ?? null)?->categoria_id,
            $itens
        )));
        AlmoxPermission::preloadForCategories(array_values($allCatIds));

        // Enriquece cada item com nome do tipo, categoria e nível do usuário
        $itensArr = array_map(function(Item $i) use ($tipoMap, $catMap) {
            $arr  = $i->toArray();
            $tipo = $tipoMap[$i->tipo_item_id] ?? null;
            $arr['nome_tipo']      = $tipo ? $tipo->nome : '—';
            $arr['nome_categoria'] = $tipo ? ($catMap[$tipo->categoria_id] ?? '—') : '—';
            $arr['categoria_id']   = $tipo ? $tipo->categoria_id : 0;
            $arr['nivel_usuario']  = $tipo ? AlmoxPermission::categoryLevel($tipo->categoria_id) : 0;
            return $arr;
        }, $itens);

        // Filtra categorias acessíveis para o select de filtro
        $todasCategoriasAtivas = Categoria::findAll(['ativo' => 1], 'nome');
        $categoriasAtivas = $accessibleCatIds === null
            ? $todasCategoriasAtivas
            : array_filter($todasCategoriasAtivas, fn($c) => in_array($c->id, $accessibleCatIds, true));

        $tiposAtivos = array_filter($todosOsTipos, fn($t) => $t->ativo);
        if ($accessibleCatIds !== null) {
            $tiposAtivos = array_filter($tiposAtivos, fn($t) => in_array($t->categoria_id, $accessibleCatIds, true));
        }

        // Pode criar itens se tiver nivel >= 2 em ao menos uma categoria
        $editableCatIds = AlmoxPermission::accessibleCategoryIds(2);
        $canCreate = $editableCatIds === null || count($editableCatIds) > 0;

        render_page('itens/index.php', [
            'itens'            => array_values($itensArr),
            'categoriasAtivas' => array_values($categoriasAtivas),
            'tiposAtivos'      => array_values($tiposAtivos),
            'filtroCategoria'  => $filtroCategoria,
            'filtroTipo'       => $filtroTipo,
            'filtroStatus'     => $filtroStatus,
            'canCreate'        => $canCreate,
        ]);
    }

    public function create(): void
    {
        \Security\Auth::requireAuth();
        $tiposPorCategoria = $this->tiposAgrupadosPorCategoria(2);
        if (empty($tiposPorCategoria)) {
            http_response_code(403); exit('Acesso negado.');
        }
        render_page('itens/form.php', [
            'modo'               => 'criar',
            'item'               => null,
            'erro'               => null,
            'tiposPorCategoria'  => $tiposPorCategoria,
            'linhasDisponiveis'  => $this->linhasDisponiveisParaSelect(),
        ]);
    }

    public function store(): void
    {
        \Security\Auth::requireAuth();
        check_csrf();

        $tiposPorCategoria = $this->tiposAgrupadosPorCategoria(2);
        $data = $this->collectPostData();

        // Valida permissão sobre a categoria do tipo selecionado
        if ($data['tipo_item_id']) {
            $tipo = TipoItem::find($data['tipo_item_id']);
            if ($tipo) AlmoxPermission::requireCategory($tipo->categoria_id, 2);
        }

        if (!$data['tipo_item_id'] || $data['descricao'] === '' || $data['status'] === '') {
            render_page('itens/form.php', [
                'modo'              => 'criar',
                'erro'              => 'Tipo, descrição e status são obrigatórios.',
                'item'              => $data,
                'tiposPorCategoria' => $tiposPorCategoria,
                'linhasDisponiveis' => $this->linhasDisponiveisParaSelect(),
            ]);
            return;
        }

        try {
            $novoId = $this->itemService->create($data);
            redirect('itens/' . $novoId);
        } catch (\RuntimeException $e) {
            render_page('itens/form.php', [
                'modo'              => 'criar',
                'erro'              => $e->getMessage(),
                'item'              => $data,
                'tiposPorCategoria' => $tiposPorCategoria,
                'linhasDisponiveis' => $this->linhasDisponiveisParaSelect(),
            ]);
        }
    }

    public function show(int $id): void
    {
        \Security\Auth::requireAuth();
        try {
            $item = $this->itemService->getWithDetail($id);
        } catch (\RuntimeException $e) {
            http_response_code(404); exit(e($e->getMessage()));
        }
        $tipo = TipoItem::find($item['tipo_item_id']);
        $cat  = $tipo ? Categoria::find($tipo->categoria_id) : null;

        AlmoxPermission::requireCategory($tipo?->categoria_id ?? 0, 1);

        $nivelUsuario = AlmoxPermission::categoryLevel($tipo?->categoria_id ?? 0);

        $pdo = Connection::get();

        $linhaVinculada = null;
        if (!empty($item['linha_item_id'])) {
            $stmt = $pdo->prepare("
                SELECT i.descricao, l.numero_linha, l.operadora
                FROM   dbo.itens i
                LEFT JOIN dbo.item_linha_telefonica l ON l.item_id = i.id
                WHERE  i.id = :id
            ");
            $stmt->execute([':id' => (int)$item['linha_item_id']]);
            $linhaVinculada = $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
        }

        $colaboradoresItem = [];
        try {
            $stmt = $pdo->prepare("
                SELECT colaborador_nome, colaborador_codpessoa, status, data_entrega
                FROM   dbo.emprestimos
                WHERE  item_id = :id
                  AND  status IN ('ativo', 'reservado')
                ORDER BY data_entrega DESC
            ");
            $stmt->execute([':id' => $id]);
            $colaboradoresItem = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // não bloqueia a exibição do item
        }

        render_page('itens/show.php', [
            'item'              => $item,
            'tipo'              => $tipo?->toArray(),
            'cat'               => $cat?->toArray(),
            'linhaVinculada'    => $linhaVinculada,
            'nivelUsuario'      => $nivelUsuario,
            'colaboradoresItem' => $colaboradoresItem,
        ]);
    }

    public function edit(int $id): void
    {
        \Security\Auth::requireAuth();
        try {
            $item = $this->itemService->getWithDetail($id);
        } catch (\RuntimeException $e) {
            http_response_code(404); exit(e($e->getMessage()));
        }
        $tipo = TipoItem::find($item['tipo_item_id']);
        AlmoxPermission::requireCategory($tipo?->categoria_id ?? 0, 2);

        $tiposPorCategoria = $this->tiposAgrupadosPorCategoria(2);
        render_page('itens/form.php', [
            'modo'              => 'editar',
            'item'              => $item,
            'erro'              => null,
            'tiposPorCategoria' => $tiposPorCategoria,
            'linhasDisponiveis' => $this->linhasDisponiveisParaSelect($id),
        ]);
    }

    public function update(int $id): void
    {
        \Security\Auth::requireAuth();
        check_csrf();

        $data = $this->collectPostData();

        // Valida permissão sobre a categoria do tipo selecionado
        if ($data['tipo_item_id']) {
            $tipo = TipoItem::find($data['tipo_item_id']);
            if ($tipo) AlmoxPermission::requireCategory($tipo->categoria_id, 2);
        }

        $tiposPorCategoria = $this->tiposAgrupadosPorCategoria(2);

        if (!$data['tipo_item_id'] || $data['descricao'] === '' || $data['status'] === '') {
            render_page('itens/form.php', [
                'modo'              => 'editar',
                'erro'              => 'Tipo, descrição e status são obrigatórios.',
                'item'              => array_merge(['id' => $id], $data),
                'tiposPorCategoria' => $tiposPorCategoria,
                'linhasDisponiveis' => $this->linhasDisponiveisParaSelect($id),
            ]);
            return;
        }

        // Protege contra sobrescrita indevida: se o item está em_uso e o POST
        // não veio com em_uso (campo desabilitado no form), preserva o status atual.
        $itemAtual = Item::find($id);
        if ($itemAtual && $itemAtual->status === 'em_uso' && $data['status'] !== 'em_uso') {
            $data['status'] = 'em_uso';
        }

        try {
            $this->itemService->update($id, $data);
            redirect('itens/' . $id);
        } catch (\RuntimeException $e) {
            render_page('itens/form.php', [
                'modo'              => 'editar',
                'erro'              => $e->getMessage(),
                'item'              => array_merge(['id' => $id], $data),
                'tiposPorCategoria' => $tiposPorCategoria,
                'linhasDisponiveis' => $this->linhasDisponiveisParaSelect($id),
            ]);
        }
    }

    public function alterarStatus(int $id): void
    {
        \Security\Auth::requireAuth();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();

        $item = Item::find($id);
        if ($item) {
            $tipo = TipoItem::find($item->tipo_item_id);
            AlmoxPermission::requireCategory($tipo?->categoria_id ?? 0, 3);
        }

        $novoStatus = trim($_POST['status'] ?? '');
        $permitidos = ['disponivel', 'reservado', 'bloqueado'];

        if ($novoStatus === 'em_uso') {
            $_SESSION['flash_error'] = 'Status "em uso" é controlado pelos empréstimos.';
            redirect('itens/' . $id);
            return;
        }

        if (!in_array($novoStatus, $permitidos, true)) {
            redirect('itens/' . $id);
            return;
        }

        $item = Item::find($id);
        if ($item) {
            $item->status     = $novoStatus;
            $item->updated_at = date('Y-m-d H:i:s');
            $item->save();
        }
        redirect('itens/' . $id);
    }

    public function tipoInfo(): void
    {
        \Security\Auth::requireAuth();
        header('Content-Type: application/json; charset=utf-8');
        $id   = (int)($_GET['id'] ?? 0);
        $tipo = TipoItem::find($id);
        if (!$tipo) {
            echo json_encode(['is_determinado' => 0, 'tabela_detalhe' => null]);
            return;
        }
        echo json_encode([
            'is_determinado' => (int)$tipo->is_determinado,
            'tabela_detalhe' => $tipo->tabela_detalhe,
        ]);
    }

    // Helpers
    private function collectPostData(): array
    {
        return [
            'tipo_item_id'       => (int)($_POST['tipo_item_id'] ?? 0),
            'descricao'          => trim($_POST['descricao'] ?? ''),
            'status'             => trim($_POST['status'] ?? 'disponivel'),
            'quantidade_total'   => max(1, (int)($_POST['quantidade_total'] ?? 1)),
            'observacao'         => trim($_POST['observacao'] ?? '') ?: null,
            'created_by'         => $_SESSION['user']['id'] ?? null,
            'updated_by'         => $_SESSION['user']['id'] ?? null,
            // --- item_linha_telefonica ---
            'numero_linha'       => trim($_POST['numero_linha'] ?? '') ?: null,
            'numero_chip'        => trim($_POST['numero_chip'] ?? '') ?: null,
            'numero_anterior'    => trim($_POST['numero_anterior'] ?? '') ?: null,
            'operadora'          => trim($_POST['operadora'] ?? '') ?: null,
            'tipo_chip'          => trim($_POST['tipo_chip'] ?? '') ?: null,
            'status_linha'       => trim($_POST['status_linha'] ?? '') ?: null,
            'contrato'           => trim($_POST['contrato'] ?? '') ?: null,
            'plano'              => trim($_POST['plano'] ?? '') ?: null,
            'custo_mensal'       => $_POST['custo_mensal'] !== '' ? (float)$_POST['custo_mensal'] : null,
            // --- item_equipamento_ti ---
            // tipo_equipamento é derivado de tipos_item.tipo_equipamento_valor no serviço
            'numero_serie'       => trim($_POST['numero_serie'] ?? '') ?: null,
            'etiqueta'           => trim($_POST['etiqueta'] ?? '') ?: null,
            'marca'              => trim($_POST['marca'] ?? '') ?: null,
            'modelo'             => trim($_POST['modelo'] ?? '') ?: null,
            'proprietario'       => trim($_POST['proprietario'] ?? '') ?: null,
            'imei'               => trim($_POST['imei'] ?? '') ?: null,
            'mac_address'        => trim($_POST['mac_address'] ?? '') ?: null,
            'fornecedor'         => trim($_POST['fornecedor'] ?? '') ?: null,
            // --- item_veiculo ---
            'placa'              => trim($_POST['placa'] ?? '') ?: null,
            'ano'                => $_POST['ano'] !== '' ? (int)$_POST['ano'] : null,
            'cor'                => trim($_POST['cor'] ?? '') ?: null,
            'renavam'            => trim($_POST['renavam'] ?? '') ?: null,
            'data_contratacao'   => trim($_POST['data_contratacao'] ?? '') ?: null,
            'data_vencimento'    => trim($_POST['data_vencimento'] ?? '') ?: null,
            // --- item_cartao ---
            'tipo_cartao'        => trim($_POST['tipo_cartao'] ?? '') ?: null,
            'numero_cartao'      => trim($_POST['numero_cartao'] ?? '') ?: null,
        ];
    }

    /**
     * Retorna tipos ativos agrupados por nome de categoria,
     * filtrados ao nível mínimo de permissão do usuário.
     * @return array<string, TipoItem[]>
     */
    private function tiposAgrupadosPorCategoria(int $minLevel = 1): array
    {
        $tipos = TipoItem::findAll(['ativo' => 1], 'nome');
        $cats  = Categoria::findAll(['ativo' => 1], 'nome');
        $catMap = [];
        foreach ($cats as $c) { $catMap[$c->id] = $c->nome; }

        $accessibleCatIds = AlmoxPermission::accessibleCategoryIds($minLevel);

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

    /**
     * Linhas telefônicas disponíveis para vincular a um equipamento móvel.
     * Exclui o próprio item em edição para evitar auto-referência.
     */
    private function linhasDisponiveisParaSelect(int $excluirItemId = 0): array
    {
        $pdo  = Connection::get();
        $sql  = "
            SELECT i.id, i.descricao, l.numero_linha, l.operadora
            FROM   dbo.itens i
            JOIN   dbo.tipos_item t  ON t.id = i.tipo_item_id
            LEFT JOIN dbo.item_linha_telefonica l ON l.item_id = i.id
            WHERE  t.tabela_detalhe = 'item_linha_telefonica'
              AND  i.status NOT IN ('baixado', 'extraviado')
              AND  i.id <> :excluir
            ORDER BY l.numero_linha, i.descricao
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':excluir' => $excluirItemId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
}


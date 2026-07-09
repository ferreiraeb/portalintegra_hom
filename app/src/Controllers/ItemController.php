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

    public function indexByTipo(int $tipoId): void
    {
        \Security\Auth::requireAuth();

        $tipo = TipoItem::find($tipoId);
        if (!$tipo || !$tipo->ativo) {
            http_response_code(404);
            exit('Tipo de item não encontrado.');
        }

        AlmoxPermission::requireCategory($tipo->categoria_id, 1);

        $listaUrl = 'itens/tipo/' . $tipoId;
        $cols     = ItemService::buildListTableCols($tipo);
        $lt       = new \Support\ListTable(base_url($listaUrl), $cols, 'itens_tipo_' . $tipoId);
        $lt->readRequest('i.descricao');

        AlmoxPermission::preloadForCategories([$tipo->categoria_id]);
        $nivelUsuario = AlmoxPermission::categoryLevel($tipo->categoria_id);

        $listing = $this->itemService->findForListing($tipo, $lt);
        $itens   = array_map(function (array $row) use ($nivelUsuario) {
            $row['nivel_usuario'] = $nivelUsuario;
            return $row;
        }, $listing['rows']);

        render_page('itens/index.php', [
            'itens'    => $itens,
            'tipo'     => $tipo,
            'listaUrl' => $listaUrl,
            'lt'       => $lt,
            'total'    => $listing['total'],
            'from'     => $listing['from'],
            'to'       => $listing['to'],
            'canCreate'=> $nivelUsuario >= 2,
        ]);
    }

    public function create(): void
    {
        \Security\Auth::requireAuth();

        $preTipoId = (int)($_GET['tipo_item_id'] ?? 0);
        $tipoFixo  = null;
        $listaUrl  = null;

        if ($preTipoId) {
            $tipoFixo = TipoItem::find($preTipoId);
            if (!$tipoFixo || !$tipoFixo->ativo) {
                http_response_code(404);
                exit('Tipo de item não encontrado.');
            }
            AlmoxPermission::requireCategory($tipoFixo->categoria_id, 2);
            $listaUrl = 'itens/tipo/' . $preTipoId;
            $tiposPorCategoria = $this->tiposAgrupadosPorCategoria(2, $preTipoId);
        } else {
            $tiposPorCategoria = $this->tiposAgrupadosPorCategoria(2);
            if (empty($tiposPorCategoria)) {
                http_response_code(403);
                exit('Acesso negado.');
            }
        }

        render_page('itens/form.php', [
            'modo'               => 'criar',
            'item'               => $preTipoId ? ['tipo_item_id' => $preTipoId] : null,
            'erro'               => null,
            'tiposPorCategoria'  => $tiposPorCategoria,
            'linhasDisponiveis'  => $this->linhasDisponiveisParaSelect(),
            'tipoFixo'           => $tipoFixo,
            'listaUrl'           => $listaUrl,
        ]);
    }

    public function store(): void
    {
        \Security\Auth::requireAuth();
        check_csrf();

        $data = $this->collectPostData();
        $tiposPorCategoria = $this->tiposAgrupadosPorCategoria(2);
        $tipoFixo = null;
        $listaUrl = null;
        if ($data['tipo_item_id']) {
            $tipo = TipoItem::find($data['tipo_item_id']);
            if ($tipo) {
                AlmoxPermission::requireCategory($tipo->categoria_id, 2);
                $tipoFixo = $tipo;
                $listaUrl = 'itens/tipo/' . $tipo->id;
            }
        }

        if (!$data['tipo_item_id'] || $data['descricao'] === '' || $data['status'] === '') {
            render_page('itens/form.php', [
                'modo'              => 'criar',
                'erro'              => 'Tipo, descrição e status são obrigatórios.',
                'item'              => $data,
                'tiposPorCategoria' => $tiposPorCategoria,
                'linhasDisponiveis' => $this->linhasDisponiveisParaSelect(),
                'tipoFixo'          => $tipoFixo,
                'listaUrl'          => $listaUrl,
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
                'tipoFixo'          => $tipoFixo,
                'listaUrl'          => $listaUrl,
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
            'listaUrl'          => $tipo ? 'itens/tipo/' . $tipo->id : null,
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
            'listaUrl'          => $tipo ? 'itens/tipo/' . $tipo->id : null,
            'tipoFixo'          => $tipo,
        ]);
    }

    public function update(int $id): void
    {
        \Security\Auth::requireAuth();
        check_csrf();

        $data = $this->collectPostData();

        $itemAtual = Item::find($id);
        if ($itemAtual) {
            $data['tipo_item_id'] = $itemAtual->tipo_item_id;
        }

        // Valida permissão sobre a categoria do tipo selecionado
        if ($data['tipo_item_id']) {
            $tipo = TipoItem::find($data['tipo_item_id']);
            if ($tipo) AlmoxPermission::requireCategory($tipo->categoria_id, 2);
        }

        $tiposPorCategoria = $this->tiposAgrupadosPorCategoria(2);
        $listaUrl = null;
        $tipoFixo = null;
        if ($data['tipo_item_id']) {
            $tipoFixo = TipoItem::find($data['tipo_item_id']);
            if ($tipoFixo) {
                $listaUrl = 'itens/tipo/' . $tipoFixo->id;
            }
        }

        if (!$data['tipo_item_id'] || $data['descricao'] === '' || $data['status'] === '') {
            render_page('itens/form.php', [
                'modo'              => 'editar',
                'erro'              => 'Tipo, descrição e status são obrigatórios.',
                'item'              => array_merge(['id' => $id], $data),
                'tiposPorCategoria' => $tiposPorCategoria,
                'linhasDisponiveis' => $this->linhasDisponiveisParaSelect($id),
                'listaUrl'          => $listaUrl,
                'tipoFixo'          => $tipoFixo,
            ]);
            return;
        }

        // Protege contra sobrescrita indevida: se o item está em_uso e o POST
        // não veio com em_uso (campo desabilitado no form), preserva o status atual.
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
                'listaUrl'          => $listaUrl,
                'tipoFixo'          => $tipoFixo,
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
    private function tiposAgrupadosPorCategoria(int $minLevel = 1, ?int $onlyTipoId = null): array
    {
        $tipos = TipoItem::findAll(['ativo' => 1], 'nome');
        if ($onlyTipoId) {
            $tipos = array_filter($tipos, fn($t) => $t->id === $onlyTipoId);
        }
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


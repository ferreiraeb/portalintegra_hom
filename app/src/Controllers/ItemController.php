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

        if (($_GET['export'] ?? '') === '1') {
            $this->exportCsv($tipo);
            return;
        }

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

        $loanState = $this->getItemLoanState($id, $item);

        render_page('itens/show.php', [
            'item'            => $item,
            'tipo'            => $tipo?->toArray(),
            'cat'             => $cat?->toArray(),
            'linhaVinculada'  => $linhaVinculada,
            'nivelUsuario'    => $nivelUsuario,
            'emprestimosItem' => $loanState['emprestimos'],
            'listaUrl'        => $tipo ? 'itens/tipo/' . $tipo->id : null,
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
        $nivelUsuario      = AlmoxPermission::categoryLevel($tipo?->categoria_id ?? 0);
        $loanState           = $this->getItemLoanState($id, $item);

        render_page('itens/form.php', [
            'modo'              => 'editar',
            'item'              => $item,
            'erro'              => null,
            'tiposPorCategoria' => $tiposPorCategoria,
            'linhasDisponiveis' => $this->linhasDisponiveisParaSelect($id),
            'listaUrl'          => $tipo ? 'itens/tipo/' . $tipo->id : null,
            'tipoFixo'          => $tipo,
            'nivelUsuario'      => $nivelUsuario,
            'emprestimosItem'   => $loanState['emprestimos'],
            'statusBloqueado'   => $loanState['locked_status'],
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
            $itemForm = array_merge(['id' => $id], $data);
            render_page('itens/form.php', array_merge(
                $this->editFormContext($id, $itemForm, $tipoFixo),
                [
                    'modo' => 'editar',
                    'erro' => 'Tipo, descrição e status são obrigatórios.',
                    'item' => $itemForm,
                ]
            ));
            return;
        }

        if ($itemAtual && $tipoFixo) {
            $nivel = AlmoxPermission::categoryLevel($tipoFixo->categoria_id);
            if ($nivel < 3) {
                $data['status'] = $itemAtual->status;
            } else {
                $loanState = $this->getItemLoanState($id, array_merge($itemAtual->toArray(), $data));
                if ($loanState['locked_status'] !== null) {
                    $data['status'] = $loanState['locked_status'];
                } elseif (!array_key_exists($data['status'], ItemService::manualStatusOptions())) {
                    $data['status'] = $itemAtual->status;
                }
            }
        }

        try {
            $this->itemService->update($id, $data);
            redirect('itens/' . $id);
        } catch (\RuntimeException $e) {
            $itemForm = array_merge(['id' => $id], $data);
            render_page('itens/form.php', array_merge(
                $this->editFormContext($id, $itemForm, $tipoFixo),
                [
                    'modo' => 'editar',
                    'erro' => $e->getMessage(),
                    'item' => $itemForm,
                ]
            ));
        }
    }

    public function destroy(int $id): void
    {
        \Security\Auth::requireAuth();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();

        $item = Item::find($id);
        if (!$item) {
            redirect('itens');
            return;
        }

        $tipo = TipoItem::find($item->tipo_item_id);
        AlmoxPermission::requireCategory($tipo?->categoria_id ?? 0, 2);

        $listaUrl = $tipo ? 'itens/tipo/' . $tipo->id : 'itens';

        try {
            $this->itemService->delete($id);
            $_SESSION['flash_success'] = 'Item excluído com sucesso.';
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        redirect($listaUrl);
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

    /** GET ?export=1 — exporta os itens filtrados como CSV. */
    private function exportCsv(TipoItem $tipo): void
    {
        \Security\Auth::requireAuth();
        AlmoxPermission::requireCategory($tipo->categoria_id, 1);

        $listaUrl = 'itens/tipo/' . $tipo->id;
        $cols     = ItemService::buildListTableCols($tipo);
        $lt       = new \Support\ListTable(base_url($listaUrl), $cols, 'itens_tipo_' . $tipo->id);
        $lt->readRequest('i.descricao');

        $rows = $this->itemService->findAllForExport($tipo, $lt);

        $exportCols = [];
        foreach ($cols as $key => $col) {
            if ($key === '_acoes') {
                continue;
            }
            $exportCols[$key] = $col['label'];
        }

        $statusLabels = ItemService::statusLabels();
        $dateCols     = ['data_contratacao', 'data_vencimento'];

        header('Content-Type: text/csv; charset=UTF-8');
        $safeName = preg_replace('/[^a-z0-9_-]+/i', '_', $tipo->nome) ?: 'itens';
        header('Content-Disposition: attachment; filename="' . $safeName . '.csv"');
        echo "\xEF\xBB\xBF";

        $f = fopen('php://output', 'w');
        fputcsv($f, array_values($exportCols), ';');

        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($exportCols) as $col) {
                $val = $row[$col] ?? '';
                if ($col === 'status') {
                    $val = $statusLabels[(string)$val] ?? (string)$val;
                } elseif ($col === 'custo_mensal' && $val !== null && $val !== '') {
                    $val = 'R$ ' . number_format((float)$val, 2, ',', '.');
                } elseif (in_array($col, $dateCols, true)) {
                    $val = format_date_br($val, '');
                } elseif ($col === 'status_linha' && $val !== null && $val !== '') {
                    $labels = \Models\ItemLinhaTelefonica::statusLinhaLabels();
                    $val = $labels[(string)$val] ?? (string)$val;
                } else {
                    $val = (string)($val ?? '');
                }
                $line[] = $val;
            }
            fputcsv($f, $line, ';');
        }
        fclose($f);
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

    /** Variáveis compartilhadas do formulário de edição. */
    private function editFormContext(int $id, array $item, ?TipoItem $tipoFixo): array
    {
        $loanState = $this->getItemLoanState($id, $item);
        return [
            'tiposPorCategoria' => $this->tiposAgrupadosPorCategoria(2),
            'linhasDisponiveis' => $this->linhasDisponiveisParaSelect($id),
            'listaUrl'          => $tipoFixo ? 'itens/tipo/' . $tipoFixo->id : null,
            'tipoFixo'          => $tipoFixo,
            'nivelUsuario'      => AlmoxPermission::categoryLevel($tipoFixo?->categoria_id ?? 0),
            'emprestimosItem'   => $loanState['emprestimos'],
            'statusBloqueado'   => $loanState['locked_status'],
        ];
    }

    /**
     * Empréstimos/reservas abertas e status bloqueado pelo fluxo de empréstimo.
     *
     * @return array{emprestimos: array, qtd_ativo: int, qtd_reservado: int, locked_status: ?string}
     */
    private function getItemLoanState(int $itemId, array $item): array
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare("
            SELECT e.id, e.status, e.colaborador_nome, e.colaborador_codpessoa,
                   e.quantidade, e.data_entrega, e.data_prevista_devolucao, e.observacao
            FROM   dbo.emprestimos e
            WHERE  e.item_id = :id
              AND  e.status IN ('ativo', 'reservado')
            ORDER BY e.status ASC, e.data_entrega DESC
        ");
        $stmt->execute([':id' => $itemId]);
        $emprestimos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $qtdAtivo = 0;
        $qtdReservado = 0;
        foreach ($emprestimos as $emp) {
            if ($emp['status'] === 'ativo') {
                $qtdAtivo += (int)$emp['quantidade'];
            } else {
                $qtdReservado += (int)$emp['quantidade'];
            }
        }

        $qtdTotal = max(1, (int)($item['quantidade_total'] ?? 1));
        $lockedStatus = null;
        if ($qtdAtivo > 0 && $qtdAtivo >= $qtdTotal) {
            $lockedStatus = 'em_uso';
        } elseif ($qtdReservado > 0 && $qtdAtivo === 0) {
            $lockedStatus = 'reservado';
        }

        return [
            'emprestimos'    => $emprestimos,
            'qtd_ativo'      => $qtdAtivo,
            'qtd_reservado'  => $qtdReservado,
            'locked_status'  => $lockedStatus,
        ];
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


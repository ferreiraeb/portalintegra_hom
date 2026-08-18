<?php
namespace Services;

use Database\Connection;
use Models\Item;
use Models\TipoItem;
use Models\ItemLinhaTelefonica;
use Models\ItemEquipamentoTi;
use Models\ItemVeiculo;
use Models\ItemCartao;
use PDO;

/**
 * Cria e edita itens garantindo que `itens` e a tabela de detalhe
 * sejam gravadas atomicamente dentro de uma única transação.
 */
class ItemService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @var array<string, class-string> */
    private const DETALHE_MODEL_MAP = [
        'item_linha_telefonica' => ItemLinhaTelefonica::class,
        'item_equipamento_ti'   => ItemEquipamentoTi::class,
        'item_veiculo'          => ItemVeiculo::class,
        'item_cartao'           => ItemCartao::class,
    ];

    /**
     * Rótulos das colunas de detalhe para listagem do tipo informado.
     * @return array<string, string> campo => rótulo
     */
    public static function listColumnsForTipo(TipoItem $tipo): array
    {
        if (!$tipo->is_determinado || empty($tipo->tabela_detalhe)) {
            return [];
        }
        $modelClass = self::DETALHE_MODEL_MAP[$tipo->tabela_detalhe] ?? null;
        if (!$modelClass || !method_exists($modelClass, 'listColumns')) {
            return [];
        }
        return $modelClass::listColumns();
    }

    /**
     * Campos identificadores do tipo para busca no autocomplete de empréstimos.
     * @return array<string, string> campo => rótulo
     */
    public static function searchColumnsForTipo(TipoItem $tipo): array
    {
        if (!$tipo->is_determinado || empty($tipo->tabela_detalhe)) {
            return [];
        }
        $modelClass = self::DETALHE_MODEL_MAP[$tipo->tabela_detalhe] ?? null;
        if (!$modelClass || !method_exists($modelClass, 'searchColumns')) {
            return [];
        }
        return $modelClass::searchColumns();
    }

    /**
     * Itens disponíveis para empréstimo, filtrados por descrição e atributos únicos.
     *
     * @return array<int, array{id: int, label: string}>
     */
    public function findForAutocomplete(TipoItem $tipo, string $q, int $limit = 20): array
    {
        $searchCols = self::searchColumnsForTipo($tipo);
        $join = '';
        $selectExtra = '';

        if ($searchCols) {
            $join = "LEFT JOIN dbo.{$tipo->tabela_detalhe} d ON d.item_id = i.id";
            $selectExtra = ', ' . implode(', ', array_map(
                static fn(string $col) => "d.{$col}",
                array_keys($searchCols)
            ));
        }

        $params = [$tipo->id];
        $searchSql = '';
        if (mb_strlen($q) >= 3) {
            $conditions = ['i.descricao LIKE ?'];
            $params[] = '%' . $q . '%';
            foreach (array_keys($searchCols) as $col) {
                $conditions[] = "d.{$col} LIKE ?";
                $params[] = '%' . $q . '%';
            }
            $searchSql = 'AND (' . implode(' OR ', $conditions) . ')';
        }

        $stmt = $this->pdo->prepare("
            SELECT TOP {$limit} i.id, i.descricao {$selectExtra}
            FROM dbo.itens i
            {$join}
            WHERE i.tipo_item_id = ?
              AND i.status NOT IN ('bloqueado','baixado','extraviado','reservado')
              AND (
                  SELECT COALESCE(SUM(e.quantidade), 0)
                  FROM dbo.emprestimos e
                  WHERE e.item_id = i.id AND e.status = 'ativo'
              ) < i.quantidade_total
              {$searchSql}
            ORDER BY i.descricao
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $label = (string)$row['descricao'];
            $extras = [];
            foreach ($searchCols as $field => $fieldLabel) {
                $value = trim((string)($row[$field] ?? ''));
                if ($value !== '') {
                    $extras[] = $fieldLabel . ': ' . $value;
                }
            }
            if ($extras) {
                $label .= ' (' . implode(', ', $extras) . ')';
            }
            $result[] = [
                'id'    => (int)$row['id'],
                'label' => $label,
            ];
        }

        return $result;
    }

    /** Rótulos de todos os status de item. */
    public static function statusLabels(): array
    {
        return [
            'disponivel' => 'Disponível',
            'em_uso'     => 'Em uso',
            'reservado'  => 'Reservado',
            'bloqueado'  => 'Bloqueado',
            'baixado'    => 'Baixado',
            'extraviado' => 'Extraviado',
        ];
    }

    /** Status que podem ser definidos manualmente (em_uso é controlado pelos empréstimos). */
    public static function manualStatusOptions(): array
    {
        return [
            'disponivel' => 'Disponível',
            'reservado'  => 'Reservado',
            'bloqueado'  => 'Bloqueado',
            'baixado'    => 'Baixado',
            'extraviado' => 'Extraviado',
        ];
    }

    /** Opções do filtro select de status na listagem. */
    public static function statusFilterOptions(): array
    {
        return ['' => 'Todos'] + self::statusLabels();
    }

    /**
     * Definição de colunas para ListTable na listagem por tipo.
     */
    public static function buildListTableCols(TipoItem $tipo): array
    {
        $cols = [
            'descricao' => [
                'label'    => 'Descrição',
                'sortable' => true,
                'filter'   => 'text',
                'param'    => 'f_descricao',
                'sql_col'  => 'i.descricao',
                'th_class' => 'col-itens-descricao',
            ],
            'status' => [
                'label'    => 'Status',
                'sortable' => true,
                'filter'   => 'select',
                'param'    => 'f_status',
                'sql_col'  => 'i.status',
                'options'  => self::statusFilterOptions(),
            ],
        ];

        if ($tipo->is_determinado) {
            $cols['colaborador_nome'] = [
                'label'    => 'Colaborador',
                'sortable' => true,
                'filter'   => 'text',
                'param'    => 'f_colaborador',
                'sql_col'  => self::emprestimoAtivoSubquery('colaborador_nome'),
            ];
        }

        foreach (self::listColumnsForTipo($tipo) as $campo => $label) {
            $colDef = [
                'label'    => $label,
                'sortable' => true,
                'param'    => 'f_' . $campo,
                'sql_col'  => self::filterSqlCol($campo, true),
            ];
            if ($campo === 'status_linha') {
                $colDef['filter']  = 'select';
                $colDef['options'] = ['' => 'Todos'] + ItemLinhaTelefonica::statusLinhaLabels();
            } else {
                $colDef['filter'] = 'text';
            }
            $cols[$campo] = $colDef;
        }

        if (!$tipo->is_determinado) {
            $cols['quantidade_total'] = [
                'label'    => 'Qtd total',
                'sortable' => true,
                'filter'   => 'text',
                'param'    => 'f_qtd',
                'sql_col'  => self::filterSqlCol('quantidade_total', false),
            ];
        }

        $cols['_acoes'] = [
            'label'    => 'Ações',
            'sortable' => false,
            'filter'   => null,
            'th_class' => 'text-right',
        ];

        return $cols;
    }

    /** Subconsulta do empréstimo ativo (item rastreável emprestado). */
    private static function emprestimoAtivoSubquery(string $field): string
    {
        return "(SELECT TOP 1 e.{$field}
                 FROM dbo.emprestimos e
                 WHERE e.item_id = i.id AND e.status = 'ativo'
                 ORDER BY e.data_entrega DESC)";
    }

    private static function filterSqlCol(string $campo, bool $isDetail): string
    {
        $col = ($isDetail ? 'd.' : 'i.') . $campo;
        if (in_array($campo, ['custo_mensal', 'ano', 'quantidade_total'], true)) {
            return "CAST({$col} AS NVARCHAR(30))";
        }
        if (in_array($campo, ['data_contratacao', 'data_vencimento'], true)) {
            return "CONVERT(NVARCHAR(10), {$col}, 23)";
        }
        return $col;
    }

    /**
     * Busca itens paginados de um tipo (com filtros e ordenação via ListTable).
     * @return array{rows: array, total: int, from: int, to: int}
     */
    public function findForListing(TipoItem $tipo, \Support\ListTable $lt): array
    {
        $detailCols = self::listColumnsForTipo($tipo);
        $join       = '';
        $selectExtra = '';

        if ($tipo->is_determinado && !empty($tipo->tabela_detalhe) && $detailCols) {
            $join = "LEFT JOIN dbo.{$tipo->tabela_detalhe} d ON d.item_id = i.id";
            $selectExtra = ', ' . implode(', ', array_map(
                static fn(string $col) => "d.{$col}",
                array_keys($detailCols)
            ));
        }

        if ($tipo->is_determinado) {
            $selectExtra .= ', ' . self::emprestimoAtivoSubquery('colaborador_nome') . ' AS colaborador_nome'
                          . ', ' . self::emprestimoAtivoSubquery('colaborador_codpessoa') . ' AS colaborador_codpessoa';
        }

        $w = $lt->buildWhere(['i.tipo_item_id = :tipo' => [':tipo' => $tipo->id]]);

        $sort    = $lt->getSort() ?: 'i.descricao';
        $dir     = strtoupper($lt->getDir());
        $offset  = ($lt->getPage() - 1) * $lt->getPerPage();
        $perPage = $lt->getPerPage();
        $fromSql = "FROM dbo.itens i {$join}";

        $sqlCount = "SELECT COUNT(*) {$fromSql} {$w['sql']}";
        $stmt = $this->pdo->prepare($sqlCount);
        foreach ($w['binds'] as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT i.id, i.descricao, i.status, i.quantidade_total,
                       (SELECT COALESCE(SUM(e.quantidade), 0)
                        FROM dbo.emprestimos e
                        WHERE e.item_id = i.id AND e.status = 'ativo') AS quantidade_em_uso
                       {$selectExtra}
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

        return [
            'rows'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'from'  => $total > 0 ? ($offset + 1) : 0,
            'to'    => min($total, $offset + $perPage),
        ];
    }

    /**
     * Busca todos os itens de um tipo (filtros e ordenação via ListTable, sem paginação).
     * @return array<int, array<string, mixed>>
     */
    public function findAllForExport(TipoItem $tipo, \Support\ListTable $lt): array
    {
        $detailCols = self::listColumnsForTipo($tipo);
        $join       = '';
        $selectExtra = '';

        if ($tipo->is_determinado && !empty($tipo->tabela_detalhe) && $detailCols) {
            $join = "LEFT JOIN dbo.{$tipo->tabela_detalhe} d ON d.item_id = i.id";
            $selectExtra = ', ' . implode(', ', array_map(
                static fn(string $col) => "d.{$col}",
                array_keys($detailCols)
            ));
        }

        if ($tipo->is_determinado) {
            $selectExtra .= ', ' . self::emprestimoAtivoSubquery('colaborador_nome') . ' AS colaborador_nome'
                          . ', ' . self::emprestimoAtivoSubquery('colaborador_codpessoa') . ' AS colaborador_codpessoa';
        }

        $w = $lt->buildWhere(['i.tipo_item_id = :tipo' => [':tipo' => $tipo->id]]);

        $sort    = $lt->getSort() ?: 'i.descricao';
        $dir     = strtoupper($lt->getDir());
        $fromSql = "FROM dbo.itens i {$join}";

        $sql = "SELECT i.id, i.descricao, i.status, i.quantidade_total
                       {$selectExtra}
                {$fromSql}
                {$w['sql']}
                ORDER BY {$sort} {$dir}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($w['binds'] as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private const ITENS_FIELDS = [
        'tipo_item_id', 'descricao', 'status', 'quantidade_total',
        'localizacao', 'observacao', 'created_by', 'updated_by',
    ];

    /**
     * Cria um item e, se o tipo for determinado com tabela de detalhe,
     * insere também o registro de detalhe — tudo numa única transação.
     *
     * @param array $data Campos de `itens` + campos da tabela de detalhe, flat.
     * @return int ID do item criado.
     * @throws \RuntimeException Erro de negócio (tipo não encontrado, etc.)
     * @throws \Exception        Erro de infra (falha de banco).
     */
    public function create(array $data): int
    {
        $this->pdo->beginTransaction();
        try {
            // 1. Separa campos de `itens`
            $itemData = $this->extractItemFields($data);

            // 2. Insere em `itens`
            $item = (new Item())->fill($itemData);
            $itemId = $item->save();

            // 3. Processa tabela de detalhe
            $this->upsertDetalhe($itemId, $data, insert: true);

            $this->pdo->commit();
            return $itemId;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atualiza um item e seu registro de detalhe atomicamente.
     *
     * @throws \RuntimeException Item não encontrado.
     * @throws \Exception        Falha de banco.
     */
    public function update(int $itemId, array $data): void
    {
        $this->pdo->beginTransaction();
        try {
            $item = Item::find($itemId);
            if (!$item) {
                throw new \RuntimeException("Item #{$itemId} não encontrado.");
            }

            $itemData = $this->extractItemFields($data);
            $itemData['updated_at'] = date('Y-m-d H:i:s');
            $item->fill($itemData);
            $item->id = $itemId; // garante UPDATE
            $item->save();

            $this->upsertDetalhe($itemId, $data, insert: false);

            $this->pdo->commit();

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Exclui permanentemente um item e seu registro de detalhe.
     *
     * @throws \RuntimeException Item não encontrado ou bloqueado por vínculos.
     */
    public function delete(int $itemId): void
    {
        $item = Item::find($itemId);
        if (!$item) {
            throw new \RuntimeException("Item #{$itemId} não encontrado.");
        }

        $st = $this->pdo->prepare("
            SELECT COUNT(*) FROM dbo.emprestimos
            WHERE item_id = ? AND status IN ('ativo', 'reservado')
        ");
        $st->execute([$itemId]);
        if ((int)$st->fetchColumn() > 0) {
            throw new \RuntimeException('Não é possível excluir: existem empréstimos ou reservas ativas para este item.');
        }

        $st = $this->pdo->prepare('SELECT COUNT(*) FROM dbo.emprestimos WHERE item_id = ?');
        $st->execute([$itemId]);
        if ((int)$st->fetchColumn() > 0) {
            throw new \RuntimeException('Não é possível excluir: existem empréstimos vinculados a este item.');
        }

        $st = $this->pdo->prepare('SELECT COUNT(*) FROM dbo.item_equipamento_ti WHERE linha_item_id = ?');
        $st->execute([$itemId]);
        if ((int)$st->fetchColumn() > 0) {
            throw new \RuntimeException('Não é possível excluir: esta linha está vinculada a um equipamento. Desvincule antes de excluir.');
        }

        $tipo = TipoItem::find($item->tipo_item_id);

        $this->pdo->beginTransaction();
        try {
            if ($tipo && $tipo->is_determinado && !empty($tipo->tabela_detalhe)) {
                $this->pdo->prepare("DELETE FROM dbo.{$tipo->tabela_detalhe} WHERE item_id = ?")
                    ->execute([$itemId]);
            }

            $item->delete();
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Retorna array mesclado com dados de `itens` + tabela de detalhe.
     * Retorna apenas os dados de `itens` se o tipo não tiver detalhe.
     *
     * @throws \RuntimeException Item não encontrado.
     */
    public function getWithDetail(int $itemId): array
    {
        $item = Item::find($itemId);
        if (!$item) {
            throw new \RuntimeException("Item #{$itemId} não encontrado.");
        }

        $result = $item->toArray();

        $tipo = TipoItem::find($item->tipo_item_id);
        if (!$tipo) {
            return $result;
        }

        // Sempre expõe campos do tipo para que o formulário de edição
        // saiba qual bloco de detalhe mostrar.
        $result['tabela_detalhe']  = $tipo->tabela_detalhe;
        $result['is_determinado']  = (int)$tipo->is_determinado;

        if (!$tipo->is_determinado || empty($tipo->tabela_detalhe)) {
            return $result;
        }

        $modelClass = self::DETALHE_MODEL_MAP[$tipo->tabela_detalhe] ?? null;
        if (!$modelClass) {
            return $result;
        }

        $rows = $modelClass::findAll(['item_id' => $itemId]);
        if (!empty($rows)) {
            $detalhe = $rows[0]->toArray();
            unset($detalhe['id'], $detalhe['item_id']); // evita sobrescrever PK
            $result = array_merge($result, $detalhe);
        }

        return $result;
    }

    // Helpers
    private function extractItemFields(array $data): array
    {
        return array_intersect_key($data, array_flip(self::ITENS_FIELDS));
    }

    /**
     * INSERT ou UPDATE do registro de detalhe, dependendo do parâmetro $insert
     * e de já existir linha para o item_id.
     *
     * @param bool $insert true = preferir INSERT; false = verificar antes.
     */
    private function upsertDetalhe(int $itemId, array $data, bool $insert): void
    {
        $tipo = TipoItem::find($data['tipo_item_id'] ?? 0);

        // Se tipo não encontrado, não há detalhe para salvar.
        if (!$tipo || !$tipo->is_determinado || empty($tipo->tabela_detalhe)) {
            return;
        }

        $modelClass = self::DETALHE_MODEL_MAP[$tipo->tabela_detalhe] ?? null;
        if (!$modelClass) {
            return;
        }

        // Campos que NÃO pertencem a `itens` são candidatos ao detalhe
        $detalheData = array_diff_key($data, array_flip(self::ITENS_FIELDS));
        $detalheData['item_id'] = $itemId;

        if (!$insert) {
            // UPDATE: verifica se já existe
            $existing = $modelClass::findAll(['item_id' => $itemId]);
            if (!empty($existing)) {
                $obj = $existing[0];
                $obj->fill($detalheData);
                $obj->save();
                return;
            }
        }

        // INSERT
        (new $modelClass())->fill($detalheData)->save();
    }
}


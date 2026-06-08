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


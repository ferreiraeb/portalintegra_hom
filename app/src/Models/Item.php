<?php
namespace Models;
class Item extends BaseModel {
    protected static string $table = 'itens';
    protected static array $fillable = [
        'tipo_item_id','descricao','status','quantidade_total',
        'localizacao','observacao','created_by','updated_by','updated_at',
    ];
    public ?int $id = null;
    public int $tipo_item_id = 0;
    public string $descricao = '';
    public string $status = 'disponivel';
    public int $quantidade_total = 1;
    public ?string $localizacao = null;
    public ?string $observacao = null;
    public ?int $created_by = null;
    public ?int $updated_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /** @return TipoItem|null */
    public function tipoItem(): ?TipoItem {
        return TipoItem::find($this->tipo_item_id);
    }
}


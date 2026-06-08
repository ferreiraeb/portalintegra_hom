<?php
namespace Models;
class ItemCartao extends BaseModel {
    protected static string $table = 'item_cartao';
    protected static array $fillable = ['item_id','tipo_cartao','numero_cartao','descricao','bandeira','fornecedor'];
    public ?int $id = null;
    public int $item_id = 0;
    public ?string $tipo_cartao = null;     // Onfly | Combustível
    public ?string $numero_cartao = null;
    public ?string $descricao = null;
    public ?string $bandeira = null;
    public ?string $fornecedor = null;
}


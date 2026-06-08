<?php
namespace Models;

class ItemLinhaTelefonica extends BaseModel {
    protected static string $table = 'item_linha_telefonica';
    protected static array $fillable = [
        'item_id','numero_linha','numero_chip','numero_anterior',
        'operadora','tipo_chip','status_linha','contrato','plano','custo_mensal',
    ];
    public ?int $id = null;
    public int $item_id = 0;
    public ?string $numero_linha = null;
    public ?string $numero_chip = null;
    public ?string $numero_anterior = null;
    public ?string $operadora = null;
    public ?string $tipo_chip = null;       // SIM | eSIM
    public string $status_linha = 'ativo'; // ativo | inativo | cancelado
    public ?string $contrato = null;
    public ?string $plano = null;
    public ?float $custo_mensal = null;
}

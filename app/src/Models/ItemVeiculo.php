<?php
namespace Models;
class ItemVeiculo extends BaseModel {
    protected static string $table = 'item_veiculo';
    protected static array $fillable = [
        'item_id','placa','marca','modelo','ano','cor','renavam',
        'proprietario','data_contratacao','data_vencimento',
    ];
    public ?int $id = null;
    public int $item_id = 0;
    public ?string $placa = null;
    public ?string $marca = null;           // renomeado de fabricante
    public ?string $modelo = null;
    public ?int $ano = null;
    public ?string $cor = null;
    public ?string $renavam = null;
    public ?string $proprietario = null;    // Stellants/Flua | Valence | Barros e Braga | MM | Localiza
    public ?string $data_contratacao = null;
    public ?string $data_vencimento = null;
}


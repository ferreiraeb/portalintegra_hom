<?php
namespace Models;

/**
 * O tipo de equipamento (celular, notebook, monitor…) é determinado por
 * itens.tipo_item_id → tipos_item
 *
 * imei e linha_item_id: preenchidos apenas para celular/tablet.
 * mac_address: preenchido para equipamentos de rede.
 */
class ItemEquipamentoTi extends BaseModel {
    protected static string $table = 'item_equipamento_ti';
    protected static array $fillable = [
        'item_id','numero_serie','etiqueta','marca','modelo',
        'proprietario','imei','linha_item_id','mac_address',
    ];
    public ?int $id = null;
    public int $item_id = 0;
    public ?string $numero_serie = null;
    public ?string $etiqueta = null;
    public ?string $marca = null;
    public ?string $modelo = null;
    public ?string $proprietario = null;
    public ?string $imei = null;
    public ?int $linha_item_id = null;
    public ?string $mac_address = null;

    /**
     * Retorna o Item do tipo Linha Telefônica associado a este aparelho, se houver.
     * Aplicável apenas a celular/tablet.
     */
    public function linhaItem(): ?Item {
        return $this->linha_item_id ? Item::find($this->linha_item_id) : null;
    }

    public static function listColumns(): array
    {
        return [
            'numero_serie'  => 'Nº de série',
            'etiqueta'      => 'Etiqueta',
            'marca'         => 'Marca',
            'modelo'        => 'Modelo',
            'proprietario'  => 'Proprietário',
            'imei'          => 'IMEI',
            'mac_address'   => 'MAC',
        ];
    }

    /** Campos identificadores usados na busca de itens (autocomplete). */
    public static function searchColumns(): array
    {
        return [
            'numero_serie' => 'Nº de série',
            'etiqueta'     => 'Etiqueta',
            'imei'         => 'IMEI',
            'mac_address'  => 'MAC',
        ];
    }
}


<?php
namespace Models;

class Emprestimo extends BaseModel {
    protected static string $table = 'emprestimos';
    protected static array $fillable = [
        'item_id','quantidade','colaborador_codpessoa','colaborador_nome',
        'criado_por','data_entrega','data_prevista_devolucao','data_devolucao',
        'status','observacao',
    ];
    public ?int $id = null;
    public int $item_id = 0;
    public int $quantidade = 1;
    public string $colaborador_codpessoa = '';
    public string $colaborador_nome = '';
    public ?int $criado_por = null;
    public string $data_entrega = '';
    public ?string $data_prevista_devolucao = null;
    public ?string $data_devolucao = null;
    public int $quantidade_devolvida = 0;
    public string $status = 'ativo';
    public ?string $observacao = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /** @return Item|null */
    public function item(): ?Item {
        return Item::find($this->item_id);
    }
}


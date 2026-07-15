<?php
namespace Models;
class TipoItem extends BaseModel {
    protected static string $table = 'tipos_item';
    protected static array $fillable = ['categoria_id','nome','descricao','is_determinado','tabela_detalhe','ativo'];
    public ?int $id = null;
    public int $categoria_id = 0;
    public string $nome = '';
    public ?string $descricao = null;
    public int $is_determinado = 1;
    public ?string $tabela_detalhe = null;
    public int $ativo = 1;
    public ?string $created_at = null;

    /** @return Categoria|null */
    public function categoria(): ?Categoria {
        return Categoria::find($this->categoria_id);
    }
}


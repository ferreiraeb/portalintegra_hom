<?php
namespace Models;
class Categoria extends BaseModel {
    protected static string $table = 'categorias';
    protected static array $fillable = ['nome','descricao','ativo'];
    public ?int $id = null;
    public string $nome = '';
    public ?string $descricao = null;
    public int $ativo = 1;
    public ?string $created_at = null;
    public ?string $updated_at = null;
}


<?php
namespace Models;

class TermoEmprestimo extends BaseModel {
    protected static string $table = 'termo_emprestimos';
    protected static array $fillable = ['termo_id','emprestimo_id'];
    public ?int $id = null;
    public int $termo_id = 0;
    public int $emprestimo_id = 0;
}


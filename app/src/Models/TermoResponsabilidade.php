<?php
namespace Models;
use Database\Connection;
use PDO;

class TermoResponsabilidade extends BaseModel {
    protected static string $table = 'termos_responsabilidade';
    protected static array $fillable = [
        'colaborador_codpessoa','colaborador_nome','status','motivo_cancelamento',
        'data_envio','data_assinatura','d4sign_uuid','documento_url','gerado_por',
    ];
    public ?int $id = null;
    public string $colaborador_codpessoa = '';
    public string $colaborador_nome = '';
    public string $status = 'pendente_envio';
    public ?string $motivo_cancelamento = null;
    public ?string $data_criacao = null;
    public ?string $data_envio = null;
    public ?string $data_assinatura = null;
    public ?string $d4sign_uuid = null;
    public ?string $documento_url = null;
    public ?int $gerado_por = null;

    /**
     * Retorna os Emprestimos vinculados a este termo via termo_emprestimos.
     *
     * @return \Models\Emprestimo[]
     */
    public function emprestimos(): array {
        if (!$this->id) return [];
        $pdo  = Connection::get();
        $stmt = $pdo->prepare(
            'SELECT e.* FROM dbo.emprestimos e
             INNER JOIN dbo.termo_emprestimos te ON te.emprestimo_id = e.id
             WHERE te.termo_id = ?'
        );
        $stmt->execute([$this->id]);
        return array_map(
            fn(array $row) => Emprestimo::fromRow($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}


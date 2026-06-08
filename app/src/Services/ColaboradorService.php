<?php
namespace Services;

use Database\OracleConnection;
use Database\Connection;
use PDO;

/**
 * Consulta colaboradores na view Oracle SIRH.VW_RH_COLABORADORES
 * e agrega informacoes de itens em posse (SQL Server).
 */
class ColaboradorService
{
    private PDO $sqlPdo;  // SQL Server (Portal_Integra)

    public function __construct(PDO $sqlPdo)
    {
        $this->sqlPdo = $sqlPdo;
    }

    /**
     * Busca colaboradores cujo nome ou CODPESSOA contenham $termo.
     * Retorna ate 20 resultados. Usado no autocomplete do formulario de emprestimo.
     *
     * @return array[] Cada item: CODPESSOA, NOMECOMPLETO (+ demais colunas da view).
     * @throws \RuntimeException Conexao Oracle nao configurada ou indisponivel.
     */
    public function search(string $termo): array
    {
        $pdo  = OracleConnection::get();
        $like = '%' . strtoupper(trim($termo)) . '%';
        $stmt = $pdo->prepare(
            "SELECT CODPESSOA, NOMECOMPLETO, CARGO, EMPRESA, UNIDADE, STATUS
             FROM SIRH.VW_RH_COLABORADORES
             WHERE STATUS = 'ATIVO'
               AND (UPPER(NOMECOMPLETO) LIKE :like OR UPPER(CODPESSOA) LIKE :like2)
             FETCH FIRST 20 ROWS ONLY"
        );
        $stmt->bindValue(':like',  $like);
        $stmt->bindValue(':like2', $like);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um colaborador especifico pelo CODPESSOA.
     * Retorna null se nao encontrado.
     *
     * @throws \RuntimeException Conexao Oracle nao configurada ou indisponivel.
     */
    public function findByCodpessoa(string $codpessoa): ?array
    {
        $pdo  = OracleConnection::get();
        $stmt = $pdo->prepare(
            "SELECT CODPESSOA, NOMECOMPLETO, CARGO, EMPRESA, UNIDADE,
                    CPF, SETOR, LIDER, STATUS, DATAADMISSAO
             FROM SIRH.VW_RH_COLABORADORES
             WHERE CODPESSOA = :codpessoa
               AND ROWNUM = 1"
        );
        $stmt->bindValue(':codpessoa', $codpessoa);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Retorna todos os empréstimos ativos do colaborador com dados completos
     * do item, tipo e categoria (query no SQL Server — Portal_Integra).
     *
     * @return array[]
     */
    public function getItensAtivos(string $codpessoa): array
    {
        $stmt = $this->sqlPdo->prepare(
            "SELECT
                e.id            AS emprestimo_id,
                e.item_id,
                e.quantidade,
                e.data_entrega,
                e.data_prevista_devolucao,
                e.created_at    AS emprestimo_criado_em,
                i.descricao     AS item_descricao,
                i.status        AS item_status,
                i.observacao    AS item_observacao,
                t.nome          AS tipo_nome,
                t.tabela_detalhe,
                t.is_determinado,
                c.nome          AS categoria_nome
             FROM dbo.emprestimos e
             INNER JOIN dbo.itens      i ON i.id = e.item_id
             INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
             INNER JOIN dbo.categorias c ON c.id = t.categoria_id
             WHERE e.colaborador_codpessoa = ?
               AND e.data_devolucao IS NULL
               AND e.status = 'ativo'
             ORDER BY c.nome, t.nome, e.data_entrega"
        );
        $stmt->execute([$codpessoa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


<?php
namespace Services;

use Models\Emprestimo;
use Models\Item;
use Models\TipoItem;
use PDO;

class EmprestimoService
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function emprestar(int $itemId, string $codpessoa, string $nome, array $dados): int
    {
        $item = Item::find($itemId);
        if (!$item) throw new \RuntimeException("Item #{$itemId} nao encontrado.");
        $this->validarDisponibilidade($item);
        $this->pdo->beginTransaction();
        try {
            $qtd = $this->resolveQuantidade($item, $dados);
            $emp = new Emprestimo();
            $emp->item_id = $itemId;
            $emp->colaborador_codpessoa = $codpessoa;
            $emp->colaborador_nome = $nome;
            $emp->status = 'ativo';
            $emp->quantidade = $qtd;
            $emp->data_entrega = $dados['data_entrega'] ?? date('Y-m-d');
            $emp->data_prevista_devolucao = $dados['data_prevista_devolucao'] ?? null;
            $emp->observacao = $dados['observacao'] ?? null;
            $emp->criado_por = isset($dados['criado_por']) ? (int)$dados['criado_por'] : null;
            $id = $emp->save();
            $emUso = $this->getQuantidadeEmUso($itemId);
            $novoStatus = ($emUso >= $item->quantidade_total) ? 'em_uso' : $item->status;
            $this->pdo->prepare('UPDATE dbo.itens SET status=?,updated_at=GETDATE() WHERE id=?')
                ->execute([$novoStatus, $itemId]);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function devolver(int $emprestimoId, ?string $observacao = null): void
    {
        $emp = Emprestimo::find($emprestimoId);
        if (!$emp) throw new \RuntimeException("Emprestimo #{$emprestimoId} nao encontrado.");
        if ($emp->status !== 'ativo') throw new \RuntimeException("Emprestimo #{$emprestimoId} nao esta ativo.");
        $this->pdo->beginTransaction();
        try {
            $emp->data_devolucao = date('Y-m-d');
            $emp->status = 'devolvido';
            if ($observacao !== null) $emp->observacao = $observacao;
            $emp->save();
            $item = Item::find($emp->item_id);
            if ($item) {
                $emUso = $this->getQuantidadeEmUso($item->id);
                $novoStatus = ($emUso > 0) ? 'em_uso' : (($item->status === 'em_uso') ? 'disponivel' : $item->status);
                $this->pdo->prepare('UPDATE dbo.itens SET status=?,updated_at=GETDATE() WHERE id=?')
                    ->execute([$novoStatus, $item->id]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function reservar(int $itemId, string $codpessoa, string $nome, array $dados): int
    {
        $item = Item::find($itemId);
        if (!$item) throw new \RuntimeException("Item #{$itemId} não encontrado.");
        if (in_array($item->status, ['bloqueado', 'baixado', 'extraviado'], true))
            throw new \RuntimeException("Item indisponível para reserva (status: {$item->status}).");

        $this->pdo->beginTransaction();
        try {
            $qtd = $this->resolveQuantidade($item, $dados);
            $emUso = $this->getQuantidadeEmUso($itemId);
            if ($emUso >= $item->quantidade_total)
                throw new \RuntimeException("Sem unidades disponíveis para reserva ({$emUso}/{$item->quantidade_total}).");

            $emp = new Emprestimo();
            $emp->item_id                 = $itemId;
            $emp->colaborador_codpessoa   = $codpessoa;
            $emp->colaborador_nome        = $nome;
            $emp->status                  = 'reservado';
            $emp->quantidade              = $qtd;
            $emp->data_entrega            = $dados['data_entrega'] ?? date('Y-m-d');
            $emp->data_prevista_devolucao = $dados['data_prevista_devolucao'] ?? null;
            $emp->observacao              = $dados['observacao'] ?? null;
            $emp->criado_por              = isset($dados['criado_por']) ? (int)$dados['criado_por'] : null;
            $id = $emp->save();

            // Marca item como reservado se ainda não estava em uso
            if ($item->status === 'disponivel') {
                $this->pdo->prepare('UPDATE dbo.itens SET status=?,updated_at=GETDATE() WHERE id=?')
                    ->execute(['reservado', $itemId]);
            }
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function ativarReserva(int $emprestimoId): void
    {
        $emp = Emprestimo::find($emprestimoId);
        if (!$emp) throw new \RuntimeException("Empréstimo #{$emprestimoId} não encontrado.");
        if ($emp->status !== 'reservado') throw new \RuntimeException("Registro não está com status reservado.");

        $this->pdo->beginTransaction();
        try {
            $emp->status       = 'ativo';
            $emp->data_entrega = date('Y-m-d');
            $emp->save();

            $item = Item::find($emp->item_id);
            if ($item) {
                $emUso = $this->getQuantidadeEmUso($item->id);
                $novoStatus = ($emUso >= $item->quantidade_total) ? 'em_uso' : 'em_uso';
                $this->pdo->prepare('UPDATE dbo.itens SET status=?,updated_at=GETDATE() WHERE id=?')
                    ->execute([$novoStatus, $item->id]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function cancelarReserva(int $emprestimoId): void
    {
        $emp = Emprestimo::find($emprestimoId);
        if (!$emp) throw new \RuntimeException("Empréstimo #{$emprestimoId} não encontrado.");
        if ($emp->status !== 'reservado') throw new \RuntimeException("Somente reservas podem ser canceladas.");

        $this->pdo->beginTransaction();
        try {
            $emp->status = 'cancelado';
            $emp->save();

            $item = Item::find($emp->item_id);
            if ($item) {
                // Se não há mais nenhum registro ativo ou reservado, volta para disponível
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(1) FROM dbo.emprestimos WHERE item_id = ? AND status IN ('ativo','reservado')"
                );
                $stmt->execute([$item->id]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $this->pdo->prepare('UPDATE dbo.itens SET status=?,updated_at=GETDATE() WHERE id=?')
                        ->execute(['disponivel', $item->id]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function itemDisponivelParaEmprestimo(int $itemId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT i.status, i.quantidade_total,
                   (SELECT COALESCE(SUM(e.quantidade), 0)
                    FROM dbo.emprestimos e
                    WHERE e.item_id = i.id AND e.status = 'ativo') AS em_uso
            FROM dbo.itens i
            WHERE i.id = ?
        ");
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        if (in_array($row['status'], ['bloqueado', 'baixado', 'extraviado', 'reservado'], true)) {
            return false;
        }
        return (int)$row['em_uso'] < (int)$row['quantidade_total'];
    }

    public function findAtivosByColaborador(string $codpessoa): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id AS emprestimo_id, e.item_id, e.quantidade, e.data_entrega,
                    e.data_prevista_devolucao, e.status AS emprestimo_status,
                    e.observacao, e.created_at,
                    i.descricao AS item_descricao, i.status AS item_status,
                    t.nome AS tipo_nome, t.tabela_detalhe, t.is_determinado
             FROM dbo.emprestimos e
             INNER JOIN dbo.itens i ON i.id = e.item_id
             INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
             WHERE e.colaborador_codpessoa = ?
               AND e.data_devolucao IS NULL
               AND e.status = 'ativo'
             ORDER BY e.created_at DESC"
        );
        $stmt->execute([$codpessoa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validarDisponibilidade(Item $item): void
    {
        if ($item->status === 'bloqueado')  throw new \RuntimeException("Item bloqueado.");
        if ($item->status === 'reservado')  throw new \RuntimeException("Item reservado.");
        if ($item->status === 'baixado')    throw new \RuntimeException("Item baixado do inventario.");
        if ($item->status === 'extraviado') throw new \RuntimeException("Item extraviado.");
        $emUso = $this->getQuantidadeEmUso($item->id);
        if ($emUso >= $item->quantidade_total)
            throw new \RuntimeException("Sem unidades disponiveis ({$emUso}/{$item->quantidade_total}).");
    }

    private function getQuantidadeEmUso(int $itemId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(quantidade), 0) FROM dbo.emprestimos WHERE item_id = ? AND status = 'ativo'"
        );
        $stmt->execute([$itemId]);
        return (int) $stmt->fetchColumn();
    }

    private function resolveQuantidade(Item $item, array $dados): int
    {
        $tipo = TipoItem::find($item->tipo_item_id);
        if ($tipo && $tipo->is_determinado) {
            return 1;
        }
        return max(1, (int)($dados['quantidade'] ?? 1));
    }
}


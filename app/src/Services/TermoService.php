<?php
namespace Services;
use Models\Emprestimo;
use Models\TermoEmprestimo;
use Models\TermoResponsabilidade;
use PDO;

// TODO: IMPLEMENTAR TERMOS
class TermoService
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /**
     * Cria um novo termo de responsabilidade para o colaborador,
     * vinculando os empréstimos informados.
     *
     * @param int[] $emprestimoIds
     * @throws \RuntimeException Nenhum empréstimo, empréstimo inexistente ou de outro colaborador.
     */
    public function gerar(string $codpessoa, string $nome, array $emprestimoIds, ?int $geradoPor = null): int
    {
        if (empty($emprestimoIds)) {
            throw new \RuntimeException("Nenhum emprestimo informado para gerar o termo.");
        }

        // Valida que todos os empréstimos existem e pertencem ao colaborador
        foreach ($emprestimoIds as $empId) {
            $emp = Emprestimo::find((int)$empId);
            if (!$emp) {
                throw new \RuntimeException("Emprestimo #{$empId} nao encontrado.");
            }
            if ($emp->colaborador_codpessoa !== $codpessoa) {
                throw new \RuntimeException(
                    "Emprestimo #{$empId} nao pertence ao colaborador {$codpessoa}."
                );
            }
        }

        $this->pdo->beginTransaction();
        try {
            $termo = new TermoResponsabilidade();
            $termo->colaborador_codpessoa = $codpessoa;
            $termo->colaborador_nome      = $nome;
            $termo->status                = 'pendente_envio';
            $termo->gerado_por            = $geradoPor;
            $termoId = $termo->save();

            foreach ($emprestimoIds as $empId) {
                $te = new TermoEmprestimo();
                $te->termo_id       = $termoId;
                $te->emprestimo_id  = (int)$empId;
                $te->save();
            }

            $this->pdo->commit();
            return $termoId;
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    /**
     * Ponto de integracao com o D4Sign — nao implementado.
     *
     * @throws \RuntimeException Termo nao encontrado ou nao esta pendente de envio.
     * @throws \LogicException   Integracao nao implementada.
     */
    public function enviar(int $termoId): void
    {
        $termo = TermoResponsabilidade::find($termoId);
        if (!$termo) throw new \RuntimeException("Termo #{$termoId} nao encontrado.");
        if ($termo->status !== 'pendente_envio') {
            throw new \RuntimeException(
                "Termo #{$termoId} nao pode ser enviado (status atual: {$termo->status})."
            );
        }
        throw new \LogicException(
            "Integracao D4Sign nao implementada. Implemente aqui a chamada HTTP " .
            "e atualize status para 'enviado', data_envio e d4sign_uuid."
        );
    }

    /**
     * Processa o webhook de assinatura do D4Sign — nao implementado.
     */
    public function processarWebhook(array $payload): void
    {
        throw new \LogicException(
            "Webhook D4Sign nao implementado. Payload esperado: uuid do documento, " .
            "data de assinatura e URL do PDF assinado. Atualizar status para " .
            "'assinado', data_assinatura e documento_url."
        );
    }

    /**
     * Cancela um termo que ainda nao foi assinado.
     *
     * @throws \RuntimeException Termo nao encontrado ou ja assinado.
     */
    public function cancelar(int $termoId, string $motivo): void
    {
        $termo = TermoResponsabilidade::find($termoId);
        if (!$termo) throw new \RuntimeException("Termo #{$termoId} nao encontrado.");
        if ($termo->status === 'assinado') {
            throw new \RuntimeException("Termo #{$termoId} ja foi assinado e nao pode ser cancelado.");
        }
        $termo->status              = 'cancelado';
        $termo->motivo_cancelamento = $motivo;
        $termo->save();
    }

    /**
     * Retorna todos os termos de um colaborador, do mais recente para o mais antigo.
     *
     * @return array[]
     */
    public function findByColaborador(string $codpessoa): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM dbo.termos_responsabilidade
             WHERE colaborador_codpessoa = ?
             ORDER BY data_criacao DESC"
        );
        $stmt->execute([$codpessoa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

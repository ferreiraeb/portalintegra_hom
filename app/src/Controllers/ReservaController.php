<?php
namespace Controllers;

use Database\Connection;
use Models\Emprestimo;
use PDO;

class ReservaController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::get();
    }

    public function index(): void
    {
        \Security\Auth::requireAuth();

        $filtroColab = trim($_GET['colaborador'] ?? '');
        $params = ['reservado'];
        $extra  = '';
        if ($filtroColab !== '') {
            $extra    = "AND (e.colaborador_nome LIKE ? OR e.colaborador_codpessoa LIKE ?)";
            $params[] = "%{$filtroColab}%";
            $params[] = "%{$filtroColab}%";
        }

        $stmt = $this->pdo->prepare("
            SELECT e.id, e.item_id, e.quantidade, e.colaborador_codpessoa, e.colaborador_nome,
                   e.data_entrega, e.data_prevista_devolucao, e.created_at,
                   i.descricao AS item_descricao,
                   t.nome AS tipo_nome
            FROM dbo.emprestimos e
            INNER JOIN dbo.itens i ON i.id = e.item_id
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
            WHERE e.status = ? {$extra}
            ORDER BY e.data_entrega ASC, e.created_at DESC
        ");
        $stmt->execute($params);
        $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        render_page('reservas/index.php', [
            'reservas'    => $reservas,
            'filtroColab' => $filtroColab,
        ]);
    }

    public function create(): void
    {
        \Security\Auth::requireAuth();
        render_page('reservas/form.php', [
            'modo'             => 'criar',
            'reserva'          => null,
            'erro'             => null,
            'itensDisponiveis' => $this->itensDisponiveis(),
        ]);
    }

    public function store(): void
    {
        \Security\Auth::requireAuth();
        check_csrf();

        $data = [
            'item_id'                 => (int)($_POST['item_id'] ?? 0),
            'colaborador_codpessoa'   => trim($_POST['colaborador_codpessoa'] ?? ''),
            'colaborador_nome'        => trim($_POST['colaborador_nome'] ?? ''),
            'quantidade'              => max(1, (int)($_POST['quantidade'] ?? 1)),
            'data_entrega'            => trim($_POST['data_entrega'] ?? ''),
            'data_prevista_devolucao' => trim($_POST['data_prevista_devolucao'] ?? '') ?: null,
            'observacao'              => trim($_POST['observacao'] ?? '') ?: null,
        ];

        if (!$data['item_id'] || !$data['colaborador_codpessoa'] || !$data['colaborador_nome'] || !$data['data_entrega']) {
            render_page('reservas/form.php', [
                'modo'             => 'criar',
                'reserva'          => $data,
                'erro'             => 'Item, colaborador e data são obrigatórios.',
                'itensDisponiveis' => $this->itensDisponiveis(),
            ]);
            return;
        }

        $emp = new Emprestimo();
        $emp->item_id                 = $data['item_id'];
        $emp->colaborador_codpessoa   = $data['colaborador_codpessoa'];
        $emp->colaborador_nome        = $data['colaborador_nome'];
        $emp->quantidade              = $data['quantidade'];
        $emp->data_entrega            = $data['data_entrega'];
        $emp->data_prevista_devolucao = $data['data_prevista_devolucao'];
        $emp->observacao              = $data['observacao'];
        $emp->status                  = 'reservado';
        $emp->criado_por              = $_SESSION['user']['id'] ?? null;
        $emp->save();

        redirect('reservas');
    }

    public function ativar(int $id): void
    {
        \Security\Auth::requireAuth();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();

        $emp = Emprestimo::find($id);
        if ($emp && $emp->status === 'reservado') {
            $emp->status       = 'ativo';
            $emp->data_entrega = date('Y-m-d');
            $emp->save();
        }
        redirect('reservas');
    }

    // Helpers
    private function itensDisponiveis(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.id, i.descricao, i.quantidade_total,
                   t.nome AS tipo_nome
            FROM dbo.itens i
            INNER JOIN dbo.tipos_item t ON t.id = i.tipo_item_id
            WHERE i.status IN ('disponivel', 'em_uso', 'reservado')
            ORDER BY t.nome, i.descricao
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

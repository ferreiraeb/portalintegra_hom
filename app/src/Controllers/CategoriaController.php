<?php
namespace Controllers;

use Database\Connection;
use Models\Categoria;
use Security\AlmoxPermission;

class CategoriaController
{
    private function requireManage(): void
    {
        if (\Security\Permission::level('users.manage') >= 2) return;
        \Security\Permission::require('almoxarifado.manage', 2);
    }

    /** @return array{disponiveis: array<int, array>, naCategoria: array<int, array>} */
    private function loadTiposDualList(int $categoriaId): array
    {
        $pdo = Connection::get();
        $st  = $pdo->query(
            'SELECT t.id, t.nome, t.ativo, t.categoria_id, c.nome AS nome_categoria
             FROM dbo.tipos_item t
             INNER JOIN dbo.categorias c ON c.id = t.categoria_id
             ORDER BY t.nome'
        );
        $disponiveis = [];
        $naCategoria = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if ((int)$row['categoria_id'] === $categoriaId) {
                $naCategoria[] = $row;
            } else {
                $disponiveis[] = $row;
            }
        }

        return ['disponiveis' => $disponiveis, 'naCategoria' => $naCategoria];
    }

    private function syncTiposCategoria(int $categoriaId, array $selectedIds, array $origemPorId): void
    {
        $pdo = Connection::get();

        $st = $pdo->prepare('SELECT id FROM dbo.tipos_item WHERE categoria_id = ?');
        $st->execute([$categoriaId]);
        $currentIds = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));

        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds))));

        if ($selectedIds) {
            $ph = implode(',', array_fill(0, count($selectedIds), '?'));
            $pdo->prepare("UPDATE dbo.tipos_item SET categoria_id = ? WHERE id IN ({$ph})")
                ->execute(array_merge([$categoriaId], $selectedIds));
        }

        foreach (array_diff($currentIds, $selectedIds) as $tipoId) {
            $orig = (int)($origemPorId[$tipoId] ?? 0);
            if ($orig > 0 && $orig !== $categoriaId) {
                $pdo->prepare('UPDATE dbo.tipos_item SET categoria_id = ? WHERE id = ?')
                    ->execute([$orig, $tipoId]);
            }
        }
    }

    public function index(): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();

        $pdo = Connection::get();

        $cols = [
            'nome'      => ['label' => 'Nome', 'sortable' => true, 'filter' => 'text', 'param' => 'f_nome', 'sql_col' => 'c.nome'],
            'descricao' => ['label' => 'Descrição', 'sortable' => true, 'filter' => 'text', 'param' => 'f_descricao', 'sql_col' => 'c.descricao'],
            'ativo'     => ['label' => 'Status', 'sortable' => true, 'filter' => 'select', 'param' => 'f_status', 'sql_col' => 'c.ativo',
                           'options' => ['' => 'Todos', '1' => 'Ativa', '0' => 'Inativa']],
            '_acoes'    => ['label' => 'Ações', 'sortable' => false, 'filter' => null, 'th_class' => 'text-right'],
        ];

        $lt = new \Support\ListTable(base_url('categorias'), $cols, 'categorias');
        $lt->readRequest('c.nome');

        $w       = $lt->buildWhere();
        $sort    = $lt->getSort() ?: 'c.nome';
        $dir     = strtoupper($lt->getDir());
        $offset  = ($lt->getPage() - 1) * $lt->getPerPage();
        $perPage = $lt->getPerPage();

        $sqlCount = "SELECT COUNT(*) FROM dbo.categorias c {$w['sql']}";
        $st = $pdo->prepare($sqlCount);
        foreach ($w['binds'] as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $total = (int)$st->fetchColumn();

        $sql = "SELECT c.id, c.nome, c.descricao, c.ativo
                FROM dbo.categorias c
                {$w['sql']}
                ORDER BY {$sort} {$dir}
                OFFSET :off ROWS FETCH NEXT :lim ROWS ONLY";
        $st = $pdo->prepare($sql);
        foreach ($w['binds'] as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':off', $offset, \PDO::PARAM_INT);
        $st->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $st->execute();
        $categorias = $st->fetchAll(\PDO::FETCH_ASSOC);

        $from = $total > 0 ? ($offset + 1) : 0;
        $to   = min($total, $offset + $perPage);

        render_page('categorias/index.php', [
            'lt'         => $lt,
            'categorias' => $categorias,
            'total'      => $total,
            'from'       => $from,
            'to'         => $to,
        ]);
    }

    public function create(): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        render_page('categorias/form.php', ['modo' => 'criar', 'categoria' => null, 'erro' => null]);
    }

    public function store(): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        check_csrf();
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            render_page('categorias/form.php', [
                'modo'      => 'criar',
                'erro'      => 'O nome é obrigatório.',
                'categoria' => ['nome' => '', 'descricao' => trim($_POST['descricao'] ?? ''), 'ativo' => 1],
            ]);
            return;
        }
        $cat = new Categoria();
        $cat->nome      = $nome;
        $cat->descricao = trim($_POST['descricao'] ?? '') ?: null;
        $cat->ativo     = 1;
        $cat->save();

        AlmoxPermission::registerCategory($cat->id, $cat->nome);

        redirect('categorias');
    }

    public function edit(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        $cat = Categoria::find($id);
        if (!$cat) { http_response_code(404); exit('Categoria não encontrada.'); }
        $tipos = $this->loadTiposDualList($id);
        render_page('categorias/form.php', [
            'modo'          => 'editar',
            'categoria'     => $cat->toArray(),
            'erro'          => null,
            'tiposDisponiveis' => $tipos['disponiveis'],
            'tiposNaCategoria' => $tipos['naCategoria'],
        ]);
    }

    public function update(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        check_csrf();
        $cat = Categoria::find($id);
        if (!$cat) { http_response_code(404); exit('Categoria não encontrada.'); }
        $nome = trim($_POST['nome'] ?? '');
        $tiposPost = $this->tiposDualListFromPost($id);
        if ($nome === '') {
            render_page('categorias/form.php', [
                'modo'               => 'editar',
                'erro'               => 'O nome é obrigatório.',
                'categoria'          => array_merge($cat->toArray(), ['nome' => '', 'descricao' => trim($_POST['descricao'] ?? '')]),
                'tiposDisponiveis'   => $tiposPost['disponiveis'],
                'tiposNaCategoria'   => $tiposPost['naCategoria'],
            ]);
            return;
        }
        $cat->nome      = $nome;
        $cat->descricao = trim($_POST['descricao'] ?? '') ?: null;
        $cat->save();

        $selectedIds  = array_map('intval', $_POST['tipo_ids'] ?? []);
        $origemPorId  = [];
        foreach ($_POST['tipo_origem'] ?? [] as $tid => $orig) {
            $origemPorId[(int)$tid] = (int)$orig;
        }
        $this->syncTiposCategoria($id, $selectedIds, $origemPorId);

        redirect('categorias');
    }

    public function toggleAtivo(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();
        $cat = Categoria::find($id);
        if ($cat) {
            $cat->ativo = $cat->ativo ? 0 : 1;
            $cat->save();
        }
        redirect('categorias');
    }

    public function destroy(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();

        $cat = Categoria::find($id);
        if (!$cat) {
            redirect('categorias');
            return;
        }

        $pdo = Connection::get();
        $st  = $pdo->prepare('SELECT COUNT(*) FROM dbo.tipos_item WHERE categoria_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            $_SESSION['flash_error'] = 'Não é possível excluir: existem tipos de item vinculados. Exclua os tipos primeiro.';
            redirect('categorias');
            return;
        }

        $cat->delete();
        AlmoxPermission::unregisterCategory($id);
        $_SESSION['flash_success'] = 'Categoria excluída com sucesso.';
        redirect('categorias');
    }

    /**
     * Reconstrói listas do dual box a partir do POST (validação com erro).
     *
     * @return array{disponiveis: array<int, array>, naCategoria: array<int, array>}
     */
    private function tiposDualListFromPost(int $categoriaId): array
    {
        $tipos = $this->loadTiposDualList($categoriaId);
        $byId  = [];
        foreach (array_merge($tipos['disponiveis'], $tipos['naCategoria']) as $row) {
            $byId[(int)$row['id']] = $row;
        }

        $selectedIds = array_map('intval', $_POST['tipo_ids'] ?? []);
        $disponiveis = [];
        $naCategoria = [];
        foreach ($byId as $id => $row) {
            if (in_array($id, $selectedIds, true)) {
                $naCategoria[] = $row;
            } else {
                $disponiveis[] = $row;
            }
        }

        return ['disponiveis' => $disponiveis, 'naCategoria' => $naCategoria];
    }
}

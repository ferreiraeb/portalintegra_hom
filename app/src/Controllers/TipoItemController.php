<?php
namespace Controllers;

use Database\Connection;
use Models\Categoria;
use Models\TipoItem;

class TipoItemController
{
    private function requireManage(): void
    {
        if (\Security\Permission::level('users.manage') >= 2) return;
        \Security\Permission::require('almoxarifado.manage', 2);
    }

    public function index(): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();

        $pdo = Connection::get();

        $catOptions = ['' => 'Todas'];
        foreach (Categoria::findAll([], 'nome') as $c) {
            $catOptions[(string)$c->id] = $c->nome;
        }

        $cols = [
            'id'             => ['label' => '#', 'sortable' => true, 'filter' => null, 'sql_col' => 't.id'],
            'nome'           => ['label' => 'Nome', 'sortable' => true, 'filter' => 'text', 'param' => 'f_nome', 'sql_col' => 't.nome'],
            'nome_categoria' => ['label' => 'Categoria', 'sortable' => true, 'filter' => 'select', 'param' => 'f_categoria', 'sql_col' => 'c.nome',
                                 'skip_where' => true, 'options' => $catOptions],
            'is_determinado' => ['label' => 'Rastreável', 'sortable' => true, 'filter' => 'select', 'param' => 'f_rastreavel', 'sql_col' => 't.is_determinado',
                                 'options' => ['' => 'Todos', '1' => 'Sim', '0' => 'Não']],
            'ativo'          => ['label' => 'Status', 'sortable' => true, 'filter' => 'select', 'param' => 'f_status', 'sql_col' => 't.ativo',
                                'options' => ['' => 'Todos', '1' => 'Ativo', '0' => 'Inativo']],
            '_acoes'         => ['label' => 'Ações', 'sortable' => false, 'filter' => null, 'th_class' => 'text-right'],
        ];

        $lt = new \Support\ListTable(base_url('tipos-item'), $cols, 'tipos_item');
        $lt->readRequest('t.nome');

        $fv    = $lt->getFilterValues();
        $extra = [];
        if (($fv['f_categoria'] ?? '') !== '') {
            $extra['t.categoria_id = :f_categoria'] = [':f_categoria' => $fv['f_categoria']];
        }
        $w       = $lt->buildWhere($extra);
        $sort    = $lt->getSort() ?: 't.nome';
        $dir     = strtoupper($lt->getDir());
        $offset  = ($lt->getPage() - 1) * $lt->getPerPage();
        $perPage = $lt->getPerPage();

        $sqlCount = "SELECT COUNT(*)
                     FROM dbo.tipos_item t
                     INNER JOIN dbo.categorias c ON c.id = t.categoria_id
                     {$w['sql']}";
        $st = $pdo->prepare($sqlCount);
        foreach ($w['binds'] as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $total = (int)$st->fetchColumn();

        $sql = "SELECT t.id, t.nome, t.categoria_id, t.is_determinado, t.ativo, c.nome AS nome_categoria
                FROM dbo.tipos_item t
                INNER JOIN dbo.categorias c ON c.id = t.categoria_id
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
        $tipos = $st->fetchAll(\PDO::FETCH_ASSOC);

        $from = $total > 0 ? ($offset + 1) : 0;
        $to   = min($total, $offset + $perPage);

        render_page('tipos_item/index.php', [
            'lt'    => $lt,
            'tipos' => $tipos,
            'total' => $total,
            'from'  => $from,
            'to'    => $to,
        ]);
    }

    public function create(): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        $categoriasAtivas = Categoria::findAll(['ativo' => 1], 'nome');
        render_page('tipos_item/form.php', [
            'modo'             => 'criar',
            'tipo'             => null,
            'erro'             => null,
            'categoriasAtivas' => $categoriasAtivas,
        ]);
    }

    public function store(): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        check_csrf();
        $categoriasAtivas = Categoria::findAll(['ativo' => 1], 'nome');

        $categoriaId    = (int)($_POST['categoria_id'] ?? 0);
        $nome           = trim($_POST['nome'] ?? '');

        if ($categoriaId === 0 || $nome === '') {
            render_page('tipos_item/form.php', [
                'modo'             => 'criar',
                'erro'             => 'Categoria e nome são obrigatórios.',
                'tipo'             => $_POST,
                'categoriasAtivas' => $categoriasAtivas,
            ]);
            return;
        }

        $t = new TipoItem();
        $t->categoria_id   = $categoriaId;
        $t->nome           = $nome;
        $t->descricao      = trim($_POST['descricao'] ?? '') ?: null;
        $t->is_determinado = 0;
        $t->tabela_detalhe = null;
        $t->ativo          = 1;
        $t->save();
        redirect('tipos-item');
    }

    public function edit(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        $tipo = TipoItem::find($id);
        if (!$tipo) { http_response_code(404); exit('Tipo de item não encontrado.'); }
        $categoriasAtivas = Categoria::findAll(['ativo' => 1], 'nome');
        render_page('tipos_item/form.php', [
            'modo'             => 'editar',
            'tipo'             => $tipo->toArray(),
            'erro'             => null,
            'categoriasAtivas' => $categoriasAtivas,
        ]);
    }

    public function update(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        check_csrf();
        $tipo = TipoItem::find($id);
        if (!$tipo) { http_response_code(404); exit('Tipo de item não encontrado.'); }
        $categoriasAtivas = Categoria::findAll(['ativo' => 1], 'nome');

        $categoriaId   = (int)($_POST['categoria_id'] ?? 0);
        $nome          = trim($_POST['nome'] ?? '');

        if ($categoriaId === 0 || $nome === '') {
            render_page('tipos_item/form.php', [
                'modo'             => 'editar',
                'erro'             => 'Categoria e nome são obrigatórios.',
                'tipo'             => array_merge($tipo->toArray(), $_POST),
                'categoriasAtivas' => $categoriasAtivas,
            ]);
            return;
        }

        $tipo->categoria_id   = $categoriaId;
        $tipo->nome           = $nome;
        $tipo->descricao      = trim($_POST['descricao'] ?? '') ?: null;
        $tipo->save();
        redirect('tipos-item');
    }

    public function toggleAtivo(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();
        $tipo = TipoItem::find($id);
        if ($tipo) {
            $tipo->ativo = $tipo->ativo ? 0 : 1;
            $tipo->save();
        }
        redirect('tipos-item');
    }

    public function destroy(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        if (!is_post()) { http_response_code(405); exit(); }
        check_csrf();

        $tipo = TipoItem::find($id);
        if (!$tipo) {
            redirect('tipos-item');
            return;
        }

        $pdo = Connection::get();
        $st  = $pdo->prepare('SELECT COUNT(*) FROM dbo.itens WHERE tipo_item_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            $_SESSION['flash_error'] = 'Não é possível excluir: existem itens vinculados a este tipo.';
            redirect('tipos-item');
            return;
        }

        $tipo->delete();
        $_SESSION['flash_success'] = 'Tipo de item excluído com sucesso.';
        redirect('tipos-item');
    }
}

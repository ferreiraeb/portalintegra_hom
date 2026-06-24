<?php
namespace Controllers;

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

        // Carrega todos os tipos e todas as categorias
        $filtroCategoria = (int)($_GET['categoria_id'] ?? 0);
        $where = $filtroCategoria ? ['categoria_id' => $filtroCategoria] : [];
        $tipos = TipoItem::findAll($where, 'nome');

        // Mapeia categorias por id para lookup O(1)
        $todasCategorias = Categoria::findAll([], 'nome');
        $catMap = [];
        foreach ($todasCategorias as $c) { $catMap[$c->id] = $c->nome; }

        // Adiciona nome_categoria a cada tipo como array
        $tiposArr = array_map(function(TipoItem $t) use ($catMap) {
            $arr = $t->toArray();
            $arr['nome_categoria'] = $catMap[$t->categoria_id] ?? '—';
            return $arr;
        }, $tipos);

        // Categorias ativas para o select de filtro
        $categoriasAtivas = Categoria::findAll(['ativo' => 1], 'nome');

        render_page('tipos_item/index.php', [
            'tipos'            => $tiposArr,
            'categoriasAtivas' => $categoriasAtivas,
            'filtroCategoria'  => $filtroCategoria,
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
}


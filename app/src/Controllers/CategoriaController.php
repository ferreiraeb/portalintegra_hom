<?php
namespace Controllers;

use Models\Categoria;
use Security\AlmoxPermission;

class CategoriaController
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
        $categorias = Categoria::findAll([], 'nome');
        render_page('categorias/index.php', ['categorias' => $categorias]);
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

        // Registra o código de permissão para a nova categoria
        AlmoxPermission::registerCategory($cat->id, $cat->nome);

        redirect('categorias');
    }

    public function edit(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        $cat = Categoria::find($id);
        if (!$cat) { http_response_code(404); exit('Categoria não encontrada.'); }
        render_page('categorias/form.php', ['modo' => 'editar', 'categoria' => $cat->toArray(), 'erro' => null]);
    }

    public function update(int $id): void
    {
        \Security\Auth::requireAuth();
        $this->requireManage();
        check_csrf();
        $cat = Categoria::find($id);
        if (!$cat) { http_response_code(404); exit('Categoria não encontrada.'); }
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            render_page('categorias/form.php', [
                'modo'      => 'editar',
                'erro'      => 'O nome é obrigatório.',
                'categoria' => array_merge($cat->toArray(), ['nome' => '', 'descricao' => trim($_POST['descricao'] ?? '')]),
            ]);
            return;
        }
        $cat->nome      = $nome;
        $cat->descricao = trim($_POST['descricao'] ?? '') ?: null;
        $cat->save();
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
}


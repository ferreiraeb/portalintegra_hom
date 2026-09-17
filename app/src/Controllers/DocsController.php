<?php
namespace Controllers;

class DocsController
{
    public function index()
    {
        \Security\Auth::requireAuth();
        // Qualquer pessoa com permissão de gestão de usuários em nível >= 1 pode ver a documentação
        \Security\Permission::require('users.manage', 1);

        render_page('docs/index.php', [
            'title' => 'Documentação do Sistema — Portal Integra',
        ]);
    }
}
?>
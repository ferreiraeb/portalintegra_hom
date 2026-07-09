<?php
require __DIR__ . '/../bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($path, '/');

use Controllers\AuthController;
use Controllers\UserController;

$authCtrl = new AuthController($config);
$userCtrl = new UserController();

// Rotas almoxarifado
if (preg_match('#^categorias/(\d+)/editar$#', $path, $m)) {
    $ctrl = new \Controllers\CategoriaController();
    is_post() ? $ctrl->update((int)$m[1]) : $ctrl->edit((int)$m[1]);
    exit;
}
if (preg_match('#^categorias/(\d+)/toggle$#', $path, $m)) {
    (new \Controllers\CategoriaController())->toggleAtivo((int)$m[1]); exit;
}
if (preg_match('#^categorias/(\d+)/excluir$#', $path, $m)) {
    (new \Controllers\CategoriaController())->destroy((int)$m[1]); exit;
}
if (preg_match('#^tipos-item/(\d+)/editar$#', $path, $m)) {
    $ctrl = new \Controllers\TipoItemController();
    is_post() ? $ctrl->update((int)$m[1]) : $ctrl->edit((int)$m[1]);
    exit;
}
if (preg_match('#^tipos-item/(\d+)/toggle$#', $path, $m)) {
    (new \Controllers\TipoItemController())->toggleAtivo((int)$m[1]); exit;
}
if (preg_match('#^tipos-item/(\d+)/excluir$#', $path, $m)) {
    (new \Controllers\TipoItemController())->destroy((int)$m[1]); exit;
}
if (preg_match('#^itens/tipo/(\d+)$#', $path, $m)) {
    (new \Controllers\ItemController())->indexByTipo((int)$m[1]); exit;
}
if (preg_match('#^itens/(\d+)/editar$#', $path, $m)) {
    $ctrl = new \Controllers\ItemController();
    is_post() ? $ctrl->update((int)$m[1]) : $ctrl->edit((int)$m[1]);
    exit;
}
if (preg_match('#^itens/(\d+)/status$#', $path, $m)) {
    (new \Controllers\ItemController())->alterarStatus((int)$m[1]); exit;
}
if (preg_match('#^itens/(\d+)$#', $path, $m)) {
    (new \Controllers\ItemController())->show((int)$m[1]); exit;
}

if (preg_match('#^emprestimos/(\d+)/devolver$#', $path, $m)) {
    (new \Controllers\EmprestimoController())->devolver((int)$m[1]); exit;
}
if (preg_match('#^emprestimos/(\d+)/ativar$#', $path, $m)) {
    (new \Controllers\EmprestimoController())->ativar((int)$m[1]); exit;
}
if (preg_match('#^emprestimos/(\d+)/cancelar$#', $path, $m)) {
    (new \Controllers\EmprestimoController())->cancelarReserva((int)$m[1]); exit;
}
if (preg_match('#^emprestimos/(\d+)$#', $path, $m)) {
    (new \Controllers\EmprestimoController())->show((int)$m[1]); exit;
}
// Redireciona rotas legadas de reservas para emprestimos
if (preg_match('#^reservas/(\d+)/ativar$#', $path, $m)) {
    (new \Controllers\EmprestimoController())->ativar((int)$m[1]); exit;
}

if ($path === 'hr/colaboradores/autocomplete') {
    (new \Controllers\HrController())->autocompleteColaboradores(); exit;
}

if (preg_match('#^hr/colaboradores/(.+)$#', $path, $m)) {
    (new \Controllers\HrController())->showColaborador(urldecode($m[1])); exit;
}

// Rotas
switch ($path) {
    case '':
    case 'home':
		\Security\Auth::requireAuth();
			render_page('home/index.php');
			break;
    case 'login':
        $authCtrl->login();
        break;

    case 'logout':
        $authCtrl->logout();
        break;

    case 'alterar-senha':
        $authCtrl->changePassword();
        break;

    case 'users':
        $userCtrl->index();
        break;

    case 'users/create':
        $userCtrl->create();
        break;

    case 'users/edit':
        $userCtrl->edit();
        break;

    case 'users/delete':
        $userCtrl->delete();
        break;

	case 'users/view-ad':
		$userCtrl->viewAd();
		break;

	case 'users/sync-ad':
		$userCtrl->syncAd();
		break;

	case 'users/permissions':
		// Exibe/edita permissões do usuário-alvo
		$permCtrl = new \Controllers\PermissionController();
		$permCtrl->edit();
		break;

	case 'users/permissions-overview':
		// Visão geral de todas as permissões (quem tem o quê)
		$permCtrl = new \Controllers\PermissionController();
		$permCtrl->overview();
		break;

	case 'mining/analise-di':
		$drill = new \Controllers\MiningController();
		$drill->analiseDI();
		break;

	case 'mining/tabela-getman':
		$ctrl = new \Controllers\MiningGetmanController();
		$ctrl->index();
		break;

	case 'mining/tabela-boart':
		$c = new \Controllers\MiningBoartController();
		$c->index();
		break;

	case 'agro/tabela-massey':
		$c = new \Controllers\AgroMasseyController();
		$c->index();
		break;

	case 'hr/colaboradores':
		$c = new \Controllers\HrController();
		$c->colaboradores();
		break;

	case 'hr/colaboradores/autocomplete':
		$c = new \Controllers\HrController();
		$c->autocompleteColaboradores();
		break;

	case 'hr/organograma':
		$c = new \Controllers\HrController();
		$c->organograma();
		break;

	case 'docs':
		$docs = new \Controllers\DocsController();
		$docs->index();
		break;

	case 'categorias':
		(new \Controllers\CategoriaController())->index(); break;
	case 'categorias/criar':
		$ctrl = new \Controllers\CategoriaController();
		is_post() ? $ctrl->store() : $ctrl->create();
		break;

	case 'tipos-item':
		(new \Controllers\TipoItemController())->index(); break;
	case 'tipos-item/criar':
		$ctrl = new \Controllers\TipoItemController();
		is_post() ? $ctrl->store() : $ctrl->create();
		break;

	case 'itens':
		http_response_code(404);
		exit('Selecione um tipo de item no menu Ativos.');
		break;
	case 'itens/criar':
		$ctrl = new \Controllers\ItemController();
		is_post() ? $ctrl->store() : $ctrl->create();
		break;
	case 'itens/tipo-info':
		(new \Controllers\ItemController())->tipoInfo(); break;

	case 'emprestimos':
		(new \Controllers\EmprestimoController())->index(); break;
	case 'emprestimos/criar':
		$ctrl = new \Controllers\EmprestimoController();
		is_post() ? $ctrl->store() : $ctrl->create();
		break;
	case 'emprestimos/itens/autocomplete':
		(new \Controllers\EmprestimoController())->autocompleteItens();
		break;

	case 'reservas':
		redirect('emprestimos?tab=reservas');
		break;
	case 'reservas/criar':
		redirect('emprestimos/criar?modo=reservar');
		break;

    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
?>
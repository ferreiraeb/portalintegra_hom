<?php
require __DIR__ . '/../bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($path, '/');

use Controllers\AuthController;
use Controllers\UserController;

$authCtrl = new AuthController($config);
$userCtrl = new UserController();

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

	case 'drilling/analise-di':
		$drill = new \Controllers\DrillingController();
		$drill->analiseDI();
		break;

	case 'drilling/tabela-getman':
		$ctrl = new \Controllers\DrillingGetmanController();
		$ctrl->index();
		break;

	case 'drilling/tabela-boart':
		$c = new \Controllers\DrillingBoartController();
		$c->index();
		break;

	case 'agro/tabela-massey':
		$c = new \Controllers\AgroMasseyController();
		$c->index();
		break;

	case 'docs':
		$docs = new \Controllers\DocsController();
		$docs->index();
		break;

    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
?>

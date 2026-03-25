<?php
namespace Controllers;

use Database\Connection;
use Security\Permission;

class PermissionController {
    /**
     * Exibe/edita a permissão 'users.manage' (0/1/2) para um usuário-alvo (local ou AD).
     * Necessita do chamador ter nível 2 (escrita) em 'users.manage'.
     * GET  ?id=  -> formulário (parcial em modal com ?modal=1)
     * POST ?id=  -> salva (se ?ajax=1, retorna JSON)
     */
   public function edit() {
		\Security\Auth::requireAuth();
		\Security\Permission::require('users.manage', 2); // quem gerencia permissões precisa escrita em Usuários

		$pdo = \Database\Connection::get();
		$targetId = (int)($_GET['id'] ?? 0);

		// Usuário-alvo
		$st = $pdo->prepare("SELECT * FROM users WHERE id = :id");
		$st->bindValue(':id', $targetId, \PDO::PARAM_INT);
		$st->execute();
		$user = $st->fetch();
		if (!$user) { http_response_code(404); exit('Usuário não encontrado.'); }

		// Códigos de permissão que vamos editar no modal
		$codes = [
			'users.manage',
			'drilling.analise_di',
			'drilling.tabela_getman',
			'drilling.tabela_boart',
			'agro.tabela_massey',
		];

		// Carrega níveis atuais
		$levels = array_fill_keys($codes, 0);
		$in = implode(',', array_fill(0, count($codes), '?'));
		$stmt = $pdo->prepare("SELECT permission_code, level FROM user_permissions WHERE user_id = ? AND permission_code IN ($in)");
		$params = array_merge([$targetId], $codes);
		$stmt->execute($params);
		foreach ($stmt->fetchAll() as $row) {
			$levels[$row['permission_code']] = (int)$row['level'];
		}

		// GET → renderiza o modal (parcial) ou página
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$vars = ['user' => $user, 'levels' => $levels];
			if (isset($_GET['modal'])) view('permissions/_user_permissions.php', $vars);
			else render_page('permissions/_user_permissions.php', $vars);
			return;
		}

		// POST → salva
		check_csrf();

		// Lê os valores enviados pelo formulário (0/1/2), com limites
		$newUsersManage  = isset($_POST['perm_users_manage'])            ? (int)$_POST['perm_users_manage']            : 0;
		$newDrillingDI   = isset($_POST['perm_drilling_analise_di'])     ? (int)$_POST['perm_drilling_analise_di']     : 0;
		$newTabelaGetman = isset($_POST['perm_drilling_tabela_getman'])  ? (int)$_POST['perm_drilling_tabela_getman']  : 0;
		$newTabelaBoart  = isset($_POST['perm_drilling_tabela_boart'])   ? (int)$_POST['perm_drilling_tabela_boart']   : 0;
		$newTabelaMassey = isset($_POST['perm_agro_tabela_massey'])      ? (int)$_POST['perm_agro_tabela_massey']      : 0;

		$toSave = [
			'users.manage'           => max(0, min(2, $newUsersManage)),
			'drilling.analise_di'    => max(0, min(2, $newDrillingDI)),
			'drilling.tabela_getman' => max(0, min(2, $newTabelaGetman)),
			'drilling.tabela_boart'  => max(0, min(2, $newTabelaBoart)),
			'agro.tabela_massey'     => max(0, min(2, $newTabelaMassey)),
		];

		foreach ($toSave as $code => $lvl) {
			$upd = $pdo->prepare("UPDATE user_permissions SET level = :lvl WHERE user_id = :uid AND permission_code = :code");
			$upd->execute([':lvl' => $lvl, ':uid' => $targetId, ':code' => $code]);

			if ($upd->rowCount() === 0) {
				$ins = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_code, level) VALUES (:uid, :code, :lvl)");
				$ins->execute([':uid' => $targetId, ':code' => $code, ':lvl' => $lvl]);
			}

			// Atualiza cache de sessão (se o alvo for o usuário logado)
			if (!empty($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] === $targetId) {
				$_SESSION['perm_levels'][$code] = $lvl;
			}
		}

		if (isset($_GET['ajax'])) {
			header('Content-Type: application/json');
			echo json_encode(['ok' => true, 'message' => 'Permissões atualizadas com sucesso.']);
			return;
		}
		redirect('users');
	}
}
?>
<?php
namespace Controllers;
use Security\Permission;
use Security\Auth as AuthSvc;
use Database\Connection;

class UserController {
	public function index() {
		\Security\Auth::requireAuth();
		\Security\Permission::require('users.manage', 1);

		$pdo = \Database\Connection::get();

		// Filtros
		$q = trim($_GET['q'] ?? '');
		$origem = trim($_GET['origem'] ?? ''); // '' | 'local' | 'ad'

		// Ordenação segura
		$allowedSort = ['nome','login','origem','email','is_active'];
		$sort = $_GET['sort'] ?? 'nome';
		if (!in_array($sort, $allowedSort, true)) $sort = 'nome';

		$dir = strtolower($_GET['dir'] ?? 'asc');
		$dir = ($dir === 'desc') ? 'DESC' : 'ASC';

		// Paginação
		$page = max(1, (int)($_GET['page'] ?? 1));
		$perPage = (int)($_GET['per_page'] ?? 25);
		if (!in_array($perPage, [10,25,50,100], true)) $perPage = 25;
		$offset = ($page - 1) * $perPage;

		// Constrói filtros
		$where = "WHERE 1=1";
		$bind = [];

		// Busca otimizada:
		// - Sem '*' → prefixo: 'termo%' (usa índice)
		// - Com '*' → contém: '%termo%' (comportamento anterior)
		if ($q !== '') {
			$contains = strpos($q, '*') !== false;
			$term = str_replace('*', '', $q);
			if ($contains) {
				$where .= " AND (nome LIKE :q1 OR login LIKE :q2)";
				$bind[':q1'] = '%'.$term.'%';
				$bind[':q2'] = '%'.$term.'%';
			} else {
				$where .= " AND (nome LIKE :q1 OR login LIKE :q2)";
				$bind[':q1'] = $term.'%';
				$bind[':q2'] = $term.'%';
			}
		}

		if ($origem !== '') {
			$where .= " AND origem = :origem";
			$bind[':origem'] = $origem;
		}

		// 1) Total para paginação
		$sqlCount = "SELECT COUNT(*) AS total FROM users $where";
		$st = $pdo->prepare($sqlCount);
		foreach ($bind as $k=>$v) $st->bindValue($k, $v);
		$st->execute();
		$total = (int)$st->fetchColumn();

		// 2) Página de dados (selecione apenas colunas necessárias)
		$sql = "SELECT id, nome, login, origem, email, is_active
				FROM users
				$where
				ORDER BY $sort $dir
				OFFSET :off ROWS FETCH NEXT :lim ROWS ONLY";
		$st = $pdo->prepare($sql);
		foreach ($bind as $k=>$v) $st->bindValue($k, $v);
		$st->bindValue(':off', $offset, \PDO::PARAM_INT);
		$st->bindValue(':lim', $perPage, \PDO::PARAM_INT);
		$st->execute();
		$users = $st->fetchAll();

		render_page('users/index.php', [
			'users'   => $users,
			'total'   => $total,
			'page'    => $page,
			'perPage' => $perPage,
			'sort'    => $sort,
			'dir'     => strtolower($dir),
			'q'       => $q,
			'origem'  => $origem
		]);
	}

	public function create() {
		\Security\Auth::requireAuth();
		\Security\Permission::require('users.manage', 2);

		if (is_post()) {
			check_csrf();
			$data = [
				'nome'  => trim($_POST['nome'] ?? ''),
				'login' => trim($_POST['login'] ?? ''),
				'email' => trim($_POST['email'] ?? ''),
				'senha' => $_POST['senha'] ?? ''
			];

			try {
				$auth = new \Security\Auth(['password_algo'=>PASSWORD_BCRYPT,'password_options'=>['cost'=>10]]);
				$auth->createLocalUser($data);
				redirect('users');

			} catch (\RuntimeException $e) {
				// Erro de validação (ex.: login duplicado)
				return render_page('users/form.php', [
					'error' => $e->getMessage(),
					'mode'  => 'create',
					'user'  => $data
				]);

			} catch (\PDOException $e) {
				// Violação de unique por segurança (código 23000)
				$msg = $e->getCode() === '23000'
					? 'Já existe um usuário com este login.'
					: 'Erro ao salvar o usuário.';
				return render_page('users/form.php', [
					'error' => $msg,
					'mode'  => 'create',
					'user'  => $data
				]);
			}
		}

		render_page('users/form.php', ['mode'=>'create']);
	}

	public function edit() {
    \Security\Auth::requireAuth();
    \Security\Permission::require('users.manage', 2);
    $pdo = \Database\Connection::get();

    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM users WHERE id=:id");
    $st->execute([':id'=>$id]);
    $user = $st->fetch();
    if (!$user) exit('Usuário não encontrado.');

    // 🔒 Bloqueia edição de usuários de origem AD
    if ($user['origem'] === 'ad') {
        http_response_code(403);
        exit('Usuário de AD não pode ser editado aqui. (Apenas permissões de acesso serão gerenciadas em tela específica.)');
    }

    if (is_post()) {
        check_csrf();
        $auth = new \Security\Auth(['password_algo'=>PASSWORD_BCRYPT,'password_options'=>['cost'=>10]]);
        $auth->updateLocalUser($id, [
            'nome'=>trim($_POST['nome'] ?? $user['nome']),
            'email'=>trim($_POST['email'] ?? $user['email']),
            'senha'=>trim($_POST['senha'] ?? '')
        ]);
        redirect('users');
    }

    render_page('users/form.php', ['mode'=>'edit', 'user'=>$user]);
}

    public function delete() {
        \Security\Auth::requireAuth();
        Permission::require('users.manage', 2);
        if (is_post()) {
            check_csrf();
            $id = (int)($_POST['id'] ?? 0);
            $auth = new \Security\Auth(['password_algo'=>PASSWORD_BCRYPT,'password_options'=>['cost'=>10]]);
            $auth->deleteUser($id);
            redirect('users');
        }
        http_response_code(405);
    }
	
	public function syncAd() {
		\Security\Auth::requireAuth();
		\Security\Permission::require('users.manage', 2);

		if (!is_post()) {
			http_response_code(405);
			header('Content-Type: application/json');
			echo json_encode(['ok' => false, 'message' => 'Método não permitido.']);
			return;
		}

		check_csrf();
		header('Content-Type: application/json');
		set_time_limit(120); // sync de AD pode demorar; 30s default é pouco

		global $config;

		try {
			$ldap = new \Services\LdapService($config['ldap']);
			$pdo  = \Database\Connection::get();

			$users = $ldap->searchActiveUsers();

			$ins = $pdo->prepare("
				INSERT INTO users
					(nome, login, email, origem, ad_dn, upn, department, title, company,
					 office, phone, employeeNumber, userAccountControl, lockoutTime,
					 is_active, created_at, last_sync_at)
				VALUES
					(:nome, :login, :email, 'ad', :dn, :upn, :department, :title, :company,
					 :office, :phone, :employeeNumber, :uac, :lockoutTime,
					 1, GETDATE(), GETDATE())
			");

			$upd = $pdo->prepare("
				UPDATE users SET
					nome=:nome, email=:email, ad_dn=:dn, upn=:upn,
					department=:department, title=:title, company=:company,
					office=:office, phone=:phone, employeeNumber=:employeeNumber,
					userAccountControl=:uac, lockoutTime=:lockoutTime,
					origem='ad', last_sync_at=GETDATE(), updated_at=GETDATE()
				WHERE login=:login
			");

			$inserted = 0;
			$updated  = 0;

			foreach ($users as $u) {
				$login = $u['sAMAccountName'] ?: ($u['upn'] ?: null);
				if (!$login) continue;

				$check = $pdo->prepare("SELECT id FROM users WHERE login = :login");
				$check->execute([':login' => $login]);
				$exists = $check->fetch();

				$params = [
					':nome'           => trim(($u['displayName'] ?: (($u['givenName'] ?? '') . ' ' . ($u['sn'] ?? '')))),
					':login'          => $login,
					':email'          => $u['mail']             ?? null,
					':dn'             => $u['dn']               ?? null,
					':upn'            => $u['upn']              ?? null,
					':department'     => $u['department']       ?? null,
					':title'          => $u['title']            ?? null,
					':company'        => $u['company']          ?? null,
					':office'         => $u['office']           ?? null,
					':phone'          => $u['phone']            ?? null,
					':employeeNumber' => $u['employeeNumber']   ?? null,
					':uac'            => $u['userAccountControl'] ?? null,
					':lockoutTime'    => $u['lockoutTime']      ?? null,
				];

				if ($exists) {
					$upd->execute($params);
					$updated++;
				} else {
					$ins->execute($params);
					$inserted++;
				}
			}

			$total = count($users);
			echo json_encode([
				'ok'       => true,
				'message'  => "Sincronização concluída: {$total} usuário(s) processado(s) — {$inserted} inserido(s), {$updated} atualizado(s).",
				'inserted' => $inserted,
				'updated'  => $updated,
				'total'    => $total,
			]);

		} catch (\Throwable $e) {
			http_response_code(500);
			echo json_encode([
				'ok'      => false,
				'message' => 'Falha na sincronização: ' . $e->getMessage(),
			]);
		}
	}

	public function viewAd() {
		\Security\Auth::requireAuth();
		\Security\Permission::require('users.manage', 1); // leitura

		$pdo = \Database\Connection::get();
		$id = (int)($_GET['id'] ?? 0);

		$st = $pdo->prepare("SELECT * FROM users WHERE id = :id");
		$st->bindValue(':id', $id, \PDO::PARAM_INT);
		$st->execute();
		$user = $st->fetch();

		if (!$user) {
			http_response_code(404);
			exit('Usuário não encontrado.');
		}

		if ($user['origem'] !== 'ad') {
			http_response_code(400);
			exit('Esta visualização é apenas para usuários de origem AD.');
		}

		if (isset($_GET['modal'])) {
			view('users/_ad_readonly.php', ['user' => $user]);
			return;
		}

		render_page('users/_ad_readonly.php', ['user' => $user]);
	}
	
}
?>
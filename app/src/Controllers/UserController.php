<?php
namespace Controllers;
use Security\Permission;
use Security\Auth as AuthSvc;
use Database\Connection;

class UserController {
	public function index() {
		\Security\Auth::requireAuth();
		\Security\Permission::require('users.manage', 1);

		if (($_GET['export'] ?? '') === '1') {
			$this->exportCsv();
			return;
		}

		$pdo = \Database\Connection::get();

		$cols = [
			'nome'             => ['label' => 'Nome',             'sortable' => true,  'filter' => 'text',   'param' => 'f_nome'],
			'colaborador_nome' => ['label' => 'Colaborador (RH)', 'sortable' => true,  'filter' => 'text',   'param' => 'f_colab_nome', 'skip_where' => true],
			'login'            => ['label' => 'Login',            'sortable' => true,  'filter' => 'text',   'param' => 'f_login'],
			'origem'           => ['label' => 'Origem',           'sortable' => true,  'filter' => 'select', 'param' => 'f_origem',
			                       'options' => ['' => 'Todas', 'local' => 'Local', 'ad' => 'AD']],
			'email'            => ['label' => 'E-mail',           'sortable' => true,  'filter' => 'text',   'param' => 'f_email'],
			'is_active'        => ['label' => 'Status',           'sortable' => true,  'filter' => 'select', 'param' => 'f_status',
			                       'options' => ['' => 'Todos', '1' => 'Ativo', '0' => 'Inativo']],
			'_acoes'           => ['label' => 'Ações',            'sortable' => false, 'filter' => null, 'th_class' => 'text-right'],
		];

		$lt = new \Support\ListTable(base_url('users'), $cols, 'users');
		$lt->readRequest('nome');

		$w       = $lt->buildWhere();
		$sort    = $lt->getSort();
		$dir     = strtoupper($lt->getDir()); // ASC | DESC for SQL
		$offset  = ($lt->getPage() - 1) * $lt->getPerPage();
		$perPage = $lt->getPerPage();

		// Busca os employeeNumbers presentes em users, depois confirma no Oracle quais batem com o filtro.
		$colabFilter = $lt->getFilterValues()['f_colab_nome'] ?? '';
		if ($colabFilter !== '') {
			$matchedCpfs = [];
			try {
				// Passo 1: quais employeeNumbers existem na tabela users?
				$stEmp = $pdo->query("SELECT DISTINCT employeeNumber FROM users WHERE employeeNumber IS NOT NULL AND employeeNumber <> ''");
				$existingEmpNums = array_column($stEmp->fetchAll(\PDO::FETCH_NUM), 0);

				if (!empty($existingEmpNums)) {
					// Passo 2: dos que existem, quais batem com o filtro no Oracle?
					$oracle  = \Database\OracleConnection::get();
					$chunks  = array_chunk($existingEmpNums, 500);
					foreach ($chunks as $chunk) {
						$ph    = implode(',', array_fill(0, count($chunk), '?'));
						$stOra = $oracle->prepare(
							"SELECT CPF FROM SIRH.VW_RH_COLABORADORES v
							 WHERE v.CPF IN ({$ph})
							   AND (UPPER(v.NOMECOMPLETO) LIKE UPPER(?)
							     OR UPPER(v.STATUS)       LIKE UPPER(?))
							   AND NOT EXISTS (
							       SELECT 1 FROM SIRH.VW_RH_COLABORADORES x
							       WHERE x.CPF = v.CPF AND x.STATUS = 'ATIVO'
							   )"
						);
						$params = array_merge($chunk, ['%' . $colabFilter . '%', '%' . $colabFilter . '%']);
						$stOra->execute($params);
						foreach ($stOra->fetchAll(\PDO::FETCH_NUM) as $r) {
							$matchedCpfs[] = $r[0];
						}
					}
				}
			} catch (\Throwable $e) { /* Oracle indisponível → sem resultados */ }

			if (!empty($matchedCpfs)) {
				$empPlaceholders = implode(',', array_map(fn($i) => ":emp{$i}", array_keys($matchedCpfs)));
				$empBinds = [];
				foreach ($matchedCpfs as $i => $cpf) { $empBinds[":emp{$i}"] = $cpf; }
				$w = $lt->buildWhere(["employeeNumber IN ({$empPlaceholders})" => $empBinds]);
			} else {
				$w = $lt->buildWhere(['1=0' => []]);
			}
		}

		// Sort guard: colaborador_nome vem do Oracle; usa fallback para o SQL Server
		$sortForDb = ($sort === 'colaborador_nome') ? 'nome' : $sort;

		// Total para paginação
		$sqlCount = "SELECT COUNT(*) AS total FROM users {$w['sql']}";
		$st = $pdo->prepare($sqlCount);
		foreach ($w['binds'] as $k => $v) $st->bindValue($k, $v);
		$st->execute();
		$total = (int)$st->fetchColumn();

		// Página de dados
		$sql = "SELECT id, nome, login, origem, email, is_active, employeeNumber
				FROM users
				{$w['sql']}
				ORDER BY $sortForDb $dir
				OFFSET :off ROWS FETCH NEXT :lim ROWS ONLY";
		$st = $pdo->prepare($sql);
		foreach ($w['binds'] as $k => $v) $st->bindValue($k, $v);
		$st->bindValue(':off', $offset, \PDO::PARAM_INT);
		$st->bindValue(':lim', $perPage, \PDO::PARAM_INT);
		$st->execute();
		$users = $st->fetchAll();

		// Enriquece com nome e status do colaborador via Oracle (uma query para a página inteira)
		$matriculas = array_values(array_filter(array_column($users, 'employeeNumber')));
		$colaboradorMap = [];
		if (!empty($matriculas)) {
			try {
				$oracle = \Database\OracleConnection::get();
				$placeholders = implode(',', array_fill(0, count($matriculas), '?'));
				$stOracle = $oracle->prepare(
					"SELECT CPF, NOMECOMPLETO, STATUS FROM SIRH.VW_RH_COLABORADORES
					 WHERE CPF IN ({$placeholders})"
				);
				$stOracle->execute($matriculas);
				foreach ($stOracle->fetchAll() as $row) {
					$row = array_change_key_case($row, CASE_UPPER);
					$cpf = (string)$row['CPF'];

					if (isset($colaboradorMap[$cpf]) && $colaboradorMap[$cpf]['status'] === 'ATIVO') {
						continue;
					}
					$colaboradorMap[$cpf] = [
						'nome'   => (string)$row['NOMECOMPLETO'],
						'status' => (string)$row['STATUS'],
					];
				}
			} catch (\Throwable $e) {
				// Oracle indisponível: exibirá "—"
			}
		}

		$users = array_map(function ($u) use ($colaboradorMap) {
			$entry = $colaboradorMap[(string)($u['employeeNumber'] ?? '')] ?? null;
			$u['colaborador_nome']   = $entry ? $entry['nome']   : null;
			$u['colaborador_status'] = $entry ? $entry['status'] : null;
			return $u;
		}, $users);

		// Ordenação em memória quando o sort é pelo campo cross-DB
		if ($sort === 'colaborador_nome') {
			usort($users, function ($a, $b) use ($dir) {
				$cmp = strcasecmp((string)($a['colaborador_nome'] ?? ''), (string)($b['colaborador_nome'] ?? ''));
				return $dir === 'DESC' ? -$cmp : $cmp;
			});
		}

		$from = $total > 0 ? ($offset + 1) : 0;
		$to   = min($total, $offset + $perPage);

		render_page('users/index.php', [
			'lt'    => $lt,
			'users' => $users,
			'total' => $total,
			'from'  => $from,
			'to'    => $to,
		]);
	}

	/** GET ?export=1 — exporta os usuários filtrados como CSV. */
	private function exportCsv(): void
	{
		\Security\Auth::requireAuth();
		\Security\Permission::require('users.manage', 1);

		$pdo = \Database\Connection::get();

		$cols = [
			'nome'             => ['label' => 'Nome',             'sortable' => true,  'filter' => 'text',   'param' => 'f_nome'],
			'colaborador_nome' => ['label' => 'Colaborador (RH)', 'sortable' => true,  'filter' => 'text',   'param' => 'f_colab_nome', 'skip_where' => true],
			'login'            => ['label' => 'Login',            'sortable' => true,  'filter' => 'text',   'param' => 'f_login'],
			'origem'           => ['label' => 'Origem',           'sortable' => true,  'filter' => 'select', 'param' => 'f_origem',
			                       'options' => ['' => 'Todas', 'local' => 'Local', 'ad' => 'AD']],
			'email'            => ['label' => 'E-mail',           'sortable' => true,  'filter' => 'text',   'param' => 'f_email'],
			'is_active'        => ['label' => 'Status',           'sortable' => true,  'filter' => 'select', 'param' => 'f_status',
			                       'options' => ['' => 'Todos', '1' => 'Ativo', '0' => 'Inativo']],
		];

		$lt = new \Support\ListTable(base_url('users'), $cols, 'users');
		$lt->readRequest('nome');

		$w           = $lt->buildWhere();
		$sort        = $lt->getSort();
		$dir         = strtoupper($lt->getDir());
		$sortForDb   = ($sort === 'colaborador_nome') ? 'nome' : $sort;
		$colabFilter = $lt->getFilterValues()['f_colab_nome'] ?? '';

		if ($colabFilter !== '') {
			$matchedCpfs = [];
			try {
				$stEmp           = $pdo->query("SELECT DISTINCT employeeNumber FROM users WHERE employeeNumber IS NOT NULL AND employeeNumber <> ''");
				$existingEmpNums = array_column($stEmp->fetchAll(\PDO::FETCH_NUM), 0);
				if (!empty($existingEmpNums)) {
					$oracle = \Database\OracleConnection::get();
					$chunks = array_chunk($existingEmpNums, 500);
					foreach ($chunks as $chunk) {
						$ph    = implode(',', array_fill(0, count($chunk), '?'));
						$stOra = $oracle->prepare(
							"SELECT CPF FROM SIRH.VW_RH_COLABORADORES v
							 WHERE v.CPF IN ({$ph})
							   AND (UPPER(v.NOMECOMPLETO) LIKE UPPER(?)
							     OR UPPER(v.STATUS)       LIKE UPPER(?))
							   AND NOT EXISTS (
							       SELECT 1 FROM SIRH.VW_RH_COLABORADORES x
							       WHERE x.CPF = v.CPF AND x.STATUS = 'ATIVO'
							   )"
						);
						$params = array_merge($chunk, ['%' . $colabFilter . '%', '%' . $colabFilter . '%']);
						$stOra->execute($params);
						foreach ($stOra->fetchAll(\PDO::FETCH_NUM) as $r) {
							$matchedCpfs[] = $r[0];
						}
					}
				}
			} catch (\Throwable $e) { }

			if (!empty($matchedCpfs)) {
				$empPlaceholders = implode(',', array_map(fn($i) => ":emp{$i}", array_keys($matchedCpfs)));
				$empBinds = [];
				foreach ($matchedCpfs as $i => $cpf) { $empBinds[":emp{$i}"] = $cpf; }
				$w = $lt->buildWhere(["employeeNumber IN ({$empPlaceholders})" => $empBinds]);
			} else {
				$w = $lt->buildWhere(['1=0' => []]);
			}
		}

		$sql = "SELECT id, nome, login, origem, email, is_active, employeeNumber
		        FROM users {$w['sql']} ORDER BY $sortForDb $dir";
		$st  = $pdo->prepare($sql);
		foreach ($w['binds'] as $k => $v) $st->bindValue($k, $v);
		$st->execute();
		$users = $st->fetchAll();

		// Enriquece com nome do colaborador (Oracle)
		$matriculas     = array_values(array_filter(array_column($users, 'employeeNumber')));
		$colaboradorMap = [];
		if (!empty($matriculas)) {
			try {
				$oracle       = \Database\OracleConnection::get();
				$placeholders = implode(',', array_fill(0, count($matriculas), '?'));
				$stOracle     = $oracle->prepare(
					"SELECT CPF, NOMECOMPLETO, STATUS FROM SIRH.VW_RH_COLABORADORES WHERE CPF IN ({$placeholders})"
				);
				$stOracle->execute($matriculas);
				foreach ($stOracle->fetchAll() as $row) {
					$row = array_change_key_case($row, CASE_UPPER);
					$cpf = (string)$row['CPF'];
					if (isset($colaboradorMap[$cpf]) && $colaboradorMap[$cpf]['status'] === 'ATIVO') {
						continue;
					}
					$colaboradorMap[$cpf] = ['nome' => (string)$row['NOMECOMPLETO'], 'status' => (string)$row['STATUS']];
				}
			} catch (\Throwable $e) { }
		}

		$users = array_map(function ($u) use ($colaboradorMap) {
			$entry = $colaboradorMap[(string)($u['employeeNumber'] ?? '')] ?? null;
			$u['colaborador_nome'] = $entry ? $entry['nome'] : '';
			return $u;
		}, $users);

		if ($sort === 'colaborador_nome') {
			usort($users, function ($a, $b) use ($dir) {
				$cmp = strcasecmp((string)($a['colaborador_nome'] ?? ''), (string)($b['colaborador_nome'] ?? ''));
				return $dir === 'DESC' ? -$cmp : $cmp;
			});
		}

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="usuarios.csv"');
		echo "\xEF\xBB\xBF"; // BOM para Excel

		$f = fopen('php://output', 'w');
		fputcsv($f, ['Nome', 'Colaborador (RH)', 'Login', 'Origem', 'E-mail', 'Status'], ';');
		foreach ($users as $u) {
			fputcsv($f, [
				(string)($u['nome']             ?? ''),
				(string)($u['colaborador_nome'] ?? ''),
				(string)($u['login']            ?? ''),
				(string)($u['origem']           ?? ''),
				(string)($u['email']            ?? ''),
				$u['is_active'] ? 'Ativo' : 'Inativo',
			], ';');
		}
		fclose($f);
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
				WHERE LOWER(login)=:login
			");

			$inserted = 0;
			$updated  = 0;

			foreach ($users as $u) {
				$login = $u['sAMAccountName'] ?: ($u['upn'] ?: null);
				if (!$login) continue;
				// Normaliza para minúsculas — sAMAccountName é case-insensitive no AD;
				// garante consistência com registros criados no login (que também normalizam).
				$login = strtolower(trim($login));

				$check = $pdo->prepare("SELECT id FROM users WHERE LOWER(login) = :login");
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
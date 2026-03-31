<?php
namespace Controllers;
use Security\Auth;
use Services\LdapService;
use Database\Connection;

class AuthController {
    private $auth;
    private $ldap;
    private $cfg;

    public function __construct(array $cfg) {
        $this->cfg = $cfg;
        $this->auth = new Auth($cfg['auth']);
        $this->ldap = new LdapService($cfg['ldap']);
    }

    public function login() {
        if (is_post()) {
            check_csrf();
            $login = trim($_POST['login'] ?? '');
            $password = $_POST['senha'] ?? '';

            // Normaliza o login: lowercase + remove sufixo de domínio se digitado (ex: user@domain → user)
            // Garante consistência com o sAMAccountName usado pelo sync AD
            $loginNorm = strtolower($login);
            $loginKey  = strpos($loginNorm, '@') !== false
                ? explode('@', $loginNorm)[0]
                : $loginNorm;

            // Busca primeiro pelo loginKey (sAMAccountName), depois pelo UPN completo
            $user = $this->auth->findByLogin($loginKey);
            if (!$user && $loginKey !== $loginNorm) {
                $user = $this->auth->findByLogin($loginNorm);
            }

            $pdo = Connection::get();

            // 1) Tenta local
            if ($user && $this->auth->verifyLocalPassword($user, $password) && $user['is_active']) {
                $this->auth->login($user);
                redirect('');
            }

            // 2) Tenta AD
            if ($this->cfg['ldap']['enabled']) {
                $ok = $this->ldap->authenticateUserPassword($login, $password);
                if ($ok) {
                    // Se usuário não existir localmente, cria/atualiza registro com origem 'ad'
                    if (!$user) {
                        // cria com mínimo de campos; permissões serão atribuídas depois
                        // Usa loginKey (sAMAccountName sem domínio) — consistente com sync AD
                        $stmt = $pdo->prepare("INSERT INTO users (nome, login, origem, is_active, created_at)
                            VALUES (:nome, :login, 'ad', 1, GETDATE())");
                        $stmt->execute([
                            ':nome' => $login,
                            ':login'=> $loginKey
                        ]);
                        $user = $this->auth->findByLogin($loginKey);
                    } else {
                        // garante origem 'ad'
                        if ($user['origem'] !== 'ad') {
                            $pdo->prepare("UPDATE users SET origem='ad' WHERE id=:id")->execute([':id'=>$user['id']]);
                            $user = $this->auth->findByLogin($login);
                        }
                    }

                    if ($user && $user['is_active']) {
                        $this->auth->login($user);
                        redirect('');
                    }
                }
            }

            $error = 'Login ou senha inválidos.';
            return view('auth/login.php', ['error'=>$error, 'config'=>$this->cfg]);
        }

        view('auth/login.php', ['config'=>$this->cfg]);
    }

    public function logout() {
        $this->auth->logout();
        redirect('login');
    }

   public function changePassword() {
		\Security\Auth::requireAuth();

		$pdo = \Database\Connection::get();
		$uid = (int)$_SESSION['user']['id'];

		// Busca usuário
		$st = $pdo->prepare("SELECT * FROM users WHERE id=:id");
		$st->execute([':id'=>$uid]);
		$user = $st->fetch();

		// Usuário AD não altera senha
		if ($user && $user['origem'] === 'ad') {
			if (isset($_GET['ajax']) || isset($_GET['modal'])) {
				header('Content-Type: application/json');
				http_response_code(400);
				echo json_encode(['ok'=>false, 'message'=>'Usuários AD não alteram senha pelo sistema.']);
				return;
			}
			// Caso acessem via rota não-ajax
			exit('Usuários AD não alteram senha pelo sistema.');
		}

		// GET – retorna o formulário parcial se modal=1
		if ($_SERVER['REQUEST_METHOD'] === 'GET') {
			if (isset($_GET['modal'])) {
				// Parcial para injetar no modal
				view('users/_change_password_form.php', ['config'=>$this->cfg]);
				return;
			}
			// (Opcional) manter a página antiga:
			view('users/change_password.php', ['config'=>$this->cfg]);
			return;
		}

		// POST – alteração de senha
		if (is_post()) {
			check_csrf();
			$new = $_POST['nova_senha'] ?? '';
			$confirm = $_POST['confirma_senha'] ?? '';
			if ($new !== $confirm || strlen($new) < 8) {
				$msg = 'As senhas não coincidem ou são muito curtas (mín. 8).';

				if (isset($_GET['ajax'])) {
					header('Content-Type: application/json');
					echo json_encode(['ok'=>false, 'message'=>$msg]);
					return;
				}

				return view('users/change_password.php', ['error'=>$msg, 'config'=>$this->cfg]);
			}

			// Atualiza
			$auth = new \Security\Auth($this->cfg['auth']);
			$auth->changePassword($uid, $new);

			if (isset($_GET['ajax'])) {
				header('Content-Type: application/json');
				echo json_encode(['ok'=>true, 'message'=>'Senha alterada com sucesso.']);
				return;
			}

			return view('users/change_password.php', ['success'=>'Senha alterada com sucesso.', 'config'=>$this->cfg]);
		}
	}

}
?>
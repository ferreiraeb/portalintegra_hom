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
            $login    = trim($_POST['login'] ?? '');
            $password = $_POST['senha'] ?? '';

            if (!csrf_is_valid()) {
                return $this->loginPage([
                    'error' => 'Sessão expirada. Tente entrar novamente.',
                    'loginAttempt' => $login,
                ]);
            }

            $identity = $this->resolveLoginIdentity($login);
            $user     = $identity['user'];

            $pdo = Connection::get();

            if ($user && $this->auth->verifyLocalPassword($user, $password) && $user['is_active']) {
                $this->auth->login($user);
                redirect('');
            }

            if ($this->cfg['ldap']['enabled']) {
                $ok = $this->ldap->authenticateUserPassword($identity['upn'], $password);
                if ($ok) {
                    if (!$user) {
                        $stmt = $pdo->prepare("INSERT INTO users (nome, login, email, upn, origem, is_active, created_at)
                            VALUES (:nome, :login, :email, :upn, 'ad', 1, GETDATE())");
                        $stmt->execute([
                            ':nome'  => $identity['displayName'] ?: $identity['sam'],
                            ':login' => $identity['sam'],
                            ':email' => $identity['email'],
                            ':upn'   => $identity['upn'],
                        ]);
                        $user = $this->auth->findByLogin($identity['sam']);
                    } else {
                        if ($user['origem'] !== 'ad') {
                            $pdo->prepare("UPDATE users SET origem='ad' WHERE id=:id")
                                ->execute([':id' => $user['id']]);
                        }
                        $user = $this->auth->findByLogin($user['login']);
                    }

                    if ($user && $user['is_active']) {
                        $this->auth->login($user);
                        redirect('');
                    }
                }
            }

            return $this->loginPage([
                'error' => 'Login ou senha inválidos.',
                'loginAttempt' => $login,
            ]);
        }

        $this->loginPage();
    }

    /**
     * Aceita login de rede (sAMAccountName), UPN (@valence.ad) ou e-mail (@valence.com.br).
     * O bind LDAP usa sempre o UPN do AD — nunca o e-mail corporativo, que o AD rejeita.
     */
    private function resolveLoginIdentity(string $raw): array {
        $norm = strtolower(trim($raw));
        $norm = str_replace('/', '\\', $norm);
        if (strpos($norm, '\\') !== false) {
            $norm = strtolower(ltrim(strrchr($norm, '\\') ?: $norm, '\\'));
        }

        $hasAt  = strpos($norm, '@') !== false;
        $local  = $hasAt ? explode('@', $norm, 2)[0] : $norm;
        $suffix = (string)($this->cfg['ldap']['user_upn_suffix'] ?? '@valence.ad');

        $user = null;
        if ($hasAt) {
            $user = $this->auth->findByEmail($norm) ?: $this->auth->findByUpn($norm);
        }
        if (!$user) {
            $user = $this->auth->findByLogin($local);
        }

        $ldapUser = null;
        if ($hasAt && (!$user || trim((string)($user['upn'] ?? '')) === '')) {
            $ldapUser = $this->ldap->findAccount($norm);
            if (!$user && $ldapUser && !empty($ldapUser['sAMAccountName'])) {
                $user = $this->auth->findByLogin((string)$ldapUser['sAMAccountName']);
            }
        }

        $sam = strtolower(trim((string)($user['login'] ?? ($ldapUser['sAMAccountName'] ?? $local))));
        $upn = trim((string)($user['upn'] ?? ''));
        if ($upn === '') {
            $upn = trim((string)($ldapUser['upn'] ?? ''));
        }
        if ($upn === '') {
            $upn = $sam . $suffix;
        }

        $email = null;
        if ($hasAt && !str_ends_with($norm, strtolower($suffix))) {
            $email = $norm;
        } elseif (!empty($user['email'])) {
            $email = $user['email'];
        } elseif (!empty($ldapUser['mail'])) {
            $email = $ldapUser['mail'];
        }

        return [
            'user'        => $user,
            'sam'         => $sam,
            'upn'         => $upn,
            'email'       => $email,
            'displayName' => $ldapUser['displayName'] ?? ($user['nome'] ?? $sam),
        ];
    }

    private function loginPage(array $extra = []): void {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        view('auth/login.php', array_merge(['config' => $this->cfg], $extra));
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
<?php
namespace Security;
use Database\Connection;
use PDO;

class Auth {
    private $pdo;
    private $cfg;

    public function __construct(array $cfg) {
        $this->pdo = Connection::get();
        $this->cfg = $cfg;
    }

    public function findByLogin(string $login) {
        $st = $this->pdo->prepare("SELECT * FROM users WHERE login = :login");
        $st->execute([':login' => $login]);
        return $st->fetch();
    }

    public function verifyLocalPassword(array $user, string $password): bool {
        if (!$user || $user['origem'] !== 'local' || empty($user['senha_hash'])) return false;
        return password_verify($password, $user['senha_hash']);
    }

    public function login(array $user) {
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'nome' => $user['nome'],
            'login' => $user['login'],
            'origem' => $user['origem']
        ];
        $this->pdo->prepare("UPDATE users SET last_login_at = GETDATE() WHERE id = :id")
            ->execute([':id' => (int)$user['id']]);
    }

    public function logout() {
        $_SESSION = [];
        session_destroy();
    }

    public static function requireAuth() {
        if (empty($_SESSION['user'])) {
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    public function changePassword(int $userId, string $newPassword) {
        $hash = password_hash($newPassword, $this->cfg['password_algo'], $this->cfg['password_options']);
        $st = $this->pdo->prepare("UPDATE users SET senha_hash=:h, updated_at=GETDATE() WHERE id=:id AND origem='local'");
        $st->execute([':h'=>$hash, ':id'=>$userId]);
    }

	public function createLocalUser(array $data) {
		// Normalização
		$nome  = trim($data['nome'] ?? '');
		$login = strtolower(trim($data['login'] ?? ''));
		$email = trim($data['email'] ?? '');
		$senha = $data['senha'] ?? '';

		if ($nome === '' || $login === '' || strlen($senha) < 8) {
			throw new \RuntimeException('Preencha Nome, Login e Senha (mínimo 8 caracteres).');
		}

		// Verifica duplicidade (qualquer origem)
		$dup = $this->pdo->prepare("SELECT 1 FROM users WHERE LOWER(login) = :login");
		$dup->execute([':login' => $login]);
		if ($dup->fetchColumn()) {
			throw new \RuntimeException('Já existe um usuário com este login.');
		}

		// Hash da senha
		$hash = password_hash($senha, $this->cfg['password_algo'], $this->cfg['password_options']);

		// Insert
		$st = $this->pdo->prepare("INSERT INTO users (nome, login, email, senha_hash, origem, is_active, created_at)
			VALUES (:nome, :login, :email, :hash, 'local', 1, GETDATE())");
		$st->execute([
			':nome'  => $nome,
			':login' => $login,
			':email' => $email !== '' ? $email : null,
			':hash'  => $hash
		]);

		return $this->pdo->lastInsertId();
	}

    public function updateLocalUser(int $id, array $data) {
        $fields = ['nome=:nome','email=:email','updated_at=GETDATE()'];
        $params = [':nome'=>$data['nome'], ':email'=>$data['email'] ?? null, ':id'=>$id];
        if (!empty($data['senha'])) {
            $hash = password_hash($data['senha'], $this->cfg['password_algo'], $this->cfg['password_options']);
            $fields[] = 'senha_hash=:hash';
            $params[':hash'] = $hash;
        }
        $sql = "UPDATE users SET ".implode(',', $fields)." WHERE id=:id AND origem='local'";
        $this->pdo->prepare($sql)->execute($params);
    }

    public function deleteUser(int $id) {
        // Evita excluir AD por padrão — se quiser permitir, remova a cláusula
        $this->pdo->prepare("DELETE FROM user_permissions WHERE user_id=:id")->execute([':id'=>$id]);
        $this->pdo->prepare("DELETE FROM users WHERE id=:id AND origem='local'")->execute([':id'=>$id]);
    }
}
?>
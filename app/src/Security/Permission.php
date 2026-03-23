<?php
namespace Security;
use Database\Connection;

class Permission {
    public static function level(string $code): int {
        if (empty($_SESSION['user']['id'])) return 0;
        $pdo = Connection::get();
        $st = $pdo->prepare("SELECT level FROM user_permissions WHERE user_id=:uid AND permission_code=:code");
        $st->execute([':uid'=>$_SESSION['user']['id'], ':code'=>$code]);
        $row = $st->fetch();
        return $row ? (int)$row['level'] : 0;
    }

    public static function require(string $code, int $minLevel) {
        $level = self::level($code);
        if ($level < $minLevel) {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }
}
?>
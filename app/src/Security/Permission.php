<?php
namespace Security;
use Database\Connection;

class Permission {

    /**
     * Returns true if the current user has the is_admin permission (level >= 1).
     * Result is cached for the duration of the request.
     */
    public static function isAdmin(): bool {
        static $cache = null;
        if ($cache !== null) return $cache;
        if (empty($_SESSION['user']['id'])) { $cache = false; return false; }
        $pdo = Connection::get();
        $st  = $pdo->prepare(
            "SELECT level FROM user_permissions WHERE user_id=:uid AND permission_code='is_admin'"
        );
        $st->execute([':uid' => (int)$_SESSION['user']['id']]);
        $row   = $st->fetch();
        $cache = $row && (int)$row['level'] >= 1;
        return $cache;
    }

    public static function level(string $code): int {
        if ($code !== 'is_admin' && self::isAdmin()) return 2;
        if (empty($_SESSION['user']['id'])) return 0;
        $pdo = Connection::get();
        $st = $pdo->prepare("SELECT level FROM user_permissions WHERE user_id=:uid AND permission_code=:code");
        $st->execute([':uid'=>$_SESSION['user']['id'], ':code'=>$code]);
        $row = $st->fetch();
        return $row ? (int)$row['level'] : 0;
    }

    public static function require(string $code, int $minLevel) {
        if (self::isAdmin()) return;
        $level = self::level($code);
        if ($level < $minLevel) {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }
}
?>
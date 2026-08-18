<?php
namespace Security;

use Database\Connection;

/**
 * Permissões do Almoxarifado por categoria.
 *
 * Códigos usados em user_permissions:
 *   almoxarifado.cat.{categoria_id}  — nivel 0-3
 *   almoxarifado.manage              — nivel 0-2 (gerenciar categorias/tipos)
 *
 * Níveis:
 *   0 = Sem acesso
 *   1 = Consultar (listar + visualizar)
 *   2 = Editar    (+ criar/editar itens, criar/operar empréstimos)
 *   3 = Gerenciar (+ alterar status do item)
 */
class AlmoxPermission
{
    /** Cache por categoria_id para evitar N queries por requisição. */
    private static array $cache = [];

    /** Limpa o cache (útil em testes). */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private static function isGlobalAdmin(): bool
    {
        return Permission::isAdmin() || Permission::level('users.manage') >= 2;
    }

    public static function categoryLevel(int $categoriaId): int
    {
        if (self::isGlobalAdmin()) return 3;
        if (empty($_SESSION['user']['id'])) return 0;

        if (!array_key_exists($categoriaId, self::$cache)) {
            $code = 'almoxarifado.cat.' . $categoriaId;
            $pdo  = Connection::get();
            $st   = $pdo->prepare(
                "SELECT level FROM dbo.user_permissions
                 WHERE user_id = :uid AND permission_code = :code"
            );
            $st->execute([':uid' => (int)$_SESSION['user']['id'], ':code' => $code]);
            $row = $st->fetch();
            self::$cache[$categoriaId] = $row ? (int)$row['level'] : 0;
        }

        return self::$cache[$categoriaId];
    }

    public static function requireCategory(int $categoriaId, int $minLevel): void
    {
        if (self::categoryLevel($categoriaId) < $minLevel) {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }

    public static function preloadForCategories(array $catIds): void
    {
        if (self::isGlobalAdmin()) {
            foreach ($catIds as $id) {
                self::$cache[(int)$id] = 3;
            }
            return;
        }
        if (empty($catIds) || empty($_SESSION['user']['id'])) return;

        $catIds = array_map('intval', $catIds);
        $placeholders = implode(',', array_fill(0, count($catIds), '?'));
        $codes = array_map(fn($id) => 'almoxarifado.cat.' . $id, $catIds);

        $pdo  = Connection::get();
        $stmt = $pdo->prepare(
            "SELECT permission_code, level FROM dbo.user_permissions
             WHERE user_id = ? AND permission_code IN ($placeholders)"
        );
        $stmt->execute(array_merge([(int)$_SESSION['user']['id']], $codes));

        // Inicializa todos como 0, depois sobrescreve com o que vier do BD
        foreach ($catIds as $id) {
            self::$cache[$id] = 0;
        }
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            // extrai o ID da categoria do código "almoxarifado.cat.{id}"
            $parts = explode('.', $row['permission_code']);
            $id = (int)end($parts);
            self::$cache[$id] = (int)$row['level'];
        }
    }

    public static function accessibleCategoryIds(int $minLevel = 1): ?array
    {
        if (self::isGlobalAdmin()) return null; // null = sem restrição

        if (empty($_SESSION['user']['id'])) return [];

        $pdo  = Connection::get();
        $stmt = $pdo->prepare(
            "SELECT permission_code, level FROM dbo.user_permissions
             WHERE user_id = :uid
               AND permission_code LIKE 'almoxarifado.cat.%'
               AND level >= :lvl"
        );
        $stmt->execute([
            ':uid' => (int)$_SESSION['user']['id'],
            ':lvl' => $minLevel,
        ]);
        $ids = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $parts = explode('.', $row['permission_code']);
            $id    = (int)end($parts);
            $ids[] = $id;
            self::$cache[$id] = (int)$row['level'];
        }
        return $ids;
    }

    public static function getForUser(int $userId): array
    {
        $pdo  = Connection::get();
        $stmt = $pdo->prepare(
            "SELECT permission_code, level FROM dbo.user_permissions
             WHERE user_id = :uid AND permission_code LIKE 'almoxarifado.cat.%'"
        );
        $stmt->execute([':uid' => $userId]);
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $parts = explode('.', $row['permission_code']);
            $id    = (int)end($parts);
            $result[$id] = (int)$row['level'];
        }
        return $result;
    }

    public static function saveForUser(int $userId, array $categoriaLevels): void
    {
        $pdo = Connection::get();
        foreach ($categoriaLevels as $catId => $nivel) {
            $catId = (int)$catId;
            $nivel = max(0, min(3, (int)$nivel));
            $code  = 'almoxarifado.cat.' . $catId;

            $upd = $pdo->prepare(
                "UPDATE dbo.user_permissions SET level = :lvl
                 WHERE user_id = :uid AND permission_code = :code"
            );
            $upd->execute([':lvl' => $nivel, ':uid' => $userId, ':code' => $code]);

            if ($upd->rowCount() === 0) {
                // Garante que o código existe em permissions
                $chk = $pdo->prepare(
                    "SELECT 1 FROM dbo.permissions WHERE code = :code"
                );
                $chk->execute([':code' => $code]);
                if (!$chk->fetch()) {
                    $cat = $pdo->prepare(
                        "SELECT nome FROM dbo.categorias WHERE id = :id"
                    );
                    $cat->execute([':id' => $catId]);
                    $catRow = $cat->fetch();
                    $nome   = $catRow ? $catRow['nome'] : "Categoria $catId";
                    $pdo->prepare(
                        "INSERT INTO dbo.permissions (code, name)
                         VALUES (:code, :name)"
                    )->execute([':code' => $code, ':name' => "Almoxarifado — Categoria: $nome"]);
                }

                $pdo->prepare(
                    "INSERT INTO dbo.user_permissions (user_id, permission_code, level)
                     VALUES (:uid, :code, :lvl)"
                )->execute([':uid' => $userId, ':code' => $code, ':lvl' => $nivel]);
            }
        }
    }

    public static function registerCategory(int $categoriaId, string $categoriaNome): void
    {
        $code = 'almoxarifado.cat.' . $categoriaId;
        $pdo  = Connection::get();
        $chk  = $pdo->prepare("SELECT 1 FROM dbo.permissions WHERE code = :code");
        $chk->execute([':code' => $code]);
        if (!$chk->fetch()) {
            $pdo->prepare(
                "INSERT INTO dbo.permissions (code, name) VALUES (:code, :name)"
            )->execute([
                ':code' => $code,
                ':name' => 'Almoxarifado — Categoria: ' . $categoriaNome,
            ]);
        }
    }

    public static function unregisterCategory(int $categoriaId): void
    {
        $code = 'almoxarifado.cat.' . $categoriaId;
        $pdo  = Connection::get();
        $pdo->prepare("DELETE FROM dbo.user_permissions WHERE permission_code = :code")
            ->execute([':code' => $code]);
        $pdo->prepare("DELETE FROM dbo.permissions WHERE code = :code")
            ->execute([':code' => $code]);
    }
}

<?php
namespace Database;

use PDO;
use PDOException;

class OracleConnection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        global $config;
        $cfg = $config['db']['oracle'] ?? [];

        if (empty($cfg['host']) || empty($cfg['service'])) {
            throw new \RuntimeException(
                'Conexão Oracle não configurada. Preencha $config[\'db\'][\'oracle\'] em config/config.php.'
            );
        }

        $host    = $cfg['host'];
        $port    = $cfg['port']    ?? 1521;
        $service = $cfg['service'];
        $charset = $cfg['charset'] ?? 'UTF8';

        $dsn = "oci:dbname=//{$host}:{$port}/{$service};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            return self::$pdo;
        } catch (PDOException $e) {
            throw new \RuntimeException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}



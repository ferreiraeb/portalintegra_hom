<?php
namespace Database;

use PDO;
use PDOException;

class Connection {
    private static $pdo;

    public static function get() {
        global $config;

        if (self::$pdo) {
            return self::$pdo;
        }

        $db = $config['db'];

        if ($db['driver'] !== 'sqlsrv') {
            throw new \RuntimeException('Driver não implementado nesta fase.');
        }

        // ✅ Parâmetros SSL explícitos (ODBC Driver 18)
        $encrypt = isset($db['encrypt']) && $db['encrypt'] ? 'yes' : 'no';
        $trust   = isset($db['trust_server_certificate']) && $db['trust_server_certificate'] ? 'yes' : 'no';

        $dsn = sprintf(
            "sqlsrv:Server=%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s",
            $db['server'],
            $db['database'],
            $encrypt,
            $trust
        );

        try {
            self::$pdo = new PDO(
                $dsn,
                $db['username'],
                $db['password'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            return self::$pdo;

        } catch (PDOException $e) {
            die('Erro de conexão MSSQL: ' . $e->getMessage());
        }
    }
}

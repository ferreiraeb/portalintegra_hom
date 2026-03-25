<?php
namespace Database;
use PDO;
use PDOException;

class Connection {
    private static $pdo;

    public static function get() {
        global $config;
        if (self::$pdo) return self::$pdo;

        $db = $config['db'];
        if ($db['driver'] !== 'sqlsrv') {
            throw new \RuntimeException('Driver não implementado nesta fase.');
        }

        //$dsn = "sqlsrv:Server={$db['server']};Database={$db['database']}";
        
          $dsn = sprintf(
                    "sqlsrv:Server=%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s",
                    $db['server'],
                    $db['database'],
                    $encrypt,
                    $trust
                );


        // ODBC Driver 18 força Encrypt=yes por padrão.
        // Para servidores com certificado self-signed é preciso
        // adicionar TrustServerCertificate=1 (e Encrypt explícito).
        if (!empty($db['encrypt'])) {
            $dsn .= ';Encrypt=1';
        }
        if (!empty($db['trust_server_certificate'])) {
            $dsn .= ';TrustServerCertificate=1';
        }
        try {
            $pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            self::$pdo = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            die('Erro de conexão MSSQL: ' . $e->getMessage());
        }
    }
}
?>

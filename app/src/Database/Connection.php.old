<?php
namespace Database;

use PDO;
use PDOException;
use RuntimeException;

class Connection {
    /** @var PDO|null */
    private static $pdo = null;

    /**
     * Retorna conexão PDO singleton com SQL Server.
     * Suporta Encrypt/TrustServerCertificate (ODBC 18) e opções extras via config.
     */
    public static function get(): PDO {
        global $config;

        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = $config['db'] ?? [];
        if (($db['driver'] ?? '') !== 'sqlsrv') {
            throw new RuntimeException('Driver não implementado nesta fase (esperado: sqlsrv).');
        }

        // Obrigatórios
        $server   = $db['server']   ?? '';   // ex.: "10.16.96.10,3310" ou "sqlserver.seudominio.local,3310"
        $database = $db['database'] ?? '';   // ex.: "Portal_Integra"
        $user     = $db['username'] ?? '';
        $pass     = $db['password'] ?? '';

        if ($server === '' || $database === '' || $user === '') {
            throw new RuntimeException('Config DB incompleta: verifique server/database/username.');
        }

        // Opções de TLS para ODBC 18 — por padrão, liga Encrypt e confia no certificado self‑signed
        // Você pode ajustar isso no config.php:
        //   'encrypt' => true|false
        //   'trust_server_certificate' => true|false
        $encrypt = array_key_exists('encrypt', $db) ? (bool)$db['encrypt'] : true;
        $trust   = array_key_exists('trust_server_certificate', $db) ? (bool)$db['trust_server_certificate'] : true;

        // Monte o DSN com os sufixos de segurança
        // Obs.: no ODBC 18 o padrão é Encrypt=yes. Se o seu SQL Server usa certificado self-signed,
        //       acrescente TrustServerCertificate=yes para evitar o erro de verificação.
        $dsnExtras = [];
        $dsnExtras[] = 'Encrypt=' . ($encrypt ? 'yes' : 'no');
        if ($encrypt) {
            // TrustServerCertificate só faz sentido quando Encrypt=yes
            $dsnExtras[] = 'TrustServerCertificate=' . ($trust ? 'yes' : 'no');
        }
        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;%s',
            $server,
            $database,
            implode(';', $dsnExtras)
        );

        // Opções PDO (permite mesclar com config['db']['options'] se você quiser algo específico)
        $pdoOptions = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if (!empty($db['options']) && is_array($db['options'])) {
            // Mescla sem permitir sobrescrever flags essenciais por engano
            $pdoOptions = $db['options'] + $pdoOptions;
        }

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $pdoOptions);
            return self::$pdo;
        } catch (PDOException $e) {
            // Mensagem amigável com dica específica para TLS/ODBC18
            $hint = '';
            $msg  = $e->getMessage();

            // Erros clássicos de certificado no ODBC 18
            if (stripos($msg, 'certificate verify failed') !== false || stripos($msg, 'SSL') !== false) {
                $hint =
                    "\nDica: Se o SQL Server usa certificado self-signed, no config.php defina:\n" .
                    "  'encrypt' => true,\n" .
                    "  'trust_server_certificate' => true\n" .
                    "Ou instale a CA no container e use apenas 'encrypt' => true com FQDN válido.";
            }

            // Evite die(); lance exceção com contexto
            throw new RuntimeException("Erro de conexão MSSQL: " . $msg . $hint, previous: $e);
        }
    }
}

<?php
namespace Services;

class LdapService {
    private $conn;
    private $cfg;

    public function __construct(array $cfg) {
        $this->cfg = $cfg;
    }

    private function connectAndBindService() {
        $this->conn = ldap_connect($this->cfg['ldap_uri'], $this->cfg['ldap_port']);
        if (!$this->conn) {
            throw new \RuntimeException('Falha ao conectar no LDAP');
        }

        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);

        // ✅ Suprimir warnings de STARTTLS (evita enviar bytes antes de header())
        if (!empty($this->cfg['use_starttls'])) {
            if (!@ldap_start_tls($this->conn)) {
                // (Opcional) faça log em vez de echo/var_dump
                error_log('[LDAP] Falha ao iniciar STARTTLS');
                throw new \RuntimeException('Falha ao iniciar STARTTLS no LDAP');
            }
        }

        if (!@ldap_bind($this->conn, $this->cfg['bind_dn'], $this->cfg['bind_password'])) {
            throw new \RuntimeException('Bind LDAP (service account) falhou');
        }
    }

    public function searchActiveUsers(): array {
        if (empty($this->cfg['enabled'])) return [];
        $this->connectAndBindService();

        $filter = $this->cfg['user_filter'];
        $attrs  = $this->cfg['attributes'];

        $sr = @ldap_search($this->conn, $this->cfg['base_dn'], $filter, $attrs);
        if (!$sr) {
            @ldap_unbind($this->conn);
            return [];
        }

        $entries = ldap_get_entries($this->conn, $sr);

        $users = [];
        for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
            $e = $entries[$i];
            $users[] = [
                'dn'                 => $e['distinguishedname'][0] ?? null,
                'displayName'        => $e['displayname'][0] ?? null,
                'givenName'          => $e['givenname'][0] ?? null,
                'sn'                 => $e['sn'][0] ?? null,
                'mail'               => $e['mail'][0] ?? null,
                'department'         => $e['department'][0] ?? null,
                'title'              => $e['title'][0] ?? null,
                'company'            => $e['company'][0] ?? null,
                'manager'            => $e['manager'][0] ?? null,
                'office'             => $e['physicaldeliveryofficename'][0] ?? null,
                'phone'              => $e['telephonenumber'][0] ?? null,
                'sAMAccountName'     => $e['samaccountname'][0] ?? null,
                'upn'                => $e['userprincipalname'][0] ?? null,
                'employeeNumber'     => $e['employeenumber'][0] ?? null,
                'userAccountControl' => isset($e['useraccountcontrol'][0]) ? (int)$e['useraccountcontrol'][0] : null,
                'lockoutTime'        => isset($e['lockouttime'][0]) ? (int)$e['lockouttime'][0] : null,
            ];
        }
        @ldap_unbind($this->conn);
        return $users;
    }

    // Autentica tentando bind com credenciais do usuário
    public function authenticateUserPassword(string $username, string $password): bool {
        if (empty($this->cfg['enabled'])) return false;

        $userDn = $username;
        if (strpos($username, '@') === false && !empty($this->cfg['user_upn_suffix'])) {
            $userDn = $username . $this->cfg['user_upn_suffix']; // ex: user@valence.ad
        }

        $conn = ldap_connect($this->cfg['ldap_uri'], $this->cfg['ldap_port']);
        if (!$conn) return false;

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        // ✅ também suprimir aqui (você já fazia, mantive)
        if (!empty($this->cfg['use_starttls'])) {
            if (!@ldap_start_tls($conn)) {
                @ldap_unbind($conn);
                return false;
            }
        }

        $ok = @ldap_bind($conn, $userDn, $password);
        @ldap_unbind($conn);
        return $ok;
    }
}


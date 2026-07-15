<?php
namespace Services;

use Database\Connection;
use Database\OracleConnection;
use PDO;

/**
 * Consulta aniversariantes do dia na view Oracle de colaboradores
 * e enriquece com e-mail/UPN do SQL Server (tabela users).
 */
class AniversarioService
{
    private const VIEW = 'SIRH.VW_RH_COLABORADORES';

    private PDO $sqlPdo;

    public function __construct(?PDO $sqlPdo = null)
    {
        $this->sqlPdo = $sqlPdo ?? Connection::get();
    }

    /**
     * Retorna colaboradores ativos cujo aniversário cai na data informada.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAniversariantesDoDia(?\DateTimeInterface $date = null): array
    {
        $date = $date ?? new \DateTimeImmutable('today');

        $pdo = OracleConnection::get();
        $stmt = $pdo->prepare(
            "SELECT DISTINCT CODPESSOA, NOMECOMPLETO, CPF, NASCIMENTO
             FROM " . self::VIEW . "
             WHERE STATUS = 'ATIVO'
               AND NASCIMENTO IS NOT NULL
               AND TO_CHAR(
                     CASE WHEN NASCIMENTO > SYSDATE
                          THEN ADD_MONTHS(NASCIMENTO, -1200)
                          ELSE NASCIMENTO
                     END,
                     'MM-DD'
                   ) = :mmdd
             ORDER BY NOMECOMPLETO"
        );
        $stmt->execute([':mmdd' => $date->format('m-d')]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $colaboradores = array_map(
            fn(array $row) => array_change_key_case($row, CASE_UPPER),
            $rows
        );

        return $this->enrichWithEmails($colaboradores);
    }

    /**
     * @param  array<int, array<string, mixed>> $colaboradores
     * @return array<int, array<string, mixed>>
     */
    public function enrichWithEmails(array $colaboradores): array
    {
        if (empty($colaboradores)) {
            return [];
        }

        $cpfs = array_values(array_filter(array_column($colaboradores, 'CPF')));
        $emailMap = $this->buildUsuarioContatoMap($cpfs);

        return array_map(function (array $row) use ($emailMap) {
            $cpf = (string)($row['CPF'] ?? '');
            $row['usuario_email'] = $emailMap[$cpf] ?? null;
            $row['primeiro_nome'] = $this->extractPrimeiroNome((string)($row['NOMECOMPLETO'] ?? ''));
            return $row;
        }, $colaboradores);
    }

    /**
     * @param  string[] $cpfs
     * @return array<string, string>
     */
    private function buildUsuarioContatoMap(array $cpfs): array
    {
        $map = [];
        if (empty($cpfs)) {
            return $map;
        }

        $placeholders = implode(',', array_map(fn(int $i) => ":cpf{$i}", array_keys($cpfs)));
        $st = $this->sqlPdo->prepare(
            "SELECT employeeNumber, email, upn FROM users
             WHERE employeeNumber IN ({$placeholders})
               AND (
                   NULLIF(LTRIM(RTRIM(email)), '') IS NOT NULL
                   OR NULLIF(LTRIM(RTRIM(upn)), '') IS NOT NULL
               )"
        );
        foreach ($cpfs as $i => $cpf) {
            $st->bindValue(":cpf{$i}", $cpf);
        }
        $st->execute();

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $email = trim((string)($row['email'] ?? ''));
            $upn   = trim((string)($row['upn'] ?? ''));
            $map[(string)$row['employeeNumber']] = $email !== '' ? $email : $upn;
        }

        return $map;
    }

    private function extractPrimeiroNome(string $nomeCompleto): string
    {
        $nome = trim(preg_replace('/\s+/', ' ', $nomeCompleto) ?? '');
        if ($nome === '') {
            return 'Colaborador(a)';
        }

        $partes = explode(' ', $nome);
        return $partes[0];
    }
}

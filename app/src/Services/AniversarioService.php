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

    /** Expressão Oracle que corrige NASCIMENTO com ano de 2 dígitos (bug YY). */
    private const NASC_CORRIGIDO = "CASE WHEN NASCIMENTO > SYSDATE"
        . " THEN ADD_MONTHS(NASCIMENTO, -1200)"
        . " ELSE NASCIMENTO END";

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
        $extraFields = $this->anivExtraFieldsSql();
        $stmt = $pdo->prepare(
            "SELECT DISTINCT CODPESSOA, NOMECOMPLETO, CPF, NASCIMENTO, {$extraFields}
             FROM " . self::VIEW . "
             WHERE STATUS = 'ATIVO'
               AND NASCIMENTO IS NOT NULL
               AND TO_CHAR(" . self::NASC_CORRIGIDO . ", 'MM-DD') = :mmdd
             ORDER BY NOMECOMPLETO"
        );
        $stmt->execute([':mmdd' => $date->format('m-d')]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $colaboradores = $this->mapRows($rows);

        return $this->enrichWithEmails($colaboradores);
    }

    /**
     * Retorna colaboradores ativos com aniversário na semana (segunda a domingo) da data informada.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAniversariantesDaSemana(?\DateTimeInterface $refDate = null): array
    {
        $ref    = \DateTimeImmutable::createFromInterface($refDate ?? new \DateTimeImmutable('today'));
        $monday = $ref->modify('-' . ((int)$ref->format('N') - 1) . ' days');

        $mmddList = [];
        for ($i = 0; $i < 7; $i++) {
            $mmddList[] = $monday->modify("+{$i} days")->format('m-d');
        }

        $colaboradores = $this->fetchByMmddList($mmddList);
        $orderMap      = array_flip($mmddList);

        usort($colaboradores, function (array $a, array $b) use ($orderMap): int {
            $ka = (string)($a['MMDD_ANIV'] ?? '');
            $kb = (string)($b['MMDD_ANIV'] ?? '');
            $oa = $orderMap[$ka] ?? 99;
            $ob = $orderMap[$kb] ?? 99;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            return strcasecmp((string)($a['NOMECOMPLETO'] ?? ''), (string)($b['NOMECOMPLETO'] ?? ''));
        });

        return $this->enrichWithEmails($colaboradores);
    }

    /**
     * Retorna colaboradores ativos com aniversário no mês informado (1–12).
     * Inclui DIA_ANIV (1–31) para agrupamento no calendário.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAniversariantesDoMes(int $month): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Mês inválido.');
        }

        $pdo = OracleConnection::get();
        $stmt = $pdo->prepare(
            "SELECT DISTINCT CODPESSOA, NOMECOMPLETO, CPF, NASCIMENTO,
                    TO_NUMBER(TO_CHAR(" . self::NASC_CORRIGIDO . ", 'DD')) AS DIA_ANIV
             FROM " . self::VIEW . "
             WHERE STATUS = 'ATIVO'
               AND NASCIMENTO IS NOT NULL
               AND TO_CHAR(" . self::NASC_CORRIGIDO . ", 'MM') = :mm
             ORDER BY DIA_ANIV, NOMECOMPLETO"
        );
        $stmt->execute([':mm' => sprintf('%02d', $month)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $colaboradores = $this->mapRows($rows);

        return $this->enrichWithEmails($colaboradores);
    }

    /**
     * @param  string[] $mmddList Valores no formato MM-DD
     * @return array<int, array<string, mixed>>
     */
    private function fetchByMmddList(array $mmddList): array
    {
        if (empty($mmddList)) {
            return [];
        }

        $pdo          = OracleConnection::get();
        $placeholders = implode(',', array_map(fn(int $i) => ":d{$i}", array_keys($mmddList)));
        $extraFields  = $this->anivExtraFieldsSql();

        $stmt = $pdo->prepare(
            "SELECT DISTINCT CODPESSOA, NOMECOMPLETO, CPF, NASCIMENTO, {$extraFields}
             FROM " . self::VIEW . "
             WHERE STATUS = 'ATIVO'
               AND NASCIMENTO IS NOT NULL
               AND TO_CHAR(" . self::NASC_CORRIGIDO . ", 'MM-DD') IN ({$placeholders})
             ORDER BY MMDD_ANIV, NOMECOMPLETO"
        );
        foreach ($mmddList as $i => $mmdd) {
            $stmt->bindValue(":d{$i}", $mmdd);
        }
        $stmt->execute();

        return $this->mapRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function anivExtraFieldsSql(): string
    {
        return "TO_NUMBER(TO_CHAR(" . self::NASC_CORRIGIDO . ", 'DD')) AS DIA_ANIV,"
            . " TO_NUMBER(TO_CHAR(" . self::NASC_CORRIGIDO . ", 'MM')) AS MES_ANIV,"
            . " TO_CHAR(" . self::NASC_CORRIGIDO . ", 'MM-DD') AS MMDD_ANIV";
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function mapRows(array $rows): array
    {
        return array_map(
            fn(array $row) => array_change_key_case($row, CASE_UPPER),
            $rows
        );
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

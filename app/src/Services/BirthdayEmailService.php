<?php
namespace Services;

/**
 * Monta e envia os e-mails de aniversário (grupo + individual).
 *
 * Enquanto birthday.test_mode = true, nenhum e-mail vai para o aniversariante
 * nem para a lista de produção: tudo é redirecionado para test_recipients.
 */
class BirthdayEmailService
{
    private const CID_HEAD = 'valence-bday-head';
    private const CID_FOOT = 'valence-bday-foot';

    private const DEFAULT_IMAGES = [
        'individual_head' => 'public/assets/img/aniversariante_a_head.png',
        'individual_foot' => 'public/assets/img/aniversariante_a_foot.png',
        'group_head'      => 'public/assets/img/aniversariantes_dia_a_head.png',
        'group_foot'      => 'public/assets/img/aniversariantes_dia_a_foot.png',
    ];

    private MailService $mail;
    private array $birthdayCfg;
    private string $projectRoot;

    public function __construct(MailService $mail, array $birthdayConfig, string $projectRoot)
    {
        $this->mail        = $mail;
        $this->birthdayCfg = $birthdayConfig;
        $this->projectRoot = rtrim($projectRoot, '/\\');
    }

    /**
     * Padrão: true. Só envia para colaboradores/lista de produção quando
     * birthday.test_mode for explicitamente false.
     */
    public function isTestMode(): bool
    {
        return (bool)($this->birthdayCfg['test_mode'] ?? true);
    }

    /**
     * Destinatários efetivos do e-mail de grupo (já aplicando modo teste).
     *
     * @param string[]|null $recipientsOverride
     * @return string[]
     */
    public function resolveGroupRecipients(?array $recipientsOverride = null): array
    {
        if ($recipientsOverride !== null) {
            return $this->normalizeEmails($recipientsOverride);
        }

        if ($this->isTestMode()) {
            $test = $this->resolveTestRecipients();
            if (empty($test)) {
                throw new \RuntimeException(
                    'Modo teste ativo: configure birthday.test_recipients (ou individual_override_email).'
                );
            }
            return $test;
        }

        $recipients = $this->normalizeEmails($this->birthdayCfg['group_recipients'] ?? []);
        if (empty($recipients)) {
            throw new \RuntimeException('Nenhum destinatário de grupo configurado (birthday.group_recipients).');
        }

        return $recipients;
    }

    /**
     * @param array<int, array<string, mixed>> $aniversariantes
     * @param string[]|null                      $recipientsOverride Destinatários do e-mail de grupo
     */
    public function sendGroupEmail(
        array $aniversariantes,
        bool $dryRun = false,
        ?array $recipientsOverride = null,
        ?\DateTimeInterface $date = null
    ): void {
        $date       = $date ?? new \DateTimeImmutable('today');
        $recipients = $this->resolveGroupRecipients($recipientsOverride);
        if (empty($recipients)) {
            throw new \RuntimeException('Nenhum destinatário de grupo informado.');
        }

        $subject = count($aniversariantes) === 1
            ? 'Aniversariante do dia — Grupo Valence'
            : 'Aniversariantes do dia — Grupo Valence';

        $html   = $this->buildGroupHtml($aniversariantes, $date);
        $images = $this->inlineImages('group');

        if ($dryRun) {
            return;
        }

        $this->mail->send($recipients, $subject, $html, null, $images);
    }

    /**
     * Resumo de sábado+domingo, enviado na segunda-feira.
     * Layout de grupo, imagens do e-mail individual.
     *
     * @param array<int, array<string, mixed>> $aniversariantes
     * @param string[]|null                      $recipientsOverride
     */
    public function sendWeekendGroupEmail(
        array $aniversariantes,
        bool $dryRun = false,
        ?array $recipientsOverride = null,
        ?\DateTimeInterface $date = null
    ): void {
        if (empty($aniversariantes)) {
            return;
        }

        $date       = $date ?? new \DateTimeImmutable('today');
        $recipients = $this->resolveGroupRecipients($recipientsOverride);
        if (empty($recipients)) {
            throw new \RuntimeException('Nenhum destinatário de grupo informado.');
        }

        $html   = $this->buildWeekendHtml($aniversariantes, $date);
        $images = $this->inlineImages('individual');

        if ($dryRun) {
            return;
        }

        $this->mail->send($recipients, 'Aniversariantes do fim de semana — Grupo Valence', $html, null, $images);
    }

    /**
     * @param array<int, array<string, mixed>> $aniversariantes
     * @param string[]|null                      $recipientsOverride Redireciona todos os individuais para estes endereços
     * @return array<int, array{to: string, nome: string}>
     */
    public function sendIndividualEmails(
        array $aniversariantes,
        bool $dryRun = false,
        ?array $recipientsOverride = null,
        ?\DateTimeInterface $date = null
    ): array {
        $date = $date ?? new \DateTimeImmutable('today');

        $overrideList = null;
        if ($recipientsOverride !== null) {
            $overrideList = $this->normalizeEmails($recipientsOverride);
        } elseif ($this->isTestMode()) {
            $overrideList = $this->resolveTestRecipients();
            if (empty($overrideList)) {
                throw new \RuntimeException(
                    'Modo teste ativo: configure birthday.test_recipients (ou individual_override_email).'
                );
            }
        }

        $sent   = [];
        $images = $this->inlineImages('individual');

        foreach ($aniversariantes as $row) {
            $nome = (string)($row['primeiro_nome'] ?? $row['NOMECOMPLETO'] ?? 'Colaborador(a)');

            if ($overrideList !== null) {
                $toList = $overrideList;
            } else {
                $override = $this->getIndividualOverrideEmail();
                $single   = $override ?? trim((string)($row['usuario_email'] ?? ''));
                if ($single === '') {
                    continue;
                }
                $toList = [$single];
            }

            if (empty($toList)) {
                continue;
            }

            $subject = 'Feliz aniversário, ' . $nome . '!';
            $html    = $this->buildIndividualHtml($nome, $date);

            if (!$dryRun) {
                $this->mail->send($toList, $subject, $html, null, $images);
            }

            $sent[] = ['to' => implode(', ', $toList), 'nome' => $nome];
        }

        return $sent;
    }

    public function getIndividualOverrideEmail(): ?string
    {
        $override = $this->birthdayCfg['individual_override_email'] ?? null;
        if ($override === null) {
            return null;
        }

        $override = trim((string)$override);
        return $override !== '' ? $override : null;
    }

    /** @return string[] */
    public function resolveTestRecipients(): array
    {
        $list = $this->birthdayCfg['test_recipients'] ?? null;
        if (is_array($list)) {
            $emails = $this->normalizeEmails($list);
            if (!empty($emails)) {
                return $emails;
            }
        }

        $override = $this->getIndividualOverrideEmail();
        return $override !== null ? [$override] : [];
    }

    /** @param array<int, array<string, mixed>> $aniversariantes */
    private function buildGroupHtml(array $aniversariantes, \DateTimeInterface $date): string
    {
        return $this->renderTemplate('aniversario_grupo.html', [
            '{{HEAD_SRC}}' => 'cid:' . self::CID_HEAD,
            '{{FOOT_SRC}}' => 'cid:' . self::CID_FOOT,
            '{{DATA}}'     => $date->format('d/m/Y'),
            '{{LISTA}}'    => $this->buildListaHtml($aniversariantes, false),
        ]);
    }

    /** @param array<int, array<string, mixed>> $aniversariantes */
    private function buildWeekendHtml(array $aniversariantes, \DateTimeInterface $date): string
    {
        return $this->renderTemplate('aniversario_fim_de_semana.html', [
            '{{HEAD_SRC}}' => 'cid:' . self::CID_HEAD,
            '{{FOOT_SRC}}' => 'cid:' . self::CID_FOOT,
            '{{DATA}}'     => $date->format('d/m/Y'),
            '{{LISTA}}'    => $this->buildListaHtml($aniversariantes, true),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $aniversariantes
     */
    private function buildListaHtml(array $aniversariantes, bool $incluirDiaSemana): string
    {
        $items = [];
        foreach ($aniversariantes as $row) {
            $nome = trim((string)($row['NOMECOMPLETO'] ?? ''));
            if ($nome === '') {
                continue;
            }

            $detalhes = $this->formatGrupoDetalhes($row, $incluirDiaSemana);
            $detalheHtml = $detalhes !== ''
                ? '<p style="margin:0;color:#5A6A75;font-size:13px;line-height:1.4;">' . $this->e($detalhes) . '</p>'
                : '';

            $items[] = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFF8E1;border-radius:8px;">'
                . '<tr>'
                . '<td width="5" style="background-color:#F9C70C;border-radius:8px 0 0 8px;font-size:0;line-height:0;">&nbsp;</td>'
                . '<td style="padding:14px 18px;">'
                . '<p style="margin:0 0 3px;color:#0A2E42;font-size:16px;line-height:1.4;font-weight:700;">' . $this->e($nome) . '</p>'
                . $detalheHtml
                . '</td></tr></table>';
        }

        $lista = '';
        $last  = count($items) - 1;
        foreach ($items as $i => $card) {
            $pad   = $i === $last ? '' : ' style="padding-bottom:10px;"';
            $lista .= '<tr><td' . $pad . '>' . $card . '</td></tr>';
        }

        return $lista;
    }

    private function buildIndividualHtml(string $primeiroNome, \DateTimeInterface $date): string
    {
        return $this->renderTemplate('aniversario_individual.html', [
            '{{HEAD_SRC}}'      => 'cid:' . self::CID_HEAD,
            '{{FOOT_SRC}}'      => 'cid:' . self::CID_FOOT,
            '{{DATA}}'          => $date->format('d/m/Y'),
            '{{PRIMEIRO_NOME}}' => $this->e($primeiroNome),
        ]);
    }

    /** @param array<string, string> $replacements */
    private function renderTemplate(string $filename, array $replacements): string
    {
        $path = $this->projectRoot . '/views/emails/' . $filename;
        if (!is_file($path)) {
            throw new \RuntimeException('Template de e-mail não encontrado: ' . $filename);
        }

        $html = (string)file_get_contents($path);
        return strtr($html, $replacements);
    }

    /** @param array<string, mixed> $row */
    private function formatGrupoDetalhes(array $row, bool $incluirDiaSemana = false): string
    {
        $partes = [];
        if ($incluirDiaSemana) {
            $dia  = trim((string)($row['DIA_SEMANA_LABEL'] ?? ''));
            $data = trim((string)($row['DATA_ANIV_LABEL'] ?? ''));
            $quando = trim($dia . ($data !== '' ? ', ' . $data : ''), " \t,");
            if ($quando !== '') {
                $partes[] = $quando;
            }
        }

        foreach ([
            trim((string)($row['EMPRESA'] ?? '')),
            trim((string)($row['UNIDADE'] ?? '')),
            trim((string)($row['SETOR'] ?? '')),
        ] as $parte) {
            if ($parte !== '') {
                $partes[] = $parte;
            }
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  'group'|'individual' $kind
     * @return array<int, array{cid: string, path: string, mime?: string}>
     */
    private function inlineImages(string $kind): array
    {
        $head = $this->resolveImagePath($kind . '_head');
        $foot = $this->resolveImagePath($kind . '_foot');
        $out  = [];

        if ($head !== null) {
            $out[] = ['cid' => self::CID_HEAD, 'path' => $head];
        }
        if ($foot !== null) {
            $out[] = ['cid' => self::CID_FOOT, 'path' => $foot];
        }

        return $out;
    }

    private function resolveImagePath(string $key): ?string
    {
        $images = $this->birthdayCfg['images'] ?? [];
        $rel    = is_array($images) ? ($images[$key] ?? null) : null;
        if (!is_string($rel) || trim($rel) === '') {
            $rel = self::DEFAULT_IMAGES[$key] ?? null;
        }
        if ($rel === null) {
            return null;
        }

        $full = $this->projectRoot . '/' . ltrim(str_replace(['\\', '/'], '/', $rel), '/');
        return is_file($full) ? $full : null;
    }

    /** @param mixed $emails */
    private function normalizeEmails($emails): array
    {
        if (!is_array($emails)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn($v) => trim((string)$v),
            $emails
        ), static fn(string $v) => $v !== ''));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

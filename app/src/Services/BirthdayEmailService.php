<?php
namespace Services;

/**
 * Monta e envia os e-mails de aniversário (grupo + individual).
 */
class BirthdayEmailService
{
    private const BANNER_CID = 'valence-birthday-banner';

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
     * @param array<int, array<string, mixed>> $aniversariantes
     * @param string[]|null                      $recipientsOverride Destinatários do e-mail de grupo
     */
    public function sendGroupEmail(array $aniversariantes, bool $dryRun = false, ?array $recipientsOverride = null): void
    {
        $recipients = $recipientsOverride ?? ($this->birthdayCfg['group_recipients'] ?? []);
        $recipients = array_values(array_filter(array_map('trim', $recipients)));
        if (empty($recipients)) {
            throw new \RuntimeException('Nenhum destinatário de grupo configurado (birthday.group_recipients).');
        }

        $nomes = array_map(
            fn(array $row) => (string)($row['NOMECOMPLETO'] ?? ''),
            $aniversariantes
        );
        $nomes = array_values(array_filter($nomes));

        $subject = count($nomes) === 1
            ? 'Aniversariante do dia — Grupo Valence'
            : 'Aniversariantes do dia — Grupo Valence';

        $html = $this->buildGroupHtml($nomes);

        if ($dryRun) {
            return;
        }

        $this->mail->send($recipients, $subject, $html, null, $this->bannerInlineImages());
    }

    /**
     * @param array<int, array<string, mixed>> $aniversariantes
     * @param string[]|null                      $recipientsOverride Redireciona todos os individuais para estes endereços
     * @return array<int, array{to: string, nome: string}>
     */
    public function sendIndividualEmails(
        array $aniversariantes,
        bool $dryRun = false,
        ?array $recipientsOverride = null
    ): array {
        $overrideList = null;
        if ($recipientsOverride !== null) {
            $overrideList = array_values(array_filter(array_map('trim', $recipientsOverride)));
        }

        $sent = [];

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
            $html    = $this->buildIndividualHtml($nome);

            if (!$dryRun) {
                $this->mail->send($toList, $subject, $html, null, $this->bannerInlineImages());
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

    /** @param string[] $nomes */
    private function buildGroupHtml(array $nomes): string
    {
        $lista = '';
        foreach ($nomes as $nome) {
            $lista .= '<li style="margin:6px 0;color:#1A303B;font-size:16px;">' . $this->e($nome) . '</li>';
        }

        $banner = $this->bannerImgTag();

        return $this->wrapHtml(
            $banner
            . '<p style="margin:0 0 16px;color:#1A303B;font-size:16px;line-height:1.6;">Olá, pessoal!</p>'
            . '<p style="margin:0 0 16px;color:#1A303B;font-size:16px;line-height:1.6;">'
            . 'O <strong>Grupo Valence</strong> parabeniza os colaboradores que celebram mais um ano de vida neste dia:</p>'
            . '<ul style="margin:0 0 0 20px;padding:0;">' . $lista . '</ul>'
            . '<br>'
            . '<p style="margin:0 0 16px;color:#1A303B;font-size:16px;line-height:1.6;">'
            . 'Desejamos a todos muita saúde, felicidade, sucesso e realizações neste novo ciclo.</p>'
            . '<p style="margin:0;color:#1A303B;font-size:16px;line-height:1.6;"><strong>Parabéns e muitas felicidades!</strong></p>'
        );
    }

    private function buildIndividualHtml(string $primeiroNome): string
    {
        $banner = $this->bannerImgTag();

        return $this->wrapHtml(
            $banner
            . '<p style="margin:0 0 16px;color:#1A303B;font-size:16px;line-height:1.6;">Olá, <strong style="color:#F9C70C;">'
            . $this->e($primeiroNome) . '</strong>!</p>'
            . '<p style="margin:0 0 16px;color:#1A303B;font-size:16px;line-height:1.6;">'
            . 'Hoje celebramos uma data muito especial: o seu aniversário.</p>'
            . '<p style="margin:0 0 16px;color:#1A303B;font-size:16px;line-height:1.6;">'
            . 'O <strong>Grupo Valence</strong> parabeniza você por mais um ano de vida e deseja que este novo ciclo seja repleto de saúde, felicidade, conquistas e realizações.</p>'
            . '<p style="margin:0 0 16px;color:#1A303B;font-size:16px;line-height:1.6;">'
            . 'Agradecemos por sua dedicação, comprometimento e pela contribuição que você oferece diariamente para o fortalecimento da nossa equipe.</p>'
            . '<p style="margin:0 0 16px;color:#1A303B;font-size:16px;line-height:1.6;">'
            . 'Que este seja um ano de novas oportunidades, aprendizados e momentos marcantes.</p>'
            . '<p style="margin:0;color:#1A303B;font-size:16px;line-height:1.6;"><strong>Feliz aniversário e aproveite o seu dia!</strong></p>'
        );
    }

    private function wrapHtml(string $content): string
    {
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#FAF9F6;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FAF9F6;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FEFBEA;border:1px solid #F9C70C;border-radius:12px;overflow:hidden;">'
            . '<tr><td style="padding:28px 32px 32px;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;">'
            . $content
            . '<hr style="border:none;border-top:2px solid #F9C70C;margin:28px 0 16px;">'
            . '<p style="margin:0;color:#808080;font-size:13px;text-align:center;">Grupo Valence</p>'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    private function bannerImgTag(): string
    {
        if ($this->resolveBannerPath() === null) {
            return '<h1 style="margin:0 0 24px;color:#1A303B;font-size:28px;text-align:center;">FELIZ ANIVERSÁRIO!</h1>';
        }

        return '<img src="cid:' . self::BANNER_CID . '" alt="Feliz Aniversário — Grupo Valence" '
            . 'style="display:block;width:100%;max-width:536px;height:auto;margin:0 auto 24px;border-radius:8px;">';
    }

    /** @return array<int, array{cid: string, path: string, mime?: string}> */
    private function bannerInlineImages(): array
    {
        $path = $this->resolveBannerPath();
        if ($path === null) {
            return [];
        }

        return [[
            'cid'  => self::BANNER_CID,
            'path' => $path,
        ]];
    }

    private function resolveBannerPath(): ?string
    {
        $path = (string)($this->birthdayCfg['banner_path'] ?? 'public/assets/img/aniversario.png');
        $full = $this->projectRoot . '/' . ltrim(str_replace(['\\', '/'], '/', $path), '/');

        return is_file($full) ? $full : null;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

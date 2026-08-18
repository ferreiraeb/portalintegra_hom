#!/usr/bin/env php
<?php
/**
 * Envia e-mails diários de aniversário:
 *   1) Resumo para o grupo (TI@valence.com.br)
 *   2) Parabéns individual para cada aniversariante
 *
 * Uso:
 *   php cli/send_birthday_emails.php
 *   php cli/send_birthday_emails.php --dry-run
 *   php cli/send_birthday_emails.php --date=2026-07-13
 *   php cli/send_birthday_emails.php --dry-run --date=2026-03-15
 */
require __DIR__ . '/../bootstrap.php';

use Services\AniversarioService;
use Services\BirthdayEmailService;
use Services\MailService;

function birthday_log(string $message, bool $stderr = false): void
{
    $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    if ($stderr) {
        fwrite(STDERR, $line);
    }
    echo $line;
}

function parseCliArgs(array $argv): array
{
    $opts = [
        'dry_run' => false,
        'date'    => null,
        'help'    => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $opts['dry_run'] = true;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
            continue;
        }
        if (str_starts_with($arg, '--date=')) {
            $opts['date'] = substr($arg, 7);
            continue;
        }
        throw new \InvalidArgumentException("Argumento desconhecido: {$arg}");
    }

    return $opts;
}

function printHelp(): void
{
    echo <<<TXT
Envio de e-mails de aniversário — Portal Integra

Uso:
  php cli/send_birthday_emails.php [--dry-run] [--date=AAAA-MM-DD]

Opções:
  --dry-run          Simula o envio (lista destinatários sem enviar)
  --date=AAAA-MM-DD  Usa outra data para buscar aniversariantes (testes)
  -h, --help         Exibe esta ajuda

Configuração: config/config.php → seções "mail" e "birthday"

TXT;
}

try {
    $opts = parseCliArgs($argv);
    if ($opts['help']) {
        printHelp();
        exit(0);
    }

    $dryRun = (bool)$opts['dry_run'];
    $refDate = new \DateTimeImmutable('today');
    if ($opts['date'] !== null) {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $opts['date']);
        if (!$parsed || $parsed->format('Y-m-d') !== $opts['date']) {
            throw new \InvalidArgumentException('Data inválida. Use o formato AAAA-MM-DD.');
        }
        $refDate = $parsed;
    }

    birthday_log('send_birthday_emails iniciado' . ($dryRun ? ' [DRY-RUN]' : ''));
    birthday_log('Data de referência: ' . $refDate->format('d/m/Y'));

    if (!($config['birthday']['enabled'] ?? true)) {
        birthday_log('Envio de aniversários desabilitado (birthday.enabled = false). Encerrando.');
        exit(0);
    }

    $aniversarioService = new AniversarioService();
    $aniversariantes    = $aniversarioService->getAniversariantesDoDia($refDate);

    birthday_log(sprintf('Aniversariantes encontrados: %d', count($aniversariantes)));

    if (empty($aniversariantes)) {
        birthday_log('Nenhum aniversariante para esta data. Nenhum e-mail enviado.');
        exit(0);
    }

    foreach ($aniversariantes as $row) {
        $emailInfo = $row['usuario_email'] ?? '—';
        birthday_log(sprintf(
            '  • %s (CPF %s) — contato: %s',
            (string)($row['NOMECOMPLETO'] ?? ''),
            (string)($row['CPF'] ?? ''),
            (string)$emailInfo
        ));
    }

    $mailService         = new MailService($config['mail'] ?? []);
    $birthdayEmailService = new BirthdayEmailService(
        $mailService,
        $config['birthday'] ?? [],
        dirname(__DIR__)
    );

    $groupRecipients = $config['birthday']['group_recipients'] ?? [];
    birthday_log('E-mail grupo → ' . implode(', ', $groupRecipients));

    if ($dryRun) {
        $birthdayEmailService->sendGroupEmail($aniversariantes, true);
        birthday_log('[DRY-RUN] E-mail de grupo seria enviado.');
    } else {
        $birthdayEmailService->sendGroupEmail($aniversariantes, false);
        birthday_log('E-mail de grupo enviado.');
    }

    $override = $birthdayEmailService->getIndividualOverrideEmail();
    if ($override !== null) {
        birthday_log('Modo teste: e-mails individuais redirecionados para ' . $override);
    }

    $sentIndividuals = $birthdayEmailService->sendIndividualEmails($aniversariantes, $dryRun);

    if (empty($sentIndividuals)) {
        birthday_log('Nenhum e-mail individual enviado (sem endereço disponível).', true);
    } else {
        foreach ($sentIndividuals as $item) {
            $prefix = $dryRun ? '[DRY-RUN] ' : '';
            birthday_log($prefix . 'E-mail individual → ' . $item['to'] . ' (' . $item['nome'] . ')');
        }
    }

    birthday_log(sprintf(
        'send_birthday_emails concluído: %d aniversariante(s), %d e-mail(s) individual(is)%s.',
        count($aniversariantes),
        count($sentIndividuals),
        $dryRun ? ' [simulação]' : ''
    ));
    exit(0);
} catch (\Throwable $e) {
    birthday_log('ERRO send_birthday_emails: ' . $e->getMessage(), true);
    exit(1);
}

#!/usr/bin/env php
<?php
/**
 * Envia e-mails diários de aniversário:
 *   1) Resumo para o grupo (TI@valence.com.br) — pulado no sábado/domingo
 *   2) Na segunda-feira: resumo agrupado dos aniversariantes do fim de semana
 *   3) Parabéns individual para cada aniversariante do dia
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

No sábado e no domingo o e-mail de grupo do dia não é enviado.
Na segunda-feira é enviado o resumo "Aniversariantes do fim de semana".

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
    $weekday            = (int)$refDate->format('N'); // 1=segunda … 7=domingo
    $isWeekend          = $weekday >= 6;
    $isMonday           = $weekday === 1;

    $aniversariantes = $aniversarioService->getAniversariantesDoDia($refDate);
    $weekendPeople   = $isMonday ? $aniversarioService->getAniversariantesDoFimDeSemana($refDate) : [];

    birthday_log(sprintf('Aniversariantes do dia: %d', count($aniversariantes)));
    if ($isMonday) {
        birthday_log(sprintf('Aniversariantes do fim de semana (sáb+dom): %d', count($weekendPeople)));
    }

    if (empty($aniversariantes) && empty($weekendPeople)) {
        birthday_log('Nenhum aniversariante para enviar. Nenhum e-mail enviado.');
        exit(0);
    }

    foreach ($aniversariantes as $row) {
        $emailInfo = $row['usuario_email'] ?? '—';
        birthday_log(sprintf(
            '  • [hoje] %s (CPF %s) — contato: %s',
            (string)($row['NOMECOMPLETO'] ?? ''),
            (string)($row['CPF'] ?? ''),
            (string)$emailInfo
        ));
    }
    foreach ($weekendPeople as $row) {
        birthday_log(sprintf(
            '  • [%s %s] %s (CPF %s)',
            (string)($row['DIA_SEMANA_LABEL'] ?? 'fim de semana'),
            (string)($row['DATA_ANIV_LABEL'] ?? ''),
            (string)($row['NOMECOMPLETO'] ?? ''),
            (string)($row['CPF'] ?? '')
        ));
    }

    $mailService          = new MailService($config['mail'] ?? []);
    $birthdayEmailService = new BirthdayEmailService(
        $mailService,
        $config['birthday'] ?? [],
        dirname(__DIR__)
    );

    $groupRecipients = $config['birthday']['group_recipients'] ?? [];
    birthday_log('E-mail grupo → ' . implode(', ', $groupRecipients));

    if (!empty($weekendPeople)) {
        if ($dryRun) {
            $birthdayEmailService->sendWeekendGroupEmail($weekendPeople, true, null, $refDate);
            birthday_log('[DRY-RUN] E-mail de fim de semana seria enviado.');
        } else {
            $birthdayEmailService->sendWeekendGroupEmail($weekendPeople, false, null, $refDate);
            birthday_log('E-mail de fim de semana enviado.');
        }
    }

    if ($isWeekend) {
        birthday_log('Fim de semana: e-mail de grupo do dia não é enviado (vai agrupado na segunda).');
    } elseif (!empty($aniversariantes)) {
        if ($dryRun) {
            $birthdayEmailService->sendGroupEmail($aniversariantes, true, null, $refDate);
            birthday_log('[DRY-RUN] E-mail de grupo seria enviado.');
        } else {
            $birthdayEmailService->sendGroupEmail($aniversariantes, false, null, $refDate);
            birthday_log('E-mail de grupo enviado.');
        }
    }

    $sentIndividuals = [];
    if (!empty($aniversariantes)) {
        $override = $birthdayEmailService->getIndividualOverrideEmail();
        if ($override !== null) {
            birthday_log('Modo teste: e-mails individuais redirecionados para ' . $override);
        }

        $sentIndividuals = $birthdayEmailService->sendIndividualEmails(
            $aniversariantes,
            $dryRun,
            null,
            $refDate
        );

        if (empty($sentIndividuals)) {
            birthday_log('Nenhum e-mail individual enviado (sem endereço disponível).', true);
        } else {
            foreach ($sentIndividuals as $item) {
                $prefix = $dryRun ? '[DRY-RUN] ' : '';
                birthday_log($prefix . 'E-mail individual → ' . $item['to'] . ' (' . $item['nome'] . ')');
            }
        }
    }

    birthday_log(sprintf(
        'send_birthday_emails concluído: %d do dia, %d do fim de semana, %d e-mail(s) individual(is)%s.',
        count($aniversariantes),
        count($weekendPeople),
        count($sentIndividuals),
        $dryRun ? ' [simulação]' : ''
    ));
    exit(0);
} catch (\Throwable $e) {
    birthday_log('ERRO send_birthday_emails: ' . $e->getMessage(), true);
    exit(1);
}

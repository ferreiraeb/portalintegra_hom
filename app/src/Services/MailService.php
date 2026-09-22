<?php
namespace Services;

/**
 * Envio de e-mail via SMTP (sem dependências externas).
 */
class MailService
{
    private array $cfg;

    public function __construct(array $config)
    {
        $this->cfg = $config;
    }

    public function isEnabled(): bool
    {
        return (bool)($this->cfg['enabled'] ?? false);
    }

    /**
     * @param string|string[]                                           $to
     * @param array<int, array{cid: string, path: string, mime?: string}> $inlineImages
     */
    public function send(
        array|string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $inlineImages = []
    ): void {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Envio de e-mail desabilitado em config (mail.enabled = false).');
        }

        $recipients = is_array($to) ? $to : [$to];
        $recipients = array_values(array_filter(array_map('trim', $recipients)));
        if (empty($recipients)) {
            throw new \InvalidArgumentException('Nenhum destinatário informado.');
        }

        $fromEmail = (string)($this->cfg['from_email'] ?? 'noreply@localhost');
        $fromName  = (string)($this->cfg['from_name'] ?? 'Portal Integra');

        if ($textBody === null || $textBody === '') {
            $textBody = html_entity_decode(
                strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $htmlBody)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }

        $boundary = 'b_' . bin2hex(random_bytes(8));
        $headers  = [
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'MIME-Version: 1.0',
        ];

        if (!empty($inlineImages)) {
            $relBoundary = 'rel_' . bin2hex(random_bytes(8));
            $headers[]   = 'Content-Type: multipart/related; boundary="' . $relBoundary . '"';

            $body  = "--{$relBoundary}\r\n";
            $body .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n\r\n";
            $body .= $this->buildAlternativeParts($boundary, $textBody, $htmlBody);
            $body .= $this->buildInlineImageParts($relBoundary, $inlineImages);
            $body .= "--{$relBoundary}--\r\n";
        } else {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body      = $this->buildAlternativeParts($boundary, $textBody, $htmlBody);
            $body     .= "--{$boundary}--\r\n";
        }

        $this->smtpSend($recipients, $subject, $body, $headers);
    }

    private function buildAlternativeParts(string $boundary, string $textBody, string $htmlBody): string
    {
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        return $body;
    }

    /**
     * @param array<int, array{cid: string, path: string, mime?: string}> $inlineImages
     */
    private function buildInlineImageParts(string $relBoundary, array $inlineImages): string
    {
        $body = '';
        foreach ($inlineImages as $img) {
            $cid      = trim((string)($img['cid'] ?? ''), '<>');
            $path     = (string)($img['path'] ?? '');
            if ($cid === '' || $path === '' || !is_file($path)) {
                continue;
            }

            $mime     = (string)($img['mime'] ?? $this->detectImageMime($path));
            $filename = basename($path);
            $data     = (string)file_get_contents($path);

            $body .= "--{$relBoundary}\r\n";
            $body .= "Content-Type: {$mime}; name=\"{$filename}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: inline; filename=\"{$filename}\"\r\n";
            $body .= "Content-ID: <{$cid}>\r\n\r\n";
            $body .= chunk_split(base64_encode($data)) . "\r\n";
        }

        return $body;
    }

    private function detectImageMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };
    }

    private function smtpSend(array $to, string $subject, string $body, array $extraHeaders): void
    {
        $host       = (string)($this->cfg['smtp_host'] ?? '');
        $port       = (int)($this->cfg['smtp_port'] ?? 587);
        $encryption = strtolower((string)($this->cfg['smtp_encryption'] ?? 'tls'));
        $user       = (string)($this->cfg['smtp_user'] ?? '');
        $password   = (string)($this->cfg['smtp_password'] ?? '');
        $timeout    = (int)($this->cfg['timeout'] ?? 30);
        $fromEmail  = (string)($this->cfg['from_email'] ?? 'noreply@localhost');

        if ($host === '') {
            throw new \RuntimeException('SMTP não configurado: defina mail.smtp_host em config/config.php.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new \RuntimeException("Falha ao conectar SMTP ({$host}:{$port}): {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO portal-integra.local', [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Falha ao negociar STARTTLS com o servidor SMTP.');
                }
                $this->command($socket, 'EHLO portal-integra.local', [250]);
            }

            if ($user !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($user), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);

            foreach ($to as $recipient) {
                $this->command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            }

            $this->command($socket, 'DATA', [354]);

            $message  = 'Subject: ' . $this->encodeHeader($subject) . "\r\n";
            $message .= 'To: ' . implode(', ', $to) . "\r\n";
            $message .= implode("\r\n", $extraHeaders) . "\r\n\r\n";
            $message .= $body . "\r\n.";
            $this->command($socket, $message, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function command($socket, string $command, array $okCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $okCodes, $command);
    }

    /** @param resource $socket */
    private function expect($socket, array $okCodes, ?string $sent = null): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new \RuntimeException('SMTP sem resposta' . ($sent ? " após: {$sent}" : '') . '.');
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            throw new \RuntimeException(
                'SMTP erro' . ($sent ? " após \"{$sent}\"" : '') . ": {$response}"
            );
        }

        return $response;
    }

    private function formatAddress(string $email, string $name): string
    {
        $name = trim(str_replace(['"', "\r", "\n"], '', $name));
        if ($name === '') {
            return $email;
        }
        return '"' . $name . '" <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}

<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Invio email con tre driver: mail() di PHP, SMTP diretto o scrittura su file.
 *
 * Nessuna dipendenza esterna: su hosting condiviso mail() è spesso l'unica
 * strada, e il driver SMTP copre i casi in cui serve un relay autenticato.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $from = (string) Config::get('site.email_from', 'noreply@localhost');
        $fromName = (string) Config::get('site.email_name', 'Noblogs');

        $headers = [
            'From'                      => self::encodeName($fromName) . ' <' . $from . '>',
            'MIME-Version'              => '1.0',
            'Content-Type'              => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            'Date'                      => gmdate('r'),
            'Message-ID'                => '<' . bin2hex(random_bytes(12)) . '@' . Config::get('site.domain', 'localhost') . '>',
            'Auto-Submitted'            => 'auto-generated',
        ];
        if ($replyTo !== null) {
            $headers['Reply-To'] = $replyTo;
        }

        $subject = self::encodeName($subject);
        $body = str_replace("\r\n", "\n", $body);

        return match ((string) Config::get('mail.driver', 'mail')) {
            'smtp' => self::sendSmtp($to, $subject, $body, $headers, $from),
            'log'  => self::sendToLog($to, $subject, $body, $headers),
            default => self::sendMail($to, $subject, $body, $headers),
        };
    }

    /** @param array<string,string> $headers */
    private static function sendMail(string $to, string $subject, string $body, array $headers): bool
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        return @mail($to, $subject, $body, implode("\r\n", $lines));
    }

    /** @param array<string,string> $headers */
    private static function sendToLog(string $to, string $subject, string $body, array $headers): bool
    {
        $log = NOBLOGS_STORAGE . '/logs/mail.log';
        $entry = str_repeat('=', 72) . "\n"
            . 'Data: ' . gmdate('c') . "\n"
            . 'A: ' . $to . "\n"
            . 'Oggetto: ' . $subject . "\n"
            . implode("\n", array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers)) . "\n\n"
            . $body . "\n";
        return @file_put_contents($log, $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Client SMTP minimale (EHLO, STARTTLS opzionale, AUTH LOGIN, DATA).
     *
     * @param array<string,string> $headers
     */
    private static function sendSmtp(string $to, string $subject, string $body, array $headers, string $from): bool
    {
        $host = (string) Config::get('mail.smtp.host', 'localhost');
        $port = (int) Config::get('mail.smtp.port', 587);
        $user = (string) Config::get('mail.smtp.user', '');
        $password = (string) Config::get('mail.smtp.password', '');
        $encryption = Config::get('mail.smtp.encryption');

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15);
        if ($socket === false) {
            error_log("SMTP: connessione fallita a $host:$port ($errstr)");
            return false;
        }
        stream_set_timeout($socket, 15);

        $read = static function () use ($socket): string {
            $response = '';
            while (($line = fgets($socket, 515)) !== false) {
                $response .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };
        $write = static function (string $command) use ($socket, $read): string {
            fwrite($socket, $command . "\r\n");
            return $read();
        };
        $ok = static fn(string $response, string $code): bool => str_starts_with($response, $code);

        try {
            if (!$ok($read(), '220')) {
                return false;
            }

            $domain = (string) Config::get('site.domain', 'localhost');
            $write('EHLO ' . $domain);

            if ($encryption === 'tls') {
                if (!$ok($write('STARTTLS'), '220')) {
                    return false;
                }
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return false;
                }
                $write('EHLO ' . $domain);
            }

            if ($user !== '') {
                if (!$ok($write('AUTH LOGIN'), '334')) {
                    return false;
                }
                if (!$ok($write(base64_encode($user)), '334')) {
                    return false;
                }
                if (!$ok($write(base64_encode($password)), '235')) {
                    return false;
                }
            }

            if (!$ok($write('MAIL FROM:<' . $from . '>'), '250')) {
                return false;
            }
            if (!$ok($write('RCPT TO:<' . $to . '>'), '250')) {
                return false;
            }
            if (!$ok($write('DATA'), '354')) {
                return false;
            }

            $message = 'To: ' . $to . "\r\n" . 'Subject: ' . $subject . "\r\n";
            foreach ($headers as $name => $value) {
                $message .= $name . ': ' . $value . "\r\n";
            }
            // Un punto a inizio riga chiuderebbe il messaggio: va raddoppiato.
            $message .= "\r\n" . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));

            if (!$ok($write($message . "\r\n."), '250')) {
                return false;
            }
            $write('QUIT');
            return true;
        } finally {
            fclose($socket);
        }
    }

    private static function encodeName(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value)) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}

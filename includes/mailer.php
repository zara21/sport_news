<?php
require_once __DIR__ . '/app_config.php';

/**
 * ელფოსტის გაგზავნა.
 *  - transport "smtp": აგზავნის SMTP სერვერით (მაგ. Gmail: smtp.gmail.com:587 tls + App Password)
 *  - transport "log" (ნაგულისხმევი): წერილი იწერება storage/logs/mail.log-ში — ლოკალური ტესტისთვის
 * პარამეტრები: config/mail.php (ნიმუში: config/mail.example.php)
 */
function mail_config(): array
{
    return app_config('mail', [
        'transport' => 'log',
        'host' => '',
        'port' => 587,
        'encryption' => 'tls', // tls (STARTTLS) | ssl | none
        'username' => '',
        'password' => '',
        'from_email' => 'no-reply@sportblog.local',
        'from_name' => 'Sport Blog',
        'timeout' => 15,
    ]);
}

function send_mail(string $to, string $subject, string $textBody): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $subject)) {
        return false;
    }

    $config = mail_config();
    $message = mail_build_message($config, $to, $subject, $textBody);

    if (($config['transport'] ?? 'log') !== 'smtp') {
        return mail_log_message($to, $subject, $textBody);
    }

    try {
        mail_smtp_send($config, $to, $message);
        return true;
    } catch (Throwable $e) {
        error_log('send_mail failed: ' . $e->getMessage());
        return false;
    }
}

function mail_build_message(array $config, string $to, string $subject, string $textBody): string
{
    $fromName = '=?UTF-8?B?' . base64_encode((string) $config['from_name']) . '?=';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $fromName . ' <' . $config['from_email'] . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', (string) $config['from_email'])[1] ?? 'localhost') . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($textBody), 76, "\r\n");
}

function mail_log_message(string $to, string $subject, string $textBody): bool
{
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $entry = '=== ' . date('c') . " ===\nTo: {$to}\nSubject: {$subject}\n\n{$textBody}\n\n";
    return file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
}

function mail_smtp_send(array $config, string $to, string $message): void
{
    $encryption = strtolower((string) $config['encryption']);
    $host = (string) $config['host'];
    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . (int) $config['port'];
    $socket = @stream_socket_client($remote, $errno, $errstr, (int) $config['timeout']);
    if (!$socket) {
        throw new RuntimeException("SMTP connection failed: {$errstr}");
    }
    stream_set_timeout($socket, (int) $config['timeout']);

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
    $command = static function (?string $line, array $expected) use ($socket, $read): string {
        if ($line !== null) {
            fwrite($socket, $line . "\r\n");
        }
        $response = $read();
        if (!in_array((int) substr($response, 0, 3), $expected, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
        return $response;
    };

    try {
        $command(null, [220]);
        $hostname = gethostname() ?: 'localhost';
        $command('EHLO ' . $hostname, [250]);
        if ($encryption === 'tls') {
            $command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS failed');
            }
            $command('EHLO ' . $hostname, [250]);
        }
        if ((string) $config['username'] !== '') {
            $command('AUTH LOGIN', [334]);
            $command(base64_encode((string) $config['username']), [334]);
            $command(base64_encode((string) $config['password']), [235]);
        }
        $command('MAIL FROM:<' . $config['from_email'] . '>', [250]);
        $command('RCPT TO:<' . $to . '>', [250, 251]);
        $command('DATA', [354]);
        // dot-stuffing
        $body = preg_replace('/^\./m', '..', $message);
        $command($body . "\r\n.", [250]);
        $command('QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

/** საიტის აბსოლუტური მისამართი ელფოსტის ბმულებისთვის — config/site.php-დან (არა Host header-იდან). */
function site_public_url(string $path = '/'): string
{
    $base = rtrim((string) (app_config('site')['base_url'] ?? ''), '/') ?: 'http://localhost/sport_blog';
    return $base . '/' . ltrim($path, '/');
}

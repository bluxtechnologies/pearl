<?php
/**
 * Pearl Framework — Mail Subsystem
 *
 * Pluggable email sending supporting Layer 2 Framework Contracts (§4, §13).
 * Supported drivers:
 *   - 'log'       Writes emails to storage/mail/ (plain text / HTML)
 *   - 'smtp'      Zero-dependency raw socket SMTP client with STARTTLS & AUTH LOGIN
 *   - 'phpmailer' Optional integration point requiring external composer package
 *
 * Includes HTML email template rendering from ink/mail/.
 *
 * Depends on: pearl/paths.php (config_path, storage_path, ink_path), bootstrap.php (env).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('config_path')) {
    throw new RuntimeException('pearl/paths.php must be required before pearl/mail.php.');
}

/**
 * Load and cache config/mail.php.
 */
if (!function_exists('mail_config')) {
    function mail_config(): array
    {
        static $config = null;

        if ($config === null) {
            $path = config_path('mail.php');
            $config = is_file($path) ? (require $path) : [];
        }

        return $config;
    }
}

/**
 * Render an HTML email template from ink/mail/{$template}.php.
 * Wraps content in ink/mail/{$layout}.php if layout is provided.
 */
if (!function_exists('mail_template')) {
    function mail_template(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $templatePath = ink_path("mail/{$template}.php");

        if (!is_file($templatePath)) {
            // Check view/mail/ fallback
            $templatePath = view_path("mail/{$template}.php");
            if (!is_file($templatePath)) {
                throw new InvalidArgumentException("Mail template not found: '{$template}' (searched ink/mail/ and view/mail/)");
            }
        }

        // Render template content
        $render = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        };

        $content = $render($templatePath, $data);

        if ($layout === null) {
            return $content;
        }

        $layoutPath = ink_path("mail/{$layout}.php");
        if (!is_file($layoutPath)) {
            $layoutPath = view_path("mail/{$layout}.php");
        }

        if (!is_file($layoutPath)) {
            return $content;
        }

        $layoutData = array_merge($data, ['content' => $content]);
        return $render($layoutPath, $layoutData);
    }
}

/**
 * Send an email via whichever driver MAIL_DRIVER selects.
 * Options array can specify:
 *   - 'html'     => (bool) true if body is HTML
 *   - 'template' => (string) template name in ink/mail/
 *   - 'data'     => (array) variables to pass to the template
 *   - 'driver'   => (string) override configured driver
 *   - 'from'     => ['address' => '...', 'name' => '...']
 */
if (!function_exists('mail_send')) {
    function mail_send(string $to, string $subject, string $body = '', array $options = []): bool
    {
        $config = mail_config();
        $driver = (string) ($options['driver'] ?? $config['driver'] ?? 'log');

        $isHtml = (bool) ($options['html'] ?? false);

        if (!empty($options['template'])) {
            $templateData = (array) ($options['data'] ?? []);
            if (!isset($templateData['subject'])) {
                $templateData['subject'] = $subject;
            }
            $body = mail_template((string) $options['template'], $templateData, $options['layout'] ?? 'layout');
            $isHtml = true;
        }

        if (!empty($options['from'])) {
            $config['from'] = array_merge($config['from'] ?? [], $options['from']);
        }

        return match ($driver) {
            'log' => mail_send_via_log($to, $subject, $body, $config, $isHtml),
            'smtp' => mail_send_via_smtp($to, $subject, $body, $config, $isHtml),
            'phpmailer' => mail_send_via_phpmailer($to, $subject, $body, $config, $isHtml),
            default => throw new InvalidArgumentException("Unknown MAIL_DRIVER: {$driver}"),
        };
    }
}

/**
 * 'log' driver — writes the email to storage/mail/ as a file.
 */
if (!function_exists('mail_send_via_log')) {
    function mail_send_via_log(string $to, string $subject, string $body, array $config, bool $isHtml = false): bool
    {
        $dir = storage_path('mail');

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $safeTo = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $to);
        $ext = $isHtml ? 'html' : 'txt';
        $filename = date('Y-m-d_His') . '_' . $safeTo . '.' . $ext;

        $fromName = $config['from']['name'] ?? 'Pearl';
        $fromAddress = $config['from']['address'] ?? 'noreply@pearlphp.org';

        $header = "From: {$fromName} <{$fromAddress}>" . PHP_EOL
            . "To: {$to}" . PHP_EOL
            . "Subject: {$subject}" . PHP_EOL
            . "Date: " . date('r') . PHP_EOL
            . "Content-Type: " . ($isHtml ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8') . PHP_EOL
            . PHP_EOL;

        file_put_contents($dir . '/' . $filename, $header . $body . PHP_EOL);

        return true;
    }
}

/**
 * 'smtp' driver — zero-dependency raw socket client implementing RFC 5321.
 * Supports STARTTLS, SSL, and AUTH LOGIN.
 */
if (!function_exists('mail_send_via_smtp')) {
    function mail_send_via_smtp(string $to, string $subject, string $body, array $config, bool $isHtml = false): bool
    {
        $smtp = $config['smtp'] ?? [];
        $host = (string) ($smtp['host'] ?? '127.0.0.1');
        $port = (int) ($smtp['port'] ?? 587);
        $username = (string) ($smtp['username'] ?? '');
        $password = (string) ($smtp['password'] ?? '');
        $encryption = strtolower((string) ($smtp['encryption'] ?? 'tls'));
        $timeout = (int) ($smtp['timeout'] ?? 15);

        if (empty($host)) {
            throw new RuntimeException("SMTP host is not configured in config/mail.php (MAIL_HOST).");
        }

        $remoteSocket = ($encryption === 'ssl' ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}");
        $errno = 0;
        $errstr = '';

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client($remoteSocket, $errno, $errstr, (float) $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            throw new RuntimeException("Could not connect to SMTP server {$remoteSocket}: ({$errno}) {$errstr}");
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = static function ($sock): string {
            $response = '';
            while (!feof($sock)) {
                $line = fgets($sock, 512);
                if ($line === false) {
                    break;
                }
                $response .= $line;
                if (strlen($line) >= 4 && ($line[3] === ' ' || $line[3] === "\r" || $line[3] === "\n")) {
                    break;
                }
            }
            return $response;
        };

        $sendCommand = static function ($sock, string $command, array $expectedCodes) use ($readResponse): string {
            fwrite($sock, $command . "\r\n");
            $res = $readResponse($sock);
            $code = (int) substr($res, 0, 3);
            if (!in_array($code, $expectedCodes, true)) {
                throw new RuntimeException("SMTP command '{$command}' failed with response: " . trim($res));
            }
            return $res;
        };

        try {
            // Initial greeting
            $greeting = $readResponse($socket);
            $greetCode = (int) substr($greeting, 0, 3);
            if ($greetCode !== 220) {
                throw new RuntimeException("Unexpected SMTP greeting: " . trim($greeting));
            }

            $clientHost = gethostname() ?: 'localhost';

            // EHLO
            $sendCommand($socket, "EHLO {$clientHost}", [250]);

            // Upgrade to TLS if requested
            if ($encryption === 'tls') {
                $sendCommand($socket, "STARTTLS", [220]);

                $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$cryptoOk) {
                    throw new RuntimeException("Failed to negotiate TLS encryption with SMTP server {$host}.");
                }

                // Re-issue EHLO after TLS handshake
                $sendCommand($socket, "EHLO {$clientHost}", [250]);
            }

            // Authenticate if credentials provided
            if ($username !== '' && $password !== '') {
                $sendCommand($socket, "AUTH LOGIN", [334]);
                $sendCommand($socket, base64_encode($username), [334]);
                $sendCommand($socket, base64_encode($password), [235]);
            }

            $fromAddress = (string) ($config['from']['address'] ?? 'noreply@pearlphp.org');
            $fromName = (string) ($config['from']['name'] ?? 'Pearl');

            // Envelope transactions
            $sendCommand($socket, "MAIL FROM:<{$fromAddress}>", [250]);
            $sendCommand($socket, "RCPT TO:<{$to}>", [250, 251]);
            $sendCommand($socket, "DATA", [354]);

            // Message headers & body
            $headers = [
                "Date: " . date('r'),
                "From: {$fromName} <{$fromAddress}>",
                "To: <{$to}>",
                "Subject: {$subject}",
                "Message-ID: <" . bin2hex(random_bytes(16)) . "@{$clientHost}>",
                "MIME-Version: 1.0",
                "Content-Type: " . ($isHtml ? "text/html; charset=UTF-8" : "text/plain; charset=UTF-8"),
                "Content-Transfer-Encoding: 8bit",
            ];

            // RFC 5321 dot-stuffing and line ending normalization
            $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
            $lines = explode("\n", $normalizedBody);
            $stuffedLines = array_map(static function (string $line): string {
                return str_starts_with($line, '.') ? '.' . $line : $line;
            }, $lines);

            $payload = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $stuffedLines) . "\r\n.\r\n";
            fwrite($socket, $payload);

            $dataRes = $readResponse($socket);
            $dataCode = (int) substr($dataRes, 0, 3);
            if ($dataCode !== 250) {
                throw new RuntimeException("SMTP DATA submission rejected: " . trim($dataRes));
            }

            // Clean session termination
            try {
                $sendCommand($socket, "QUIT", [221]);
            } catch (\Throwable) {
                // Ignore QUIT errors if message was already accepted
            }

            return true;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }
}

/**
 * 'phpmailer' driver — optional integration point.
 */
if (!function_exists('mail_send_via_phpmailer')) {
    function mail_send_via_phpmailer(string $to, string $subject, string $body, array $config, bool $isHtml = false): bool
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new RuntimeException(
                "MAIL_DRIVER=phpmailer requires the phpmailer/phpmailer package, " .
                "which is not installed. Run: composer require phpmailer/phpmailer"
            );
        }

        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);

        $mailer->isSMTP();
        $mailer->Host = $config['smtp']['host'] ?? '127.0.0.1';
        $mailer->Port = (int) ($config['smtp']['port'] ?? 587);
        $mailer->SMTPAuth = !empty($config['smtp']['username']);
        $mailer->Username = $config['smtp']['username'] ?? '';
        $mailer->Password = $config['smtp']['password'] ?? '';

        if (!empty($config['smtp']['encryption'])) {
            $mailer->SMTPSecure = $config['smtp']['encryption'];
        }

        $mailer->setFrom($config['from']['address'] ?? 'noreply@pearlphp.org', $config['from']['name'] ?? 'Pearl');
        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        $mailer->isHTML($isHtml);
        $mailer->Body = $body;

        return (bool) $mailer->send();
    }
}

<?php
/**
 * Pearl Framework — CLI: mail:test
 *
 * Handles: php bin/pearl mail:test [to] [--driver=...] [--template=...]
 *
 * Sends a test email via the configured mail driver and reports status.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

if (!function_exists('mail_send')) {
    throw new RuntimeException('pearl/mail.php must be required before mail-test.php.');
}

return function (array $args): int {
    $to = 'test@pearlphp.org';
    $driver = null;
    $template = null;

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--driver=')) {
            $driver = substr($arg, 9);
        } elseif (str_starts_with($arg, '--template=')) {
            $template = substr($arg, 11);
        } elseif (!str_starts_with($arg, '--')) {
            $to = $arg;
        }
    }

    $config = mail_config();
    $activeDriver = $driver ?? $config['driver'] ?? 'log';

    echo "=== Pearl Mail Test ===" . PHP_EOL . PHP_EOL;
    echo "Driver:    {$activeDriver}" . PHP_EOL;
    echo "Recipient: {$to}" . PHP_EOL;

    $options = [];
    if ($driver !== null) {
        $options['driver'] = $driver;
    }

    $subject = "Pearl Framework Test Mail (" . date('H:i:s') . ")";

    if ($template !== null) {
        $options['template'] = $template;
        $options['data'] = [
            'name' => 'Pearl Tester',
            'email' => $to,
            'actionUrl' => 'http://localhost/pearlphp/login',
        ];
        echo "Template:  {$template}" . PHP_EOL;
        $body = '';
    } else {
        $body = "This is a test email sent from the Pearl Framework CLI (mail:test) at " . date('c') . ".\n\n"
              . "If you are reading this, your mail subsystem configuration is operating correctly.";
    }

    echo PHP_EOL . "Sending email..." . PHP_EOL;

    try {
        $result = mail_send($to, $subject, $body, $options);

        if ($result) {
            echo "SUCCESS: Email accepted for delivery." . PHP_EOL;

            if ($activeDriver === 'log') {
                $dir = storage_path('mail');
                $files = glob("{$dir}/*") ?: [];
                if (!empty($files)) {
                    sort($files);
                    $latest = end($files);
                    echo "Logged to: {$latest}" . PHP_EOL;
                }
            }

            return 0;
        }

        echo "FAILED: Mail driver returned false." . PHP_EOL;
        return 1;
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . PHP_EOL;
        return 1;
    }
};

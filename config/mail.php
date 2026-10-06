<?php
/**
 * Pearl Framework — Mail Configuration
 *
 * Returned array, read by pearl/mail.php. Not auto-loaded on every
 * request — only required when mail_send() is actually called, same
 * lazy-load pattern as other config/ files.
 *
 * driver:
 *   'log'       (default) — writes emails to storage/mail/ as plain
 *                text files instead of sending. Safe default for dev;
 *                nothing here requires SMTP credentials to work.
 *   'smtp'      — NOT YET IMPLEMENTED. Calling mail_send() with this
 *                driver throws a clear error rather than silently
 *                pretending to send. A real from-scratch SMTP client
 *                is deliberately deferred, not forgotten.
 *   'phpmailer' — optional integration point. Only works if you've
 *                separately run `composer require phpmailer/phpmailer`
 *                yourself — Pearl's own composer.json stays
 *                dependency-free. If the class isn't found, mail_send()
 *                throws telling you to install it.
 */

declare(strict_types=1);

return [
    'driver' => env('MAIL_DRIVER', 'log'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Pearl'),
    ],

    // Used by both 'smtp' (once implemented) and 'phpmailer' drivers.
    'smtp' => [
        'host' => env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'), // tls | ssl | ''
    ],
];

<?php
/**
 * wire/admin/login.php — admin-context login.
 *
 * Same fat-module pattern as wire/login.php, but sits under wire/admin/
 * so the router (via the /admin path prefix) and session context
 * detection both treat it as admin context automatically.
 */

declare(strict_types=1);

match (request_method()) {
    'GET' => pearl_view('admin/login', ['error' => null, 'title' => 'Admin sign in'], 'layouts/auth'),

    'POST' => (function () {
        $input = request_input();
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        $rateKey = 'admin_login:' . request_ip() . ':' . strtolower(trim((string) $email));

        if (!rate_limit_check($rateKey, 5, 60)) {
            $seconds = rate_limit_available_in($rateKey);
            response_too_many_requests($seconds, "Too many login attempts. Please try again in {$seconds} seconds.");
            return;
        }

        if (auth_login($email, $password)) {
            rate_limit_clear($rateKey);
            response_redirect('/admin/dashboard');
            return;
        }

        rate_limit_hit($rateKey, 60);
        pearl_view('admin/login', ['error' => 'Invalid email or password.', 'title' => 'Admin sign in'], 'layouts/auth', 401);
    })(),

    default => response_method_not_allowed(['GET', 'POST']),
};

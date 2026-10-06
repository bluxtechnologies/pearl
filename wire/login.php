<?php
/**
 * wire/login.php — user-context login.
 *
 * Fat module: handles both GET (show form) and POST (attempt login)
 * for the /login route. Matches the router convention — $params is
 * available here from pearl_dispatch(), empty for this exact route.
 */

declare(strict_types=1);

match (request_method()) {
    'GET' => pearl_view('login', ['error' => null, 'title' => 'Sign in'], 'layouts/auth'),

    'POST' => (function () {
        $input = request_input();
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        $rateKey = 'login:' . request_ip() . ':' . strtolower(trim((string) $email));

        if (!rate_limit_check($rateKey, 5, 60)) {
            $seconds = rate_limit_available_in($rateKey);
            response_too_many_requests($seconds, "Too many login attempts. Please try again in {$seconds} seconds.");
            return;
        }

        if (auth_login($email, $password)) {
            rate_limit_clear($rateKey);
            response_redirect('/dashboard');
            return;
        }

        rate_limit_hit($rateKey, 60);
        pearl_view('login', ['error' => 'Invalid email or password.', 'title' => 'Sign in'], 'layouts/auth', 401);
    })(),

    default => response_method_not_allowed(['GET', 'POST']),
};

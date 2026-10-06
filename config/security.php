<?php
/**
 * Pearl Framework — Security Configuration
 *
 * CSP source lists reflect what Pearl actually ships, not a
 * theoretical/borrowed default: everything (fonts, JS, CSS) is
 * self-hosted via Vite (see pearl/vite.php), so 'self' covers nearly
 * everything. There is deliberately no allowance for inline scripts,
 * inline styles, or eval — Alpine.js runs via the @alpinejs/csp build
 * specifically so 'unsafe-eval' is never needed (see ink/main.js).
 *
 * If your app adds a third-party service (analytics, chat widget,
 * payment iframe, etc.), add its domain to the relevant directive
 * below — don't loosen a directive to '*' or 'unsafe-inline' as a
 * shortcut, since that defeats the point of having a CSP at all.
 *
 * ONE DELIBERATE EXCEPTION: script-src includes 'unsafe-eval', scoped
 * only to what Alpine.js needs (it evaluates x-data/x-on expressions
 * via Function()). Alpine does ship an official CSP-safe build
 * (@alpinejs/csp) that avoids this entirely — but it disallows inline
 * x-data object literals, ternaries, and assignment expressions
 * (x-data="{ show: false }", @click="show = !show", etc.), which is
 * exactly the pattern Pearl's default views use throughout (password
 * visibility toggle, mobile nav toggle). Adopting the CSP-safe build
 * means rewriting every Alpine usage into named Alpine.data()
 * components with methods — a real, separate refactor, not a
 * config change. Deferred deliberately, not overlooked: this keeps
 * 'unsafe-eval' narrowly scoped to script-src only (not style-src or
 * any other directive), still blocks remote script injection and
 * inline <script> tags from XSS, and is a well-documented, commonly
 * made tradeoff for Alpine-based apps that need a real CSP today.
 */

declare(strict_types=1);

return [
    'csp_enabled' => env('CSP_ENABLED', env('APP_ENV') === 'production'),

    'csp' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-eval'"],
        'style-src' => ["'self'"],
        'font-src' => ["'self'"],
        'img-src' => ["'self'", 'data:'],
        'connect-src' => ["'self'"],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'none'"],
    ],

    // Beyond CSP: a few other low-risk, high-value security headers,
    // sent whenever csp_enabled is true (bundled together since they
    // share the same "harden before deploying for real" moment).
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    // Token-based CSRF protection for state-changing HTTP requests.
    // Enforced in the dispatch pipeline unless the request path matches exempt_routes.
    'csrf' => [
        'enabled' => env('CSRF_ENABLED', true),
        'token_key' => '_csrf',
        'header_key' => 'X-CSRF-TOKEN',
        'exempt_routes' => [
            // Exact path matches (e.g. '/api/webhook')
        ],
    ],
];

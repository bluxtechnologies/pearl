<?php
/**
 * Pearl Framework — Front Controller
 *
 * Every HTTP request enters here. Boot order matters and mirrors each
 * file's declared dependencies (see the header comment in each file):
 *
 *   bootstrap -> paths -> errors -> request -> response -> view
 *   -> db -> query -> queue -> mail -> session -> auth -> rbac -> router
 *
 * db/query/queue/mail/session/auth/rbac are required before router
 * because wire/ modules the router dispatches to call query(),
 * queue_push(), mail_send(), auth_login(), auth_check(),
 * auth_require_admin() etc. directly, and PHP needs those functions
 * declared before they're used. router.php itself only needs
 * request.php and errors.php, but sits last since it's the dispatch
 * step — everything else must be ready before a wire/ module runs.
 */

declare(strict_types=1);

if (php_sapi_name() === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($uriPath !== '/' && file_exists(__DIR__ . $uriPath)) {
        return false;
    }
}

require dirname(__DIR__) . '/pearl/bootstrap.php';
require dirname(__DIR__) . '/pearl/paths.php';
require dirname(__DIR__) . '/pearl/errors.php';
require dirname(__DIR__) . '/pearl/csp.php';
require dirname(__DIR__) . '/pearl/request.php';
require dirname(__DIR__) . '/pearl/response.php';
require dirname(__DIR__) . '/pearl/view.php';
require dirname(__DIR__) . '/pearl/icons.php';
require dirname(__DIR__) . '/pearl/vite.php';
require dirname(__DIR__) . '/pearl/db.php';
require dirname(__DIR__) . '/pearl/query.php';
require dirname(__DIR__) . '/pearl/queue.php';
require dirname(__DIR__) . '/pearl/mail.php';
require dirname(__DIR__) . '/pearl/cache.php';
require dirname(__DIR__) . '/pearl/rate_limit.php';
require dirname(__DIR__) . '/pearl/session.php';
require dirname(__DIR__) . '/pearl/auth.php';
require dirname(__DIR__) . '/pearl/api.php';
require dirname(__DIR__) . '/pearl/rbac.php';
require dirname(__DIR__) . '/pearl/router.php';

pearl_register_error_handlers();
header('X-Request-ID: ' . request_id());
send_security_headers();
session_start_pearl();

pearl_dispatch();
<?php
/**
 * wire/admin/logout.php — admin-context logout.
 */

declare(strict_types=1);

auth_logout();
response_redirect('/admin/login');
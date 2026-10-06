<?php
/**
 * Pearl Framework — View Rendering
 *
 * Renders a view/ template file with data extracted into local scope,
 * with optional layout wrapping. Depends on view_path() (pearl/paths.php).
 *
 * Template convention: pass paths without the .php extension and
 * relative to view/, e.g. pearl_view('dashboard/index', [...]).
 *
 * Layout convention: a layout file receives the rendered view output
 * as $content, plus the same $data the view received, e.g.
 * view/layouts/app.php can do: <body><?= $content ?></body>
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('view_path')) {
    throw new RuntimeException(
        'Path helpers are not loaded. pearl/paths.php must be required before pearl/view.php.'
    );
}

/**
 * Render a single template file in isolation, with $data extracted
 * into local variable scope. Returns the rendered string rather than
 * echoing, so callers (pearl_view, layout wrapping) control output
 * buffer flow.
 */
if (!function_exists('pearl_render_file')) {
    function pearl_render_file(string $absolutePath, array $data = []): string
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException("View file not found: {$absolutePath}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $absolutePath;
        return ob_get_clean();
    }
}

/**
 * Render a view/ template and send it as the HTTP response body.
 *
 * @param string      $template Dot- or slash-separated path relative to
 *                               view/, without .php (e.g. 'dashboard/index'
 *                               or 'dashboard.index').
 * @param array       $data     Variables made available to the template.
 * @param string|null $layout   Optional layout template (same path rules),
 *                               e.g. 'layouts/app'. When given, the
 *                               rendered view becomes $content inside it.
 * @param int         $status   HTTP status code to send.
 */
if (!function_exists('pearl_view')) {
    function pearl_view(string $template, array $data = [], ?string $layout = null, int $status = 200): void
    {
        $templatePath = view_path(str_replace('.', '/', $template) . '.php');
        $body = pearl_render_file($templatePath, $data);

        if ($layout !== null) {
            $layoutPath = view_path(str_replace('.', '/', $layout) . '.php');
            $body = pearl_render_file($layoutPath, array_merge($data, ['content' => $body]));
        }

        response_status($status);

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $body;
    }
}

/**
 * Escape a value for safe HTML output. Short name deliberately, since
 * it will be used constantly inside every view template.
 */
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Render a hidden input tag containing the session CSRF token for forms.
 */
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = function_exists('csrf_token') ? csrf_token() : '';
        return '<input type="hidden" name="_csrf" value="' . e($token) . '">';
    }
}

<?php
/**
 * Pearl Framework — Icons
 *
 * Inlines real Lucide SVG icons (from lucide-static, MIT/ISC licensed)
 * stored under ink/icons/. Inlined rather than referenced via <img> so
 * `stroke="currentColor"` works — the icon inherits whatever text
 * color is set via CSS (e.g. text-accent, text-muted), matching
 * however many icon instances/colors a page needs without generating
 * separate colored image files.
 *
 * Depends on: pearl/paths.php (ink_path).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('ink_path')) {
    throw new RuntimeException('pearl/paths.php must be required before pearl/icons.php.');
}

/**
 * Render an inline SVG icon by name (matches the filename under
 * ink/icons/, without .svg). $class is appended to the SVG's existing
 * class attribute — use it for sizing (w-5 h-5) and color (text-accent).
 *
 * Returns an empty HTML comment (not an empty string) if the icon
 * doesn't exist, so a typo'd icon name is visible in page source
 * during development rather than silently rendering nothing.
 */
if (!function_exists('icon')) {
    function icon(string $name, string $class = 'w-5 h-5'): string
    {
        static $cache = [];

        if (!isset($cache[$name])) {
            $path = ink_path("icons/{$name}.svg");

            if (!is_file($path)) {
                $cache[$name] = false;
            } else {
                $cache[$name] = file_get_contents($path);
            }
        }

        if ($cache[$name] === false) {
            return "<!-- icon not found: " . e($name) . " -->";
        }

        $svg = $cache[$name];

        // Merge the caller's class into the existing class="lucide lucide-x" attribute.
        $svg = preg_replace(
            '/class="([^"]*)"/',
            'class="$1 ' . e($class) . '"',
            $svg,
            1
        );

        return $svg;
    }
}

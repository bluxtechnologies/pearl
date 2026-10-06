<?php
/**
 * Pearl Framework — CLI: vite:install
 *
 * Handles: php bin/pearl vite:install
 *
 * The Vite + Tailwind v4 scaffolding logic, extracted out of
 * install.php so both commands share one implementation instead of
 * duplicating it — install.php now calls pearl_scaffold_vite()
 * directly rather than repeating this code inline.
 *
 * Useful standalone if package.json/vite.config.js got deleted or
 * you're adding the frontend layer to a project that skipped it
 * during initial install.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

/**
 * Generate package.json, vite.config.js, ink/main.css, ink/main.js —
 * each only if missing, same idempotent behavior as install.php's
 * Step 3 always had. Shared by both install and vite:install.
 */
if (!function_exists('pearl_scaffold_vite')) {
    function pearl_scaffold_vite(): void
    {
        $packageJsonPath = PEARL_ROOT . '/package.json';

        if (is_file($packageJsonPath)) {
            echo "package.json already exists — skipping generation." . PHP_EOL;
        } else {
            $packageJson = [
                'name' => 'pearl-app',
                'private' => true,
                'type' => 'module',
                'scripts' => [
                    'dev' => 'vite',
                    'build' => 'vite build',
                ],
                'devDependencies' => [
                    'vite' => '^6.0.0',
                    'tailwindcss' => '^4.0.0',
                    '@tailwindcss/vite' => '^4.0.0',
                ],
                'dependencies' => [
                    '@fontsource/inter' => '^5.1.0',
                    '@fontsource/jetbrains-mono' => '^5.1.0',
                    '@fontsource/sora' => '^5.1.0',
                    'alpinejs' => '^3.14.8',
                    'htmx.org' => '^2.0.10',
                ],
            ];

            file_put_contents(
                $packageJsonPath,
                json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );

            echo "Created package.json (Vite + Tailwind v4)." . PHP_EOL;
        }

        $viteConfigPath = PEARL_ROOT . '/vite.config.js';

        if (is_file($viteConfigPath)) {
            echo "vite.config.js already exists — skipping generation." . PHP_EOL;
        } else {
            $viteConfig = <<<'JS'
                import { defineConfig, loadEnv } from 'vite';
                import tailwindcss from '@tailwindcss/vite';
                import path from 'path';
                import { fileURLToPath } from 'url';

                const __dirname = path.dirname(fileURLToPath(import.meta.url));

                export default defineConfig(({ mode }) => {
                    const env = loadEnv(mode, process.cwd(), '');

                    return {
                        plugins: [
                            tailwindcss(),
                        ],
                        root: 'ink',
                        build: {
                            outDir: path.resolve(__dirname, 'public/build'),
                            emptyOutDir: true,
                            manifest: true,
                            rollupOptions: {
                                input: path.resolve(__dirname, 'ink/main.js'),
                            },
                        },
                        server: {
                            host: env.VITE_HOST || 'localhost',
                            port: Number(env.VITE_PORT) || 5173,
                            strictPort: true,
                        },
                    };
                });
                JS;

            file_put_contents($viteConfigPath, $viteConfig . PHP_EOL);
            echo "Created vite.config.js." . PHP_EOL;
        }

        $cssPath = ink_path('main.css');
        $jsPath = ink_path('main.js');

        if (!is_file($cssPath)) {
            file_put_contents($cssPath, '@import "tailwindcss";' . PHP_EOL);
            echo "Created ink/main.css (Tailwind v4 entry)." . PHP_EOL;
        }

        if (!is_file($jsPath)) {
            file_put_contents($jsPath, "import './main.css';" . PHP_EOL);
            echo "Created ink/main.js." . PHP_EOL;
        }
    }
}

return function (array $args): int {
    echo "=== Pearl vite:install ===" . PHP_EOL . PHP_EOL;

    pearl_scaffold_vite();

    echo PHP_EOL . "Next steps:" . PHP_EOL;
    echo "  npm install" . PHP_EOL;
    echo "  npm run dev" . PHP_EOL;

    return 0;
};

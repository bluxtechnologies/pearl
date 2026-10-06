<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Pearl — Procedural PHP Framework') ?></title>
    <meta name="description" content="A high-performance, procedural PHP framework with strict security boundaries and zero magic.">
    <?= vite('main.js') ?>
</head>
<body class="bg-canvas min-h-screen font-body text-ink flex flex-col antialiased selection:bg-accent selection:text-white">
    <!-- Top Navigation Header -->
    <header class="w-full bg-surface/80 backdrop-blur-md border-b border-border sticky top-0 z-30">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="/" class="flex items-center gap-2 group">
                    <span class="w-8 h-8 rounded-lg bg-accent text-white flex items-center justify-center font-display font-bold text-base shadow-sm group-hover:bg-accent-hover transition-colors">
                        P
                    </span>
                    <span class="font-display text-lg font-bold tracking-tight text-ink group-hover:text-accent transition-colors">
                        Pearl
                    </span>
                </a>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono font-medium bg-canvas border border-border text-muted">
                    v<?= e($pearlVersion ?? '1.0.0') ?>
                </span>
            </div>

            <nav class="flex items-center gap-4">
                <a
                    href="https://github.com/bluxtechnologies/pearl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm font-medium text-muted hover:text-ink transition-colors hidden sm:inline-flex items-center gap-1.5"
                >
                    GitHub
                </a>
                <a
                    href="https://packagist.org/packages/bluxtechnologies/pearl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm font-medium text-muted hover:text-ink transition-colors hidden sm:inline-flex items-center gap-1.5"
                >
                    Packagist
                </a>
                <a
                    href="/login"
                    class="pearl-btn pearl-btn--secondary text-xs sm:text-sm py-1.5 px-3"
                >
                    <?= icon('log-in', 'w-3.5 h-3.5') ?>
                    <span>Sign In</span>
                </a>
                <a
                    href="/admin/login"
                    class="pearl-btn pearl-btn--primary text-xs sm:text-sm py-1.5 px-3"
                >
                    <?= icon('shield-check', 'w-3.5 h-3.5') ?>
                    <span>Admin Panel</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-10 sm:py-16">
        <?= $content ?>
    </main>

    <!-- Footer -->
    <footer class="w-full border-t border-border bg-surface py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-muted">
            <div class="flex items-center gap-2">
                <span class="font-display font-semibold text-ink">PEARL</span>
                <span>— Open source under the MIT License.</span>
            </div>
            <div class="flex items-center gap-6">
                <span>PHP <?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?>+</span>
                <a href="https://github.com/bluxtechnologies/pearl" target="_blank" rel="noopener noreferrer" class="hover:text-ink transition-colors">Documentation</a>
                <a href="https://github.com/bluxtechnologies/pearl/blob/main/PEARL_ARCHITECTURE.md" target="_blank" rel="noopener noreferrer" class="hover:text-ink transition-colors">Architecture Invariants</a>
            </div>
        </div>
    </footer>
</body>
</html>

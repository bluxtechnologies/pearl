<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Pearl') ?></title>
    <?= vite('main.js') ?>
</head>
<body class="bg-canvas min-h-screen font-body text-ink" x-data="{ mobileNavOpen: false }">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside
            class="w-60 bg-surface border-r border-border flex-shrink-0 flex-col fixed inset-y-0 left-0 z-20 md:flex transition-transform"
            :class="mobileNavOpen ? 'flex' : 'hidden'"
        >
            <div class="h-16 flex items-center px-6 border-b border-border">
                <span class="font-display text-lg font-bold text-ink">Pearl</span>
            </div>

            <nav class="flex-1 px-3 py-4 flex flex-col gap-1">
                <a href="/admin/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-ink bg-canvas transition-colors">
                    <?= icon('layout-dashboard', 'w-4 h-4') ?>
                    Dashboard
                </a>
                <a href="/admin/users" class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-muted hover:bg-canvas hover:text-ink transition-colors">
                    <?= icon('users', 'w-4 h-4') ?>
                    Users
                </a>
            </nav>

            <div class="p-3 border-t border-border">
                <form method="post" action="/admin/logout">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="pearl-btn pearl-btn--ghost w-full justify-start">
                        <?= icon('log-out', 'w-4 h-4') ?>
                        Sign out
                    </button>
                </form>
            </div>
        </aside>

        <!-- Content -->
        <div class="flex-1 md:ml-60">
            <header class="h-16 bg-surface border-b border-border flex items-center justify-between px-6 sticky top-0 z-10">
                <button class="md:hidden text-muted" @click="mobileNavOpen = !mobileNavOpen" aria-label="Toggle navigation">
                    <?= icon('menu', 'w-5 h-5') ?>
                </button>
                <h1 class="font-display text-base font-semibold text-ink"><?= e($pageTitle ?? '') ?></h1>
                <div></div>
            </header>

            <main class="p-6 max-w-5xl mx-auto pearl-animate-in">
                <?= $content ?>
            </main>
        </div>
    </div>
</body>
</html>

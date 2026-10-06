<div class="space-y-16 pearl-animate-in">
    <!-- Hero Section -->
    <div class="text-center max-w-2xl mx-auto space-y-6">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-surface border border-border shadow-xs text-xs font-medium text-muted">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Framework Installed</span>
            <span class="text-border">•</span>
            <span class="font-mono text-ink">PHP <?= e($phpVersion) ?></span>
        </div>

        <h1 class="font-display text-4xl sm:text-5xl font-bold tracking-tight text-ink leading-tight">
            Build faster with <span class="text-accent underline decoration-accent/25 underline-offset-4">procedural PHP</span>.
        </h1>

        <p class="text-base sm:text-lg text-muted leading-relaxed">
            A lightweight, secure procedural PHP framework with file-based routing, isolated dual-context authentication, and zero magic.
        </p>

        <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
            <a href="/login" class="pearl-btn pearl-btn--primary px-5 py-2.5">
                <?= icon('user', 'w-4 h-4') ?>
                <span>User Login</span>
            </a>
            <a href="/admin/login" class="pearl-btn pearl-btn--secondary px-5 py-2.5">
                <?= icon('shield-check', 'w-4 h-4') ?>
                <span>Admin Panel</span>
            </a>
            <a
                href="https://github.com/bluxtechnologies/pearl#readme"
                target="_blank"
                rel="noopener noreferrer"
                class="pearl-btn pearl-btn--ghost px-4 py-2.5 text-muted hover:text-ink"
            >
                <?= icon('info', 'w-4 h-4') ?>
                <span>Documentation</span>
            </a>
        </div>
    </div>

    <!-- 4 Core Pillars Grid (Spacious 2x2) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Card 1: File-Based Routing -->
        <div class="pearl-card p-6 space-y-3 hover:border-accent/50 transition-colors">
            <div class="w-9 h-9 rounded-lg bg-accent/10 text-accent flex items-center justify-center">
                <?= icon('layout-dashboard', 'w-5 h-5') ?>
            </div>
            <h2 class="font-display text-base font-semibold text-ink">File-Based Routing</h2>
            <p class="text-sm text-muted leading-relaxed">
                URLs map straight to domain files in <code class="font-mono text-xs px-1.5 py-0.5 rounded bg-canvas border border-border text-ink">wire/</code> by longest-prefix match. No routing tables or controller boilerplate.
            </p>
            <div class="pt-2 font-mono text-xs text-muted-2">
                Edit <span class="text-ink font-semibold">wire/home.php</span> to customize this page
            </div>
        </div>

        <!-- Card 2: Dual Auth Security -->
        <div class="pearl-card p-6 space-y-3 hover:border-accent/50 transition-colors">
            <div class="w-9 h-9 rounded-lg bg-accent/10 text-accent flex items-center justify-center">
                <?= icon('shield-check', 'w-5 h-5') ?>
            </div>
            <h2 class="font-display text-base font-semibold text-ink">Dual-Context Authentication</h2>
            <p class="text-sm text-muted leading-relaxed">
                Users and administrators are permanently isolated across separate tables, sessions, and cookies. Privilege escalation is structurally impossible.
            </p>
            <div class="pt-2 flex items-center gap-4 text-xs font-medium">
                <a href="/login" class="text-accent hover:underline flex items-center gap-1">
                    <span>User Login</span> &rarr;
                </a>
                <a href="/admin/login" class="text-accent hover:underline flex items-center gap-1">
                    <span>Admin Login</span> &rarr;
                </a>
            </div>
        </div>

        <!-- Card 3: Fluent SQL -->
        <div class="pearl-card p-6 space-y-3 hover:border-accent/50 transition-colors">
            <div class="w-9 h-9 rounded-lg bg-accent/10 text-accent flex items-center justify-center">
                <?= icon('settings', 'w-5 h-5') ?>
            </div>
            <h2 class="font-display text-base font-semibold text-ink">Fluent Query Builder</h2>
            <p class="text-sm text-muted leading-relaxed">
                Construct safe, parameter-bound SQL via <code class="font-mono text-xs px-1.5 py-0.5 rounded bg-canvas border border-border text-ink">query()</code> without heavy ORM memory overhead or implicit mutations.
            </p>
            <div class="pt-2 font-mono text-xs text-muted-2">
                <span class="text-accent">query</span>('users')->where('active', 1)->get();
            </div>
        </div>

        <!-- Card 4: Built-in Subsystems -->
        <div class="pearl-card p-6 space-y-3 hover:border-accent/50 transition-colors">
            <div class="w-9 h-9 rounded-lg bg-accent/10 text-accent flex items-center justify-center">
                <?= icon('lock', 'w-5 h-5') ?>
            </div>
            <h2 class="font-display text-base font-semibold text-ink">Zero-Dependency Tooling</h2>
            <p class="text-sm text-muted leading-relaxed">
                Includes atomic background queues, raw socket SMTP email, sharded caching, rate limiting, and single-DDL migrations out of the box.
            </p>
            <div class="pt-2 font-mono text-xs text-muted-2">
                <span class="text-accent">$</span> php bin/pearl notify:work
            </div>
        </div>
    </div>

    <!-- Terminal Quickstart Box -->
    <div class="bg-[#0f1117] border border-zinc-800 rounded-xl p-5 text-zinc-100 shadow-md">
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-zinc-800 text-xs text-zinc-400 font-mono">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500/80"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></span>
                <span class="ml-2 text-zinc-300">Terminal Quickstart</span>
            </div>
            <span class="text-zinc-500">bin/pearl</span>
        </div>
        <div class="space-y-2 font-mono text-xs sm:text-sm text-zinc-300">
            <div class="flex items-center gap-2">
                <span class="text-accent select-none">$</span>
                <span>composer create-project bluxtechnologies/pearl my-app</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-accent select-none">$</span>
                <span>cd my-app && php bin/pearl serve --port=7200</span>
            </div>
        </div>
    </div>
</div>

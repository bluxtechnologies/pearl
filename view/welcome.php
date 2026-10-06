<div class="space-y-12 pearl-animate-in">
    <!-- Hero Section -->
    <div class="text-center max-w-3xl mx-auto space-y-5">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-surface border border-border shadow-xs text-xs font-medium text-muted">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Framework Operational</span>
            <span class="text-border">•</span>
            <span class="font-mono text-ink">PHP <?= e($phpVersion) ?></span>
        </div>

        <h1 class="font-display text-4xl sm:text-5xl font-bold tracking-tight text-ink leading-tight">
            Build faster with <span class="text-accent underline decoration-accent/20 underline-offset-4">procedural simplicity</span>.
        </h1>

        <p class="text-base sm:text-lg text-muted max-w-2xl mx-auto leading-relaxed">
            Pearl is a modern, high-performance PHP framework with file-based routing, strict security boundaries, isolated dual-context authentication, and zero hidden magic.
        </p>

        <!-- Action Buttons -->
        <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
            <a href="/login" class="pearl-btn pearl-btn--primary px-5 py-2.5">
                <?= icon('user', 'w-4 h-4') ?>
                <span>User Login</span>
            </a>
            <a href="/admin/login" class="pearl-btn pearl-btn--secondary px-5 py-2.5">
                <?= icon('shield-check', 'w-4 h-4') ?>
                <span>Admin Login</span>
            </a>
            <a
                href="https://github.com/bluxtechnologies/pearl#readme"
                target="_blank"
                rel="noopener noreferrer"
                class="pearl-btn pearl-btn--ghost px-4 py-2.5"
            >
                <?= icon('info', 'w-4 h-4') ?>
                <span>Read Documentation</span>
            </a>
        </div>
    </div>

    <!-- Quickstart Banner -->
    <div class="bg-surface border border-border rounded-xl p-5 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg bg-info-bg text-info flex items-center justify-center flex-shrink-0 mt-0.5">
                <?= icon('check', 'w-5 h-5') ?>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-ink">Installation Verified & Ready to Build</h2>
                <p class="text-xs text-muted mt-0.5">
                    To start building your own application, open <code class="px-1.5 py-0.5 rounded bg-canvas border border-border text-ink font-mono text-[11px]">wire/home.php</code> and edit this controller.
                </p>
            </div>
        </div>
        <div class="flex-shrink-0 self-stretch md:self-auto flex items-center">
            <div class="w-full bg-canvas border border-border rounded-lg px-3 py-1.5 flex items-center gap-2 font-mono text-xs text-muted">
                <span class="text-accent">$</span>
                <span class="text-ink">php bin/pearl serve --port=7200</span>
            </div>
        </div>
    </div>

    <!-- Architectural Highlights & Code Showcase -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- Core Principles Grid (Left Column) -->
        <div class="lg:col-span-6 space-y-4">
            <h2 class="font-display text-lg font-semibold text-ink flex items-center gap-2">
                <span>Core Architectural Invariants</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Pillar 1: Dual Auth -->
                <div class="pearl-card p-5 space-y-2 border-border/80 hover:border-accent/40 transition-colors">
                    <div class="w-8 h-8 rounded-md bg-accent/10 text-accent flex items-center justify-center">
                        <?= icon('shield-check', 'w-4 h-4') ?>
                    </div>
                    <h3 class="text-sm font-semibold text-ink">Dual-Context Auth</h3>
                    <p class="text-xs text-muted leading-relaxed">
                        <code class="font-mono text-[11px] text-ink">users</code> and <code class="font-mono text-[11px] text-ink">admins</code> are isolated in separate tables, sessions, and cookies. Privilege escalation via role collision is structurally impossible.
                    </p>
                </div>

                <!-- Pillar 2: File-Based Routing -->
                <div class="pearl-card p-5 space-y-2 border-border/80 hover:border-accent/40 transition-colors">
                    <div class="w-8 h-8 rounded-md bg-accent/10 text-accent flex items-center justify-center">
                        <?= icon('layout-dashboard', 'w-4 h-4') ?>
                    </div>
                    <h3 class="text-sm font-semibold text-ink">Longest-Prefix Routing</h3>
                    <p class="text-xs text-muted leading-relaxed">
                        URLs map straight into <code class="font-mono text-[11px] text-ink">wire/</code> domain modules. No routing tables, reflection magic, or controller class hierarchies.
                    </p>
                </div>

                <!-- Pillar 3: Fluent SQL -->
                <div class="pearl-card p-5 space-y-2 border-border/80 hover:border-accent/40 transition-colors">
                    <div class="w-8 h-8 rounded-md bg-accent/10 text-accent flex items-center justify-center">
                        <?= icon('settings', 'w-4 h-4') ?>
                    </div>
                    <h3 class="text-sm font-semibold text-ink">Fluent Query Builder</h3>
                    <p class="text-xs text-muted leading-relaxed">
                        Construct safe, parameter-bound SQL via <code class="font-mono text-[11px] text-ink">query()</code> without heavyweight ORM models or hidden database mutations.
                    </p>
                </div>

                <!-- Pillar 4: Multi-Driver Subsystems -->
                <div class="pearl-card p-5 space-y-2 border-border/80 hover:border-accent/40 transition-colors">
                    <div class="w-8 h-8 rounded-md bg-accent/10 text-accent flex items-center justify-center">
                        <?= icon('lock', 'w-4 h-4') ?>
                    </div>
                    <h3 class="text-sm font-semibold text-ink">Multi-Driver Core</h3>
                    <p class="text-xs text-muted leading-relaxed">
                        Built-in drivers for Sessions, Cache, and Queues across File, Database (PDO), and Redis with zero extra package dependencies.
                    </p>
                </div>
            </div>
        </div>

        <!-- Code Preview (Right Column) -->
        <div class="lg:col-span-6 space-y-4">
            <h2 class="font-display text-lg font-semibold text-ink flex items-center gap-2">
                <span>The Pearl Way — Pure Procedural Clarity</span>
            </h2>

            <div class="rounded-xl overflow-hidden border border-zinc-800 bg-[#0f1117] text-zinc-100 shadow-md">
                <!-- Terminal Window Title -->
                <div class="px-4 py-2.5 bg-zinc-900/90 border-b border-zinc-800 flex items-center justify-between text-xs text-zinc-400 font-mono">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500/80"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></span>
                        <span class="ml-2 text-zinc-400 font-mono text-[11px]">wire/posts.php</span>
                    </div>
                    <span class="text-zinc-500 text-[11px]">Fat Domain Module</span>
                </div>

                <!-- Code Body -->
                <div class="p-4 overflow-x-auto font-mono text-xs leading-relaxed">
<pre><code class="language-php"><span class="text-zinc-500">&lt;?php</span>
<span class="text-purple-400">declare</span>(strict_types=1);

<span class="text-blue-400">match</span> (request_method()) {
    <span class="text-emerald-400">'GET'</span> => (function () use (<span class="text-amber-300">$params</span>) {
        <span class="text-amber-300">$posts</span> = query(<span class="text-emerald-400">'posts'</span>)
            ->where(<span class="text-emerald-400">'published'</span>, 1)
            ->orderBy(<span class="text-emerald-400">'created_at'</span>, <span class="text-emerald-400">'DESC'</span>)
            ->get();

        <span class="text-blue-400">return</span> pearl_view(<span class="text-emerald-400">'posts/index'</span>, [<span class="text-emerald-400">'posts'</span> => <span class="text-amber-300">$posts</span>]);
    })(),

    <span class="text-emerald-400">'POST'</span> => (function () {
        auth_require_login();
        <span class="text-amber-300">$input</span> = request_input();

        <span class="text-amber-300">$id</span> = query(<span class="text-emerald-400">'posts'</span>)->insert([
            <span class="text-emerald-400">'user_id'</span> => auth_user_id(),
            <span class="text-emerald-400">'title'</span>   => <span class="text-amber-300">$input</span>[<span class="text-emerald-400">'title'</span>],
        ]);

        response_redirect(<span class="text-emerald-400">"/posts/{$id}"</span>);
    })(),

    <span class="text-purple-400">default</span> => response_method_not_allowed([<span class="text-emerald-400">'GET'</span>, <span class="text-emerald-400">'POST'</span>]),
};</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- CLI Command Reference Section -->
    <div class="space-y-4 pt-4">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold text-ink">Essential CLI Tooling</h2>
            <span class="font-mono text-xs text-muted">php bin/pearl &lt;command&gt;</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="pearl-card p-3.5 space-y-1">
                <code class="text-xs font-mono font-bold text-accent">serve</code>
                <p class="text-[11px] text-muted">Starts the development server with Vite asset support.</p>
                <div class="pt-1 font-mono text-[10px] text-zinc-500">php bin/pearl serve --port=7200</div>
            </div>

            <div class="pearl-card p-3.5 space-y-1">
                <code class="text-xs font-mono font-bold text-accent">new</code>
                <p class="text-[11px] text-muted">Scaffolds a fresh Pearl application into a new folder.</p>
                <div class="pt-1 font-mono text-[10px] text-zinc-500">php bin/pearl new my-app</div>
            </div>

            <div class="pearl-card p-3.5 space-y-1">
                <code class="text-xs font-mono font-bold text-accent">migrate</code>
                <p class="text-[11px] text-muted">Executes atomic single-DDL migrations safely.</p>
                <div class="pt-1 font-mono text-[10px] text-zinc-500">php bin/pearl migrate</div>
            </div>

            <div class="pearl-card p-3.5 space-y-1">
                <code class="text-xs font-mono font-bold text-accent">make:migration</code>
                <p class="text-[11px] text-muted">Generates a new migration with up() and down().</p>
                <div class="pt-1 font-mono text-[10px] text-zinc-500">php bin/pearl make:migration create_posts</div>
            </div>

            <div class="pearl-card p-3.5 space-y-1">
                <code class="text-xs font-mono font-bold text-accent">notify:work</code>
                <p class="text-[11px] text-muted">Starts the background queue worker with atomic claims.</p>
                <div class="pt-1 font-mono text-[10px] text-zinc-500">php bin/pearl notify:work</div>
            </div>

            <div class="pearl-card p-3.5 space-y-1">
                <code class="text-xs font-mono font-bold text-accent">make:auth</code>
                <p class="text-[11px] text-muted">Scaffolds authentication tables and login wire modules.</p>
                <div class="pt-1 font-mono text-[10px] text-zinc-500">php bin/pearl make:auth</div>
            </div>

            <div class="pearl-card p-3.5 space-y-1">
                <code class="text-xs font-mono font-bold text-accent">deploy</code>
                <p class="text-[11px] text-muted">Runs production readiness audit and automated deploy.</p>
                <div class="pt-1 font-mono text-[10px] text-zinc-500">php bin/pearl deploy</div>
            </div>

            <div class="pearl-card p-3.5 space-y-1">
                <code class="text-xs font-mono font-bold text-accent">package</code>
                <p class="text-[11px] text-muted">Packages clean standalone release ZIP archive.</p>
                <div class="pt-1 font-mono text-[10px] text-zinc-500">php bin/pearl package</div>
            </div>
        </div>
    </div>
</div>

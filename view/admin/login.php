<h1 class="font-display text-xl font-semibold text-ink mb-1">Admin sign in</h1>
<p class="text-sm text-muted mb-6">Restricted access</p>

<?php if (!empty($error)): ?>
    <div class="pearl-alert pearl-alert--danger mb-6">
        <?= icon('circle-alert', 'w-5 h-5') ?>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<form method="post" action="/admin/login">
    <?= csrf_field() ?>
    <div class="pearl-field">
        <label class="pearl-label" for="email">Email</label>
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-2 pointer-events-none">
                <?= icon('mail', 'w-4 h-4') ?>
            </span>
            <input class="pearl-input pl-9" type="email" id="email" name="email" required autofocus>
        </div>
    </div>

    <div class="pearl-field" x-data="{ show: false }">
        <label class="pearl-label" for="password">Password</label>
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-2 pointer-events-none">
                <?= icon('lock', 'w-4 h-4') ?>
            </span>
            <input
                class="pearl-input pl-9 pr-9"
                :type="show ? 'text' : 'password'"
                id="password"
                name="password"
                required
            >
            <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-2 hover:text-ink transition-colors"
                @click="show = !show"
                :aria-label="show ? 'Hide password' : 'Show password'"
            >
                <span x-show="!show" x-cloak><?= icon('eye', 'w-4 h-4') ?></span>
                <span x-show="show" x-cloak><?= icon('eye-off', 'w-4 h-4') ?></span>
            </button>
        </div>
    </div>

    <button type="submit" class="pearl-btn pearl-btn--primary w-full mt-2">
        <?= icon('shield-check', 'w-4 h-4') ?>
        Sign in
    </button>
</form>

<div class="pearl-alert pearl-alert--info mb-6">
    <?= icon('info', 'w-5 h-5') ?>
    <span>This dashboard is scaffolding — reshape it once your real app's needs take shape.</span>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="pearl-card">
        <p class="text-xs font-medium text-muted uppercase tracking-wide mb-2">Total users</p>
        <p class="font-display text-2xl font-bold text-ink"><?= e((string) $totalUsers) ?></p>
    </div>
    <div class="pearl-card">
        <p class="text-xs font-medium text-muted uppercase tracking-wide mb-2">Active</p>
        <p class="font-display text-2xl font-bold text-success"><?= e((string) $activeUsers) ?></p>
    </div>
    <div class="pearl-card">
        <p class="text-xs font-medium text-muted uppercase tracking-wide mb-2">Pending</p>
        <p class="font-display text-2xl font-bold text-warning"><?= e((string) $pendingUsers) ?></p>
    </div>
</div>

<div class="flex items-center gap-2 mb-4">
    <span class="text-xs font-medium text-muted uppercase tracking-wide mr-2">Filters</span>
    <span class="pearl-pill">
        Active
        <button type="button" aria-label="Remove filter"><?= icon('x', 'w-3 h-3') ?></button>
    </span>
    <span class="pearl-pill">
        Last 30 days
        <button type="button" aria-label="Remove filter"><?= icon('x', 'w-3 h-3') ?></button>
    </span>
</div>

<div class="pearl-card">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-display text-sm font-semibold text-ink">Recent users</h2>
        <a href="/admin/users" class="pearl-btn pearl-btn--secondary pearl-btn--sm">
            <?= icon('users', 'w-4 h-4') ?>
            View all
        </a>
    </div>

    <?php if ($recentUsers === []): ?>
        <p class="text-sm text-muted">No users yet.</p>
    <?php else: ?>
        <table class="pearl-table">
            <thead>
                <tr>
                    <th class="w-8">
                        <input type="checkbox" class="pearl-checkbox" aria-label="Select all">
                    </th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentUsers as $user): ?>
                    <tr>
                        <td><input type="checkbox" class="pearl-checkbox" aria-label="Select <?= e($user['name']) ?>"></td>
                        <td class="font-medium"><?= e($user['name']) ?></td>
                        <td class="pearl-table-mono"><?= e($user['email']) ?></td>
                        <td>
                            <?php
                            $badgeClass = match ($user['status']) {
                                'active' => 'pearl-badge--success',
                                'pending' => 'pearl-badge--warning',
                                'suspended' => 'pearl-badge--danger',
                                default => 'pearl-badge--neutral',
                            };
                            ?>
                            <span class="pearl-badge <?= $badgeClass ?>"><?= e($user['status']) ?></span>
                        </td>
                        <td class="pearl-table-mono"><?= e($user['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

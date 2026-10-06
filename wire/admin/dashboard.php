<?php
/**
 * wire/admin/dashboard.php — admin dashboard shell.
 *
 * Deliberately built to exercise every Phase 5 component together
 * (cards, badges, alert, pill, checkbox, table) against real data
 * from the query builder — the Phase 5 "done" proof, same spirit as
 * wire/admin/users.php was for Phase 3.
 */

declare(strict_types=1);

auth_require_admin();

$totalUsers = query('users')->count();
$activeUsers = query('users')->where('status', '=', 'active')->count();
$pendingUsers = query('users')->where('status', '=', 'pending')->count();

$recentUsers = query('users')
    ->select('id', 'email', 'name', 'status', 'created_at')
    ->order('created_at', 'desc')
    ->limit(5)
    ->get();

pearl_view('admin/dashboard', [
    'title' => 'Dashboard',
    'pageTitle' => 'Dashboard',
    'totalUsers' => $totalUsers,
    'activeUsers' => $activeUsers,
    'pendingUsers' => $pendingUsers,
    'recentUsers' => $recentUsers,
], 'layouts/app');

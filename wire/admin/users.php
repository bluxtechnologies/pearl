<?php
/**
 * wire/admin/users.php — admin user management.
 *
 * Fat module handling the full CRUD lifecycle for /admin/users and
 * /admin/users/{id}, plus a joined login-history view — deliberately
 * built to exercise every part of the Phase 3 query builder together:
 * select(), where(), join(), the fixed order() (table-qualified +
 * accumulating), limit() via first(), insert(), update(), delete().
 *
 * Gated on auth_require_admin() — this path resolves to admin context
 * automatically (wire/admin/* convention from Phase 2), so this check
 * is a defensive backstop, not the only thing standing between an
 * anonymous request and this data.
 *
 * Routes (via $params from pearl_dispatch()):
 *   GET    /admin/users        list all users
 *   GET    /admin/users/{id}   one user + their login history (join demo)
 *   POST   /admin/users        create a user
 *   PUT    /admin/users/{id}   update a user
 *   DELETE /admin/users/{id}   delete a user
 */

declare(strict_types=1);

auth_require_admin();

$id = $params[0] ?? null;

// Columns explicitly listed (never '*') so password_hash never leaves
// this module, the same discipline auth_user() already follows.
$publicColumns = ['id', 'email', 'name', 'status', 'created_at', 'updated_at'];

match (request_method()) {
    'GET' => (function () use ($id, $publicColumns) {
        if ($id === null) {
            // List all users. Multi-order demo: group by status, then
            // alphabetical within each group — two order() calls that
            // must accumulate, not overwrite (the original bug).
            $users = query('users')
                ->select(...$publicColumns)
                ->order('status')
                ->order('name')
                ->get();

            response_json(['users' => $users]);
            return;
        }

        $user = query('users')
            ->select(...$publicColumns)
            ->where('id', '=', $id)
            ->first();

        if ($user === null) {
            response_status(404);
            return;
        }

        // Join demo: login_history joined to users, table-qualified
        // columns in both select() and order(), two accumulated
        // order() calls (most recent login first, then name as a
        // tiebreaker) — the exact scenario query.php was verified
        // against in Phase 3 testing.
        $history = query('login_history')
            ->select('login_history.id', 'login_history.logged_in_at', 'users.name')
            ->join('users', 'login_history.user_id', '=', 'users.id')
            ->where('login_history.user_id', '=', $id)
            ->order('login_history.logged_in_at', 'desc')
            ->order('users.name')
            ->get();

        response_json(['user' => $user, 'login_history' => $history]);
    })(),

    'POST' => (function () {
        $input = request_input();

        if (empty($input['email']) || empty($input['password']) || empty($input['name'])) {
            response_json(['error' => 'email, password, and name are required'], 422);
            return;
        }

        $newId = query('users')->insert([
            'email' => $input['email'],
            'password_hash' => auth_hash_password($input['password']),
            'name' => $input['name'],
            'status' => $input['status'] ?? 'pending',
        ]);

        response_json(['created' => true, 'id' => $newId], 201);
    })(),

    'PUT', 'PATCH' => (function () use ($id) {
        if ($id === null) {
            response_status(400);
            return;
        }

        $input = request_input();
        $data = [];

        foreach (['email', 'name', 'status'] as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        if (isset($input['password']) && $input['password'] !== '') {
            $data['password_hash'] = auth_hash_password($input['password']);
        }

        if ($data === []) {
            response_json(['error' => 'no fields to update'], 422);
            return;
        }

        $affected = query('users')->where('id', '=', $id)->update($data);
        response_json(['updated' => true, 'affected' => $affected]);
    })(),

    'DELETE' => (function () use ($id) {
        if ($id === null) {
            response_status(400);
            return;
        }

        $affected = query('users')->where('id', '=', $id)->delete();
        response_json(['deleted' => true, 'affected' => $affected]);
    })(),

    default => response_method_not_allowed(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
};

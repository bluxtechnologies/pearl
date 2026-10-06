<?php
/**
 * Welcome Email Template
 *
 * Variables:
 *   $name (string)
 *   $email (string)
 *   $actionUrl (string)
 */
?>
<h2 style="margin-top: 0; color: #f8fafc; font-size: 20px;">Welcome to Pearl, <?= htmlspecialchars($name ?? 'User', ENT_QUOTES, 'UTF-8') ?>!</h2>

<p>Your account has been successfully configured. Pearl is a lightweight, secure, procedural PHP framework designed for rapid and predictable application development.</p>

<p>Here are your account details:</p>
<ul style="padding-left: 20px; color: #94a3b8;">
    <li><strong>Email:</strong> <?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></li>
    <li><strong>Environment:</strong> <?= htmlspecialchars(env('APP_ENV', 'local'), ENT_QUOTES, 'UTF-8') ?></li>
</ul>

<?php if (!empty($actionUrl)): ?>
<div style="margin: 24px 0;">
    <a href="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn">Get Started</a>
</div>
<?php endif; ?>

<p style="margin-bottom: 0; color: #94a3b8; font-size: 13px;">If you have any questions, consult the project architecture documentation or reach out to your administrator.</p>

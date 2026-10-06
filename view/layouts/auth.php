<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Pearl') ?></title>
    <?= vite('main.js') ?>
</head>
<body class="bg-canvas min-h-screen flex items-center justify-center px-4 font-body text-ink">
    <div class="w-full max-w-sm pearl-animate-in">
        <div class="text-center mb-8">
            <span class="font-display text-2xl font-bold text-ink">Pearl</span>
        </div>

        <div class="pearl-card">
            <?= $content ?>
        </div>
    </div>
</body>
</html>

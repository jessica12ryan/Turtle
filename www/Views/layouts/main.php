<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title ?? 'Home') ?> - <?= h(site_name()) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@4.3.0/marked.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php require base_path('www/Views/partials/nav.php'); ?>
    <main class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($msg = flash('error')): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?= h($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?= h($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['_errors'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($_SESSION['_errors'] as $field => $errors): ?>
                        <?php foreach ((array)$errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['_errors']); ?>
        <?php endif; ?>
        <?= $content ?>
    </main>
</body>
</html>

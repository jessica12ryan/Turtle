<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title ?? 'Turtle') ?> - Turtle</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md mx-4">
        <div class="text-center mb-8">
            <img src="/assets/logo.svg" alt="Turtle" class="h-12 mx-auto">
            <p class="text-gray-500 mt-2">Tenant Management Portal</p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-8">
            <?php if ($msg = flash('error')): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= h($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('success')): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= h($msg) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </div>
    </div>
</body>
</html>

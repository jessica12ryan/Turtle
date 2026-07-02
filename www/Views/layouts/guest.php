<!DOCTYPE html>
<html lang="en" data-theme="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= h(base_url()) ?>/">
    <link rel="icon" type="image/svg+xml" href="/assets/logo-icon.svg">
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico" sizes="any">
    <script>window.baseUrl = '<?= h(base_url()) ?>';</script>
    <title><?= h($title ?? site_name()) ?> - <?= h(site_name()) ?></title>
    <script>
        (function() {
            var theme = document.documentElement.getAttribute('data-theme') || 'light';
            function apply() {
                if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
            apply();
            if (theme === 'system') {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', apply);
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .dark,
        .dark body { background-color: #0f172a; color: #e2e8f0; }

        .dark .bg-white { background-color: #1e293b !important; }
        .dark .bg-gray-100 { background-color: #0f172a !important; }

        .dark .text-gray-800 { color: #f1f5f9 !important; }
        .dark .text-gray-700 { color: #e2e8f0 !important; }
        .dark .text-gray-600 { color: #cbd5e1 !important; }
        .dark .text-gray-500 { color: #94a3b8 !important; }

        .dark .border-gray-300 { border-color: #475569 !important; }

        .dark .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5) !important; }

        .dark .bg-red-100 { background-color: #450a0a !important; border-color: #991b1b !important; color: #fecaca !important; }
        .dark .bg-green-100 { background-color: #052e16 !important; border-color: #15803d !important; color: #bbf7d0 !important; }

        .dark input,
        .dark select,
        .dark textarea { background-color: #0f172a !important; color: #e2e8f0 !important; border-color: #475569 !important; }

        .dark .logo-default { filter: brightness(0) invert(0.85); }

        .dark input[type="checkbox"] { accent-color: #60a5fa; }
        input[type="checkbox"] { accent-color: #2563eb; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md mx-4">
        <div class="text-center mb-8">
            <img src="<?= h(site_logo()) ?>" alt="<?= h(site_name()) ?>" class="h-12 mx-auto<?= site_logo() === '/assets/logo.svg' ? ' logo-default' : '' ?>">
            <p class="text-gray-500 mt-2"><?= __('Tenant Management Portal') ?></p>
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

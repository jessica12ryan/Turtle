<!DOCTYPE html>
<?php
$themeUser = \App\Core\Auth::instance()->user();
$themePref = $themeUser['theme'] ?? 'system';
?>
<html lang="en" data-theme="<?= h($themePref) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= h(base_url()) ?>/">
    <link rel="icon" type="image/svg+xml" href="/assets/logo-icon.svg">
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico" sizes="any">
    <script>window.baseUrl = '<?= h(base_url()) ?>';</script>
    <title><?= h($title ?? 'Home') ?> - <?= h(site_name()) ?></title>
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
    <script src="https://cdn.jsdelivr.net/npm/marked@4.3.0/marked.min.js"></script>
    <style>
        :root { color-scheme: light; }
        html[data-theme="dark"] { color-scheme: dark; }
        html[data-theme="light"] { color-scheme: light; }
        html:not(.dark) body { background-color: #f9fafb; color: #1f2937; }
        html:not(.dark) h1, html:not(.dark) h2, html:not(.dark) h3 { color: #111827; }
        html[data-theme="light"] .bg-white { background-color: #ffffff !important; }

        .dark,
        .dark body { background-color: #0f172a; color: #e2e8f0; }

        .dark .bg-white { background-color: #1e293b !important; }
        .dark .bg-gray-50 { background-color: #0f172a !important; }
        .dark .bg-gray-100 { background-color: #1e293b !important; }
        .dark .bg-gray-200 { background-color: #334155 !important; }

        .dark .text-gray-800 { color: #f1f5f9 !important; }
        .dark .text-gray-700 { color: #e2e8f0 !important; }
        .dark .text-gray-600 { color: #cbd5e1 !important; }
        .dark .text-gray-500 { color: #94a3b8 !important; }

        .dark .border-gray-300 { border-color: #475569 !important; }
        .dark .border-gray-200 { border-color: #334155 !important; }

        .dark .divide-gray-200 > * { border-color: #334155 !important; }
        .dark .border-t-2 { border-color: #334155 !important; }

        .dark .shadow { box-shadow: 0 1px 3px 0 rgba(0,0,0,0.5) !important; }
        .dark .shadow-md { box-shadow: 0 4px 6px -1px rgba(0,0,0,0.5) !important; }
        .dark .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5) !important; }
        .dark .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0,0,0,0.4) !important; }

        .dark .bg-red-100 { background-color: #450a0a !important; border-color: #991b1b !important; color: #fecaca !important; }
        .dark .bg-green-100 { background-color: #052e16 !important; border-color: #15803d !important; color: #bbf7d0 !important; }

        .dark a.text-blue-600,
        .dark .text-blue-600 { color: #60a5fa !important; }
        .dark a.text-blue-600:hover { color: #93c5fd !important; }

        .dark .hover\:bg-gray-50:hover { background-color: #334155 !important; }
        .dark .hover\:bg-gray-100:hover { background-color: #334155 !important; }

        .dark .bg-blue-600 { background-color: #2563eb !important; }
        .dark .bg-blue-600:hover { background-color: #1d4ed8 !important; }
        .dark .bg-blue-100 { background-color: #1e3a5f !important; color: #93c5fd !important; }
        .dark .text-blue-700 { color: #93c5fd !important; }

        .dark input,
        .dark select,
        .dark textarea { background-color: #0f172a !important; color: #e2e8f0 !important; border-color: #475569 !important; }
        .dark input:disabled,
        .dark select:disabled,
        .dark textarea:disabled,
        .dark input.bg-gray-100 { background-color: #1e293b !important; color: #64748b !important; }

        .dark hr { border-color: #334155 !important; }

        .dark .bg-orange-600 { background-color: #ea580c !important; }
        .dark .bg-red-600 { background-color: #dc2626 !important; }
        .dark .bg-green-600 { background-color: #16a34a !important; }

        .dark nav.bg-white { background-color: #1e293b !important; }
        .dark nav .text-gray-600 { color: #cbd5e1 !important; }
        .dark nav .hover\:text-gray-900:hover { color: #f1f5f9 !important; }
        .dark nav a.text-gray-600 { color: #cbd5e1 !important; }
        .dark nav a:hover.text-gray-900 { color: #f1f5f9 !important; }

        .dark .bg-white.rounded-lg.shadow { background-color: #1e293b !important; }

        .dark table.bg-white { background-color: #1e293b !important; }

        .dark th.bg-gray-50 { background-color: #1e293b !important; }

        .dark .logo-default { filter: brightness(0) invert(0.85); }

        .dark input[type="checkbox"] { accent-color: #60a5fa; }
        input[type="checkbox"] { accent-color: #2563eb; }
    </style>
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

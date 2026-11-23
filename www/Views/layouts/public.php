<?php $publicUser = \App\Core\Auth::instance()->user(); ?>
<!DOCTYPE html>
<html lang="<?= h(current_language()) ?>" data-theme="system">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Visitor chat URLs carry a bearer token: never leak them via Referer. -->
    <meta name="referrer" content="no-referrer">
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js" integrity="sha384-X9kJyAubVxnP0hcA+AMMs21U445qsnqhnUF8EBlEpP3a42Kh/JwWjlv2ZcvGfphb" crossorigin="anonymous"></script>
    <style>
        [x-cloak] { display: none !important; }
        .dark,
        .dark body { background-color: #0f172a; color: #e2e8f0; }
        .dark input,
        .dark textarea { background-color: #0f172a !important; color: #e2e8f0 !important; border-color: #475569 !important; }
        .dark .logo-default { filter: brightness(0) invert(0.85); }
        .listing-clamp { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .listing-clamp-expanded { display: block; overflow: visible; }
        .listing-photo { -webkit-user-drag: none; user-select: none; -webkit-touch-callout: none; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <header class="bg-white shadow-sm dark:bg-slate-900 dark:border-b dark:border-slate-700">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
            <a href="/listings" class="flex items-center space-x-3 min-w-0">
                <img src="<?= h(site_logo()) ?>" alt="<?= h(site_name()) ?>" class="h-10 flex-shrink-0<?= site_logo() === '/assets/logo.svg' ? ' logo-default' : '' ?>">
            </a>
            <nav class="flex items-center gap-3 sm:gap-5 text-sm">
                <a href="/applications/create" class="text-gray-600 hover:text-gray-900 dark:text-slate-300 dark:hover:text-white"><?= __('Application for Tenancy') ?></a>
                <?php if ($publicUser): ?>
                    <a href="/home" data-testid="dashboard-link" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700"><?= __('Dashboard') ?></a>
                    <form method="POST" action="/logout" class="inline">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <button type="submit" data-testid="logout-button" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"><?= __('Logout') ?></button>
                    </form>
                <?php else: ?>
                    <a href="/login" data-testid="login-link" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700"><?= __('Sign In') ?></a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="flex-1 w-full max-w-6xl mx-auto px-4 py-10">
        <?php if ($msg = flash('error')): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?= h($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?= h($msg) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>

    <footer class="text-center text-xs text-gray-500 py-6">
        &copy; <?= date('Y') ?> <?= h(site_name()) ?>
    </footer>
</body>
</html>

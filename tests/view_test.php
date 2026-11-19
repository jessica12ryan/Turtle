<?php
// Minimal smoke test for the view renderer. Run with: php tests/view_test.php

require_once __DIR__ . '/../www/autoload.php';

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__) . '/' . ltrim($path, '/');
    }
}

$view = new \App\Core\View();
ob_start();
$view->render('../../tests/fixtures/view_data', ['data' => ['key' => 'value']]);
$output = ob_get_clean();

if ($output !== 'value') {
    fwrite(STDERR, "FAIL: expected 'value', got '{$output}'\n");
    exit(1);
}

$layoutView = new \App\Core\View();
$layoutView->layout('../../tests/fixtures/layout_data', ['data' => ['key' => 'value']]);
ob_start();
$layoutView->render('../../tests/fixtures/view_data', ['data' => ['key' => 'value']]);
$layoutOutput = ob_get_clean();

if ($layoutOutput !== 'LAYOUT:value:value') {
    fwrite(STDERR, "FAIL: expected 'LAYOUT:value:value', got '{$layoutOutput}'\n");
    exit(1);
}

echo "View data variable OK\n";

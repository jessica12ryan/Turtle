<?php

// Test-only bootstrap: minimal helpers needed by the code under test.

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__) . '/' . ltrim($path, '/');
    }
}

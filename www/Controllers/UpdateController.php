<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class UpdateController
{
    public function index(): void
    {
        $currentVersion = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'app_version'");
        $latestVersion = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'latest_version'");
        $lastCheck = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'last_update_check'");

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Updates']);
        $view->render('updates/index', [
            'currentVersion' => $currentVersion['value'] ?? '0.0.0',
            'latestVersion' => $latestVersion['value'] ?? '',
            'lastCheck' => $lastCheck['value'] ?? '',
        ]);
    }

    public function check(): void
    {
        header('Content-Type: application/json');

        $repo = 'jessica12ryan/Turtle';
        $url = "https://api.github.com/repos/{$repo}/releases/latest";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Turtle-App/1.0\r\nAccept: application/vnd.github.v3+json\r\n",
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            http_response_code(502);
            echo json_encode(['error' => 'Could not reach GitHub API.']);
            return;
        }

        $release = json_decode($response, true);

        if (!$release || !isset($release['tag_name'])) {
            http_response_code(502);
            echo json_encode(['error' => 'Invalid response from GitHub.']);
            return;
        }

        $latestVersion = ltrim($release['tag_name'], 'v');
        $downloadUrl = $release['zipball_url'] ?? '';
        $releaseUrl = $release['html_url'] ?? '';
        $releaseBody = $release['body'] ?? '';

        Database::execute("UPDATE settings SET `value` = ? WHERE `key` = 'latest_version'", [$latestVersion]);
        Database::execute("UPDATE settings SET `value` = ? WHERE `key` = 'last_update_check'", [date('Y-m-d H:i:s')]);

        $currentVersion = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'app_version'");
        $currentVersion = $currentVersion['value'] ?? '0.0.0';

        $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

        echo json_encode([
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'update_available' => $updateAvailable,
            'release_url' => $releaseUrl,
            'release_body' => $releaseBody,
        ]);
    }

    public function apply(): void
    {
        header('Content-Type: application/json');

        $updateId = bin2hex(random_bytes(8));
        $logFile = sys_get_temp_dir() . "/turtle_update_{$updateId}.log";

        $setupCmd = 'git config --global --add safe.directory /var/www/html 2>/dev/null; cd /var/www/html';

        $steps = [
            'Fetching latest code...' => "{$setupCmd} && git fetch origin 2>&1",
            'Checking for changes...' => "{$setupCmd} && git log HEAD..origin/main --oneline 2>&1",
            'Pulling updates...' => "{$setupCmd} && git pull origin main 2>&1",
            'Running migrations...' => "{$setupCmd} && bash docker/php/start.sh 2>&1",
        ];

        $script = '#!/bin/bash' . "\n";
        foreach ($steps as $label => $cmd) {
            $script .= "echo '[TURTLE_STEP] {$label}'\n";
            $script .= "{$cmd}\n";
            $script .= "echo '[TURTLE_DONE]'\n";
        }
        $script .= "echo '[TURTLE_COMPLETE]'\n";

        $scriptFile = sys_get_temp_dir() . "/turtle_update_{$updateId}.sh";
        file_put_contents($scriptFile, $script);
        chmod($scriptFile, 0755);

        $escapedLogFile = escapeshellarg($logFile);
        $escapedScript = escapeshellarg($scriptFile);
        exec("nohup {$escapedScript} > {$escapedLogFile} 2>&1 & echo $!", $pidOutput);

        $pid = (int)($pidOutput[0] ?? 0);
        file_put_contents($logFile . '.pid', (string)$pid);

        echo json_encode([
            'update_id' => $updateId,
            'pid' => $pid,
        ]);
    }

    public function progress(): void
    {
        header('Content-Type: application/json');

        $updateId = $_GET['update_id'] ?? '';
        if (!$updateId || !preg_match('/^[a-f0-9]{16}$/', $updateId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid update ID.']);
            return;
        }

        $logFile = sys_get_temp_dir() . "/turtle_update_{$updateId}.log";
        $pidFile = $logFile . '.pid';
        $scriptFile = sys_get_temp_dir() . "/turtle_update_{$updateId}.sh";

        $done = false;
        if (file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            $done = !file_exists("/proc/{$pid}");
        } else {
            $done = true;
        }

        if (!file_exists($logFile)) {
            echo json_encode(['steps' => [], 'done' => $done, 'current_step' => '', 'error' => 'No log file found.']);
            return;
        }

        $log = file_get_contents($logFile);
        $lines = explode("\n", trim($log));

        $steps = [];
        $currentStep = '';
        $error = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, '[TURTLE_STEP]')) {
                $currentStep = trim(substr($line, strlen('[TURTLE_STEP]')));
                $steps[] = ['label' => $currentStep, 'status' => 'in_progress'];
            } elseif (str_starts_with($line, '[TURTLE_DONE]')) {
                if (!empty($steps)) {
                    $steps[count($steps) - 1]['status'] = 'done';
                }
            } elseif (str_starts_with($line, '[TURTLE_COMPLETE]')) {
                $done = true;
            } elseif (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                $error .= $line . "\n";
            }
        }

        if ($done && file_exists($scriptFile)) {
            $version = trim(shell_exec('cd /var/www/html && git describe --tags 2>/dev/null') ?? '');
            if ($version) {
                $version = ltrim($version, 'v');
                Database::execute("UPDATE settings SET `value` = ? WHERE `key` = 'app_version'", [$version]);
            }

            @unlink($logFile);
            @unlink($pidFile);
            @unlink($scriptFile);
        }

        echo json_encode([
            'steps' => $steps,
            'done' => $done,
            'current_step' => $currentStep,
            'error' => trim($error),
        ]);
    }

    public static function getLatestVersion(): ?string
    {
        try {
            $repo = 'jessica12ryan/Turtle';
            $url = "https://api.github.com/repos/{$repo}/releases/latest";

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Turtle-App/1.0\r\nAccept: application/vnd.github.v3+json\r\n",
                    'timeout' => 5,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) return null;

            $release = json_decode($response, true);
            return isset($release['tag_name']) ? ltrim($release['tag_name'], 'v') : null;
        } catch (\Throwable $e) {
            error_log('Update check failed: ' . $e->getMessage());
            return null;
        }
    }
}

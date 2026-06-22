<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class UpdateController
{
    public function check(): void
    {
        header('Content-Type: application/json');

        $channel = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'update_channel'");
        $channel = $channel['value'] ?? 'stable';

        $currentVersion = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'app_version'");
        $currentVersion = $currentVersion['value'] ?? '0.0.0';

        if ($channel === 'development') {
            $result = $this->checkDevChannel($currentVersion);
        } else {
            $result = $this->checkStableChannel($currentVersion);
        }

        Database::execute("UPDATE settings SET `value` = ? WHERE `key` = 'last_update_check'", [date('Y-m-d H:i:s')]);

        if (isset($result['latest_version'])) {
            Database::execute("UPDATE settings SET `value` = ? WHERE `key` = 'latest_version'", [$result['latest_version']]);
        }

        echo json_encode(array_merge([
            'current_version' => $currentVersion,
            'channel' => $channel,
        ], $result));
    }

    private function checkStableChannel(string $currentVersion): array
    {
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
            return ['error' => 'Could not reach GitHub API.'];
        }

        $release = json_decode($response, true);

        if (!$release || !isset($release['tag_name'])) {
            return ['error' => 'Invalid response from GitHub.'];
        }

        $latestVersion = ltrim($release['tag_name'], 'v');
        $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

        return [
            'latest_version' => $latestVersion,
            'update_available' => $updateAvailable,
            'release_url' => $release['html_url'] ?? '',
            'release_body' => $release['body'] ?? '',
        ];
    }

    private function checkDevChannel(string $currentVersion): array
    {
        $setupCmd = 'git config --global --add safe.directory /var/www/html 2>/dev/null; cd /var/www/html';
        exec("{$setupCmd} && git fetch origin 2>&1", $fetchOutput, $fetchExitCode);

        if ($fetchExitCode !== 0) {
            return ['error' => 'Failed to fetch from remote.', 'latest_version' => $currentVersion, 'update_available' => false];
        }

        exec("{$setupCmd} && git rev-list --count HEAD..origin/master 2>&1", $countOutput, $countExitCode);

        if ($countExitCode !== 0) {
            exec("{$setupCmd} && git rev-parse --short HEAD 2>&1", $hashOutput);
            $currentHash = trim($hashOutput[0] ?? $currentVersion);
            return ['error' => 'Could not check origin/master. Ensure the remote branch exists.', 'latest_version' => $currentHash, 'update_available' => false];
        }

        $behindCount = (int)trim($countOutput[0] ?? '0');

        exec("{$setupCmd} && git rev-parse --short HEAD 2>&1", $hashOutput);
        $currentHash = trim($hashOutput[0] ?? $currentVersion);

        if ($behindCount > 0) {
            exec("{$setupCmd} && git rev-parse --short origin/master 2>&1", $remoteHashOutput);
            $remoteHash = trim($remoteHashOutput[0] ?? '');
            return [
                'latest_version' => $remoteHash ?: "origin/master",
                'update_available' => true,
                'behind_count' => $behindCount,
                'release_body' => "{$behindCount} commit(s) behind origin/master.",
            ];
        }

        return [
            'latest_version' => $currentHash,
            'update_available' => false,
            'behind_count' => 0,
            'release_body' => '',
        ];
    }

    public function apply(): void
    {
        header('Content-Type: application/json');

        $updateId = bin2hex(random_bytes(8));
        $logFile = sys_get_temp_dir() . "/turtle_update_{$updateId}.log";

        $setupCmd = 'git config --global --add safe.directory /var/www/html 2>/dev/null; cd /var/www/html';

        $steps = [
            'Preparing working directory...' => "{$setupCmd} && git reset --hard HEAD 2>&1 && git clean -fd 2>&1",
            'Fetching latest code...' => "{$setupCmd} && git fetch origin 2>&1",
            'Checking for changes...' => "{$setupCmd} && git log HEAD..origin/master --oneline 2>&1",
            'Pulling updates...' => "{$setupCmd} && git pull origin master 2>&1",
            'Running migrations...' => "{$setupCmd} && bash database/migrate.sh 2>&1",
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

    public static function getLatestVersion(?string $channel = null): ?string
    {
        if ($channel === null) {
            $row = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'update_channel'");
            $channel = $row['value'] ?? 'stable';
        }

        if ($channel === 'development') {
            $setupCmd = 'git config --global --add safe.directory /var/www/html 2>/dev/null; cd /var/www/html';
            exec("{$setupCmd} && git fetch origin 2>&1", $fetchOutput, $fetchExitCode);
            if ($fetchExitCode !== 0) return null;

            exec("{$setupCmd} && git rev-list --count HEAD..origin/master 2>&1", $countOutput, $countExitCode);
            if ($countExitCode !== 0) return null;

            $behindCount = (int)trim($countOutput[0] ?? '0');
            if ($behindCount <= 0) return null;

            exec("{$setupCmd} && git rev-parse --short origin/master 2>&1", $hashOutput);
            return trim($hashOutput[0] ?? '');
        }

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

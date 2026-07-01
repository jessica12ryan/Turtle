<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class UpdateController
{
    private static function repoPath(): string
    {
        static $path = null;
        if ($path === null) {
            $path = escapeshellarg(dirname(__DIR__, 2));
        }
        return $path;
    }

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
            'last_check' => date('M j, Y g:i A'),
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
        $git = 'git -c safe.directory=' . self::repoPath() . ' -C ' . self::repoPath();

        // Try git fetch first — works when container has outbound git access
        exec("{$git} fetch origin 2>&1", $fetchOutput, $fetchExitCode);

        if ($fetchExitCode !== 0) {
            $output = implode("\n", $fetchOutput);
            error_log("checkDevChannel: git fetch failed (exit {$fetchExitCode}): {$output}");
            // Fall back to GitHub API
            return $this->checkDevViaApi($currentVersion);
        }

        exec("{$git} rev-list --count HEAD..origin/master 2>&1", $countOutput, $countExitCode);

        if ($countExitCode !== 0) {
            $countOut = implode("\n", $countOutput);
            error_log("checkDevChannel: git rev-list failed (exit {$countExitCode}): {$countOut}");
            return $this->checkDevViaApi($currentVersion);
        }

        $behindCount = (int)trim($countOutput[0] ?? '0');

        exec("{$git} rev-parse --short HEAD 2>&1", $hashOutput);
        $currentHash = trim($hashOutput[0] ?? $currentVersion);

        if ($behindCount > 0) {
            exec("{$git} rev-parse --short origin/master 2>&1", $remoteHashOutput);
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

    private function checkDevViaApi(string $currentVersion): array
    {
        $repo = 'jessica12ryan/Turtle';

        // Get local HEAD via git (no network needed)
        exec('git -c safe.directory=' . self::repoPath() . ' -C ' . self::repoPath() . ' rev-parse --short HEAD 2>&1', $localHashOutput, $localExitCode);
        $localHash = $localExitCode === 0 ? trim($localHashOutput[0] ?? '') : '';

        // Fetch latest commit on master via GitHub API
        $url = "https://api.github.com/repos/{$repo}/commits/master";
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Turtle-App/1.0\r\nAccept: application/vnd.github.v3+json\r\n",
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            error_log('checkDevViaApi: GitHub API unreachable after git fetch failed');
            return ['error' => 'Cannot reach GitHub. Check container network access.', 'latest_version' => $currentVersion, 'update_available' => false];
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['sha'])) {
            error_log('checkDevViaApi: invalid GitHub API response');
            return ['error' => 'Invalid response from GitHub API.', 'latest_version' => $currentVersion, 'update_available' => false];
        }

        $remoteHash = $data['sha'];
        $remoteShort = substr($remoteHash, 0, 7);
        $commitMsg = $data['commit']['message'] ?? '';
        $commitUrl = $data['html_url'] ?? '';

        $localRef = $localHash !== '' ? $localHash : $currentVersion;
        $updateAvailable = $localRef !== $remoteShort;
        return [
            'latest_version' => $remoteShort,
            'update_available' => $updateAvailable,
            'behind_count' => $updateAvailable ? 1 : 0,
            'release_body' => $updateAvailable ? "Update available: {$commitMsg}" : '',
        ];
    }

    public function apply(): void
    {
        header('Content-Type: application/json');

        $updateId = bin2hex(random_bytes(8));
        $logFile = sys_get_temp_dir() . "/turtle_update_{$updateId}.log";

        $repo = self::repoPath();
        $git = 'git -c safe.directory=' . $repo;
        $cd = 'cd ' . $repo;

        $steps = [
            'Fixing permissions...' => "chmod -R a+w {$repo} 2>/dev/null; rm -f {$repo}/storage/framework {$repo}/storage/logs {$repo}/storage/uploads; true",
            'Preparing working directory...' => "{$cd} && {$git} reset --hard HEAD 2>&1 && {$git} clean -fd -e www/assets/uploads/logo/ -e storage/uploads/ 2>&1",
            'Ensuring storage directories...' => "{$cd} && mkdir -p storage/uploads/property_photos storage/uploads/leases storage/uploads/application_photos storage/framework storage/logs www/assets/uploads/logo 2>&1",
            'Fetching latest code...' => "{$cd} && {$git} fetch origin 2>&1",
            'Checking for changes...' => "{$cd} && {$git} log HEAD..origin/master --oneline 2>&1",
            'Pulling updates...' => "{$cd} && {$git} pull origin master 2>&1",
            'Running migrations...' => "{$cd} && bash database/migrate.sh 2>&1",
            'Restarting services...' => "{$cd} && php -r 'opcache_reset();' 2>&1; apachectl graceful 2>&1 || httpd -k graceful 2>&1 || true",
        ];

        $script = '#!/bin/bash' . "\n";
        $script .= "export HOME=/tmp\n";
        foreach ($steps as $label => $cmd) {
            $script .= "echo '[TURTLE_STEP] {$label}'\n";
            $script .= "{$cmd}\n";
            $script .= "EXITCODE=\$?\n";
            $script .= "echo \"[TURTLE_EXIT: \${EXITCODE}]\"\n";
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
        $currentOutput = '';
        $error = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, '[TURTLE_STEP]')) {
                $currentStep = trim(substr($line, strlen('[TURTLE_STEP]')));
                $currentOutput = '';
                $steps[] = ['label' => $currentStep, 'status' => 'in_progress'];
            } elseif (str_starts_with($line, '[TURTLE_EXIT:')) {
                $exitCode = (int)trim(substr($line, strlen('[TURTLE_EXIT:'), -1));
                if ($exitCode !== 0 && $currentOutput !== '') {
                    $error .= "Step '{$currentStep}' failed (exit code {$exitCode}):\n{$currentOutput}\n";
                }
            } elseif (str_starts_with($line, '[TURTLE_DONE]')) {
                if (!empty($steps)) {
                    $steps[count($steps) - 1]['status'] = 'done';
                }
            } elseif (str_starts_with($line, '[TURTLE_COMPLETE]')) {
                $done = true;
            } else {
                $currentOutput .= $line . "\n";
            }
        }

        if ($done && file_exists($scriptFile)) {
            $version = trim(shell_exec('git -c safe.directory=' . self::repoPath() . ' -C ' . self::repoPath() . ' describe --tags 2>/dev/null') ?? '');
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
            $git = 'git -c safe.directory=' . self::repoPath() . ' -C ' . self::repoPath();
            exec("{$git} fetch origin 2>&1", $fetchOutput, $fetchExitCode);
            if ($fetchExitCode !== 0) return null;

            exec("{$git} rev-list --count HEAD..origin/master 2>&1", $countOutput, $countExitCode);
            if ($countExitCode !== 0) return null;

            $behindCount = (int)trim($countOutput[0] ?? '0');
            if ($behindCount <= 0) return null;

            exec("{$git} rev-parse --short origin/master 2>&1", $hashOutput);
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

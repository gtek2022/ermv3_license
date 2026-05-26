<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Cron management service — detect, install, verify, and monitor the
 * Laravel scheduler cron entry across multiple server environments.
 *
 * Supported install modes (auto-detected):
 *   1. user-crontab     → `crontab -l | crontab -` (most portable)
 *   2. spool-direct     → write to /var/spool/cron/crontabs/<user> (requires root)
 *   3. cron-d-fragment  → /etc/cron.d/laravel-{appname} (requires root, cleanest)
 *   4. aapanel          → /www/server/cron/<hash> + register in root crontab
 *   5. unsupported      → user must add manually, page shows snippet
 *
 * The "tick file" approach (touched by HeartbeatService on every successful
 * run) lets us verify cron is actually firing — even without OS introspection.
 */
class CronManager
{
    public const TICK_FILE = 'storage/app/.cron-last-tick';

    /**
     * Inspect environment and report what install modes are available.
     */
    public function detectCapabilities(): array
    {
        $isRoot       = (function_exists('posix_geteuid') && posix_geteuid() === 0);
        $hasCrontab   = $this->commandExists('crontab');
        $hasAaPanel   = is_dir('/www/server/panel');
        $cronDirWritable = is_writable('/etc/cron.d');
        $aapanelCronDir  = '/www/server/cron';
        $aapanelCronWritable = is_dir($aapanelCronDir) && is_writable($aapanelCronDir);
        $userName     = function_exists('posix_geteuid')
            ? posix_getpwuid(posix_geteuid())['name'] ?? 'unknown'
            : (getenv('USER') ?: 'unknown');

        return [
            'php_user'             => $userName,
            'is_root'              => $isRoot,
            'has_crontab'          => $hasCrontab,
            'has_aapanel'          => $hasAaPanel,
            'cron_d_writable'      => $cronDirWritable,
            'aapanel_cron_writable'=> $aapanelCronWritable,
            'aapanel_cron_dir'     => $hasAaPanel ? $aapanelCronDir : null,
            'os'                   => PHP_OS_FAMILY,
            'cron_running'         => $this->isCronDaemonRunning(),
        ];
    }

    /**
     * Check if a cron entry for this Laravel app already exists somewhere.
     */
    public function detectExistingEntry(): ?array
    {
        $marker = $this->cronMarkerString();

        // 1. Check user crontab
        if ($this->commandExists('crontab')) {
            $output = $this->run(['crontab', '-l']);
            if ($output !== null && str_contains($output, $marker)) {
                return [
                    'mode'  => 'user-crontab',
                    'where' => 'crontab -l',
                    'line'  => $this->extractMarkerLine($output, $marker),
                ];
            }
        }

        // 2. Check /etc/cron.d/laravel-{appname}
        $cronDFile = '/etc/cron.d/' . $this->cronDFilename();
        if (is_file($cronDFile) && is_readable($cronDFile)) {
            $content = @file_get_contents($cronDFile);
            if ($content !== false && str_contains($content, $marker)) {
                return [
                    'mode'  => 'cron-d-fragment',
                    'where' => $cronDFile,
                    'line'  => $this->extractMarkerLine($content, $marker),
                ];
            }
        }

        // 3. Check root crontab (we may not have access; ignore errors)
        $rootCronFile = '/var/spool/cron/crontabs/root';
        if (is_readable($rootCronFile)) {
            $content = @file_get_contents($rootCronFile);
            if ($content && str_contains($content, $marker)) {
                return [
                    'mode'  => 'spool-direct',
                    'where' => $rootCronFile,
                    'line'  => $this->extractMarkerLine($content, $marker),
                ];
            }
        }

        // 4. aaPanel — scan /www/server/cron/* for our marker
        if (is_dir('/www/server/cron')) {
            $files = @glob('/www/server/cron/*');
            if ($files) {
                foreach ($files as $file) {
                    if (! is_file($file) || str_ends_with($file, '.log')) continue;
                    $content = @file_get_contents($file);
                    if ($content && str_contains($content, $marker)) {
                        return [
                            'mode'  => 'aapanel',
                            'where' => $file,
                            'line'  => '*/1 * * * * ' . $file,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Try to install the cron entry automatically using the best available mode.
     * Returns ['success' => bool, 'mode' => string, 'message' => string, 'detail' => mixed].
     */
    public function autoInstall(): array
    {
        $cap = $this->detectCapabilities();

        // Strategy 1: aaPanel — write hash file + register in root crontab
        if ($cap['has_aapanel'] && $cap['aapanel_cron_writable'] && $cap['is_root']) {
            return $this->installAaPanel();
        }

        // Strategy 2: /etc/cron.d/ fragment (clean, system-wide)
        if ($cap['cron_d_writable']) {
            return $this->installCronD();
        }

        // Strategy 3: user crontab via `crontab` command (works for non-root)
        if ($cap['has_crontab']) {
            return $this->installUserCrontab();
        }

        return [
            'success' => false,
            'mode'    => 'manual',
            'message' => 'Tidak bisa auto-install. Tambahkan baris cron secara manual.',
            'detail'  => ['snippet' => $this->cronLine()],
        ];
    }

    /**
     * Remove our cron entry from wherever it lives.
     */
    public function uninstall(): array
    {
        $existing = $this->detectExistingEntry();
        if (! $existing) {
            return ['success' => true, 'message' => 'Tidak ada cron entry untuk app ini.'];
        }

        $marker = $this->cronMarkerString();

        switch ($existing['mode']) {
            case 'user-crontab':
                $current = $this->run(['crontab', '-l']);
                if ($current === null) return ['success' => false, 'message' => 'Gagal baca crontab.'];
                $cleaned = $this->stripMarkerBlock($current, $marker);
                if ($this->writeUserCrontab($cleaned)) {
                    return ['success' => true, 'message' => 'Cron entry dihapus dari user crontab.'];
                }
                return ['success' => false, 'message' => 'Gagal write crontab.'];

            case 'cron-d-fragment':
                if (@unlink($existing['where'])) {
                    return ['success' => true, 'message' => "File {$existing['where']} dihapus."];
                }
                return ['success' => false, 'message' => "Gagal hapus {$existing['where']}."];

            case 'aapanel':
                // Remove file, then strip from root crontab
                @unlink($existing['where']);
                @unlink($existing['where'] . '.log');
                $current = $this->run(['crontab', '-l']);
                if ($current) {
                    $cleaned = preg_replace('#^.*' . preg_quote(basename($existing['where']), '#') . '.*$\n?#m', '', $current);
                    $this->writeUserCrontab((string) $cleaned);
                }
                return ['success' => true, 'message' => 'aaPanel cron entry dihapus.'];

            case 'spool-direct':
                $content = @file_get_contents($existing['where']);
                if ($content !== false) {
                    $cleaned = $this->stripMarkerBlock($content, $marker);
                    @file_put_contents($existing['where'], $cleaned);
                    return ['success' => true, 'message' => 'Cron entry dihapus dari root spool.'];
                }
                return ['success' => false, 'message' => 'Tidak bisa write root spool.'];
        }

        return ['success' => false, 'message' => 'Mode tidak dikenal.'];
    }

    // ── Mode-specific installers ───────────────────────────────────────────

    protected function installUserCrontab(): array
    {
        $existing = $this->run(['crontab', '-l']);
        // Crontab returns exit 1 with "no crontab" message when empty — treat as empty
        $current = ($existing === null || str_contains($existing, 'no crontab'))
            ? ''
            : $existing;

        // Avoid duplicate
        if (str_contains($current, $this->cronMarkerString())) {
            return ['success' => true, 'mode' => 'user-crontab', 'message' => 'Cron entry sudah ada.'];
        }

        $newContent = trim($current) . "\n\n" . $this->cronBlock() . "\n";

        if ($this->writeUserCrontab($newContent)) {
            return [
                'success' => true,
                'mode'    => 'user-crontab',
                'message' => 'Cron entry ditambahkan ke user crontab.',
                'detail'  => ['user' => $this->detectCapabilities()['php_user']],
            ];
        }

        return ['success' => false, 'mode' => 'user-crontab', 'message' => 'Gagal write crontab.'];
    }

    protected function installCronD(): array
    {
        $file = '/etc/cron.d/' . $this->cronDFilename();
        $content = "# Laravel scheduler for " . config('app.name') . "\n"
            . "# Auto-generated by CronManager — do not edit manually\n"
            . "SHELL=/bin/bash\n"
            . "PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin\n"
            . $this->cronLine(includeUser: true) . "\n";

        if (file_put_contents($file, $content) === false) {
            return ['success' => false, 'mode' => 'cron-d-fragment', 'message' => "Gagal write {$file}."];
        }

        chmod($file, 0644);

        return [
            'success' => true,
            'mode'    => 'cron-d-fragment',
            'message' => "File {$file} dibuat. Cron daemon akan baca otomatis dalam 1 menit.",
            'detail'  => ['file' => $file],
        ];
    }

    protected function installAaPanel(): array
    {
        $hash = md5(base_path() . microtime(true));
        $cronFile = "/www/server/cron/{$hash}";
        $logFile  = "{$cronFile}.log";

        // Create the script that aaPanel runs
        $php = $this->detectPhpCliBinary();
        $script = "#!/bin/bash\n"
            . "# " . $this->cronMarkerString() . "\n"
            . "# Laravel scheduler for " . config('app.name') . "\n"
            . "cd " . base_path() . " && " . $php . " artisan schedule:run\n";

        if (file_put_contents($cronFile, $script) === false) {
            return ['success' => false, 'mode' => 'aapanel', 'message' => "Gagal write {$cronFile}."];
        }
        chmod($cronFile, 0755);

        // Register in root crontab
        $cronEntry = "*/1 * * * *  {$cronFile} >> {$logFile} 2>&1";
        $existing = $this->run(['crontab', '-l']);
        $current = ($existing === null || str_contains($existing, 'no crontab')) ? '' : $existing;

        if (! str_contains($current, $cronFile)) {
            $newContent = trim($current) . "\n" . $cronEntry . "\n";
            if (! $this->writeUserCrontab($newContent)) {
                @unlink($cronFile);
                return ['success' => false, 'mode' => 'aapanel', 'message' => 'Gagal register di root crontab.'];
            }
        }

        return [
            'success' => true,
            'mode'    => 'aapanel',
            'message' => 'aaPanel cron task dibuat (interval: setiap menit).',
            'detail'  => ['hash' => $hash, 'script' => $cronFile, 'log' => $logFile],
        ];
    }

    // ── Health monitoring ──────────────────────────────────────────────────

    /**
     * When was schedule:run last triggered?
     * Determined by reading the tick file written by HeartbeatService.
     */
    public function lastTickAt(): ?\Carbon\Carbon
    {
        $file = storage_path('app/.cron-last-tick');
        if (! is_file($file)) return null;

        return \Carbon\Carbon::createFromTimestamp(filemtime($file));
    }

    public function tickFreshness(): array
    {
        $last = $this->lastTickAt();
        if (! $last) {
            return [
                'last_tick_at' => null,
                'seconds_ago'  => null,
                'status'       => 'never',
                'message'      => 'Cron belum pernah jalan. Setelah install, tunggu 1 menit lalu refresh.',
            ];
        }

        $age = now()->diffInSeconds($last);

        if ($age < 90) {
            return [
                'last_tick_at' => $last->toIso8601String(),
                'seconds_ago'  => $age,
                'status'       => 'healthy',
                'message'      => 'Cron jalan normal (last tick ' . $last->diffForHumans() . ').',
            ];
        }

        if ($age < 600) {
            return [
                'last_tick_at' => $last->toIso8601String(),
                'seconds_ago'  => $age,
                'status'       => 'stale',
                'message'      => 'Cron mungkin telat (last tick ' . $last->diffForHumans() . '). Tunggu 1-2 menit lagi.',
            ];
        }

        return [
            'last_tick_at' => $last->toIso8601String(),
            'seconds_ago'  => $age,
            'status'       => 'broken',
            'message'      => 'Cron tidak jalan! Last tick ' . $last->diffForHumans() . '.',
        ];
    }

    /**
     * Touch the tick file. Called by HeartbeatService and other scheduled tasks.
     */
    public function recordTick(): void
    {
        $file = storage_path('app/.cron-last-tick');
        @touch($file);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function cronLine(bool $includeUser = false): string
    {
        $php  = $this->detectPhpCliBinary();
        $path = base_path();
        $marker = $this->cronMarkerString();

        if ($includeUser) {
            $user = $this->detectCapabilities()['php_user'];
            return "* * * * * {$user} cd {$path} && {$php} artisan schedule:run >> /dev/null 2>&1  # {$marker}";
        }

        return "* * * * * cd {$path} && {$php} artisan schedule:run >> /dev/null 2>&1  # {$marker}";
    }

    /**
     * Resolve the path to the PHP CLI binary.
     *
     * Important: in PHP-FPM context, PHP_BINARY points to `php-fpm` (the FPM
     * daemon binary) which CANNOT run artisan. We need the CLI binary instead.
     */
    public function detectPhpCliBinary(): string
    {
        if (PHP_SAPI === 'cli' && PHP_BINARY && ! str_contains(PHP_BINARY, 'fpm')) {
            return PHP_BINARY;
        }

        if (PHP_BINARY && str_contains(PHP_BINARY, 'fpm')) {
            $derived = preg_replace('#/sbin/php-fpm$#', '/bin/php', PHP_BINARY);
            if ($derived && $derived !== PHP_BINARY && is_file($derived) && is_executable($derived)) {
                return $derived;
            }
            if (preg_match('#/www/server/php/(\d+)/#', PHP_BINARY, $m)) {
                $candidate = "/www/server/php/{$m[1]}/bin/php";
                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        foreach (['/usr/bin/php', '/usr/local/bin/php'] as $bin) {
            if (is_file($bin) && is_executable($bin)) {
                return $bin;
            }
        }

        return 'php';
    }

    public function cronBlock(): string
    {
        return "# Laravel scheduler for " . config('app.name') . "\n" . $this->cronLine();
    }

    /**
     * Unique marker per app+path so multiple apps on same server don't collide.
     */
    public function cronMarkerString(): string
    {
        return 'laravel-cron:' . substr(md5(base_path()), 0, 12);
    }

    public function cronDFilename(): string
    {
        $name = preg_replace('/[^a-z0-9]/i', '-', strtolower((string) config('app.name')));
        $name = trim($name, '-');
        return 'laravel-' . ($name ?: 'app') . '-' . substr(md5(base_path()), 0, 6);
    }

    protected function commandExists(string $cmd): bool
    {
        $output = $this->run(['which', $cmd]);
        return ! empty($output);
    }

    protected function isCronDaemonRunning(): bool
    {
        // Try systemd
        $sd = $this->run(['systemctl', 'is-active', 'cron']);
        if ($sd && trim($sd) === 'active') return true;

        $sd = $this->run(['systemctl', 'is-active', 'crond']);
        if ($sd && trim($sd) === 'active') return true;

        // Try pgrep
        $pg = $this->run(['pgrep', '-f', 'cron']);
        return ! empty($pg);
    }

    protected function run(array $cmd): ?string
    {
        // Bail early if proc_open is disabled (security hardening)
        if (! function_exists('proc_open')) {
            return null;
        }

        try {
            $process = new Process($cmd);
            $process->setTimeout(8);
            $process->run();
            // Return both stdout + stderr for diagnostics, but treat 'no crontab' as empty
            return $process->getOutput() . $process->getErrorOutput();
        } catch (\Throwable $e) {
            Log::debug('[CronManager] run failed: ' . $e->getMessage());
            return null;
        }
    }

    protected function writeUserCrontab(string $content): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        try {
            // Pipe content into `crontab -`
            $tmp = tempnam(sys_get_temp_dir(), 'cron-');
            file_put_contents($tmp, rtrim($content, "\n") . "\n");

            $process = new Process(['crontab', $tmp]);
            $process->setTimeout(8);
            $process->run();

            @unlink($tmp);

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            Log::warning('[CronManager] writeUserCrontab failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function extractMarkerLine(string $haystack, string $marker): ?string
    {
        foreach (explode("\n", $haystack) as $line) {
            if (str_contains($line, $marker)) return trim($line);
        }
        return null;
    }

    protected function stripMarkerBlock(string $content, string $marker): string
    {
        $lines  = explode("\n", $content);
        $kept = [];
        $skipNext = 0;

        foreach ($lines as $line) {
            // Skip lines containing the marker, plus the comment line above it (if it starts with #)
            if (str_contains($line, $marker)) {
                // Pop the previous line if it was a comment header
                if (! empty($kept) && str_starts_with(trim(end($kept)), '#')) {
                    array_pop($kept);
                }
                continue;
            }
            $kept[] = $line;
        }

        return rtrim(implode("\n", $kept), "\n") . "\n";
    }
}

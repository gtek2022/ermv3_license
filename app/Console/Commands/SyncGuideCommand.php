<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Sync the canonical panduan-lisensi.html from the workspace root into
 * gemilang/public/ so it can be served by the GuideController.
 *
 * Usage:
 *   php artisan guide:sync
 *
 * The canonical file lives at d:\1project\ERM\panduan-lisensi.html (or the
 * equivalent relative path in production deployments).
 */
class SyncGuideCommand extends Command
{
    protected $signature   = 'guide:sync {--source= : Path to source HTML file}';
    protected $description = 'Sync panduan-lisensi.html from workspace root into public/.';

    public function handle(): int
    {
        $source = $this->option('source')
            ?: realpath(base_path('../panduan-lisensi.html'));

        if (! $source || ! is_file($source)) {
            $this->error("Source file not found: " . ($source ?: base_path('../panduan-lisensi.html')));
            $this->line('Pass --source=/path/to/panduan-lisensi.html to override.');
            return self::FAILURE;
        }

        $dest = public_path('panduan-lisensi.html');

        File::copy($source, $dest);

        $this->info("✓ Copied {$source}");
        $this->line("  → {$dest}");
        $this->line('  Size: ' . number_format(filesize($dest)) . ' bytes');

        return self::SUCCESS;
    }
}

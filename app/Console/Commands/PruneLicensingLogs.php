<?php

namespace App\Console\Commands;

use App\Models\LicenseLogsHeartbeat;
use Illuminate\Console\Command;

/**
 * Trim the heartbeat log.
 *
 * Every install writes one row per heartbeat, and the default interval is hourly, so this table grows
 * by roughly 24 rows per install per day forever. It was at 6,961 rows with nothing ever removing any
 * of them - the only table in the licensing schema that grows without bound.
 *
 * The rows are useful for exactly one thing: answering "was this install talking to us recently, and
 * what did it say". Ninety days of that is generous; a heartbeat from last spring tells nobody
 * anything, and license_installations.last_heartbeat_at already carries the current answer.
 *
 *   php artisan license:prune-logs
 *   php artisan license:prune-logs --days=30
 *   php artisan license:prune-logs --dry-run
 */
class PruneLicensingLogs extends Command
{
    protected $signature = 'license:prune-logs
        {--days=90 : Keep heartbeats newer than this many days}
        {--dry-run : Report what would be removed without deleting anything}';

    protected $description = 'Remove heartbeat log rows older than the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        // heartbeat_at, not created_at: this table has no timestamps() at all - see the
        // 2026_05_23_000020 migration - and asking for created_at answered
        // SQLSTATE[42703] "column does not exist".
        $total = LicenseLogsHeartbeat::count();
        $stale = LicenseLogsHeartbeat::where('heartbeat_at', '<', $cutoff)->count();

        $this->line('heartbeat rows: ' . $total . ', older than ' . $days . ' days: ' . $stale);

        if ($stale === 0) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn($stale . ' row(s) would be removed. Nothing was deleted.');

            return self::SUCCESS;
        }

        /*
         * Deleted in batches, by primary key.
         *
         * In batches because this is the largest table here and one unbounded DELETE would hold locks
         * for its whole duration while heartbeats are still arriving.
         *
         * By primary key rather than `->limit(1000)->delete()`, because DELETE ... LIMIT is MySQL
         * syntax and this runs on PostgreSQL. Collecting ids first and deleting those is unambiguous
         * on any driver.
         */
        $removed = 0;

        do {
            $ids = LicenseLogsHeartbeat::where('heartbeat_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(1000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $removed += LicenseLogsHeartbeat::whereIn('id', $ids)->delete();
        } while (true);

        $this->info($removed . ' row(s) removed. ' . LicenseLogsHeartbeat::count() . ' remain.');

        return self::SUCCESS;
    }
}

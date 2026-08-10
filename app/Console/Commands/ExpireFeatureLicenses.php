<?php

namespace App\Console\Commands;

use App\Models\LicenseFeatureActivation;
use Illuminate\Console\Command;

/**
 * Mark feature activations whose term has run out.
 *
 * This is housekeeping, not enforcement. Nothing depends on it having run: every read path already
 * weighs the deadline - LicenseFeatureActivation::isCurrentlyActive(), the live() scope behind
 * /feature/status, and the catalogue config-sync ships to clients. A module locks the moment its term
 * passes whether or not this command has fired since.
 *
 * What it buys is an honest `status` column. Without it a row sits at 'active' forever with a deadline
 * in the past, so anything reading status alone - reports, a future admin filter, somebody at a
 * database prompt - would be told the licence is live when the client has already locked it. Two
 * places disagreeing about the same fact is the thing worth avoiding here.
 *
 *   php artisan license:feature-expire
 *   php artisan license:feature-expire --dry-run
 */
class ExpireFeatureLicenses extends Command
{
    protected $signature = 'license:feature-expire
        {--dry-run : Report what would be marked without writing anything}';

    protected $description = 'Mark feature licence activations whose validity period has ended.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $lapsed = LicenseFeatureActivation::awaitingExpiry()
            ->orderBy('expires_at')
            ->get();

        if ($lapsed->isEmpty()) {
            $this->info('No feature activations have lapsed.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%-14s %-22s %-38s %s', 'app', 'feature', 'installation', 'expired'));
        $this->line(str_repeat('-', 92));

        foreach ($lapsed as $activation) {
            $this->line(sprintf('%-14s %-22s %-38s %s',
                $activation->app_code,
                mb_substr($activation->feature_key, 0, 21),
                $activation->installation_uuid,
                $activation->expires_at->toDateTimeString(),
            ));
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn($lapsed->count() . ' activation(s) would be marked expired. Nothing was written.');

            return self::SUCCESS;
        }

        // Updated by primary key rather than re-running the predicate as a mass update: the set that
        // gets marked is then exactly the set that was just reported, even if a deadline passes
        // between the select and the write.
        $marked = LicenseFeatureActivation::whereKey($lapsed->modelKeys())
            ->update(['status' => 'expired']);

        $this->info($marked . ' activation(s) marked expired.');

        // revoked_at is left alone on purpose. It records an administrator withdrawing a licence;
        // a term simply ending is a different event, and expires_at already says when it happened.

        return self::SUCCESS;
    }
}

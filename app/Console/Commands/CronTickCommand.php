<?php

namespace App\Console\Commands;

use App\Services\Licensing\CronManager;
use Illuminate\Console\Command;

/**
 * Ultra-light command — touches a tick file so the admin UI can verify
 * the scheduler is actually firing, even without OS introspection.
 */
class CronTickCommand extends Command
{
    protected $signature   = 'cron:tick';
    protected $description = 'Update tick file to confirm scheduler is running.';

    public function handle(CronManager $cron): int
    {
        $cron->recordTick();
        return self::SUCCESS;
    }
}

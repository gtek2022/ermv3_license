<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LucaLongo\Licensing\Models\License;

class IssueErmv3License extends Command
{
    protected $signature = 'app:license:issue-ermv3
        {--customer= : Customer name}
        {--email= : Customer email}
        {--max-usages=1 : Maximum activated devices}
        {--days=365 : Validity in days}';

    protected $description = 'Issue an ermv3 license key.';

    public function handle(): int
    {
        $customer = (string) ($this->option('customer') ?? 'Internal Test');
        $email = (string) ($this->option('email') ?? 'admin@example.com');
        $maxUsages = (int) $this->option('max-usages');
        $days = (int) $this->option('days');

        $license = License::createWithKey([
            'max_usages' => $maxUsages,
            'expires_at' => now()->addDays($days),
            'meta' => [
                'product' => 'ermv3',
                'customer_name' => $customer,
                'customer_email' => $email,
            ],
        ]);

        $license->activate();

        $this->info('License created.');
        $this->line('  Key:        ' . $license->license_key);
        $this->line('  Status:     ' . $license->status->value);
        $this->line('  Max usages: ' . $license->max_usages);
        $this->line('  Expires at: ' . $license->expires_at?->toIso8601String());

        $this->newLine();
        $this->warn('Save this key — it will not be displayed again.');

        return self::SUCCESS;
    }
}

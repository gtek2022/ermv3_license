<?php

namespace App\Console\Commands;

use App\Models\LicenseCompany;
use Illuminate\Console\Command;
use LucaLongo\Licensing\Models\License;

/**
 * One-time / safe-to-rerun sync to ensure every LicenseCompany has a matching
 * package License row so /api/licensing/v1/activate can find them.
 */
class SyncLicensesToPackage extends Command
{
    protected $signature = 'licenses:sync-package';
    protected $description = 'Sync LicenseCompany rows to package licenses table for activation lookup';

    public function handle(): int
    {
        $companies = LicenseCompany::with('company')->get();
        $this->info("Found {$companies->count()} LicenseCompany records.");

        $created = 0;
        $updated = 0;

        foreach ($companies as $lc) {
            $correctHash = License::hashKey($lc->license_key);

            // Heal hash if it was using a different salt previously
            if ($lc->license_key_hash !== $correctHash) {
                $this->warn("Healing hash for LicenseCompany #{$lc->id}");
                $lc->update(['license_key_hash' => $correctHash]);
            }

            $pkg = License::where('key_hash', $correctHash)->first();

            $meta = [
                'product'            => 'license_company',
                'license_company_id' => $lc->id,
                'company_name'       => $lc->company?->name,
                'encrypted_key'      => encrypt($lc->license_key),
            ];

            if ($pkg) {
                $existingMeta = is_array($pkg->meta) ? (array) $pkg->meta : (is_string($pkg->meta) ? (json_decode($pkg->meta, true) ?: []) : []);
                $pkg->update([
                    'status'       => $lc->status === 'active' ? 'active' : 'suspended',
                    'activated_at' => $lc->activated_at,
                    'expires_at'   => $lc->expires_at,
                    'max_usages'   => $lc->max_installations,
                    'meta'         => array_merge($existingMeta, $meta),
                ]);
                $updated++;
                $this->line("  ↻ updated package License #{$pkg->id} for {$lc->license_key}");
            } else {
                $created_pkg = License::create([
                    'key_hash'     => $correctHash,
                    'status'       => $lc->status === 'active' ? 'active' : 'suspended',
                    'activated_at' => $lc->activated_at,
                    'expires_at'   => $lc->expires_at,
                    'max_usages'   => $lc->max_installations,
                    'meta'         => $meta,
                ]);
                $created++;
                $this->line("  + created package License #{$created_pkg->id} for {$lc->license_key}");
            }
        }

        $this->info("Done. Created: {$created}, Updated: {$updated}");

        return self::SUCCESS;
    }
}

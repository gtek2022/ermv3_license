<?php

namespace App\Console\Commands;

use App\Models\MasterAppFeature;
use Illuminate\Console\Command;

/**
 * Register / ensure the licensed PDS features that back the sidebar menus.
 *
 * PDS gates these via feature:<key> middleware + menu lock icons; each needs a
 * MasterAppFeature row here (app_code=pds) with an FLK the client can activate.
 *
 * Idempotent: re-running keeps existing FLKs unless --regenerate is passed.
 * Prints the plain FLK for each feature so it can be activated on a client.
 */
class RegisterPdsFeatures extends Command
{
    protected $signature = 'features:register-pds {--app=pds : App code to register under (pds, pds-dev, pdslocal)} {--regenerate : Force a fresh FLK even if one already exists}';

    protected $description = 'Register/ensure PDS licensed features (forecast submenus + Outstanding) and print their FLKs.';

    public function handle(): int
    {
        $appCode = (string) $this->option('app');

        $features = [
            'forecast_ar'  => ['name' => 'AR Forecast',            'category' => 'forecast',    'description' => 'Forecast → AR Forecast submenu.'],
            'forecast_yoy' => ['name' => 'Year on Year Forecast',  'category' => 'forecast',    'description' => 'Forecast → Year on Year submenu.'],
            'outstanding'  => ['name' => 'Outstanding',            'category' => 'operational', 'description' => 'Outstanding stale-tender dashboard (per-role cards).'],
            'approval'     => ['name' => 'Approval',               'category' => 'operational', 'description' => 'Approval hub (quotation + forecast approvals), exclusive to BOD/Presdir/Administrator.'],
        ];

        $this->info('Registering PDS features (app_code=' . $appCode . '):');
        $this->newLine();

        foreach ($features as $key => $meta) {
            $feature = MasterAppFeature::updateOrCreate(
                ['app_code' => $appCode, 'feature_key' => $key],
                [
                    'name'             => $meta['name'],
                    'description'      => $meta['description'],
                    'category'         => $meta['category'],
                    'requires_license' => true,
                    'is_active'        => true,
                ]
            );

            if ($this->option('regenerate') || ! $feature->feature_license_key_hash) {
                $flk = $feature->generateFeatureLicenseKey();
                $this->line(sprintf('  %-14s %s  (new)', $key, $flk));
            } else {
                $flk = $feature->retrieveFeatureLicenseKey();
                $this->line(sprintf('  %-14s %s', $key, $flk ?? '(stored as hash only — pass --regenerate to reissue)'));
            }
        }

        $this->newLine();
        $this->info('Done. Activate each FLK on the client (PDS) to unlock the feature.');

        return self::SUCCESS;
    }
}

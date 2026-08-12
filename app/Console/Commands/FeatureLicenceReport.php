<?php

namespace App\Console\Commands;

use App\Models\LicenseApp;
use App\Models\LicenseAppFeature;
use App\Models\LicenseFeatureActivation;
use App\Models\LicenseInstallation;
use App\Models\MasterAppFeature;
use Illuminate\Console\Command;

/**
 * What an app's licences actually grant, and what its installations actually redeemed.
 *
 * Written because there was no way to answer that question without opening a database client. The
 * admin page shows one licence at a time and the client's `license:feature list` shows a cache, so a
 * disagreement between the two - which is the failure that matters - had nowhere to be seen.
 *
 * The two axes are deliberately printed side by side, because a module opens only when both agree:
 *
 *   entitlement  license_app_features    this licence includes the module
 *   activation   license_feature_activations  this installation redeemed the FLK
 *
 * Read-only. It exists to be run before changing licence data and again afterwards.
 */
class FeatureLicenceReport extends Command
{
    protected $signature = 'license:features-report {--app= : app_code to report on, e.g. crm}';

    protected $description = 'Report feature entitlements and activations for an app_code.';

    public function handle(): int
    {
        $appCode = trim((string) $this->option('app'));

        if ($appCode === '') {
            $codes = MasterAppFeature::query()->distinct()->orderBy('app_code')->pluck('app_code');
            $this->error('Pass --app=<code>. Known: ' . ($codes->implode(', ') ?: '(none)'));

            return self::FAILURE;
        }

        $features = MasterAppFeature::where('app_code', $appCode)->orderBy('category')->orderBy('feature_key')->get();

        if ($features->isEmpty()) {
            $this->error('No master_app_features rows for app_code=' . $appCode . '.');

            return self::FAILURE;
        }

        $licenceApps = LicenseApp::where('app_code', $appCode)->get();

        $this->line('app_code   : ' . $appCode);
        $this->line('catalogue  : ' . $features->count() . ' feature(s)');
        $this->line('licences   : ' . $licenceApps->count());

        foreach ($licenceApps as $licenceApp) {
            $this->reportLicence($licenceApp, $features);
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MasterAppFeature>  $features
     */
    private function reportLicence(LicenseApp $licenceApp, $features): void
    {
        $entitlements = LicenseAppFeature::where('license_app_id', $licenceApp->id)->get()->keyBy('feature_key');

        $this->newLine();
        $this->line('── licence #' . $licenceApp->id . '  company=' . $licenceApp->license_company_id
            . '  status=' . $licenceApp->status . ' ──');

        /*
         * The rule that surprises people, so it is stated rather than implied: with no entitlement
         * rows at all, ConfigSyncController::buildLicensedFeatures() reports every catalogue feature
         * as licensed. "No restrictions recorded" means "no restrictions", which is right for an
         * app-level licence and catastrophic if the rows were expected to be there.
         */
        if ($entitlements->isEmpty()) {
            $this->warn('  NO entitlement rows - every catalogue feature is reported as licensed.');
        }

        $installations = LicenseInstallation::where('license_app_id', $licenceApp->id)->get();

        $activations = LicenseFeatureActivation::where('app_code', $licenceApp->app_code)
            ->whereIn('installation_uuid', $installations->pluck('installation_uuid')->filter())
            ->get()
            ->groupBy('feature_key');

        $this->line(sprintf('  %-13s %-10s %-22s %-9s %s', 'module', 'entitled', 'entitlement status', 'term', 'activations (live)'));
        $this->line('  ' . str_repeat('-', 82));

        foreach ($features as $feature) {
            $row = $entitlements->get($feature->feature_key);

            $entitled = $entitlements->isEmpty()
                ? 'yes (*)'
                : ($row && $row->isActive() ? 'yes' : 'no');

            $status = $row
                ? $row->status . ($row->valid_until ? ' until ' . $row->valid_until->format('Y-m-d H:i') : '')
                : '(no row)';

            $live = ($activations->get($feature->feature_key) ?? collect())
                ->filter(fn (LicenseFeatureActivation $a) => $a->isCurrentlyActive())
                ->count();

            $this->line(sprintf(
                '  %-13s %-10s %-22s %-9s %s',
                $feature->feature_key,
                $entitled,
                $status,
                $feature->validityLabel(),
                $live . '/' . ($activations->get($feature->feature_key)?->count() ?? 0)
            ));
        }

        $this->line('  installations: ' . $installations->count() . ' ('
            . ($installations->pluck('installation_uuid')->filter()->implode(', ') ?: 'none') . ')');
    }
}

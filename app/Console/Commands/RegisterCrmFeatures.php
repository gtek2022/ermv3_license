<?php

namespace App\Console\Commands;

use App\Models\LicenseApp;
use App\Models\LicenseAppFeature;
use App\Models\MasterAppFeature;
use Illuminate\Console\Command;

/**
 * Register the licensed crm modules, and grant them per environment.
 *
 * crm is one app with two installations - app_code 'crm' for production and 'crm-dev' for the
 * dev site - each with its own licence. Every module in the sidebar is licensed except Dashboard and
 * Master Data: the dashboard is the landing page and would leave a licensed install with nowhere to
 * go, and master data is reference data the licensed modules read.
 *
 * Two things have to be true for a module to open on a client, and this command sets both:
 *
 *   master_app_features    the catalogue: the module exists, is active, and needs an FLK
 *   license_app_features   the entitlement: this licence includes the module
 *
 * The FLK is what the client activates, which is what ties the module to one installation. The
 * entitlement is what ties it to the licence, so a key lifted from the dev site does not unlock a
 * module production has not paid for.
 *
 *   php artisan features:register-crm --app=crm-dev --grant=all
 *   php artisan features:register-crm --app=crm --grant=procurement,finance,approval
 *   php artisan features:register-crm --app=crm --regenerate     # reissue every FLK
 *
 * Idempotent. Existing FLKs are kept unless --regenerate is passed, because reissuing one silently
 * would lock out an installation that had already activated it.
 */
class RegisterCrmFeatures extends Command
{
    protected $signature = 'features:register-crm
        {--app=crm : App code to register under (crm, crm-dev)}
        {--grant= : Entitle the licence to these features - "all", or a comma separated list}
        {--regenerate : Force a fresh FLK even if one already exists}';

    protected $description = 'Register the licensed crm modules with FLKs, and optionally grant them to that app\'s licence.';

    /**
     * One key per sidebar module. Dashboard and Master Data are deliberately absent.
     *
     * The keys match App\Support\ModuleLicense in the crm client, which maps each one to the URL
     * prefixes and the menu entries it covers. Changing a key here means changing it there.
     */
    private const FEATURES = [
        // the procurement modules
        'approval'    => ['Approval', 'procurement', 'Approval hub: PR, PO and RFP approvals for managers and the Direktur.'],
        'procurement' => ['Procurement', 'procurement', 'Purchase Request, Purchase Order, Goods Receipt and Request For Payment.'],
        'finance'     => ['Finance', 'procurement', 'Finance review, RFP transactions and payment confirmation.'],

        // crm modules, one key per sidebar group
        'clients'   => ['Customers', 'crm', 'Clients and client users.'],
        'projects'  => ['Projects', 'crm', 'Projects, milestones and project tasks.'],
        'tasks'     => ['Tasks', 'crm', 'Task board and task lists.'],
        'leads'     => ['Leads', 'crm', 'Lead pipeline.'],
        'sales'     => ['Sales', 'crm', 'Invoices, payments, estimates, products, expenses and subscriptions.'],
        'proposals' => ['Proposals', 'crm', 'Proposal documents and templates.'],
        'contracts' => ['Contracts', 'crm', 'Contract documents and templates.'],
        'spaces'    => ['Spaces', 'crm', 'User and team spaces.'],
        'support'   => ['Support', 'crm', 'Tickets, knowledge base and messages.'],
        'team'      => ['Team', 'crm', 'Team directory, timesheets and time tracking.'],
        'reports'   => ['Reports', 'crm', 'Reporting screens.'],
        'calendar'  => ['Calendar', 'crm', 'Calendar and reminders.'],
    ];

    public function handle(): int
    {
        $appCode = (string) $this->option('app');

        $this->info('crm features for app_code=' . $appCode);
        $this->newLine();

        $keys = [];

        foreach (self::FEATURES as $key => [$name, $category, $description]) {
            $feature = MasterAppFeature::updateOrCreate(
                ['app_code' => $appCode, 'feature_key' => $key],
                [
                    'name'             => $name,
                    'description'      => $description,
                    'category'         => $category,
                    'requires_license' => true,
                    'is_active'        => true,
                ]
            );

            $keys[] = $key;

            if ($this->option('regenerate') || ! $feature->feature_license_key_hash) {
                $flk = $feature->generateFeatureLicenseKey();
                $this->line(sprintf('  %-12s %-28s %s  (new)', $key, $name, $flk));
            } else {
                $flk = $feature->retrieveFeatureLicenseKey();
                $this->line(sprintf('  %-12s %-28s %s', $key, $name,
                    $flk ?? '(hash only - pass --regenerate to reissue)'));
            }
        }

        $grant = trim((string) $this->option('grant'));

        if ($grant !== '') {
            $this->newLine();
            $this->grant($appCode, $grant === 'all' ? $keys : array_map('trim', explode(',', $grant)), $keys);
        }

        $this->newLine();
        $this->info('Activate each FLK on the client: php artisan license:feature activate <key> <FLK>');

        return self::SUCCESS;
    }

    /**
     * Entitle this app's licence to exactly these features.
     *
     * Exactly, not additionally: a feature the licence used to include and no longer should has its
     * row set to 'revoked' rather than deleted, so what happened stays on the record.
     *
     * @param  array<int, string>  $wanted
     * @param  array<int, string>  $all
     */
    private function grant(string $appCode, array $wanted, array $all): void
    {
        $licenseApp = LicenseApp::where('app_code', $appCode)->where('status', 'active')->first();

        if (! $licenseApp) {
            $this->error('No active licence covers app_code=' . $appCode . ' - nothing granted.');

            return;
        }

        $unknown = array_diff($wanted, $all);

        if ($unknown !== []) {
            $this->error('Unknown feature key(s): ' . implode(', ', $unknown));

            return;
        }

        foreach ($all as $key) {
            $entitled = in_array($key, $wanted, true);

            LicenseAppFeature::updateOrCreate(
                ['license_app_id' => $licenseApp->id, 'feature_key' => $key],
                [
                    'app_code' => $appCode,
                    'status'   => $entitled ? 'active' : 'revoked',
                ]
            );
        }

        $this->info('Licence #' . $licenseApp->id . ' entitled to: ' . implode(', ', $wanted));
        $this->line('  revoked: ' . (implode(', ', array_diff($all, $wanted)) ?: '(none)'));
    }
}

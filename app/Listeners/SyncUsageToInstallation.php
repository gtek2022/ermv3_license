<?php

namespace App\Listeners;

use App\Models\LicenseApp;
use App\Models\LicenseCompany;
use App\Models\LicenseInstallation;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Support\Str;
use LucaLongo\Licensing\Events\UsageRegistered;
use LucaLongo\Licensing\Events\UsageRevoked;

/**
 * Bridges the package's `license_usages` rows to our own `license_installations`
 * table so admin can see human-readable install info (hostname, MAC, domain)
 * at /licenses/{hash}.
 *
 * Triggered by:
 *   - UsageRegistered → create or reactivate a LicenseInstallation
 *   - UsageRevoked    → mark the matching LicenseInstallation as revoked
 */
class SyncUsageToInstallation
{
    public function handleRegistered(UsageRegistered $event): void
    {
        $usage = $event->usage;

        // Find our LicenseCompany by package License key_hash
        $licenseCompany = LicenseCompany::where('license_key_hash', $usage->license->key_hash ?? '')->first();
        if (! $licenseCompany) {
            return;
        }

        $meta = $this->normalizeMeta($usage->meta);

        $appCode = $meta['app'] ?? $usage->client_type ?? 'ermv3';
        // Find the matching LicenseApp inside this license company
        $licenseApp = LicenseApp::where('license_company_id', $licenseCompany->id)
            ->where('app_code', $appCode)
            ->first()
            ?? $licenseCompany->licenseApps()->first(); // fallback to first

        // Lookup-or-create by fingerprint
        $existing = LicenseInstallation::where('license_company_id', $licenseCompany->id)
            ->where('fingerprint', $usage->usage_fingerprint)
            ->first();

        $payload = [
            'license_app_id'      => $licenseApp?->id,
            'license_company_id'  => $licenseCompany->id,
            'app_code'            => $appCode,
            'fingerprint'         => $usage->usage_fingerprint,
            'hostname'            => $meta['hostname']     ?? $usage->name ?? null,
            'domain'              => $meta['domain']       ?? null,
            'ip_address'          => $meta['server_ip']    ?? $usage->ip ?? null,
            'app_version'         => $meta['app_version']  ?? null,
            'status'              => 'active',
            'first_verified_at'   => $existing?->first_verified_at ?? now(),
            'last_heartbeat_at'   => now(),
            'meta'                => $meta,
        ];

        if ($existing) {
            $existing->update(array_merge($payload, ['revoked_at' => null, 'revoke_reason' => null]));
        } else {
            LicenseInstallation::create(array_merge($payload, [
                'installation_uuid' => (string) Str::uuid(),
            ]));
        }
    }

    public function handleRevoked(UsageRevoked $event): void
    {
        $usage = $event->usage;

        $licenseCompany = LicenseCompany::where('license_key_hash', $usage->license->key_hash ?? '')->first();
        if (! $licenseCompany) {
            return;
        }

        LicenseInstallation::where('license_company_id', $licenseCompany->id)
            ->where('fingerprint', $usage->usage_fingerprint)
            ->where('status', 'active')
            ->update([
                'status'        => 'revoked',
                'revoked_at'    => now(),
                'revoke_reason' => 'usage_revoked',
            ]);
    }

    protected function normalizeMeta($meta): array
    {
        if (is_null($meta)) return [];
        if (is_array($meta)) return $meta;
        if ($meta instanceof ArrayObject) return $meta->toArray();
        if (is_object($meta) && method_exists($meta, 'toArray')) return $meta->toArray();
        if (is_string($meta)) {
            $d = json_decode($meta, true);
            return is_array($d) ? $d : [];
        }
        return [];
    }
}

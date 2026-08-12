<?php

namespace App\Listeners;

use App\Models\LicenseApp;
use App\Models\LicenseCompany;
use App\Models\LicenseInstallation;
use App\Models\LicenseLogsHeartbeat;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Support\Str;
use LucaLongo\Licensing\Events\UsageRegistered;
use LucaLongo\Licensing\Events\UsageRevoked;
use LucaLongo\Licensing\Models\LicenseUsage;

/**
 * Bridges the package's `license_usages` rows to our own `license_installations`
 * table so admin can see human-readable install info (hostname, MAC, domain)
 * at /licenses/{hash}.
 *
 * Triggered by:
 *   - UsageRegistered      → create or reactivate a LicenseInstallation
 *   - UsageRevoked         → mark the matching LicenseInstallation as revoked
 *   - LicenseUsage updated → mirror heartbeat (last_seen_at) onto LicenseInstallation.last_heartbeat_at
 *                            and append a row to license_logs_heartbeats
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

        // Prefer client_type ('ermv3', 'pds') sebagai app_code karena itu adalah
        // kode aplikasi yang stabil. $meta['app'] berisi config('app.name')
        // yang berbeda untuk setiap deployment (e.g. "ERM - ABM" vs "PD System Kencana").
        $appCode = $usage->client_type ?? $meta['app_code'] ?? $meta['client_type'] ?? 'ermv3';

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
                'installation_uuid' => $this->resolveInstallationUuid($meta),
            ]));
        }
    }

    /**
     * The client's own installation uuid, not a fresh one.
     *
     * Str::uuid() used to be called here unconditionally, and that quietly broke the only thing this
     * column is for. The client generates its uuid once, keeps it in encrypted state, and sends it on
     * every call - feature activations are keyed to it, and ConfigSyncController::buildFeatureCatalog()
     * looks activations up by it. Minting a different one server-side leaves the installation row
     * describing an installation that does not exist: on crm production the row said 1b4e468c while
     * the client, its four feature activations and the catalogue lookup all said 39cdae01.
     *
     * Feature gating survived that because it never consults this row, which is exactly why it went
     * unnoticed. What did not survive: the Installation Slots panel, revoke-by-installation from the
     * admin page, and the uuid arm of FeatureLicenseController::resolveLicenseAppId().
     *
     * A generated uuid is kept as the last resort, since a row without one cannot be saved, and some
     * heartbeats genuinely carry no client metadata.
     */
    protected function resolveInstallationUuid(array $meta, ?string $fallback = ''): ?string
    {
        // Nested client_data.meta as well as the top level, because which one carries the uuid
        // depends on whether this came from an activation or a heartbeat.
        $nested = [];
        if (isset($meta['client_data']) && is_array($meta['client_data'])) {
            $nested = is_array($meta['client_data']['meta'] ?? null) ? $meta['client_data']['meta'] : [];
        }

        $haystack = array_merge($nested, $meta);

        foreach ([$haystack['installation_uuid'] ?? null, $haystack['install_uuid'] ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        // '' (the default) means "mint one"; null means "tell me you found nothing", which the
        // repair path above needs so it can leave an existing row alone.
        return $fallback === '' ? (string) Str::uuid() : $fallback;
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

    /**
     * Mirror a heartbeat (LicenseUsage.last_seen_at update) onto our own
     * LicenseInstallation row + append a heartbeat log entry. The package
     * does not fire a dedicated event for heartbeat, so we hook into the
     * Eloquent "updated" event on LicenseUsage and detect the case where
     * `last_seen_at` was actually changed.
     */
    public function handleHeartbeat(LicenseUsage $usage): void
    {
        if (! $usage->wasChanged('last_seen_at')) {
            return;
        }

        $licenseCompany = LicenseCompany::where('license_key_hash', $usage->license->key_hash ?? '')->first();
        if (! $licenseCompany) {
            return;
        }

        $installation = LicenseInstallation::where('license_company_id', $licenseCompany->id)
            ->where('fingerprint', $usage->usage_fingerprint)
            ->first();

        // Heartbeat menyertakan fresh metadata via 'data' field di payload yang
        // disimpan oleh package ke meta.client_data. Kita extract supaya
        // domain/hostname/IP/version selalu reflect kondisi terkini client.
        $rawUsageMeta = $this->normalizeMeta($usage->meta);
        $clientData   = $rawUsageMeta['client_data'] ?? [];
        // Format dari client: { client_type, name, user_agent, meta: {...} }
        // Inner 'meta' dari payload berisi hostname/domain/etc.
        $clientMeta = (is_array($clientData) ? ($clientData['meta'] ?? []) : []);
        $appCode    = $clientData['client_type'] ?? $rawUsageMeta['app_code'] ?? 'ermv3';

        if (! $installation) {
            // Activation predates this listener — bootstrap row from heartbeat data
            $licenseApp = LicenseApp::where('license_company_id', $licenseCompany->id)
                ->where('app_code', $appCode)
                ->first()
                ?? $licenseCompany->licenseApps()->first();

            $installation = LicenseInstallation::create([
                // The client's uuid where it sent one - see resolveInstallationUuid(). Both the
                // metadata the heartbeat carries and the usage's own meta are searched, because
                // which of the two holds it depends on how the client called in.
                'installation_uuid'   => $this->resolveInstallationUuid(array_merge($rawUsageMeta, is_array($clientMeta) ? $clientMeta : [])),
                'license_app_id'      => $licenseApp?->id,
                'license_company_id'  => $licenseCompany->id,
                'app_code'            => $appCode,
                'fingerprint'         => $usage->usage_fingerprint,
                'hostname'            => $clientMeta['hostname']    ?? $usage->name ?? null,
                'domain'              => $clientMeta['domain']      ?? null,
                'ip_address'          => $clientMeta['server_ip']   ?? $usage->ip ?? null,
                'app_version'         => $clientMeta['app_version'] ?? null,
                'status'              => 'active',
                'first_verified_at'   => $usage->registered_at ?? now(),
                'last_heartbeat_at'   => $usage->last_seen_at ?? now(),
                'meta'                => $clientMeta,
            ]);
        } else {
            // Refresh fields yang bisa berubah di client (domain, IP, version, dll).
            // Hostname dan fingerprint tidak berubah (sudah jadi part of fingerprint).
            $updates = [
                'last_heartbeat_at' => $usage->last_seen_at ?? now(),
                'status'            => $installation->status === 'revoked' ? 'revoked' : 'active',
            ];

            /*
             * Adopt the client's uuid if the row is carrying one we invented.
             *
             * Creating the row cannot always get this right: this listener runs from the
             * LicenseUsage "updated" hook, and on a heartbeat the fresh client metadata is not
             * necessarily on $usage->meta yet, so resolveInstallationUuid() falls through to a
             * generated value. The result was a row that never matched the installation it
             * described - and since the uuid is what feature activations are keyed to, that row was
             * useless for the admin panel and for revoke-by-installation.
             *
             * Repairing it here rather than at creation means it is self-healing: whatever the row
             * started with, the next heartbeat that carries a client uuid corrects it. The
             * fingerprint identifies the row, so adopting the uuid cannot move it to a different
             * machine.
             */
            $reported = $this->resolveInstallationUuid(
                array_merge($rawUsageMeta, is_array($clientMeta) ? $clientMeta : []),
                null
            );

            if ($reported !== null && $reported !== $installation->installation_uuid) {
                $updates['installation_uuid'] = $reported;
            }

            // Kalau app_code berubah (migrasi env, e.g. pds → pds-dev), ikut sync
            // ke installation row + license_app_id supaya dashboard akurat.
            if ($appCode && $appCode !== $installation->app_code) {
                $updates['app_code'] = $appCode;
                $newLa = LicenseApp::where('license_company_id', $licenseCompany->id)
                    ->where('app_code', $appCode)
                    ->first();
                if ($newLa) {
                    $updates['license_app_id'] = $newLa->id;
                }
            }

            // Hanya update kalau client kirim data fresh (jangan timpa null kalau
            // heartbeat tanpa metadata seperti dari endpoint legacy).
            if (! empty($clientMeta)) {
                if (! empty($clientMeta['domain']))      $updates['domain']      = $clientMeta['domain'];
                if (! empty($clientMeta['hostname']))    $updates['hostname']    = $clientMeta['hostname'];
                if (! empty($clientMeta['server_ip']))   $updates['ip_address']  = $clientMeta['server_ip'];
                if (! empty($clientMeta['app_version'])) $updates['app_version'] = $clientMeta['app_version'];
                $updates['meta'] = array_merge((array) $installation->meta, $clientMeta);
            }

            $installation->update($updates);
        }

        // Append heartbeat log row so admin can see history
        LicenseLogsHeartbeat::create([
            'installation_id'     => $installation->id,
            'license_company_id'  => $licenseCompany->id,
            'app_code'            => $installation->app_code,
            'installation_uuid'   => $installation->installation_uuid,
            'fingerprint'         => $installation->fingerprint,
            'ip_address'          => request()?->ip() ?? $installation->ip_address,
            'app_version'         => $installation->app_version,
            'domain'              => $installation->domain,
            'status'              => 'success',
            'heartbeat_at'        => $usage->last_seen_at ?? now(),
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

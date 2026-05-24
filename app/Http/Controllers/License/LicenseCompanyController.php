<?php

namespace App\Http\Controllers\License;

use App\Http\Controllers\Controller;
use App\Models\LicenseApp;
use App\Models\LicenseCompany;
use App\Models\LicenseLogsAudit;
use App\Models\MasterApp;
use App\Models\MasterCompany;
use App\Models\MasterConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use LucaLongo\Licensing\Models\License;
use Vinkla\Hashids\Facades\Hashids;

class LicenseCompanyController extends Controller
{
    public function index(): View
    {
        $licenses = LicenseCompany::with(['company', 'licenseApps'])
            ->withCount('activeInstallations')
            ->withCount(['installations as total_installations_count'])
            ->latest()->paginate(20);

        return view('license.companies.index', compact('licenses'));
    }

    public function create(): View
    {
        $companies = MasterCompany::active()->orderBy('name')->pluck('name', 'id');
        $apps      = MasterApp::active()->orderBy('name')->get();

        return view('license.companies.create', compact('companies', 'apps'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id'        => 'required|integer',
            'label'             => 'nullable|string|max:255',
            'days'              => 'required|integer|min:1|max:3650',
            'max_installations' => 'required|integer|min:1|max:100',
            'notes'             => 'nullable|string',
            'app_codes'         => 'required|array|min:1',
            'app_codes.*'       => 'string|exists:master_apps,code',
            'app_max_inst'      => 'nullable|array',
        ]);

        // Generate license key
        $key     = 'LIC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4))
                 . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        $keyHash = LicenseCompany::hashKey($key);

        $license = LicenseCompany::create([
            'company_id'        => $data['company_id'],
            'license_key'       => $key,
            'license_key_hash'  => $keyHash,
            'label'             => $data['label'] ?? null,
            'status'            => 'active',
            'activated_at'      => now(),
            'expires_at'        => now()->addDays((int) $data['days']),
            'max_installations' => (int) $data['max_installations'],
            'notes'             => $data['notes'] ?? null,
            'created_by'        => auth()->id(),
        ]);

        // Create license_apps entries
        foreach ($data['app_codes'] as $appCode) {
            $maxInst = (int) ($data['app_max_inst'][$appCode] ?? $data['max_installations']);
            LicenseApp::create([
                'license_company_id' => $license->id,
                'app_code'           => $appCode,
                'status'             => 'active',
                'valid_from'         => now(),
                'valid_until'        => now()->addDays((int) $data['days']),
                'max_installations'  => $maxInst,
                'created_by'        => auth()->id(),
            ]);
        }

        LicenseLogsAudit::record('activated', 'license_company', $license->id, [
            'new' => ['key' => substr($key, 0, 8) . '...', 'apps' => $data['app_codes']],
        ]);

        // Sync to package's `licenses` table — required for the
        // /api/licensing/v1/activate endpoint to find this key.
        try {
            $this->syncToPackageLicense($license, $key);
        } catch (\Throwable $e) {
            \Log::error('Failed to sync new license to package License: ' . $e->getMessage());
        }

        return redirect()->route('license.companies.show', Hashids::encode($license->id))
            ->with('new_license_key', $key)
            ->with('success', 'License created.');
    }

    public function show(string $hash): View
    {
        $license = $this->findOrFail($hash);
        $license->load(['company', 'licenseApps.features', 'activeInstallations']);
        $heartbeatLogs = $license->heartbeatLogs()->orderByDesc('heartbeat_at')->limit(20)->get();

        // Load active package License usages so we can show + revoke them
        $pkgLicense = License::where('key_hash', $license->license_key_hash)->first();
        $activeUsages = $pkgLicense
            ? $pkgLicense->usages()->where('status', 'active')->orderByDesc('last_seen_at')->get()
            : collect();

        return view('license.companies.show', compact('license', 'heartbeatLogs', 'activeUsages'));
    }

    public function suspend(string $hash): RedirectResponse
    {
        $license = $this->findOrFail($hash);
        $license->update(['status' => 'suspended', 'updated_by' => auth()->id()]);
        LicenseLogsAudit::record('suspended', 'license_company', $license->id);
        return back()->with('success', 'License suspended.');
    }

    public function reinstate(string $hash): RedirectResponse
    {
        $license = $this->findOrFail($hash);
        $license->update(['status' => 'active', 'updated_by' => auth()->id()]);
        LicenseLogsAudit::record('activated', 'license_company', $license->id);
        return back()->with('success', 'License reinstated.');
    }

    public function cancel(string $hash): RedirectResponse
    {
        $license = $this->findOrFail($hash);
        $license->update(['status' => 'cancelled', 'updated_by' => auth()->id()]);
        LicenseLogsAudit::record('cancelled', 'license_company', $license->id);
        return back()->with('success', 'License cancelled.');
    }

    public function renew(Request $request, string $hash): RedirectResponse
    {
        $data    = $request->validate(['days' => 'required|integer|min:1|max:3650']);
        $license = $this->findOrFail($hash);

        $newExpiry = ($license->expires_at && $license->expires_at->isFuture())
            ? $license->expires_at->addDays((int) $data['days'])
            : now()->addDays((int) $data['days']);

        $license->update([
            'expires_at' => $newExpiry,
            'status'     => 'active',
            'updated_by' => auth()->id(),
        ]);

        // Extend all license_apps too
        $license->licenseApps()->update(['valid_until' => $newExpiry]);

        LicenseLogsAudit::record('renewed', 'license_company', $license->id, [
            'new' => ['expires_at' => $newExpiry->toDateString()],
        ]);

        return back()->with('success', 'License renewed until ' . $newExpiry->format('d M Y') . '.');
    }

    public function updatePolicy(Request $request, string $hash): RedirectResponse
    {        $data = $request->validate([
            'heartbeat_tolerance' => 'required|integer|min:1|max:20',
            'warning_days'        => 'required|integer|min:1|max:30',
        ]);

        // Store policy overrides in master_configs scoped to this license
        // We use the license's id as a reference in app_configs
        $license = $this->findOrFail($hash);

        // Update or create per-license policy in master_app_configs
        foreach ($data as $key => $value) {
            \App\Models\MasterAppConfig::updateOrCreate(
                ['app_code' => 'license_' . $license->id, 'config_key' => $key, 'config_scope' => 'global'],
                ['config_value' => (string) $value, 'config_type' => 'integer', 'updated_by' => auth()->id()]
            );
        }

        return back()->with('success', 'Policy updated.');
    }

    /**
     * Add a feature to a license_app entry.
     */
    public function addFeature(Request $request, string $hash): RedirectResponse
    {
        $licenseCompany = $this->findOrFail($hash);

        $data = $request->validate([
            'license_app_id' => 'required|integer',
            'feature_key'    => 'required|string|max:100',
            'valid_until'    => 'nullable|date',
        ]);

        $licenseApp = \App\Models\LicenseApp::where('id', $data['license_app_id'])
            ->where('license_company_id', $licenseCompany->id)
            ->firstOrFail();

        // Prevent duplicate
        $exists = \App\Models\LicenseAppFeature::where('license_app_id', $licenseApp->id)
            ->where('feature_key', $data['feature_key'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['feature_key' => 'Feature already licensed for this app.']);
        }

        \App\Models\LicenseAppFeature::create([
            'license_app_id' => $licenseApp->id,
            'app_code'       => $licenseApp->app_code,
            'feature_key'    => $data['feature_key'],
            'status'         => 'active',
            'valid_until'    => $data['valid_until'] ?? null,
            'created_by'     => auth()->id(),
        ]);

        LicenseLogsAudit::record('feature_added', 'license_app', $licenseApp->id, [
            'new' => ['feature_key' => $data['feature_key']],
        ]);

        return back()->with('success', 'Feature "' . $data['feature_key'] . '" added to license.');
    }

    /**
     * Remove / revoke a licensed feature.
     */
    public function removeFeature(string $hash, int $featureId): RedirectResponse
    {
        $licenseCompany = $this->findOrFail($hash);

        $feature = \App\Models\LicenseAppFeature::findOrFail($featureId);

        // Verify it belongs to this license
        $licenseApp = \App\Models\LicenseApp::where('id', $feature->license_app_id)
            ->where('license_company_id', $licenseCompany->id)
            ->firstOrFail();

        LicenseLogsAudit::record('feature_removed', 'license_app', $licenseApp->id, [
            'previous' => ['feature_key' => $feature->feature_key],
        ]);

        $feature->delete();

        return back()->with('success', 'Feature removed from license.');
    }

    /**
     * Toggle feature status (active ↔ suspended).
     */
    public function toggleFeature(string $hash, int $featureId): RedirectResponse
    {
        $licenseCompany = $this->findOrFail($hash);

        $feature = \App\Models\LicenseAppFeature::findOrFail($featureId);

        \App\Models\LicenseApp::where('id', $feature->license_app_id)
            ->where('license_company_id', $licenseCompany->id)
            ->firstOrFail();

        $newStatus = $feature->status === 'active' ? 'suspended' : 'active';
        $feature->update(['status' => $newStatus]);

        return back()->with('success', 'Feature ' . $newStatus . '.');
    }

    /**
     * Return the company's public key info for this license.
     */
    public function publicKey(string $hash): \Illuminate\Http\JsonResponse
    {
        $license = $this->findOrFail($hash);
        $company = \App\Models\MasterCompany::find($license->company_id);

        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Company tidak ditemukan.'], 404);
        }

        $keyService = app(\App\Services\CompanyKeyService::class);
        $info       = $keyService->getPublicKeyInfo($company);

        return response()->json(['success' => true, 'data' => $info, 'company' => $company->name]);
    }

    /**
     * Retrieve the original license key (decrypted from meta).
     * Only works if APP_KEY has not changed since the license was created.
     */
    public function retrieveKey(string $hash): JsonResponse
    {
        $licenseCompany = $this->findOrFail($hash);

        // Our license_companies stores the key encrypted directly
        // Try to retrieve from our own encrypted storage first
        $key = $licenseCompany->license_key ?? null;

        // license_key is stored in plain text in our table (shown once at creation)
        // If it's there, return it
        if ($key) {
            LicenseLogsAudit::record('key_retrieved', 'license_company', $licenseCompany->id, [
                'reason' => 'Admin retrieved key',
            ]);

            return response()->json(['success' => true, 'key' => $key]);
        }

        // Fallback: try package License record
        $license = License::where('key_hash', $licenseCompany->license_key_hash)->first();

        if ($license) {
            $pkgKey = $license->retrieveKey();
            if ($pkgKey) {
                return response()->json(['success' => true, 'key' => $pkgKey]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Kunci tidak dapat dipulihkan. Gunakan fitur Regenerate Key untuk membuat kunci baru.',
        ], 422);
    }

    /**
     * Sync our LicenseCompany record to the package's `licenses` table.
     * The package's /api/licensing/v1/activate endpoint looks up by key_hash
     * in the package table, so every key (new or regenerated) must be there.
     *
     * @return License The synchronized package License record.
     */
    protected function syncToPackageLicense(LicenseCompany $licenseCompany, string $plainKey, ?string $oldKeyHash = null): License
    {
        // 1. Find existing package License by old hash (if any)
        $pkgLicense = null;
        if ($oldKeyHash) {
            $pkgLicense = License::where('key_hash', $oldKeyHash)->first();
        }
        if (! $pkgLicense) {
            $pkgLicense = License::where('key_hash', $licenseCompany->license_key_hash)->first();
        }

        $newKeyHash = License::hashKey($plainKey);

        // Encrypt the key for retrieval (matches package's EncryptedLicenseKeyGenerator behavior)
        $encryptedKey = encrypt($plainKey);

        $meta = [
            'product'        => 'license_company',
            'license_company_id' => $licenseCompany->id,
            'company_name'   => $licenseCompany->company?->name,
            'encrypted_key'  => $encryptedKey,
        ];

        if ($pkgLicense) {
            // Update existing record
            // Package's License model casts `meta` to ArrayObject — handle all
            // possible runtime types defensively (string, array, ArrayObject, null).
            $existingMeta = $this->normalizeMeta($pkgLicense->meta);
            // Track previous hash for audit
            $prev = $existingMeta['previous_key_hashes'] ?? [];
            if ($pkgLicense->key_hash && $pkgLicense->key_hash !== $newKeyHash) {
                $prev[] = ['hash' => $pkgLicense->key_hash, 'rotated_at' => now()->toIso8601String()];
            }
            $meta['previous_key_hashes'] = $prev;

            $pkgLicense->update([
                'key_hash'    => $newKeyHash,
                'status'      => $licenseCompany->status === 'active' ? 'active' : 'suspended',
                'activated_at' => $licenseCompany->activated_at,
                'expires_at'  => $licenseCompany->expires_at,
                'max_usages'  => $licenseCompany->max_installations,
                'meta'        => array_merge($existingMeta, $meta),
            ]);
        } else {
            // Create new package License row
            $pkgLicense = License::create([
                'key_hash'     => $newKeyHash,
                'status'       => $licenseCompany->status === 'active' ? 'active' : 'suspended',
                'activated_at' => $licenseCompany->activated_at,
                'expires_at'   => $licenseCompany->expires_at,
                'max_usages'   => $licenseCompany->max_installations,
                'meta'         => $meta,
            ]);
        }

        return $pkgLicense;
    }

    /**
     * Normalize meta column from any of the runtime types it can hold:
     * null, string (json), array, or Eloquent ArrayObject.
     */
    protected function normalizeMeta($meta): array
    {
        if (is_null($meta)) {
            return [];
        }
        if (is_array($meta)) {
            return $meta;
        }
        if ($meta instanceof \Illuminate\Database\Eloquent\Casts\ArrayObject) {
            return $meta->toArray();
        }
        if (is_object($meta) && method_exists($meta, 'toArray')) {
            return $meta->toArray();
        }
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Show confirmation page for regenerating license key.
     */
    public function regenerateConfirm(string $hash): View
    {
        $licenseCompany = $this->findOrFail($hash);
        $licenseCompany->load('company');

        return view('license.companies.regenerate-confirm', compact('licenseCompany', 'hash'));
    }

    /**
     * Regenerate a new license key for this license.
     * Use when the original key cannot be recovered (APP_KEY changed).
     * The client (ERMv3) must re-activate with the new key.
     */
    public function regenerateKey(Request $request, string $hash): RedirectResponse
    {
        $licenseCompany = $this->findOrFail($hash);

        $oldKeyHash = $licenseCompany->license_key_hash;

        // ── Step 1: Hard-delete usages tied to the old key ────────────────
        // We DELETE rather than status='revoked' because (license_id,
        // usage_fingerprint) is a UNIQUE INDEX in the package's table, so
        // soft-revoke leaves the slot occupied and the same device cannot
        // re-activate. Hard delete frees the slot completely.
        $revokedCount = 0;
        $oldPkgLicense = License::where('key_hash', $oldKeyHash)->first();
        if ($oldPkgLicense) {
            $revokedCount = $oldPkgLicense->usages()->delete();
        }

        // Mark our LicenseInstallation rows as revoked (audit trail kept)
        foreach ($licenseCompany->activeInstallations as $installation) {
            $installation->update(['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => 'key_regenerated']);
        }

        // ── Step 2: Generate a new key ────────────────────────────────────
        $newKey = 'LIC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4))
                . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        $newKeyHash = LicenseCompany::hashKey($newKey);

        $licenseCompany->update([
            'license_key'      => $newKey,
            'license_key_hash' => $newKeyHash,
            'updated_by'       => auth()->id(),
        ]);

        // ── Step 3: Sync to package's `licenses` table ─────────────────────
        // Required so the /api/licensing/v1/activate endpoint can find this key.
        try {
            $this->syncToPackageLicense($licenseCompany, $newKey, $oldKeyHash);
        } catch (\Throwable $e) {
            \Log::error('Failed to sync regenerated key to package License: ' . $e->getMessage());
        }

        LicenseLogsAudit::record('key_regenerated', 'license_company', $licenseCompany->id, [
            'reason'           => $request->input('reason', 'Key regenerated by admin'),
            'revoked_usages'   => $revokedCount,
        ]);

        $msg = 'Kunci lisensi berhasil di-generate ulang.';
        if ($revokedCount > 0) {
            $msg .= " {$revokedCount} usage aktif ikut di-revoke — client harus aktivasi ulang dengan kunci baru.";
        }

        return redirect()
            ->route('license.companies.show', $hash)
            ->with('new_license_key', $newKey)
            ->with('success', $msg);
    }

    private function findOrFail(string $hash): LicenseCompany
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return LicenseCompany::findOrFail($ids[0]);
    }

    /**
     * Show confirmation page for deleting a license.
     */
    public function deleteConfirm(string $hash): View
    {
        $licenseCompany = $this->findOrFail($hash);
        $licenseCompany->load(['company', 'activeInstallations', 'licenseApps']);

        // Also load package License usages so admin sees the impact
        $pkgLicense = License::where('key_hash', $licenseCompany->license_key_hash)->first();
        $activeUsages = $pkgLicense
            ? $pkgLicense->usages()->where('status', 'active')->get()
            : collect();

        return view('license.companies.delete-confirm', compact('licenseCompany', 'hash', 'activeUsages'));
    }

    /**
     * Soft delete a license.
     * Auto-revokes all active package usages first to free seats.
     */
    public function destroy(string $hash): RedirectResponse
    {
        $license = $this->findOrFail($hash);

        // Hard-delete package usages (frees unique-index slot for re-use)
        $pkgLicense = License::where('key_hash', $license->license_key_hash)->first();
        $revokedCount = 0;
        if ($pkgLicense) {
            $revokedCount = $pkgLicense->usages()->delete();
        }

        // Mark our installations as revoked (kept for audit)
        foreach ($license->activeInstallations as $installation) {
            $installation->update(['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => 'license_deleted']);
        }

        LicenseLogsAudit::record('deleted', 'license_company', $license->id, [
            'previous' => [
                'company'    => $license->company?->name,
                'label'      => $license->label,
                'status'     => $license->status,
                'expires_at' => $license->expires_at?->toDateString(),
                'revoked_usages' => $revokedCount,
            ],
        ]);

        $license->delete(); // soft delete

        $msg = 'Lisensi berhasil dihapus.';
        if ($revokedCount > 0) {
            $msg .= " {$revokedCount} usage aktif ikut di-revoke.";
        }

        return redirect()->route('license.companies.index')->with('success', $msg);
    }

    /**
     * Revoke all active usages for a license — frees up all installation slots.
     */
    public function revokeAllUsages(string $hash): RedirectResponse
    {
        $licenseCompany = $this->findOrFail($hash);

        $pkgLicense = License::where('key_hash', $licenseCompany->license_key_hash)->first();
        if (! $pkgLicense) {
            return back()->withErrors(['error' => 'Package License record not found. Run: php artisan licenses:sync-package']);
        }

        // Hard-delete to free unique-index slots for re-activation
        $count = $pkgLicense->usages()->delete();

        // Mark our LicenseInstallation rows (kept for audit)
        foreach ($licenseCompany->activeInstallations as $installation) {
            $installation->update(['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => 'admin_revoked_all']);
        }

        LicenseLogsAudit::record('usages_revoked', 'license_company', $licenseCompany->id, [
            'previous' => ['count' => $count],
        ]);

        return back()->with('success', "Berhasil revoke {$count} usage. Slot lisensi sekarang kosong dan client bisa aktivasi ulang.");
    }

    /**
     * Revoke a single usage by id.
     */
    public function revokeUsage(string $hash, int $usageId): RedirectResponse
    {
        $licenseCompany = $this->findOrFail($hash);

        $pkgLicense = License::where('key_hash', $licenseCompany->license_key_hash)->first();
        if (! $pkgLicense) {
            return back()->withErrors(['error' => 'Package License record not found.']);
        }

        $usage = $pkgLicense->usages()->where('id', $usageId)->first();
        if (! $usage) {
            return back()->withErrors(['error' => 'Usage tidak ditemukan untuk lisensi ini.']);
        }

        $fingerprint = $usage->usage_fingerprint;
        $usage->delete(); // hard delete to free unique-index slot

        // Also revoke our installation row by fingerprint
        \App\Models\LicenseInstallation::where('license_company_id', $licenseCompany->id)
            ->where('fingerprint', $fingerprint)
            ->where('status', 'active')
            ->update(['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => 'admin_revoked']);

        LicenseLogsAudit::record('usage_revoked', 'license_company', $licenseCompany->id, [
            'previous' => ['usage_id' => $usageId, 'fingerprint' => substr($usage->usage_fingerprint, 0, 16) . '...'],
        ]);

        return back()->with('success', 'Usage berhasil di-revoke.');
    }
}

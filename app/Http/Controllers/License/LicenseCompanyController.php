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

        return redirect()->route('license.companies.show', Hashids::encode($license->id))
            ->with('success', 'License created. Key: ' . $key);
    }

    public function show(string $hash): View
    {
        $license = $this->findOrFail($hash);
        $license->load(['company', 'licenseApps.features', 'activeInstallations']);
        $heartbeatLogs = $license->heartbeatLogs()->orderByDesc('heartbeat_at')->limit(20)->get();

        return view('license.companies.show', compact('license', 'heartbeatLogs'));
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
     * Regenerate a new license key for this license.
     * Use when the original key cannot be recovered (APP_KEY changed).
     * The client (ERMv3) must re-activate with the new key.
     */
    public function regenerateKey(Request $request, string $hash): RedirectResponse
    {
        $licenseCompany = $this->findOrFail($hash);

        // Generate a new key directly (no dependency on package License record)
        $newKey     = 'LIC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4))
                    . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        $newKeyHash = LicenseCompany::hashKey($newKey);

        $licenseCompany->update([
            'license_key'      => $newKey,
            'license_key_hash' => $newKeyHash,
            'updated_by'       => auth()->id(),
        ]);

        // Also update package License record if it exists
        $pkgLicense = License::where('key_hash', $licenseCompany->getOriginal('license_key_hash') ?? '')->first();
        if ($pkgLicense && $pkgLicense->canRegenerateKey()) {
            try {
                $pkgLicense->regenerateKey();
            } catch (\Throwable) {
                // Non-fatal — our record is already updated
            }
        }

        LicenseLogsAudit::record('key_regenerated', 'license_company', $licenseCompany->id, [
            'reason' => $request->input('reason', 'Key regenerated by admin'),
        ]);

        return redirect()
            ->route('license.companies.show', $hash)
            ->with('success', 'Kunci lisensi baru: ' . $newKey . ' — Catat segera, tidak akan ditampilkan lagi.');
    }

    private function findOrFail(string $hash): LicenseCompany
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return LicenseCompany::findOrFail($ids[0]);
    }

    /**
     * Soft delete a license.
     * Only allowed if license has no active installations.
     */
    public function destroy(string $hash): RedirectResponse
    {
        $license = $this->findOrFail($hash);

        if ($license->activeInstallations()->count() > 0) {
            return back()->withErrors([
                'error' => 'Tidak bisa menghapus lisensi yang masih memiliki instalasi aktif. Revoke semua instalasi terlebih dahulu.',
            ]);
        }

        LicenseLogsAudit::record('deleted', 'license_company', $license->id, [
            'previous' => [
                'company'    => $license->company?->name,
                'label'      => $license->label,
                'status'     => $license->status,
                'expires_at' => $license->expires_at?->toDateString(),
            ],
        ]);

        $license->delete(); // soft delete

        return redirect()->route('license.companies.index')
            ->with('success', 'Lisensi berhasil dihapus.');
    }
}

<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\LogAudit;
use App\Models\LicenseFeatureActivation;
use App\Models\MasterApp;
use App\Models\MasterAppFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Vinkla\Hashids\Facades\Hashids;

class AppController extends Controller
{
    public function index(): View
    {
        $apps = MasterApp::withCount('features')->latest()->paginate(20);
        return view('master.apps.index', compact('apps'));
    }

    public function create(): View
    {
        return view('master.apps.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|max:50|unique:master_apps,code',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'version'     => 'nullable|string|max:20',
            'base_url'    => 'nullable|url|max:255',
            'icon'        => 'nullable|string|max:50',
        ]);

        $app = MasterApp::create([...$data, 'created_by' => auth()->id()]);

        LogAudit::record('created', 'master_app', [
            'subject_type'  => 'MasterApp',
            'subject_id'    => $app->id,
            'subject_label' => $app->name,
            'new'           => $data,
        ]);

        return redirect()->route('master.apps.show', Hashids::encode($app->id))
            ->with('success', 'App registered.');
    }

    public function show(string $hash): View
    {
        $app = $this->findOrFail($hash);
        $features = $app->features()->orderBy('category')->orderBy('name')->get();

        // Load all active license_apps for this app_code so we can offer
        // "License this feature to..." directly from the master app page.
        // Filter out orphans whose LicenseCompany was soft-deleted so the
        // view can safely access $la->licenseCompany->id.
        $licenseApps = \App\Models\LicenseApp::with('licenseCompany.company')
            ->where('app_code', $app->code)
            ->where('status', 'active')
            ->whereHas('licenseCompany') // exclude orphans
            ->get();

        return view('master.apps.show', compact('app', 'features', 'licenseApps'));
    }

    public function edit(string $hash): View
    {
        $app = $this->findOrFail($hash);
        return view('master.apps.edit', compact('app'));
    }

    public function update(Request $request, string $hash): RedirectResponse
    {
        $app = $this->findOrFail($hash);

        $data = $request->validate([
            'code'        => 'required|string|max:50|unique:master_apps,code,' . $app->id,
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'version'     => 'nullable|string|max:20',
            'base_url'    => 'nullable|url|max:255',
            'status'      => 'required|in:active,inactive,deprecated',
            'icon'        => 'nullable|string|max:50',
        ]);

        $app->update([...$data, 'updated_by' => auth()->id()]);

        return redirect()->route('master.apps.show', $hash)->with('success', 'App updated.');
    }

    // ── Features ─────────────────────────────────────────────────────────────

    public function storeFeature(Request $request, string $hash): RedirectResponse
    {
        $app = $this->findOrFail($hash);

        $data = $request->validate([
            'feature_key'      => 'required|string|max:100',
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'category'         => 'nullable|string|max:50',
            'requires_license' => 'boolean',
        ]);

        $requiresLicense = $request->boolean('requires_license');

        $feature = MasterAppFeature::create([
            'app_code'         => $app->code,
            'feature_key'      => $data['feature_key'],
            'name'             => $data['name'],
            'description'      => $data['description'] ?? null,
            'category'         => $data['category'] ?? null,
            'is_active'        => true,
            'requires_license' => $requiresLicense,
            'created_by'       => auth()->id(),
        ]);

        $plainKey = null;
        if ($requiresLicense) {
            $plainKey = $feature->generateFeatureLicenseKey();
        }

        $message = 'Feature "' . $feature->name . '" added.';
        if ($plainKey) {
            $message .= ' Feature License Key: ' . $plainKey . ' — Catat segera, tidak akan ditampilkan lagi.';
        }

        return back()->with('success', $message);
    }

    public function toggleFeature(string $hash, int $featureId): RedirectResponse
    {
        $this->findOrFail($hash);
        $feature = MasterAppFeature::findOrFail($featureId);
        $feature->update(['is_active' => ! $feature->is_active]);

        return back()->with('success', 'Feature "' . $feature->name . '" ' . ($feature->is_active ? 'diaktifkan' : 'dinonaktifkan') . '.');
    }

    public function retrieveFeatureKey(string $hash, int $featureId): \Illuminate\Http\JsonResponse
    {
        $this->findOrFail($hash);
        $feature = MasterAppFeature::findOrFail($featureId);

        if (! $feature->requires_license) {
            return response()->json(['success' => false, 'message' => 'Feature ini tidak memerlukan lisensi.'], 400);
        }

        $key = $feature->retrieveFeatureLicenseKey();

        if (! $key) {
            return response()->json([
                'success' => false,
                'message' => 'Kunci tidak dapat dipulihkan. APP_KEY mungkin telah berubah. Gunakan Regenerate.',
            ], 422);
        }

        return response()->json(['success' => true, 'key' => $key]);
    }

    public function regenerateFeatureKey(string $hash, int $featureId): RedirectResponse
    {
        $this->findOrFail($hash);
        $feature = MasterAppFeature::findOrFail($featureId);

        if (! $feature->requires_license) {
            return back()->withErrors(['error' => 'Feature ini tidak memerlukan lisensi.']);
        }

        $newKey = $feature->generateFeatureLicenseKey();

        // Revoke all existing activations since key changed
        LicenseFeatureActivation::where('feature_key', $feature->feature_key)
            ->where('app_code', $feature->app_code)
            ->update(['status' => 'revoked', 'revoked_at' => now()]);

        return back()->with('success', 'Kunci baru: ' . $newKey . ' — Semua aktivasi lama dicabut. ERMv3 harus aktivasi ulang.');
    }

    private function findOrFail(string $hash): MasterApp
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return MasterApp::findOrFail($ids[0]);
    }
}

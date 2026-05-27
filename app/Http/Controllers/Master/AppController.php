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
        $app = $this->findOrFail($hash);
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

        return response()->json([
            'success'  => true,
            'key'      => $key,
            'app_name' => $app->name,
            'app_code' => $app->code,
        ]);
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

        return back()->with('success', 'Kunci baru: ' . $newKey . ' — Semua aktivasi lama dicabut. Aplikasi client harus aktivasi ulang.');
    }

    /**
     * Soft-delete a registered app. Aman dilakukan kalau:
     *   - Tidak ada license_apps yang masih aktif memakai code ini
     *   - Tidak ada feature activation yang masih aktif
     *
     * Kalau ada dependency aktif → block dengan pesan yang jelas.
     * Master features ikut soft-deleted (cascade) supaya kalau app didaftar ulang
     * dengan code sama, tidak konflik dengan feature lama.
     */
    public function destroy(string $hash): RedirectResponse
    {
        $app = $this->findOrFail($hash);

        // Block kalau masih ada license_apps active yang memakai code ini
        $activeLicenseApps = \App\Models\LicenseApp::where('app_code', $app->code)
            ->where('status', 'active')
            ->count();

        if ($activeLicenseApps > 0) {
            return back()->with('error',
                "Tidak dapat menghapus app \"{$app->name}\" — masih ada {$activeLicenseApps} lisensi aktif yang memakai app code \"{$app->code}\". "
                . "Cancel/suspend semua lisensi terkait dulu sebelum hapus app."
            );
        }

        // Block kalau masih ada activation aktif untuk fitur app ini
        $activeFeatureActivations = LicenseFeatureActivation::where('app_code', $app->code)
            ->where('status', 'active')
            ->count();

        if ($activeFeatureActivations > 0) {
            return back()->with('error',
                "Tidak dapat menghapus app \"{$app->name}\" — masih ada {$activeFeatureActivations} feature activation aktif. "
                . "Revoke semua FLK aktif untuk app ini dulu."
            );
        }

        // Aman dihapus — cascade ke features juga (soft-delete kalau model pakai SoftDeletes,
        // hard-delete kalau tidak)
        \DB::transaction(function () use ($app) {
            // Master features tidak pakai SoftDeletes → hard delete
            MasterAppFeature::where('app_code', $app->code)->delete();

            // App pakai SoftDeletes
            $app->delete();
        });

        LogAudit::record('deleted', 'master_app', [
            'subject_type'  => 'MasterApp',
            'subject_id'    => $app->id,
            'subject_label' => $app->name,
        ]);

        return redirect()->route('master.apps.index')
            ->with('success', "Aplikasi \"{$app->name}\" berhasil dihapus.");
    }

    private function findOrFail(string $hash): MasterApp
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return MasterApp::findOrFail($ids[0]);
    }
}

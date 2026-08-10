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

        /*
         * Instalasi mana saja yang memegang FLK app ini, satu baris per installation_uuid.
         *
         * Ada karena instalasi bisa kehilangan identitasnya - uuid client hilang bersama storage atau
         * saat APP_KEY berputar, lalu client mencetak uuid baru - dan aktivasi lamanya tertinggal
         * sebagai instalasi hantu. Sebelum ada panel ini, satu-satunya gejalanya adalah angka
         * "N instalasi aktif" yang terus naik tanpa penjelasan dan tanpa cara memperbaikinya dari UI.
         *
         * Diringkas di controller, bukan di blade: view sebelumnya menembak dua query agregat per
         * baris fitur, dan menambah daftar instalasi ke dalam loop itu akan menjadikannya empat.
         */
        $installations = LicenseFeatureActivation::where('app_code', $app->code)
            ->orderBy('installation_uuid')
            ->get()
            ->groupBy('installation_uuid')
            ->map(fn ($rows) => [
                'total'        => $rows->count(),
                'live'         => $rows->filter(fn ($a) => $a->isCurrentlyActive())->count(),
                'lapsed'       => $rows->filter(fn ($a) => $a->isExpired() && $a->status !== 'revoked')->count(),
                'revoked'      => $rows->where('status', 'revoked')->count(),
                'first_seen'   => $rows->min('activated_at'),
                'last_seen'    => $rows->max('updated_at'),
                'fingerprints' => $rows->pluck('fingerprint')->filter()->unique()->count(),
            ])
            ->sortByDesc('last_seen');

        return view('master.apps.show', compact('app', 'features', 'licenseApps', 'installations'));
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
            // Lifetime atau berjangka, dipilih eksplisit. Kolom kosong tidak dipakai sebagai penanda
            // lifetime di form: admin tidak bisa membedakan "sengaja permanen" dari "lupa mengisi",
            // dan salah satunya menjual modul selamanya tanpa ada yang menyadarinya.
            'license_validity_mode' => 'nullable|in:lifetime,term',
            // Dibatasi 3650 hari (10 tahun): di atas itu praktis lifetime, dan angka sebesar itu
            // hampir selalu salah ketik. Wajib kalau modenya berjangka.
            'license_duration_days' => 'nullable|integer|min:1|max:3650|required_if:license_validity_mode,term',
        ], [
            'license_duration_days.required_if' => 'Isi jumlah hari, atau pilih Lifetime.',
        ]);

        $requiresLicense = $request->boolean('requires_license');
        $durationDays = $this->resolveDurationDays($data);

        $feature = MasterAppFeature::create([
            'app_code'         => $app->code,
            'feature_key'      => $data['feature_key'],
            'name'             => $data['name'],
            'description'      => $data['description'] ?? null,
            'category'         => $data['category'] ?? null,
            'is_active'        => true,
            'requires_license' => $requiresLicense,
            // Masa aktif hanya berarti untuk fitur berlisensi: fitur gratis tidak pernah diaktivasi,
            // jadi tidak ada saat mulai untuk menghitung tenggatnya.
            'license_duration_days' => $requiresLicense ? $durationDays : null,
            'created_by'       => auth()->id(),
        ]);

        $plainKey = null;
        if ($requiresLicense) {
            $plainKey = $feature->generateFeatureLicenseKey();
        }

        $message = 'Feature "' . $feature->name . '" added.';
        if ($plainKey) {
            $message .= ' Feature License Key: ' . $plainKey . ' — Catat segera, tidak akan ditampilkan lagi.';
            $message .= ' Masa aktif: ' . $feature->validityLabel() . '.';
        }

        return back()->with('success', $message);
    }

    /**
     * Ubah masa aktif FLK sebuah fitur yang sudah terdaftar.
     *
     * Perlu berdiri sendiri karena semua fitur sudah dibuat sebelum masa aktif ada. Tanpa ini, satu-
     * satunya cara memberi tenggat pada fitur lama adalah menghapus lalu mendaftarkannya ulang, yang
     * mencabut setiap aktivasi yang sedang berjalan.
     *
     * Aktivasi yang sudah berjalan tidak diubah: tenggatnya sudah dicap di license_feature_activations
     * dan itu yang benar-benar dijual ke pelanggan tersebut. Masa aktif baru berlaku untuk aktivasi
     * berikutnya - termasuk perpanjangan, karena aktivasi ulang menghitung ulang dari saat itu.
     */
    public function updateFeatureDuration(Request $request, string $hash, int $featureId): RedirectResponse
    {
        $this->findOrFail($hash);
        $feature = MasterAppFeature::findOrFail($featureId);

        $data = $request->validate([
            'license_validity_mode' => 'nullable|in:lifetime,term',
            'license_duration_days' => 'nullable|integer|min:1|max:3650|required_if:license_validity_mode,term',
        ], [
            'license_duration_days.required_if' => 'Isi jumlah hari, atau pilih Lifetime.',
        ]);

        if (! $feature->requires_license) {
            return back()->withErrors(['error' => 'Fitur gratis tidak punya masa aktif.']);
        }

        $feature->update(['license_duration_days' => $this->resolveDurationDays($data)]);

        $liveCount = LicenseFeatureActivation::where('feature_key', $feature->feature_key)
            ->where('app_code', $feature->app_code)
            ->live()
            ->count();

        $message = $feature->isLifetime()
            ? 'Masa aktif "' . $feature->name . '" sekarang Lifetime - aktivasi baru tidak akan kedaluwarsa.'
            : 'Masa aktif "' . $feature->name . '" sekarang ' . $feature->license_duration_days . ' hari.';

        if ($liveCount > 0) {
            $message .= ' ' . $liveCount . ' aktivasi yang sedang berjalan tetap memakai tenggat lamanya;'
                . ' masa aktif baru berlaku saat aktivasi berikutnya.';
        }

        return back()->with('success', $message);
    }

    /**
     * Cabut aktivasi satu instalasi dari sebuah fitur.
     *
     * Dibutuhkan karena instalasi bisa kehilangan identitasnya. installation_uuid client disimpan di
     * storage terenkripsi APP_KEY; kalau storage-nya terhapus atau APP_KEY berputar, client mencetak
     * uuid baru dan aktivasi lamanya tertinggal sebagai instalasi hantu yang menggelembungkan hitungan
     * "instalasi aktif" selamanya.
     *
     * Sengaja tidak digabungkan otomatis oleh server. Fingerprint pun ikut berubah saat APP_KEY
     * berputar - HardenedFingerprintGenerator memang mengikat ke APP_KEY - jadi dari sisi server
     * sebuah redeploy tidak bisa dibedakan dari aplikasi yang dikloning ke mesin lain. Itu justru inti
     * proteksi anti-clone-nya; menggabungkan otomatis akan melubanginya. Yang tahu bedanya hanya orang
     * yang melakukan deploy, jadi keputusannya diberikan ke dia lewat tombol ini.
     *
     * Di-revoke, bukan dihapus: catatan bahwa lisensi pernah dipakai di sana tetap ada, dan scope
     * live() sudah mengecualikan yang revoked sehingga hitungannya langsung benar.
     */
    public function revokeFeatureInstallation(Request $request, string $hash, int $featureId): RedirectResponse
    {
        $this->findOrFail($hash);
        $feature = MasterAppFeature::findOrFail($featureId);

        $data = $request->validate([
            'installation_uuid' => 'required|string|max:64',
        ]);

        $activation = LicenseFeatureActivation::where('app_code', $feature->app_code)
            ->where('feature_key', $feature->feature_key)
            ->where('installation_uuid', $data['installation_uuid'])
            ->first();

        if (! $activation) {
            return back()->withErrors(['error' => 'Aktivasi tidak ditemukan.']);
        }

        $activation->update(['status' => 'revoked', 'revoked_at' => now()]);

        return back()->with('success', 'Aktivasi "' . $feature->name . '" untuk instalasi '
            . substr($data['installation_uuid'], 0, 13) . '… dicabut.');
    }

    /**
     * Cabut semua aktivasi satu instalasi, di seluruh fitur app ini.
     *
     * Kehilangan identitas mengenai semua fitur sekaligus - di crm-dev satu kejadian meninggalkan 15
     * baris hantu - jadi mencabutnya satu per satu berarti 15 klik untuk satu keputusan yang sama.
     */
    public function revokeInstallation(Request $request, string $hash): RedirectResponse
    {
        $app = $this->findOrFail($hash);

        $data = $request->validate([
            'installation_uuid' => 'required|string|max:64',
        ]);

        $count = LicenseFeatureActivation::where('app_code', $app->code)
            ->where('installation_uuid', $data['installation_uuid'])
            ->where('status', '!=', 'revoked')
            ->update(['status' => 'revoked', 'revoked_at' => now()]);

        if ($count === 0) {
            return back()->withErrors(['error' => 'Tidak ada aktivasi aktif untuk instalasi itu.']);
        }

        return back()->with('success', $count . ' aktivasi instalasi '
            . substr($data['installation_uuid'], 0, 13) . '… dicabut.');
    }

    /**
     * Terjemahkan pilihan masa aktif dari form menjadi nilai kolomnya.
     *
     * null = lifetime, dan itu satu-satunya penanda lifetime di database. Mode 'lifetime' membuang
     * angka hari yang mungkin masih tertinggal di input - kalau admin mengetik 30 lalu memilih
     * Lifetime, yang dia maksud adalah Lifetime.
     *
     * Kalau mode tidak dikirim sama sekali (permintaan lama, atau form tanpa radio), jatuh kembali ke
     * "ada angka berarti berjangka" supaya pemanggil lama tetap bekerja.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveDurationDays(array $data): ?int
    {
        $mode = $data['license_validity_mode'] ?? null;

        if ($mode === 'lifetime') {
            return null;
        }

        $days = $data['license_duration_days'] ?? null;

        return $days !== null ? (int) $days : null;
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

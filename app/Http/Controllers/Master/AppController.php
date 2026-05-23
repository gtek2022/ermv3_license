<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\LogAudit;
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
        return view('master.apps.show', compact('app', 'features'));
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
            'feature_key' => 'required|string|max:100',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'nullable|string|max:50',
        ]);

        MasterAppFeature::create([
            'app_code'    => $app->code,
            'feature_key' => $data['feature_key'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'category'    => $data['category'] ?? null,
            'created_by'  => auth()->id(),
        ]);

        return back()->with('success', 'Feature added.');
    }

    public function destroyFeature(string $hash, int $featureId): RedirectResponse
    {
        $this->findOrFail($hash);
        MasterAppFeature::findOrFail($featureId)->delete();
        return back()->with('success', 'Feature removed.');
    }

    private function findOrFail(string $hash): MasterApp
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return MasterApp::findOrFail($ids[0]);
    }
}

<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\LogAudit;
use App\Models\MasterApp;
use App\Models\MasterFeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Vinkla\Hashids\Facades\Hashids;

class FeatureFlagController extends Controller
{
    public function index(): View
    {
        $flags = MasterFeatureFlag::orderBy('app_scope')->orderBy('feature_key')->paginate(30);
        $apps  = MasterApp::active()->pluck('name', 'code');

        return view('master.feature-flags.index', compact('flags', 'apps'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'feature_key'         => 'required|string|max:150',
            'app_scope'           => 'required|string|max:50',
            'enabled'             => 'boolean',
            'rollout_percentage'  => 'required|integer|min:0|max:100',
            'description'         => 'nullable|string',
        ]);

        $flag = MasterFeatureFlag::create([...$data, 'created_by' => auth()->id()]);

        LogAudit::record('created', 'master_feature_flag', [
            'subject_type'  => 'MasterFeatureFlag',
            'subject_id'    => $flag->id,
            'subject_label' => $flag->feature_key,
            'new'           => $data,
        ]);

        return back()->with('success', 'Feature flag created.');
    }

    public function toggle(string $hash): RedirectResponse
    {
        $flag = $this->findOrFail($hash);
        $flag->update(['enabled' => ! $flag->enabled, 'updated_by' => auth()->id()]);

        LogAudit::record('updated', 'master_feature_flag', [
            'subject_type'  => 'MasterFeatureFlag',
            'subject_id'    => $flag->id,
            'subject_label' => $flag->feature_key,
            'new'           => ['enabled' => $flag->enabled],
        ]);

        return back()->with('success', 'Feature flag ' . ($flag->enabled ? 'enabled' : 'disabled') . '.');
    }

    public function update(Request $request, string $hash): RedirectResponse
    {
        $flag = $this->findOrFail($hash);

        $data = $request->validate([
            'enabled'            => 'boolean',
            'rollout_percentage' => 'required|integer|min:0|max:100',
            'description'        => 'nullable|string',
        ]);

        $flag->update([...$data, 'updated_by' => auth()->id()]);

        return back()->with('success', 'Feature flag updated.');
    }

    public function destroy(string $hash): RedirectResponse
    {
        $flag = $this->findOrFail($hash);
        $flag->delete();
        return back()->with('success', 'Feature flag deleted.');
    }

    private function findOrFail(string $hash): MasterFeatureFlag
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return MasterFeatureFlag::findOrFail($ids[0]);
    }
}

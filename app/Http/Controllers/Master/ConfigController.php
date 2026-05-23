<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\LogAudit;
use App\Models\MasterConfig;
use App\Models\MasterConfigVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;
use Vinkla\Hashids\Facades\Hashids;

class ConfigController extends Controller
{
    public function index(Request $request): View
    {
        $query = MasterConfig::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('config_key', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $configs    = $query->orderBy('category')->orderBy('config_key')->paginate(30);
        $categories = MasterConfig::distinct()->pluck('category')->sort()->values();

        return view('master.configs.index', compact('configs', 'categories'));
    }

    public function create(): View
    {
        $categories = MasterConfig::distinct()->pluck('category')->sort()->values();
        return view('master.configs.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'config_key'   => 'required|string|max:150|unique:master_configs,config_key',
            'config_value' => 'nullable|string',
            'config_type'  => 'required|in:string,integer,boolean,json,encrypted',
            'category'     => 'required|string|max:80',
            'description'  => 'nullable|string',
            'is_encrypted' => 'boolean',
            'is_public'    => 'boolean',
        ]);

        $value = $data['config_value'] ?? null;
        if ($request->boolean('is_encrypted') && $value) {
            $value = Crypt::encryptString($value);
        }

        $config = MasterConfig::create([
            'config_key'   => $data['config_key'],
            'config_value' => $value,
            'config_type'  => $data['config_type'],
            'category'     => $data['category'],
            'description'  => $data['description'] ?? null,
            'is_encrypted' => $request->boolean('is_encrypted'),
            'is_public'    => $request->boolean('is_public'),
            'created_by'   => auth()->id(),
        ]);

        MasterConfigVersion::snapshot('master_config', $config->id, $config->config_key, null, $data['config_value'] ?? null);
        LogAudit::record('created', 'master_config', ['subject_type' => 'MasterConfig', 'subject_id' => $config->id, 'subject_label' => $config->config_key]);

        return redirect()->route('master.configs.index')->with('success', 'Config created.');
    }

    public function edit(string $hash): View
    {
        $config = $this->findOrFail($hash);
        $categories = MasterConfig::distinct()->pluck('category')->sort()->values();
        return view('master.configs.edit', compact('config', 'categories'));
    }

    public function update(Request $request, string $hash): RedirectResponse
    {
        $config = $this->findOrFail($hash);

        $data = $request->validate([
            'config_value' => 'nullable|string',
            'config_type'  => 'required|in:string,integer,boolean,json,encrypted',
            'category'     => 'required|string|max:80',
            'description'  => 'nullable|string',
            'is_encrypted' => 'boolean',
            'is_public'    => 'boolean',
            'change_reason'=> 'nullable|string|max:255',
        ]);

        $previousRaw = $config->is_encrypted
            ? '[encrypted]'
            : $config->config_value;

        $newValue = $data['config_value'] ?? null;
        $storedValue = $request->boolean('is_encrypted') && $newValue
            ? Crypt::encryptString($newValue)
            : $newValue;

        $config->update([
            'config_value' => $storedValue,
            'config_type'  => $data['config_type'],
            'category'     => $data['category'],
            'description'  => $data['description'] ?? null,
            'is_encrypted' => $request->boolean('is_encrypted'),
            'is_public'    => $request->boolean('is_public'),
            'updated_by'   => auth()->id(),
        ]);

        MasterConfigVersion::snapshot(
            'master_config', $config->id, $config->config_key,
            $previousRaw, $request->boolean('is_encrypted') ? '[encrypted]' : $newValue,
            $data['change_reason'] ?? null
        );

        LogAudit::record('updated', 'master_config', [
            'subject_type'  => 'MasterConfig',
            'subject_id'    => $config->id,
            'subject_label' => $config->config_key,
        ]);

        return redirect()->route('master.configs.index')->with('success', 'Config updated.');
    }

    public function history(string $hash): View
    {
        $config  = $this->findOrFail($hash);
        $history = MasterConfigVersion::where('config_type', 'master_config')
            ->where('config_id', $config->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('master.configs.history', compact('config', 'history'));
    }

    public function rollback(Request $request, string $hash): RedirectResponse
    {
        $config  = $this->findOrFail($hash);
        $version = MasterConfigVersion::findOrFail($request->input('version_id'));

        $config->update([
            'config_value' => $version->previous_value,
            'updated_by'   => auth()->id(),
        ]);

        MasterConfigVersion::snapshot(
            'master_config', $config->id, $config->config_key,
            $config->config_value, $version->previous_value,
            'Rollback to version #' . $version->id
        );

        return back()->with('success', 'Config rolled back.');
    }

    public function destroy(string $hash): RedirectResponse
    {
        $config = $this->findOrFail($hash);
        LogAudit::record('deleted', 'master_config', ['subject_type' => 'MasterConfig', 'subject_id' => $config->id, 'subject_label' => $config->config_key]);
        $config->delete();
        return redirect()->route('master.configs.index')->with('success', 'Config deleted.');
    }

    private function findOrFail(string $hash): MasterConfig
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return MasterConfig::findOrFail($ids[0]);
    }
}

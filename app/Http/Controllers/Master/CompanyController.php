<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\LogAudit;
use App\Models\MasterCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Vinkla\Hashids\Facades\Hashids;

class CompanyController extends Controller
{
    public function index(): View
    {
        $companies = MasterCompany::withCount(['licenseCompanies'])
            ->latest()->paginate(20);

        return view('master.companies.index', compact('companies'));
    }

    public function create(): View
    {
        return view('master.companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'    => 'required|string|max:50|unique:master_companies,code',
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'city'    => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
            'notes'   => 'nullable|string',
        ]);

        $company = MasterCompany::create([...$data, 'created_by' => auth()->id()]);

        LogAudit::record('created', 'master_company', [
            'subject_type'  => 'MasterCompany',
            'subject_id'    => $company->id,
            'subject_label' => $company->name,
            'new'           => $data,
        ]);

        return redirect()->route('master.companies.show', Hashids::encode($company->id))
            ->with('success', 'Company created.');
    }

    public function show(string $hash): View
    {
        $company = $this->findOrFail($hash);
        $licenses = $company->licenseCompanies()->with('licenseApps')->latest()->get();

        return view('master.companies.show', compact('company', 'licenses'));
    }

    public function edit(string $hash): View
    {
        $company = $this->findOrFail($hash);
        return view('master.companies.edit', compact('company'));
    }

    public function update(Request $request, string $hash): RedirectResponse
    {
        $company = $this->findOrFail($hash);

        $data = $request->validate([
            'code'    => 'required|string|max:50|unique:master_companies,code,' . $company->id,
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'city'    => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
            'status'  => 'required|in:active,inactive,suspended',
            'notes'   => 'nullable|string',
        ]);

        $previous = $company->toArray();
        $company->update([...$data, 'updated_by' => auth()->id()]);

        LogAudit::record('updated', 'master_company', [
            'subject_type'  => 'MasterCompany',
            'subject_id'    => $company->id,
            'subject_label' => $company->name,
            'previous'      => $previous,
            'new'           => $data,
        ]);

        return redirect()->route('master.companies.show', $hash)
            ->with('success', 'Company updated.');
    }

    public function destroy(string $hash): RedirectResponse
    {
        $company = $this->findOrFail($hash);

        // Block kalau company masih punya license aktif/suspended/expired (any non-deleted)
        $licenseCount = \App\Models\LicenseCompany::where('company_id', $company->id)
            ->whereIn('status', ['active', 'suspended', 'expired'])
            ->count();

        if ($licenseCount > 0) {
            return back()->with('error',
                "Tidak dapat menghapus \"{$company->name}\" — masih ada {$licenseCount} lisensi yang terdaftar. "
                . "Hapus atau cancel semua lisensi terkait dulu sebelum hapus company."
            );
        }

        LogAudit::record('deleted', 'master_company', [
            'subject_type'  => 'MasterCompany',
            'subject_id'    => $company->id,
            'subject_label' => $company->name,
        ]);

        // Soft-delete (model pakai SoftDeletes)
        $company->delete();

        return redirect()->route('master.companies.index')
            ->with('success', "Company \"{$company->name}\" berhasil dihapus.");
    }

    private function findOrFail(string $hash): MasterCompany
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return MasterCompany::findOrFail($ids[0]);
    }
}

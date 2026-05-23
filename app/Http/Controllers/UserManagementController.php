<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Vinkla\Hashids\Facades\Hashids;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::latest()->paginate(20);
        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|in:super_admin,admin,viewer',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'],
        ]);

        LogAudit::record('created', 'user_management', [
            'subject_type'  => 'User',
            'subject_id'    => $user->id,
            'subject_label' => $user->email,
        ]);

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(string $hash): View
    {
        $user = $this->findOrFail($hash);
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, string $hash): RedirectResponse
    {
        $user = $this->findOrFail($hash);

        // Prevent non-super-admin from editing super_admin
        if ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role'     => 'required|in:super_admin,admin,viewer',
            'is_active'=> 'boolean',
        ]);

        $user->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'is_active' => $request->boolean('is_active', true),
            ...(!empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
        ]);

        LogAudit::record('updated', 'user_management', [
            'subject_type'  => 'User',
            'subject_id'    => $user->id,
            'subject_label' => $user->email,
        ]);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(string $hash): RedirectResponse
    {
        $user = $this->findOrFail($hash);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        if ($user->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        LogAudit::record('deleted', 'user_management', [
            'subject_type'  => 'User',
            'subject_id'    => $user->id,
            'subject_label' => $user->email,
        ]);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    private function findOrFail(string $hash): User
    {
        $ids = Hashids::decode($hash);
        abort_if(empty($ids), 404);
        return User::findOrFail($ids[0]);
    }
}

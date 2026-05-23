<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LucaLongo\Licensing\Models\License;

class LicenseManagementController extends Controller
{
    public function index(): View
    {
        $licenses = License::latest()->paginate(20);

        return view('licenses.index', compact('licenses'));
    }

    public function create(): View
    {
        return view('licenses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'max_usages'     => 'required|integer|min:1|max:100',
            'days'           => 'required|integer|min:1|max:3650',
            'heartbeat_tolerance' => 'nullable|integer|min:1|max:20',
            'warning_days'   => 'nullable|integer|min:1|max:30',
        ]);

        $meta = [
            'product'        => 'ermv3',
            'customer_name'  => $data['customer_name'],
            'customer_email' => $data['customer_email'],
        ];

        // Store policy overrides in meta if provided
        if (! empty($data['heartbeat_tolerance']) || ! empty($data['warning_days'])) {
            $meta['policy'] = [
                'heartbeat_tolerance' => (int) ($data['heartbeat_tolerance'] ?? config('licensing-policy.heartbeat_tolerance', 3)),
                'warning_days'        => (int) ($data['warning_days'] ?? config('licensing-policy.warning_days', 3)),
            ];
        }

        $license = License::createWithKey([
            'max_usages' => (int) $data['max_usages'],
            'expires_at' => now()->addDays((int) $data['days']),
            'meta'       => $meta,
        ]);

        $license->activate();

        return redirect()
            ->route('licenses.show', $license->uid)
            ->with('success', 'License created. Key: ' . $license->license_key);
    }

    public function show(string $uid): View
    {
        $license = License::where('uid', $uid)->firstOrFail();
        $usages  = $license->usages()->latest()->get();

        return view('licenses.show', compact('license', 'usages'));
    }
    public function suspend(string $uid): RedirectResponse
    {
        $license = License::where('uid', $uid)->firstOrFail();
        $license->suspend();

        return back()->with('success', 'License suspended.');
    }

    public function cancel(string $uid): RedirectResponse
    {
        $license = License::where('uid', $uid)->firstOrFail();
        $license->cancel();

        return back()->with('success', 'License cancelled.');
    }

    public function renew(Request $request, string $uid): RedirectResponse
    {
        $data = $request->validate([
            'days' => 'required|integer|min:1|max:3650',
        ]);

        $license = License::where('uid', $uid)->firstOrFail();
        $license->renew(now()->addDays((int) $data['days']));

        return back()->with('success', 'License renewed for ' . $data['days'] . ' days.');
    }

    public function updatePolicy(Request $request, string $uid): RedirectResponse
    {
        $data = $request->validate([
            'heartbeat_tolerance' => 'required|integer|min:1|max:20',
            'warning_days'        => 'required|integer|min:1|max:30',
        ]);

        $license = License::where('uid', $uid)->firstOrFail();

        $meta = (array) ($license->meta ?? []);
        $meta['policy'] = [
            'heartbeat_tolerance' => (int) $data['heartbeat_tolerance'],
            'warning_days'        => (int) $data['warning_days'],
        ];

        $license->update(['meta' => $meta]);

        return back()->with('success', 'Policy updated.');
    }
}

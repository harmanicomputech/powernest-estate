<?php

namespace App\Http\Controllers\Platform;

use App\Enums\EstateRole;
use App\Http\Controllers\Controller;
use App\Models\Estate;
use App\Services\Audit\AuditLogger;
use App\Services\Estate\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EstateController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function create(): View
    {
        return view('platform.estates.create');
    }

    public function store(Request $request, MembershipService $members): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:12', 'alpha_dash', 'unique:estates,code'],
            'address' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'timezone' => ['required', 'timezone'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'owner_email' => ['required', 'email', 'max:160'],
            'owner_name' => ['nullable', 'string', 'max:120'],
        ]);

        $estate = Estate::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'code' => strtoupper($data['code']),
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'timezone' => $data['timezone'],
            'contact_email' => $data['contact_email'] ?? null,
            'status' => 'active',
            'settings' => [],
        ]);

        $this->audit->record('estate.created', $estate, ['code' => $estate->code], $estate);
        $members->invite($estate, $data['owner_email'], EstateRole::EstateOwner, $request->user(), $data['owner_name'] ?? null);

        return redirect()->route('platform.estates.show', $estate)->with('success', "{$estate->name} created. Owner invitation sent.");
    }

    public function show(Estate $estate): View
    {
        return view('platform.estates.show', [
            'estate' => $estate->loadCount(['properties', 'activeMemberships']),
            'owners' => $estate->memberships()->whereIn('role', [EstateRole::EstateOwner->value, EstateRole::EstateManager->value])->with('user')->get(),
        ]);
    }

    public function status(Estate $estate, Request $request): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $estate->update(['status' => $data['status']]);
        $this->audit->record('estate.status_changed', $estate, ['status' => $data['status']], $estate);

        return back()->with('success', "{$estate->name} is now {$data['status']}.");
    }

    public function invite(Estate $estate, Request $request, MembershipService $members): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'name' => ['nullable', 'string', 'max:120'], 'role' => ['required', Rule::in([EstateRole::EstateOwner->value, EstateRole::EstateManager->value])]]);
        $members->invite($estate, $data['email'], EstateRole::from($data['role']), $request->user(), $data['name'] ?? null);

        return back()->with('success', 'Invitation sent.');
    }
}

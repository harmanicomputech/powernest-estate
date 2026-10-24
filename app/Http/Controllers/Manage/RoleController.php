<?php

namespace App\Http\Controllers\Manage;

use App\Enums\EstateRole;
use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\EstateMembership;
use App\Services\Audit\AuditLogger;
use App\Support\EstateContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lets an estate decide what each role may do. The Estate Owner role always keeps every permission
 * so the estate can never lock itself out.
 */
class RoleController extends Controller
{
    public function __construct(private EstateContext $context, private AuditLogger $audit) {}

    public function index(): View
    {
        $estate = $this->context->estate();
        $counts = EstateMembership::forEstate($estate)->where('status', MembershipStatus::Active->value)
            ->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return view('manage.roles.index', [
            'roles' => collect(EstateRole::cases())->map(fn (EstateRole $role) => [
                'role' => $role,
                'permissions' => count($estate->permissionsFor($role)),
                'members' => (int) ($counts[$role->value] ?? 0),
                'customised' => is_array(data_get($estate->settings, 'role_permissions.'.$role->value)),
            ]),
            'total' => count(Permission::cases()),
        ]);
    }

    public function edit(EstateRole $role): View
    {
        $estate = $this->context->estate();

        return view('manage.roles.edit', [
            'role' => $role,
            'granted' => collect($estate->permissionsFor($role))->map->value->all(),
            'defaults' => collect($role->permissions())->map->value->all(),
            'groups' => collect(Permission::cases())->groupBy(fn (Permission $permission) => $permission->group()),
            'locked' => $role === EstateRole::EstateOwner,
            'customised' => is_array(data_get($estate->settings, 'role_permissions.'.$role->value)),
        ]);
    }

    public function update(EstateRole $role, Request $request): RedirectResponse
    {
        abort_if($role === EstateRole::EstateOwner, 403, 'The Estate Owner role always has every permission.');
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ]);

        $estate = $this->context->estate();
        $permissions = array_values(array_unique($data['permissions'] ?? []));
        $estate->putSetting('role_permissions.'.$role->value, $permissions);
        $estate->save();

        $this->audit->record('roles.permissions_updated', $estate, ['role' => $role->value, 'permissions' => $permissions], $estate, $request->user());

        return redirect()->route('manage.roles.index')->with('success', "{$role->label()} permissions saved.");
    }

    public function reset(EstateRole $role, Request $request): RedirectResponse
    {
        $estate = $this->context->estate();
        $settings = $estate->settings ?? [];
        unset($settings['role_permissions'][$role->value]);
        $estate->settings = $settings;
        $estate->save();

        $this->audit->record('roles.permissions_reset', $estate, ['role' => $role->value], $estate, $request->user());

        return redirect()->route('manage.roles.edit', $role)->with('success', "{$role->label()} is back to its standard permissions.");
    }
}

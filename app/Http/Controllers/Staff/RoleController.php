<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreRoleRequest;
use App\Http\Requests\Staff\UpdateRoleRequest;
use App\Support\AppPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->withCount(['permissions', 'users'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->payload($role, includePermissions: true));

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'matrix' => AppPermissions::matrix(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('roles.index');
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'name' => $request->string('name')->toString(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($request->validated('permissions', []));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('staff')->causedBy($request->user())->performedOn($role)->event('created')->log('role_created');

        return redirect()->route('roles.index')->with('success', 'staff.role_created');
    }

    public function edit(Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        return redirect()->route('roles.index');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        if ($role->name !== AppPermissions::SuperAdmin) {
            $role->update(['name' => $request->string('name')->toString()]);
        }

        if ($role->name === AppPermissions::SuperAdmin) {
            $role->syncPermissions(AppPermissions::names());
        } else {
            $role->syncPermissions($request->validated('permissions', []));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('staff')->causedBy($request->user())->performedOn($role)->event('updated')->log('role_updated');

        return redirect()->route('roles.index')->with('success', 'staff.role_updated');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        $error = $this->deletionError($role);

        if ($error !== null) {
            return back()->withErrors(['delete' => $error]);
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('staff')->causedBy($request->user())->performedOn($role)->event('deleted')->log('role_deleted');

        return redirect()->route('roles.index')->with('success', 'staff.role_deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:roles,id'],
        ])['ids'];

        $deleted = 0;
        $skippedReason = null;

        foreach (Role::query()->where('guard_name', 'web')->whereIn('id', $ids)->get() as $role) {
            $error = $this->deletionError($role);

            if ($error !== null) {
                $skippedReason ??= $error;

                continue;
            }

            $role->delete();
            activity('staff')->causedBy($request->user())->performedOn($role)->event('deleted')->log('role_deleted');
            $deleted++;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($deleted === 0) {
            return back()->withErrors(['delete' => $skippedReason ?? 'common.bulk_delete_failed']);
        }

        $redirect = redirect()->route('roles.index')
            ->with('success', 'common.bulk_deleted')
            ->with('deleted_count', $deleted);

        if ($skippedReason !== null) {
            return $redirect->withErrors(['delete' => 'staff.bulk_delete_roles_partial']);
        }

        return $redirect;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Role $role, bool $includePermissions = false): array
    {
        $payload = [
            'id' => $role->id,
            'name' => $role->name,
            'is_locked' => $role->name === AppPermissions::SuperAdmin,
            'permissions_count' => $role->permissions_count ?? $role->permissions->count(),
            'users_count' => $role->users_count ?? $role->users()->count(),
        ];

        if ($includePermissions) {
            $payload['permissions'] = $role->permissions->pluck('name')->values()->all();
        }

        return $payload;
    }

    private function deletionError(Role $role): ?string
    {
        if ($role->name === AppPermissions::SuperAdmin) {
            return 'staff.cannot_delete_super_admin_role';
        }

        if ($role->users()->exists()) {
            return 'staff.role_in_use';
        }

        return null;
    }
}

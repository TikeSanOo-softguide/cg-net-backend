<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AdminStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Models\Admin;
use App\Support\AppPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $sortable = ['name', 'email', 'status', 'created_at'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }

        $staff = Admin::query()
            ->with('roles:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '' && in_array($status, array_column(AdminStatus::cases(), 'value'), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Admin $admin) => $this->payload($admin));

        return Inertia::render('Staff/Index', [
            'staff' => $staff,
            'roles' => $this->roleOptions(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('staff.index');
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('role_ids', 'password_confirmation');
        $data['password'] = Hash::make($request->string('password')->toString());

        $admin = Admin::query()->create($data);
        $admin->syncRoles($this->allowedRoles($request->user(), $request->validated('role_ids', [])));

        activity('staff')->causedBy($request->user())->performedOn($admin)->event('created')->log('staff_created');

        return redirect()->route('staff.index')->with('success', 'staff.created');
    }

    public function show(Admin $admin): Response
    {
        $admin->load('roles:id,name');

        return Inertia::render('Staff/Show', [
            'staffMember' => $this->payload($admin),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function edit(Admin $admin): RedirectResponse
    {
        return redirect()->route('staff.show', $admin);
    }

    public function update(UpdateStaffRequest $request, Admin $admin): RedirectResponse
    {
        $data = $request->safe()->except('role_ids', 'password', 'password_confirmation');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->string('password')->toString());
        }

        $admin->update($data);
        $admin->syncRoles($this->allowedRoles($request->user(), $request->validated('role_ids', []), $admin));

        activity('staff')->causedBy($request->user())->performedOn($admin)->event('updated')->log('staff_updated');

        if ($request->headers->has('X-Modal')) {
            return back()->with('success', 'staff.updated');
        }

        return redirect()->route('staff.show', $admin)->with('success', 'staff.updated');
    }

    public function destroy(Request $request, Admin $admin): RedirectResponse
    {
        $error = $this->deletionError($request, $admin);

        if ($error !== null) {
            return back()->withErrors(['delete' => $error]);
        }

        $admin->delete();

        activity('staff')->causedBy($request->user())->performedOn($admin)->event('deleted')->log('staff_deleted');

        return redirect()->route('staff.index')->with('success', 'staff.deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:admins,id'],
        ])['ids'];

        $deleted = 0;
        $skippedReason = null;

        foreach (Admin::query()->whereIn('id', $ids)->get() as $admin) {
            $error = $this->deletionError($request, $admin);

            if ($error !== null) {
                $skippedReason ??= $error;

                continue;
            }

            $admin->delete();
            activity('staff')->causedBy($request->user())->performedOn($admin)->event('deleted')->log('staff_deleted');
            $deleted++;
        }

        if ($deleted === 0) {
            return back()->withErrors(['delete' => $skippedReason ?? 'common.bulk_delete_failed']);
        }

        $redirect = redirect()->route('staff.index')
            ->with('success', 'common.bulk_deleted')
            ->with('deleted_count', $deleted);

        if ($skippedReason !== null) {
            return $redirect->withErrors(['delete' => 'staff.bulk_delete_partial']);
        }

        return $redirect;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'status' => $admin->status->value,
            'roles' => $admin->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values()->all(),
            'role_ids' => $admin->roles->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'created_at' => $admin->created_at?->toDateString(),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function roleOptions(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $roleIds
     * @return list<Role>
     */
    private function allowedRoles(mixed $actor, array $roleIds, ?Admin $target = null): array
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $roleIds)
            ->get();

        $actorIsSuper = $actor instanceof Admin && $actor->hasRole(AppPermissions::SuperAdmin);

        if (! $actorIsSuper) {
            $roles = $roles->reject(fn (Role $role) => $role->name === AppPermissions::SuperAdmin);

            if ($target?->hasRole(AppPermissions::SuperAdmin)) {
                $super = Role::query()->where('name', AppPermissions::SuperAdmin)->where('guard_name', 'web')->first();

                if ($super) {
                    $roles->push($super);
                }
            }
        }

        return $roles->unique('id')->values()->all();
    }

    private function superAdminCount(): int
    {
        return Admin::query()->role(AppPermissions::SuperAdmin)->count();
    }

    private function deletionError(Request $request, Admin $admin): ?string
    {
        if ($request->user()?->is($admin)) {
            return 'staff.cannot_delete_self';
        }

        if ($admin->hasRole(AppPermissions::SuperAdmin) && $this->superAdminCount() <= 1) {
            return 'staff.cannot_delete_last_super_admin';
        }

        return null;
    }
}

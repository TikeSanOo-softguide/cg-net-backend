<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AppPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RolePermissionSeeder::sync();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guests_cannot_view_staff_or_roles(): void
    {
        $this->get('/staff')->assertRedirect('/login');
        $this->get('/roles')->assertRedirect('/login');
    }

    public function test_admins_without_permission_are_forbidden(): void
    {
        $this->autoGrantPermissions = false;
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/staff')
            ->assertForbidden();

        $this->actingAs($admin, 'web')
            ->get('/roles')
            ->assertForbidden();
    }

    public function test_admins_can_list_staff_and_assign_multiple_roles(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $staffRole = Role::query()->where('name', AppPermissions::StaffOfficer)->firstOrFail();
        $supportRole = Role::query()->where('name', AppPermissions::SupportAgent)->firstOrFail();

        $this->actingAs($actor, 'web')
            ->get('/staff')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Staff/Index'));

        $this->actingAs($actor, 'web')
            ->post('/staff', [
                'username' => 'ops',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 'active',
                'role_ids' => [$staffRole->id, $supportRole->id],
            ])
            ->assertRedirect('/staff');

        $created = Admin::query()->where('username', 'ops')->firstOrFail();
        $this->assertTrue($created->hasRole(AppPermissions::StaffOfficer));
        $this->assertTrue($created->hasRole(AppPermissions::SupportAgent));

        $this->actingAs($actor, 'web')
            ->get('/staff/'.$created->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Show')
                ->where('staffMember.username', 'ops')
                ->has('staffMember.roles', 2));
    }

    public function test_staff_create_validates_required_fields(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $this->actingAs($actor, 'web')
            ->from('/staff/create')
            ->post('/staff', [
                'username' => 'ab',
                'password' => 'short',
                'password_confirmation' => 'mismatch',
                'status' => '',
                'role_ids' => [],
            ])
            ->assertRedirect('/staff/create')
            ->assertSessionHasErrors(['username', 'password', 'status', 'role_ids']);
    }

    public function test_admins_can_create_update_and_delete_a_role_with_permission_matrix(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $this->actingAs($actor, 'web')
            ->get('/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Roles/Index')->has('roles'));

        $this->actingAs($actor, 'web')
            ->post('/roles', [
                'name' => 'Billing Desk',
                'permissions' => ['dashboard.view', 'billing.view', 'billing.update'],
            ])
            ->assertRedirect('/roles');

        $role = Role::query()->where('name', 'Billing Desk')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('billing.view', 'web'));
        $this->assertFalse($role->hasPermissionTo('customers.view', 'web'));

        $this->actingAs($actor, 'web')
            ->put('/roles/'.$role->id, [
                'name' => 'Billing Desk',
                'permissions' => ['dashboard.view', 'billing.view'],
            ])
            ->assertRedirect('/roles');

        $this->assertFalse($role->fresh()->hasPermissionTo('billing.update', 'web'));

        $this->actingAs($actor, 'web')
            ->delete('/roles/'.$role->id)
            ->assertRedirect('/roles');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $super = Role::query()->where('name', AppPermissions::SuperAdmin)->firstOrFail();

        $this->actingAs($actor, 'web')
            ->from('/roles')
            ->delete('/roles/'.$super->id)
            ->assertRedirect('/roles')
            ->assertSessionHasErrors('delete');
    }

    public function test_staff_cannot_access_cms_without_cms_view(): void
    {
        $this->autoGrantPermissions = false;
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['dashboard.view', 'staff.view']);

        $this->actingAs($admin, 'web')
            ->get('/cms/promotions')
            ->assertForbidden();

        $this->actingAs($admin, 'web')
            ->get('/staff')
            ->assertOk();
    }

    public function test_admins_can_bulk_delete_staff_skipping_self(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $first = Admin::factory()->create();
        $second = Admin::factory()->create();

        $this->actingAs($actor, 'web')
            ->from('/staff')
            ->delete('/staff/bulk-destroy', ['ids' => [$actor->id, $first->id, $second->id]])
            ->assertRedirect('/staff')
            ->assertSessionHas('success', 'common.bulk_deleted')
            ->assertSessionHasErrors(['delete' => 'staff.bulk_delete_partial']);

        $this->assertNotSoftDeleted($actor);
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    }

    public function test_bulk_delete_staff_fails_when_only_self_is_selected(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);

        $this->actingAs($actor, 'web')
            ->from('/staff')
            ->delete('/staff/bulk-destroy', ['ids' => [$actor->id]])
            ->assertRedirect('/staff')
            ->assertSessionHasErrors(['delete' => 'staff.cannot_delete_self']);

        $this->assertNotSoftDeleted($actor);
    }

    public function test_admins_can_bulk_delete_roles_skipping_super_admin(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $super = Role::query()->where('name', AppPermissions::SuperAdmin)->firstOrFail();

        $first = Role::query()->create(['name' => 'Night Desk', 'guard_name' => 'web']);
        $second = Role::query()->create(['name' => 'Field Ops', 'guard_name' => 'web']);

        $this->actingAs($actor, 'web')
            ->from('/roles')
            ->delete('/roles/bulk-destroy', ['ids' => [$super->id, $first->id, $second->id]])
            ->assertRedirect('/roles')
            ->assertSessionHas('success', 'common.bulk_deleted')
            ->assertSessionHasErrors(['delete' => 'staff.bulk_delete_roles_partial']);

        $this->assertDatabaseHas('roles', ['id' => $super->id]);
        $this->assertDatabaseMissing('roles', ['id' => $first->id]);
        $this->assertDatabaseMissing('roles', ['id' => $second->id]);
    }

    public function test_bulk_delete_roles_fails_when_only_locked_or_in_use_roles_are_selected(): void
    {
        $actor = Admin::factory()->create();
        $actor->assignRole(AppPermissions::SuperAdmin);
        $super = Role::query()->where('name', AppPermissions::SuperAdmin)->firstOrFail();

        $this->actingAs($actor, 'web')
            ->from('/roles')
            ->delete('/roles/bulk-destroy', ['ids' => [$super->id]])
            ->assertRedirect('/roles')
            ->assertSessionHasErrors(['delete' => 'staff.cannot_delete_super_admin_role']);

        $this->assertDatabaseHas('roles', ['id' => $super->id]);
    }
}

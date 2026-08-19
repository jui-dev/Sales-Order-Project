<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the authorization structure works while only the admin role exists,
 * so the roles added later slot in as data rather than as new plumbing.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_passes_every_gate(): void
    {
        $admin = User::find(1);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->can('supplies.manage'));
        $this->assertTrue($admin->can('journal-entries.post'));
        $this->assertTrue($admin->can('users.manage'));

        // Gate::before short-circuits for admins, so even an ability nobody
        // has defined resolves true rather than falling through to a miss.
        $this->assertTrue($admin->can('some.future.permission'));
    }

    public function test_user_without_roles_is_denied(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->can('products.view'));
        $this->assertFalse($user->can('users.manage'));
    }

    public function test_user_with_roles_gets_only_the_granted_permissions(): void
    {
        $role = Role::create(['name' => 'stock-clerk', 'label' => 'Stock Clerk']);
        $role->permissions()->sync(
            Permission::whereIn('name', ['products.view', 'stock-management.view'])->pluck('id')
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->assertTrue($user->can('products.view'));
        $this->assertTrue($user->can('stock-management.view'));
        $this->assertFalse($user->can('products.manage'));
        $this->assertFalse($user->can('journal-entries.post'));
    }

    public function test_permission_middleware_blocks_a_user_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/users')->assertForbidden();
    }

    public function test_permission_middleware_allows_a_user_holding_it(): void
    {
        $role = Role::create(['name' => 'auditor', 'label' => 'Auditor']);
        $role->permissions()->sync(Permission::where('name', 'users.view')->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user);

        // users.view gets them the list...
        $this->get('/users')->assertOk();
        // ...but not the screens gated on users.manage.
        $this->get('/users/create')->assertForbidden();
    }

    public function test_dashboard_is_gated_like_every_other_module(): void
    {
        // The landing page and its AJAX feeds carry revenue, top sellers and
        // stock levels, so being signed in is not on its own enough.
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertForbidden();
        $this->get('/dashboard/stats')->assertForbidden();
        $this->get('/dashboard/top-products')->assertForbidden();
    }

    public function test_permission_cache_is_reset_when_roles_change(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->can('products.view'));

        $role = Role::create(['name' => 'viewer', 'label' => 'Viewer']);
        $role->permissions()->sync(Permission::where('name', 'products.view')->pluck('id'));
        $user->roles()->attach($role);

        // Still stale until told otherwise - that is what makes the reset necessary.
        $this->assertFalse($user->can('products.view'));

        $user->forgetCachedPermissions();

        $this->assertTrue($user->can('products.view'));
    }
}

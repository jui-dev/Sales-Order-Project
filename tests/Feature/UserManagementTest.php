<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $this->get('/users')
            ->assertOk()
            ->assertViewIs('users.index')
            ->assertSee('Administrator');
    }

    public function test_admin_can_create_a_user_with_roles(): void
    {
        $role = Role::where('name', Role::ADMIN)->first();

        $this->post('/users', [
            'name' => 'Warehouse Lead',
            'email' => 'lead@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'roles' => [$role->id],
        ])->assertRedirect('/users');

        $created = User::where('email', 'lead@example.com')->first();

        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('secret-password', $created->password));
        $this->assertTrue($created->isAdmin());
    }

    public function test_creating_a_user_requires_a_confirmed_password(): void
    {
        $this->post('/users', [
            'name' => 'Mismatch',
            'email' => 'mismatch@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.com']);
    }

    public function test_editing_without_a_password_leaves_it_unchanged(): void
    {
        $user = User::factory()->create(['password' => 'original-password']);

        $this->put("/users/{$user->id}", [
            'name' => 'Renamed',
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/users');

        $user->refresh();

        $this->assertSame('Renamed', $user->name);
        $this->assertTrue(Hash::check('original-password', $user->password));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $this->delete('/users/1')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => 1]);
    }

    public function test_the_last_admin_cannot_have_the_role_removed(): void
    {
        // User #1 is the only admin, so dropping the role would leave nobody
        // able to administer the system and no way back in through the UI.
        $this->put('/users/1', [
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'roles' => [],
        ])->assertSessionHasErrors('roles');

        $this->assertTrue(User::find(1)->fresh()->isAdmin());
    }

    public function test_an_admin_role_can_be_removed_once_another_admin_exists(): void
    {
        $adminRole = Role::where('name', Role::ADMIN)->first();

        $second = User::factory()->create();
        $second->roles()->attach($adminRole);

        $this->put("/users/{$second->id}", [
            'name' => $second->name,
            'email' => $second->email,
            'roles' => [],
        ])->assertRedirect('/users');

        $this->assertFalse($second->fresh()->isAdmin());
    }

    public function test_admin_can_delete_another_user(): void
    {
        $victim = User::factory()->create();

        $this->delete("/users/{$victim->id}")->assertRedirect('/users');

        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    }

    public function test_a_user_without_the_users_permission_cannot_manage_users(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/users')->assertForbidden();
        $this->post('/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }
}

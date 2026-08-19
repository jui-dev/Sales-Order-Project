<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tests\TestCase logs in as the seeded admin for every test, which is what
     * the rest of the suite needs. These tests are about the guest experience,
     * so they drop that first.
     */
    private function asGuest(): void
    {
        Auth::logout();
        $this->app['auth']->forgetGuards();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->asGuest();

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/products')->assertRedirect(route('login'));
        $this->get('/supplies')->assertRedirect(route('login'));
        $this->get('/journal-entries')->assertRedirect(route('login'));
    }

    public function test_guest_cannot_reach_api_routes(): void
    {
        $this->asGuest();

        // These are called by fetch() from Blade pages. Before the session guard
        // was attached they answered anyone who asked.
        $this->getJson('/api/products')->assertUnauthorized();
        $this->getJson('/api/stock-management')->assertUnauthorized();
    }

    public function test_login_page_is_reachable_by_a_guest(): void
    {
        $this->asGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login');
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $this->asGuest();

        User::factory()->create([
            'email' => 'someone@example.com',
            'password' => 'correct-horse',
        ]);

        $this->post(route('login'), [
            'email' => 'someone@example.com',
            'password' => 'correct-horse',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_login_fails_with_an_invalid_password(): void
    {
        $this->asGuest();

        User::factory()->create([
            'email' => 'someone@example.com',
            'password' => 'correct-horse',
        ]);

        $this->post(route('login'), [
            'email' => 'someone@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_signed_in_user_reaches_the_dashboard(): void
    {
        $this->get('/')->assertOk();
    }
}

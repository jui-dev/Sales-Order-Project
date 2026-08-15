<?php

namespace Tests\Feature;

use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The index fallback used to chain ->with('error', ...) onto the View, which
 * binds a view variable rather than flashing to the session. Both the layout
 * toast and the page's inline alert read session('error'), so the message was
 * silently dropped whenever loading orders failed.
 */
class OrderIndexErrorMessageTest extends TestCase
{
    use RefreshDatabase;

    private function failOrderListing(): void
    {
        $service = \Mockery::mock(OrderService::class);
        $service->shouldReceive('list')->andThrow(new \RuntimeException('boom'));
        $service->shouldReceive('getFilterOptions')->andReturn([]);
        $service->shouldReceive('getSortOptions')->andReturn([]);

        $this->app->instance(OrderService::class, $service);
    }

    public function test_it_shows_the_error_message_when_orders_cannot_be_loaded(): void
    {
        $this->failOrderListing();

        $response = $this->get('/orders');

        $response->assertOk();
        $response->assertSee('Unable to load orders. Please try again later.');
    }

    public function test_the_error_does_not_leak_into_the_next_request(): void
    {
        $this->failOrderListing();

        $this->get('/orders')->assertOk();

        // session()->now() must not persist the message the way flash() would,
        // or an unrelated follow-up page would show a stale error.
        $this->assertNull(session('error'));
    }
}

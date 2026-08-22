<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RoutePermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Nothing reaches the application without an authority behind it.
 *
 * The permission catalogue described the whole application while only nine of
 * its permissions were ever checked; everything else was gated by an `@can` in
 * the sidebar, which hides a link and stops nobody who types the URL. These
 * tests are what stop that coming back: the first proves every route is
 * covered, so a new one that is neither in the table nor gated on itself fails
 * here rather than shipping open.
 */
class RoutePermissionCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that answer before auth and so have no permission to carry. */
    private const OUTSIDE_AUTH = ['login', 'logout', 'up'];

    public function test_every_route_is_covered_by_a_permission(): void
    {
        $open = [];

        foreach (RouteFacade::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            // Anything the auth middleware does not protect is a sign-in page
            // or the health check, and is listed above rather than gated.
            if (! in_array('auth', $middleware, true)) {
                $this->assertContains(
                    ltrim((string) $route->uri(), '/'),
                    self::OUTSIDE_AUTH,
                    sprintf('%s is reachable without signing in.', $route->uri()),
                );

                continue;
            }

            if (RoutePermissions::declaresItsOwn($route)) {
                continue;
            }

            if (RoutePermissions::isUnrestricted($route)) {
                continue;
            }

            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD') ?? 'GET';

            if (RoutePermissions::for($route, $method) === []) {
                $open[] = sprintf('%s %s', $method, $route->uri());
            }
        }

        $this->assertSame(
            [],
            $open,
            "These routes are behind auth but no permission:\n  " . implode("\n  ", $open)
                . "\n\nAdd the URI prefix to App\\Support\\RoutePermissions::MODULES,"
                . ' or list the route in OVERRIDES or UNRESTRICTED_*.',
        );
    }

    public function test_every_permission_the_table_names_actually_exists(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $known = Permission::pluck('name')->all();
        $missing = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD') ?? 'GET';

            foreach (RoutePermissions::for($route, $method) as $permission) {
                if (! in_array($permission, $known, true)) {
                    $missing[$permission] = $permission;
                }
            }
        }

        $this->assertSame(
            [],
            array_values($missing),
            'The route table asks for permissions the catalogue does not define: '
                . implode(', ', $missing),
        );
    }

    /**
     * A user holding nothing is refused everywhere that matters.
     *
     * One case per module rather than per route: the table is what decides,
     * and the test above proves the table covers everything.
     *
     * @dataProvider gatedRoutes
     */
    public function test_a_user_without_the_permission_is_refused(string $routeName, string $method, array $parameters): void
    {
        $user = $this->userHolding();

        $response = $this->actingAs($user)->call($method, route($routeName, $parameters));

        $this->assertSame(
            403,
            $response->getStatusCode(),
            sprintf('%s %s let a user with no permissions through.', $method, $routeName),
        );
    }

    /**
     * Holding the module's permission opens the same route.
     *
     * Without this the test above would pass just as well if the middleware
     * refused everybody.
     *
     * @dataProvider gatedRoutes
     */
    public function test_a_user_holding_the_permission_is_allowed(string $routeName, string $method, array $parameters, string $permission): void
    {
        $user = $this->userHolding($permission);

        $response = $this->actingAs($user)->call($method, route($routeName, $parameters));

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            sprintf('%s %s refused a user holding %s.', $method, $routeName, $permission),
        );
    }

    /**
     * The state-changing routes that matter most, one per module.
     *
     * @return array<string,array{0:string,1:string,2:array<string,mixed>,3:string}>
     */
    public static function gatedRoutes(): array
    {
        return [
            'post a journal entry' => ['journal-entries.post', 'PATCH', ['journalEntry' => 1], 'journal-entries.post'],
            'approve a journal entry' => ['journal-entries.approve', 'PATCH', ['journalEntry' => 1], 'journal-entries.approve'],
            'create a journal entry' => ['journal-entries.store', 'POST', [], 'journal-entries.manage'],
            'read the journal' => ['journal-entries.index', 'GET', [], 'journal-entries.view'],
            'pay a supplier bill' => ['supplier-bills.pay', 'POST', ['supplierBill' => 1], 'supplier-bills.manage'],
            'post a supplier bill' => ['supplier-bills.post', 'POST', ['supplierBill' => 1], 'supplier-bills.manage'],
            'post a GRN' => ['grns.update-status', 'PATCH', ['grn' => 1], 'grns.manage'],
            'read GRNs' => ['grns.index', 'GET', [], 'grns.view'],
            'delete a supply' => ['supplies.destroy', 'DELETE', ['supply' => 1], 'supplies.manage'],
            'read supplies' => ['supplies.index', 'GET', [], 'supplies.view'],
            'post a credit note' => ['credit-notes.post', 'POST', ['creditNote' => 1], 'credit-notes.manage'],
            'post a debit note' => ['debit-notes.post', 'POST', ['debitNote' => 1], 'debit-notes.manage'],
            'complete a return' => ['returns.complete', 'POST', ['return' => 1], 'returns.manage'],
            'pay an invoice' => ['invoices.pay', 'POST', ['invoice' => 1], 'invoices.manage'],
            'read invoices' => ['invoices.index', 'GET', [], 'invoices.view'],
            'delete an order' => ['orders.destroy', 'DELETE', ['id' => 1], 'orders.manage'],
            'read reports' => ['reports.trial-balance', 'GET', [], 'reports.view'],
            'read the audit trail' => ['audit-logs.index', 'GET', [], 'audit-logs.view'],
            'read accounting health' => ['accounting.health', 'GET', [], 'accounting.view'],
            'read stock management' => ['stock-management.index', 'GET', [], 'stock-management.view'],
        ];
    }

    /**
     * A signed-in user holding exactly the named permission and nothing else.
     *
     * TestCase signs in as an admin for every test, and an admin clears every
     * gate, so these have to build their own user.
     */
    private function userHolding(?string $permission = null): User
    {
        $this->seed(RolePermissionSeeder::class);

        $role = Role::firstOrCreate(
            ['name' => 'gate-subject-' . ($permission ?? 'none')],
            ['label' => 'Gate subject'],
        );

        $role->permissions()->sync(
            $permission === null
                ? []
                : Permission::where('name', $permission)->pluck('id')->all(),
        );

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->forgetCachedPermissions();

        return $user;
    }
}

<?php

namespace App\Support;

use Illuminate\Routing\Route;

/**
 * Which permission every route requires.
 *
 * The permission catalogue in RolePermissionSeeder has always described the
 * whole application, but only a handful of routes ever checked it: the rest
 * were gated by an `@can` in the sidebar, which hides a link and nothing more.
 * Any signed-in user could post a journal entry, pay a supplier bill or delete
 * a supply by typing the URL.
 *
 * Gating two hundred routes by hand would have meant two hundred places for the
 * next one to be forgotten. This is one table instead, enforced by
 * EnforceRoutePermissions and checked for completeness by
 * RoutePermissionCoverageTest - so a new route that is neither listed here nor
 * gated on itself fails the build rather than shipping open.
 *
 * The rule, unless OVERRIDES says otherwise:
 *
 *   GET/HEAD       ->  "<module>.view" OR "<module>.manage"
 *   anything else  ->  "<module>.manage"
 *
 * A read is satisfied by either permission because a manage holder should not
 * have to be granted view as well - the same reasoning EnsurePermission
 * documents for its alternatives.
 */
final class RoutePermissions
{
    /**
     * URI prefix => the permission family that guards it.
     *
     * Matched longest-prefix-first, so "warehouse/receiving" wins over
     * "warehouse". An "api/" prefix is stripped before matching: the API is
     * session-authenticated and would otherwise be a way around every gate
     * below.
     *
     * @var array<string,string>
     */
    private const MODULES = [
        'products' => 'products',
        'customers' => 'customers',
        'vendors' => 'vendors',

        'orders' => 'orders',
        'fulfillment-locations' => 'orders',

        'supplies' => 'supplies',
        'grns' => 'grns',
        'warehouse/receiving' => 'grns',
        'supplier-bills' => 'supplier-bills',
        'supplier-bill-payments' => 'supplier-bill-payments',

        'picking' => 'picking',
        'picking-lists' => 'picking',
        'customer-picking' => 'picking',
        'warehouse-to-customer-picking' => 'picking',
        'retailer-to-customer-picking' => 'picking',
        'transaction-flow' => 'picking',

        'stock-transfers' => 'stock-transfers',
        'stock-management' => 'stock-management',
        'stock-info' => 'stock-management',
        'stock-locations' => 'stock-locations',
        'warehouses' => 'stock-locations',

        'returns' => 'returns',
        'credit-notes' => 'credit-notes',
        'debit-notes' => 'debit-notes',

        'invoices' => 'invoices',
        'payments' => 'payments',

        'journal-entries' => 'journal-entries',
        'audit-logs' => 'audit-logs',
        'accounting' => 'accounting',
        'chart-of-accounts' => 'accounting',

        'reports' => 'reports',
    ];

    /**
     * Families the catalogue defines no ".manage" for.
     *
     * Reading is the only thing anyone does to a report or an audit log, so
     * ".view" answers every verb rather than the rule demanding a permission
     * that does not exist and locking the page for everybody.
     *
     * @var array<int,string>
     */
    private const VIEW_ONLY = ['reports', 'audit-logs'];

    /**
     * Route name => the permissions that satisfy it, overriding the rule.
     *
     * Two kinds of exception live here: the ledger's separation of duties,
     * which is the whole reason journal-entries.approve and .post were split
     * out of .manage; and the two note-raising routes, which hang off a
     * returns/ URI but are an authority over notes rather than over returns.
     *
     * @var array<string,array<int,string>>
     */
    private const OVERRIDES = [
        'journal-entries.approve' => ['journal-entries.approve'],
        'journal-entries.reject' => ['journal-entries.approve'],
        'journal-entries.post' => ['journal-entries.post'],

        'credit-notes.generate-for-return' => ['credit-notes.manage'],
        'debit-notes.generate-for-return' => ['debit-notes.manage'],

        // Listed on the supplies side, but it is purchase order data on the
        // page, so it is purchase order access that opens it. SupplyController
        // makes the same call for itself in canSeeOrders().
        'supplies.purchase-orders' => ['purchase-orders.view', 'purchase-orders.manage'],
    ];

    /**
     * Reachable by anyone the auth middleware has already let through.
     *
     * Signing out is not an authority, and the health endpoint carries no
     * business data.
     *
     * @var array<int,string>
     */
    private const UNRESTRICTED_NAMES = ['logout'];

    /** @var array<int,string> */
    private const UNRESTRICTED_URIS = ['api/health', 'up'];

    /**
     * The permissions that let this request through; empty means no gate.
     *
     * @return array<int,string>
     */
    public static function for(Route $route, string $method): array
    {
        if (self::isUnrestricted($route)) {
            return [];
        }

        $name = $route->getName();

        if ($name !== null && isset(self::OVERRIDES[$name])) {
            return self::OVERRIDES[$name];
        }

        $module = self::moduleFor($route);

        if ($module === null) {
            return [];
        }

        if (in_array($module, self::VIEW_ONLY, true)) {
            return [$module . '.view'];
        }

        return in_array(strtoupper($method), ['GET', 'HEAD'], true)
            ? [$module . '.view', $module . '.manage']
            : [$module . '.manage'];
    }

    /**
     * The permission family this route belongs to, by longest matching prefix.
     */
    public static function moduleFor(Route $route): ?string
    {
        $uri = ltrim((string) $route->uri(), '/');

        if (str_starts_with($uri, 'api/')) {
            $uri = substr($uri, 4);
        }

        $best = null;

        foreach (array_keys(self::MODULES) as $prefix) {
            if ($uri !== $prefix && ! str_starts_with($uri, $prefix . '/')) {
                continue;
            }

            if ($best === null || strlen($prefix) > strlen($best)) {
                $best = $prefix;
            }
        }

        return $best === null ? null : self::MODULES[$best];
    }

    public static function isUnrestricted(Route $route): bool
    {
        return in_array($route->getName(), self::UNRESTRICTED_NAMES, true)
            || in_array(ltrim((string) $route->uri(), '/'), self::UNRESTRICTED_URIS, true);
    }

    /**
     * Whether the route carries a permission: middleware of its own.
     *
     * The modules that were gated before this table existed keep their inline
     * middleware: it is more specific than any rule could be, and it is what
     * the existing tests pin. This table covers everything else, and the
     * coverage test proves the two together leave no route open.
     */
    public static function declaresItsOwn(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                return true;
            }
        }

        return false;
    }
}

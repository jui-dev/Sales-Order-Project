<?php

namespace App\Support\Nav;

use Illuminate\Http\Request;

/**
 * The sidebar's structure as data.
 *
 * The sidebar itself is still hand-written Blade - this class does not render
 * it. What it provides is a stable key per menu item, so that a record created
 * somewhere in the application can say "I belong on that menu item", and a page
 * visit can say "the badge on that menu item is spent".
 *
 * Keys are dot-separated: the part before the dot is the collapsible group the
 * item sits in, which is also a key in its own right (its count is the sum of
 * its children).
 *
 * Each entry declares:
 *   label      - how the item is named in the sidebar, reused in the panel
 *   parent     - the group key, or null for a top-level item
 *   landing    - the exact route the item's sidebar link points at. Opening it
 *                clears the item's badge; opening one record inside it does
 *                not, because the badge counts what is in the list, not what
 *                the reader happens to have open. Exact names, never wildcards
 *                - `grns.*` would match `grns.show`, and the redirect after
 *                completing a supply lands there, so the badge was being spent
 *                by the very page whose banner had just pointed at it.
 *   query      - optional query-string pairs that must also match before a
 *                visit counts. The Returns submenu is one index filtered by
 *                ?type=, so without this the three type rows would clear
 *                together - the same distinction the sidebar itself draws.
 *   permission - the ability the sidebar gates the item with, so a badge can
 *                never point at a screen the reader cannot open
 */
final class NavCatalog
{
    /** @var array<string, array{label: string, parent: string|null, landing: array<int, string>, query?: array<string, string>, permission: string}> */
    private const ITEMS = [
        // --- Catalog -------------------------------------------------------
        // What we sell and what it costs. Products and their prices are
        // separate screens because they are separate decisions: a product is
        // described once, while its prices vary by buyer and change over time.
        'catalog' => [
            'label' => 'Catalog',
            'parent' => null,
            'landing' => [],
            'permission' => 'products.view',
        ],
        'catalog.products' => [
            'label' => 'Products',
            'parent' => 'catalog',
            'landing' => ['products.index'],
            'permission' => 'products.view',
        ],
        'catalog.product-pricing' => [
            'label' => 'Product Pricing',
            'parent' => 'catalog',
            'landing' => ['product-pricing.index'],
            'permission' => 'product-pricing.view',
        ],

        // --- Master data ---------------------------------------------------
        'vendors' => [
            'label' => 'Vendors',
            'parent' => null,
            'landing' => ['vendors.index'],
            'permission' => 'vendors.view',
        ],
        'customers' => [
            'label' => 'Customers',
            'parent' => null,
            'landing' => ['customers.index'],
            'permission' => 'customers.view',
        ],

        // --- Procurement ---------------------------------------------------
        'procurement' => [
            'label' => 'Procurement',
            'parent' => null,
            'landing' => [],
            'permission' => 'purchase-orders.view',
        ],
        'procurement.purchase-orders' => [
            'label' => 'Purchase Orders',
            'parent' => 'procurement',
            'landing' => ['purchase-orders.index'],
            'permission' => 'purchase-orders.view',
        ],
        'procurement.supplies' => [
            'label' => 'Supplies',
            'parent' => 'procurement',
            'landing' => ['supplies.index'],
            'permission' => 'supplies.view',
        ],
        'procurement.grns' => [
            'label' => 'Good Receipt Notes (GRNs)',
            'parent' => 'procurement',
            'landing' => ['grns.index'],
            'permission' => 'grns.view',
        ],
        'procurement.supplier-bills' => [
            'label' => 'Supplier Bills',
            'parent' => 'procurement',
            'landing' => ['supplier-bills.index'],
            'permission' => 'supplier-bills.view',
        ],
        'procurement.supplier-bill-payments' => [
            'label' => 'Supplier Bills Payment',
            'parent' => 'procurement',
            'landing' => ['supplier-bill-payments.index'],
            'permission' => 'supplier-bill-payments.view',
        ],

        // --- Picking & Transfers -------------------------------------------
        'picking' => [
            'label' => 'Picking & Transfers',
            'parent' => null,
            'landing' => [],
            'permission' => 'picking.view',
        ],
        'picking.warehouse-to-retailers' => [
            'label' => 'Warehouse → Retailers',
            'parent' => 'picking',
            'landing' => ['stock-transfers.warehouse-to-retailer'],
            'permission' => 'stock-transfers.view',
        ],
        'picking.warehouse-to-customers' => [
            'label' => 'Warehouse → Customers',
            'parent' => 'picking',
            'landing' => ['warehouse-to-customer-picking.index'],
            'permission' => 'picking.view',
        ],
        'picking.retailer-to-customers' => [
            'label' => 'Retailers → Customers',
            'parent' => 'picking',
            'landing' => ['retailer-to-customer-picking.index'],
            'permission' => 'picking.view',
        ],
        'picking.all' => [
            'label' => 'All Picking Lists',
            'parent' => 'picking',
            'landing' => ['picking-lists.index'],
            'permission' => 'picking.view',
        ],

        // --- Returns ---------------------------------------------------------
        'returns' => [
            'label' => 'Returns',
            'parent' => null,
            'landing' => [],
            'permission' => 'returns.view',
        ],
        'returns.all' => [
            'label' => 'All Returns',
            'parent' => 'returns',
            'landing' => ['returns.index'],
            'permission' => 'returns.view',
        ],
        'returns.credit-notes' => [
            'label' => 'Credit Notes',
            'parent' => 'returns',
            'landing' => ['credit-notes.index'],
            'permission' => 'credit-notes.view',
        ],
        'returns.debit-notes' => [
            'label' => 'Debit Notes',
            'parent' => 'returns',
            'landing' => ['debit-notes.index'],
            'permission' => 'debit-notes.view',
        ],
        'returns.customer_return' => [
            'label' => 'Customer Returns',
            'parent' => 'returns',
            'landing' => ['returns.index'],
            'query' => ['type' => 'customer_return'],
            'permission' => 'returns.view',
        ],
        'returns.vendor_return' => [
            'label' => 'Vendor Returns',
            'parent' => 'returns',
            'landing' => ['returns.index'],
            'query' => ['type' => 'vendor_return'],
            'permission' => 'returns.view',
        ],
        'returns.retailer_return' => [
            'label' => 'Retailer Returns',
            'parent' => 'returns',
            'landing' => ['returns.index'],
            'query' => ['type' => 'retailer_return'],
            'permission' => 'returns.view',
        ],

        // --- Stock -------------------------------------------------------------
        'stock' => [
            'label' => 'Stock Management',
            'parent' => null,
            'landing' => [],
            'permission' => 'stock-management.view',
        ],
        'stock.stock-management' => [
            'label' => 'Stock Management',
            'parent' => 'stock',
            'landing' => ['stock-management.index'],
            'permission' => 'stock-management.view',
        ],
        'stock.stock-locations' => [
            'label' => 'Stock Locations',
            'parent' => 'stock',
            'landing' => ['stock-locations.index'],
            'permission' => 'stock-locations.view',
        ],
        'stock.transaction-flow' => [
            'label' => 'Transaction Flow',
            'parent' => 'stock',
            'landing' => ['picking.transaction-flow'],
            'permission' => 'stock-management.view',
        ],

        // --- Sales -------------------------------------------------------------
        'sales' => [
            'label' => 'Sales Order',
            'parent' => null,
            'landing' => [],
            'permission' => 'orders.view',
        ],
        'sales.orders' => [
            'label' => 'Orders',
            'parent' => 'sales',
            'landing' => ['orders.index'],
            'permission' => 'orders.view',
        ],
        'sales.invoices' => [
            'label' => 'Invoices',
            'parent' => 'sales',
            'landing' => ['invoices.index'],
            'permission' => 'invoices.view',
        ],
        'sales.payments' => [
            'label' => 'Invoice Payments',
            'parent' => 'sales',
            'landing' => ['payments.index'],
            'permission' => 'payments.view',
        ],

        // --- Accounting ---------------------------------------------------------
        'accounting' => [
            'label' => 'Accounting',
            'parent' => null,
            'landing' => [],
            'permission' => 'accounting.view',
        ],
        'accounting.chart-of-accounts' => [
            'label' => 'Chart of Accounts',
            'parent' => 'accounting',
            'landing' => ['accounting.chart-of-accounts'],
            'permission' => 'accounting.view',
        ],
        'accounting.health' => [
            'label' => 'Accounting Health',
            'parent' => 'accounting',
            'landing' => ['accounting.health'],
            'permission' => 'accounting.view',
        ],
        'accounting.journal-entries' => [
            'label' => 'Journal Entries',
            'parent' => 'accounting',
            'landing' => ['journal-entries.index'],
            'permission' => 'journal-entries.view',
        ],
        'accounting.audit-logs' => [
            'label' => 'Audit Trail',
            'parent' => 'accounting',
            'landing' => ['audit-logs.index'],
            'permission' => 'audit-logs.view',
        ],

        // --- Administration -------------------------------------------------------
        'admin' => [
            'label' => 'Administration',
            'parent' => null,
            'landing' => [],
            'permission' => 'users.view',
        ],
        'admin.users' => [
            'label' => 'Users',
            'parent' => 'admin',
            'landing' => ['users.index'],
            'permission' => 'users.view',
        ],
    ];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return self::ITEMS;
    }

    public static function has(string $key): bool
    {
        return isset(self::ITEMS[$key]);
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key): ?array
    {
        return self::ITEMS[$key] ?? null;
    }

    public static function label(string $key): string
    {
        return self::ITEMS[$key]['label'] ?? $key;
    }

    /**
     * Full trail for a key, e.g. "Procurement > Good Receipt Notes (GRNs)".
     * Used wherever a reader needs to be told where to look.
     */
    public static function path(string $key): string
    {
        $item = self::ITEMS[$key] ?? null;

        if (! $item) {
            return $key;
        }

        return $item['parent']
            ? self::label($item['parent']).' > '.$item['label']
            : $item['label'];
    }

    public static function permission(string $key): ?string
    {
        return self::ITEMS[$key]['permission'] ?? null;
    }

    /** @return list<string> */
    public static function childrenOf(string $group): array
    {
        return array_keys(array_filter(
            self::ITEMS,
            fn (array $item) => ($item['parent'] ?? null) === $group,
        ));
    }

    /** @return list<string> */
    public static function groups(): array
    {
        return array_keys(array_filter(
            self::ITEMS,
            fn (array $item, string $key) => $item['parent'] === null && self::childrenOf($key) !== [],
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * The keys whose list screen this request is showing.
     *
     * A detail page is deliberately not a visit: reading GRN-0006 says nothing
     * about whatever else is waiting in the GRN list.
     *
     * @return list<string>
     */
    public static function keysVisitedBy(Request $request): array
    {
        if (! $request->route()?->getName()) {
            return [];
        }

        $visited = [];

        foreach (self::ITEMS as $key => $item) {
            if (! $item['landing'] || ! $request->routeIs(...$item['landing'])) {
                continue;
            }

            // A row pinned to a query value counts as visited only on that
            // filter; a row without one is not visited by a filtered page.
            if ($expected = $item['query'] ?? []) {
                foreach ($expected as $param => $value) {
                    if ($request->query($param) !== $value) {
                        continue 2;
                    }
                }
            } else {
                foreach (self::siblingQueryParams($key) as $param) {
                    if ($request->query($param) !== null) {
                        continue 2;
                    }
                }
            }

            $visited[] = $key;
        }

        return $visited;
    }

    /**
     * Query parameters claimed by sibling rows that share this row's landing route.
     *
     * @return list<string>
     */
    private static function siblingQueryParams(string $key): array
    {
        $landing = self::ITEMS[$key]['landing'];
        $params = [];

        foreach (self::ITEMS as $other => $item) {
            if ($other === $key || empty($item['query']) || $item['landing'] !== $landing) {
                continue;
            }

            $params = array_merge($params, array_keys($item['query']));
        }

        return array_values(array_unique($params));
    }
}

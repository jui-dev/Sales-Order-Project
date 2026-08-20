<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the permission catalogue and the built-in admin role.
 *
 * Idempotent - it updateOrCreate()s by name, so re-running after adding a
 * module to the catalogue below adds only the new rows.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * The permission catalogue, grouped the same way the sidebar is.
     *
     * "manage" covers create/update/delete AND state transitions (post, pay,
     * approve, cancel). Most routes in this application are transitions rather
     * than plain CRUD, so splitting the two would not have matched the routes.
     *
     * @var array<string, array<string, string>>
     */
    private const CATALOGUE = [
        'Dashboard' => [
            'dashboard.view' => 'View dashboard',
        ],
        'Master Data' => [
            'products.view' => 'View products',
            'products.manage' => 'Manage products',
            'vendors.view' => 'View vendors',
            'vendors.manage' => 'Manage vendors',
            'customers.view' => 'View customers',
            'customers.manage' => 'Manage customers',
        ],
        'Procurement' => [
            'purchase-orders.view' => 'View purchase orders',
            'purchase-orders.manage' => 'Manage purchase orders',
            'supplies.view' => 'View supplies',
            'supplies.manage' => 'Manage supplies',
            'grns.view' => 'View goods receipt notes',
            'grns.manage' => 'Manage goods receipt notes',
            'supplier-bills.view' => 'View supplier bills',
            'supplier-bills.manage' => 'Manage supplier bills',
            'supplier-bill-payments.view' => 'View supplier bill payments',
            'supplier-bill-payments.manage' => 'Manage supplier bill payments',
        ],
        'Picking & Transfers' => [
            'picking.view' => 'View picking lists',
            'picking.manage' => 'Manage picking lists',
            'stock-transfers.view' => 'View stock transfers',
            'stock-transfers.manage' => 'Manage stock transfers',
        ],
        'Returns' => [
            'returns.view' => 'View returns',
            'returns.manage' => 'Manage returns',
            'credit-notes.view' => 'View credit notes',
            'credit-notes.manage' => 'Manage credit notes',
            'debit-notes.view' => 'View debit notes',
            'debit-notes.manage' => 'Manage debit notes',
        ],
        'Stock' => [
            'stock-management.view' => 'View stock management',
            'stock-management.manage' => 'Manage stock',
            'stock-locations.view' => 'View stock locations',
            'stock-locations.manage' => 'Manage stock locations',
        ],
        'Sales' => [
            'orders.view' => 'View orders',
            'orders.manage' => 'Manage orders',
            'invoices.view' => 'View invoices',
            'invoices.manage' => 'Manage invoices',
            'payments.view' => 'View invoice payments',
            'payments.manage' => 'Manage invoice payments',
        ],
        'Accounting' => [
            'accounting.view' => 'View chart of accounts',
            'accounting.manage' => 'Manage chart of accounts',
            'journal-entries.view' => 'View journal entries',
            'journal-entries.manage' => 'Create and edit journal entries',
            // Split out because separation of duties on the ledger is exactly
            // what a future accountant/reviewer role will need to divide.
            'journal-entries.approve' => 'Approve or reject journal entries',
            'journal-entries.post' => 'Post journal entries',
            'audit-logs.view' => 'View audit trail',
        ],
        'Reports' => [
            'reports.view' => 'View reports',
        ],
        'Reference' => [
            'reference.view' => 'View the action effects reference',
        ],
        'Administration' => [
            'users.view' => 'View users',
            'users.manage' => 'Create, edit and delete users',
        ],
    ];

    public function run(): void
    {
        $permissionIds = [];

        foreach (self::CATALOGUE as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                $permissionIds[] = Permission::updateOrCreate(
                    ['name' => $name],
                    ['label' => $label, 'group' => $group],
                )->id;
            }
        }

        $admin = Role::updateOrCreate(
            ['name' => Role::ADMIN],
            [
                'label' => 'Administrator',
                'description' => 'Full access to every part of the system.',
            ],
        );

        // sync() rather than attach() so a re-run after removing a permission
        // from the catalogue does not leave the role holding a stale grant.
        $admin->permissions()->sync($permissionIds);
    }
}

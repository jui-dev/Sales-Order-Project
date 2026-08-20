<?php

namespace App\Support\Nav;

use Illuminate\Support\Str;

/**
 * What each button in the application sets off, written down.
 *
 * The badges report what actually happened; this reports what is *meant* to
 * happen, which is the part nobody can currently read off the screens. It has
 * two readers:
 *
 *   - the panel shown after an action, for its headline
 *   - the Action Effects reference page, which renders this whole table
 *
 * Keyed by route name. Actions not listed here still raise badges - they just
 * fall back to a headline derived from the route name - so this table is a
 * documentation effort that can be filled in as modules settle, not a
 * registration step that gates the feature.
 */
final class ActionCatalog
{
    /**
     * @var array<string, array{label: string, module: string, where: string, effects: array<int, array{key: string, what: string}>, note?: string}>
     */
    private const ACTIONS = [
        // --- Procurement -----------------------------------------------------
        'purchase-orders.store' => [
            'label' => 'Raise a purchase order',
            'module' => 'Procurement',
            'where' => 'Save on the new purchase order form',
            'effects' => [
                ['key' => 'procurement.purchase-orders', 'what' => 'The order is created as Draft.'],
            ],
            'note' => 'An order is a request only - no stock, no cost, no price changes.',
        ],
        'purchase-orders.approve' => [
            'label' => 'Approve a purchase order',
            'module' => 'Procurement',
            'where' => 'Approve on the purchase order page',
            'effects' => [
                ['key' => 'procurement.purchase-orders', 'what' => 'The order moves to Approved and its lines are frozen.'],
            ],
        ],
        'purchase-orders.send' => [
            'label' => 'Send a purchase order to the vendor',
            'module' => 'Procurement',
            'where' => 'Send to Vendor on the purchase order page',
            'effects' => [
                ['key' => 'procurement.purchase-orders', 'what' => 'The order moves to Sent and can now be received against.'],
            ],
        ],
        'purchase-orders.record-supply' => [
            'label' => 'Record a supply against a purchase order',
            'module' => 'Procurement',
            'where' => 'Record Supply on the purchase order page',
            'effects' => [
                ['key' => 'procurement.supplies', 'what' => 'A Pending supply is created for what arrived, linked back to the order.'],
                ['key' => 'procurement.purchase-orders', 'what' => 'Received quantities are recounted; the order closes or stays partially received.'],
                ['key' => 'picking.vendor-to-warehouse', 'what' => 'It appears on the vendor picking screen.'],
            ],
            'note' => 'Still nothing in inventory - stock moves when the GRN is posted.',
        ],
        'supplies.store' => [
            'label' => 'Record a supply',
            'module' => 'Procurement',
            'where' => 'Save on the new supply form',
            'effects' => [
                ['key' => 'procurement.supplies', 'what' => 'The supply is created as Pending.'],
                ['key' => 'picking.vendor-to-warehouse', 'what' => 'It appears on the vendor picking screen, which lists supplies rather than records of its own.'],
            ],
            'note' => 'Nothing enters inventory here, and no price changes - a supply only records what arrived.',
        ],
        'supplies.completed' => [
            'label' => 'Mark supply completed',
            'module' => 'Procurement',
            'where' => 'Mark Completed on the supply detail page',
            'effects' => [
                ['key' => 'procurement.supplies', 'what' => 'The supply moves to Completed.'],
                ['key' => 'procurement.grns', 'what' => 'A draft goods receipt note is raised for the receiving team, and you are sent straight to it.'],
                ['key' => 'picking.vendor-to-warehouse', 'what' => 'The supply now reads as awaiting receipt.'],
            ],
            'note' => 'Still no stock: inventory only moves when the GRN is posted.',
        ],
        'grns.update-status' => [
            'label' => 'Post a goods receipt note',
            'module' => 'Procurement',
            'where' => 'Post on the GRN detail page',
            'effects' => [
                ['key' => 'procurement.grns', 'what' => 'The GRN moves to Posted.'],
                ['key' => 'stock.stock-management', 'what' => 'One inbound stock movement per supplied line, which is what actually puts the goods in the warehouse.'],
                ['key' => 'picking.vendor-to-warehouse', 'what' => 'A Vendor to Warehouse stock transfer records the inbound movement.'],
                ['key' => 'procurement.supplier-bills', 'what' => 'A draft supplier bill is created automatically from the supply lines.'],
                ['key' => 'products', 'what' => 'Each received line writes its cost to the product, and the selling price is recalculated from that cost plus the product markup.'],
                ['key' => 'accounting.audit-logs', 'what' => 'The bill creation is written to the audit trail.'],
            ],
            'note' => 'This is the step where stock becomes real and sellable, and the only step that changes a price.',
        ],
        'supplier-bills.post' => [
            'label' => 'Post a supplier bill',
            'module' => 'Procurement',
            'where' => 'Post on the supplier bill page',
            'effects' => [
                ['key' => 'procurement.supplier-bills', 'what' => 'The bill moves to Posted.'],
                ['key' => 'accounting.journal-entries', 'what' => 'A draft purchase journal is raised: Inventory debit, Accounts Payable credit.'],
                ['key' => 'procurement.supplier-bill-payments', 'what' => 'An unpaid payment record is opened against the bill.'],
                ['key' => 'accounting.audit-logs', 'what' => 'The posting is written to the audit trail.'],
            ],
        ],
        'supplier-bills.pay' => [
            'label' => 'Pay a supplier bill',
            'module' => 'Procurement',
            'where' => 'Mark as Paid on the supplier bill page',
            'effects' => [
                ['key' => 'procurement.supplier-bill-payments', 'what' => 'The payment record moves to Paid.'],
                ['key' => 'accounting.journal-entries', 'what' => 'A draft payment journal is raised: Accounts Payable debit, Cash credit.'],
                ['key' => 'accounting.audit-logs', 'what' => 'The settlement is written to the audit trail.'],
            ],
            'note' => 'Both journals are raised as drafts; they still need approving and posting under Accounting.',
        ],

        // --- Sales -------------------------------------------------------------
        'orders.store' => [
            'label' => 'Create an order',
            'module' => 'Sales',
            'where' => 'Save on the new order form',
            'effects' => [
                ['key' => 'sales.orders', 'what' => 'The order is created.'],
            ],
        ],
        'orders.update-status' => [
            'label' => 'Change an order status',
            'module' => 'Sales',
            'where' => 'The status control on the order page',
            'effects' => [
                ['key' => 'sales.orders', 'what' => 'The order moves to the chosen status.'],
                ['key' => 'picking.retailer-to-customers', 'what' => 'On Confirmed, a retailer-fulfilled order raises a picking list and reserves the stock.'],
                ['key' => 'products', 'what' => 'On Confirmed, gross profit per product is recalculated from the order lines.'],
                ['key' => 'sales.invoices', 'what' => 'On Completed, an invoice is generated from the order.'],
            ],
            'note' => 'Which of these fire depends on the status chosen and on where the order is fulfilled from.',
        ],
        'invoices.pay' => [
            'label' => 'Record a payment against an invoice',
            'module' => 'Sales',
            'where' => 'Record Payment on the invoice page',
            'effects' => [
                ['key' => 'sales.payments', 'what' => 'The payment is recorded.'],
                ['key' => 'accounting.journal-entries', 'what' => 'A journal is raised: Cash debit, Accounts Receivable credit.'],
                ['key' => 'accounting.audit-logs', 'what' => 'The payment is written to the audit trail.'],
            ],
        ],

        // --- Picking & Transfers ------------------------------------------------
        'stock-transfers.warehouse-to-retailer.store' => [
            'label' => 'Create a warehouse to retailer transfer',
            'module' => 'Picking & Transfers',
            'where' => 'Save on the new transfer form',
            'effects' => [
                ['key' => 'picking.warehouse-to-retailers', 'what' => 'A picking list is raised for the transfer.'],
                ['key' => 'picking.all', 'what' => 'It also appears in the combined picking list index.'],
            ],
            'note' => 'Creating the transfer does not move stock; completing it does.',
        ],
        'stock-transfers.warehouse-to-retailer.process' => [
            'label' => 'Process a warehouse to retailer transfer',
            'module' => 'Picking & Transfers',
            'where' => 'Process on the transfer page, with per-line quantities',
            'effects' => [
                ['key' => 'picking.warehouse-to-retailers', 'what' => 'The picking list and its transfer are marked completed.'],
                ['key' => 'stock.stock-management', 'what' => 'Stock leaves the warehouse and arrives at the retailer, and the reservation is released.'],
            ],
        ],
        'stock-transfers.warehouse-to-retailer.quick-complete' => [
            'label' => 'Quick-complete a warehouse to retailer transfer',
            'module' => 'Picking & Transfers',
            'where' => 'Complete on the transfer page',
            'effects' => [
                ['key' => 'picking.warehouse-to-retailers', 'what' => 'Every line is picked at the requested quantity and the transfer completes.'],
                ['key' => 'stock.stock-management', 'what' => 'Stock leaves the warehouse and arrives at the retailer.'],
            ],
        ],
        'stock-transfers.warehouse-to-retailer.cancel' => [
            'label' => 'Cancel a warehouse to retailer transfer',
            'module' => 'Picking & Transfers',
            'where' => 'Cancel on the transfer page',
            'effects' => [
                ['key' => 'picking.warehouse-to-retailers', 'what' => 'The picking list is cancelled and any reservation released.'],
            ],
        ],
        'retailer-to-customer-picking.complete' => [
            'label' => 'Complete a retailer to customer picking list',
            'module' => 'Picking & Transfers',
            'where' => 'Complete on the picking list page',
            'effects' => [
                ['key' => 'picking.retailer-to-customers', 'what' => 'The picking list is marked completed.'],
                ['key' => 'stock.stock-management', 'what' => 'Picked stock leaves the retailer.'],
            ],
        ],
        'customer-picking.update-status' => [
            'label' => 'Change a customer picking list status',
            'module' => 'Picking & Transfers',
            'where' => 'The status control on the customer picking page',
            'effects' => [
                ['key' => 'picking.all', 'what' => 'The picking list moves to the chosen status.'],
                ['key' => 'stock.stock-management', 'what' => 'On completion, the picked stock is deducted from the source location.'],
            ],
        ],

        // --- Returns --------------------------------------------------------------
        'returns.store' => [
            'label' => 'Record a return',
            'module' => 'Returns',
            'where' => 'Save on the new return form',
            'effects' => [
                ['key' => 'returns.all', 'what' => 'A pending return is recorded against the chosen customer, vendor or retailer.'],
            ],
            'note' => 'Returns are stock movements filtered by type, which is why they share a screen with the stock ledger.',
        ],
        'returns.approve' => [
            'label' => 'Approve a return',
            'module' => 'Returns',
            'where' => 'Approve on the return page',
            'effects' => [
                ['key' => 'returns.all', 'what' => 'The return is approved.'],
                ['key' => 'stock.stock-management', 'what' => 'The returned stock is booked back in or out, depending on the return type.'],
                ['key' => 'returns.credit-notes', 'what' => 'A customer return generates a credit note, and you are sent to it.'],
                ['key' => 'returns.debit-notes', 'what' => 'A vendor return generates a debit note, and you are sent to it.'],
                ['key' => 'accounting.journal-entries', 'what' => 'The matching journal is raised for the return.'],
            ],
            'note' => 'Which note is produced follows the return type; retailer returns produce neither.',
        ],
        'returns.reject' => [
            'label' => 'Reject a return',
            'module' => 'Returns',
            'where' => 'Reject on the return page',
            'effects' => [
                ['key' => 'returns.all', 'what' => 'The return is rejected.'],
            ],
            'note' => 'Deliberately inert: no stock and no accounting entries are made.',
        ],
        'returns.complete' => [
            'label' => 'Complete a return',
            'module' => 'Returns',
            'where' => 'Complete on the return page',
            'effects' => [
                ['key' => 'returns.all', 'what' => 'The return is closed off.'],
            ],
        ],
        'credit-notes.post' => [
            'label' => 'Post a credit note',
            'module' => 'Returns',
            'where' => 'Post on the credit note page',
            'effects' => [
                ['key' => 'returns.credit-notes', 'what' => 'The credit note moves to Posted.'],
                ['key' => 'accounting.journal-entries', 'what' => 'Its journal entry is raised.'],
            ],
        ],
        'debit-notes.post' => [
            'label' => 'Post a debit note',
            'module' => 'Returns',
            'where' => 'Post on the debit note page',
            'effects' => [
                ['key' => 'returns.debit-notes', 'what' => 'The debit note moves to Posted.'],
                ['key' => 'accounting.journal-entries', 'what' => 'Its journal entry is raised.'],
            ],
        ],

        // --- Accounting -------------------------------------------------------------
        'journal-entries.store' => [
            'label' => 'Create a journal entry',
            'module' => 'Accounting',
            'where' => 'Save on the new journal entry form',
            'effects' => [
                ['key' => 'accounting.journal-entries', 'what' => 'A draft entry is created; it does not affect the ledger yet.'],
            ],
        ],
        'journal-entries.approve' => [
            'label' => 'Approve a journal entry',
            'module' => 'Accounting',
            'where' => 'Approve on the journal entry page',
            'effects' => [
                ['key' => 'accounting.journal-entries', 'what' => 'The entry moves to Approved and becomes eligible for posting.'],
                ['key' => 'accounting.audit-logs', 'what' => 'The approval is written to the audit trail.'],
            ],
        ],
        'journal-entries.post' => [
            'label' => 'Post a journal entry',
            'module' => 'Accounting',
            'where' => 'Post on the journal entry page',
            'effects' => [
                ['key' => 'accounting.journal-entries', 'what' => 'The entry moves to Posted and reaches the ledger and the reports.'],
                ['key' => 'accounting.audit-logs', 'what' => 'The posting is written to the audit trail.'],
            ],
        ],
        'accounting.chart-of-accounts.store' => [
            'label' => 'Add an account',
            'module' => 'Accounting',
            'where' => 'Save on the new account form',
            'effects' => [
                ['key' => 'accounting.chart-of-accounts', 'what' => 'The account is added to the chart.'],
            ],
        ],
    ];

    public static function has(string $routeName): bool
    {
        return isset(self::ACTIONS[$routeName]);
    }

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return self::ACTIONS;
    }

    /**
     * Catalogued actions grouped by the module heading they belong under.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function byModule(): array
    {
        $grouped = [];

        foreach (self::ACTIONS as $route => $action) {
            $grouped[$action['module']][$route] = $action;
        }

        return $grouped;
    }

    /**
     * How to name the action a request performed.
     *
     * Falls back to the route name read as words, so an uncatalogued action
     * still produces a sensible headline rather than nothing.
     */
    public static function labelFor(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        if (isset(self::ACTIONS[$routeName])) {
            return self::ACTIONS[$routeName]['label'];
        }

        $segments = explode('.', $routeName);
        $verb = array_pop($segments);
        $subject = implode(' ', $segments);

        return Str::ucfirst(trim(str_replace('-', ' ', $verb).' '.str_replace('-', ' ', $subject)));
    }
}

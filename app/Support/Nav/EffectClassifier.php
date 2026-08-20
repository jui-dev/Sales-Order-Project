<?php

namespace App\Support\Nav;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\DebitNote;
use App\Models\Grn;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickingList;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use App\Models\PurchaseOrder;
use App\Models\Supply;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Turns a saved model into the sidebar entries it should light up.
 *
 * The map is not one-to-one, which is most of the reason the workflow is hard
 * to follow by reading the screens:
 *
 *   - "Vendors -> Warehouse" is a filtered list of supplies, not its own record
 *     (see the vendor-to-warehouse-picking route in routes/web.php).
 *   - The other three picking screens are all picking_lists rows, told apart by
 *     from_location_type / to_location_type.
 *   - The Returns rows are stock_transactions rows, told apart by
 *     transaction_type (see ReturnService).
 *
 * Anything not listed in MODELS is ignored, which is what keeps line items,
 * pivot rows and bookkeeping tables out of the notifications.
 */
final class EffectClassifier
{
    /**
     * Tracked models: the noun used when describing one, the route its detail
     * page lives on, and the column that carries its state.
     *
     * The status column is what an update is judged against - without it every
     * incidental save would raise a badge - and it is also what the description
     * reads out. A null status means the record is only reportable on create.
     *
     * @var array<class-string, array{noun: string, route: string|null, status: string|null}>
     */
    private const MODELS = [
        Supply::class => ['noun' => 'Supply', 'route' => 'supplies.show', 'status' => 'status'],
        Grn::class => ['noun' => 'Goods receipt note', 'route' => 'grns.show', 'status' => 'status'],
        SupplierBill::class => ['noun' => 'Supplier bill', 'route' => 'supplier-bills.show', 'status' => 'status'],
        // Settlement lives on payment_status, not status - see SupplierBillService::paySupplierBill().
        SupplierBillPayment::class => ['noun' => 'Supplier bill payment', 'route' => 'supplier-bill-payments.show', 'status' => 'payment_status'],

        PickingList::class => ['noun' => 'Picking list', 'route' => 'picking-lists.show', 'status' => 'status'],
        StockTransfer::class => ['noun' => 'Stock transfer', 'route' => 'stock-transfers.warehouse-to-retailer.show', 'status' => 'status'],
        StockTransaction::class => ['noun' => 'Stock movement', 'route' => null, 'status' => 'status'],

        Order::class => ['noun' => 'Order', 'route' => 'orders.show', 'status' => 'status'],
        Invoice::class => ['noun' => 'Invoice', 'route' => 'invoices.show', 'status' => 'status'],
        Payment::class => ['noun' => 'Payment', 'route' => 'payments.show', 'status' => 'status'],

        CreditNote::class => ['noun' => 'Credit note', 'route' => 'credit-notes.show', 'status' => 'status'],
        DebitNote::class => ['noun' => 'Debit note', 'route' => 'debit-notes.show', 'status' => 'status'],

        JournalEntry::class => ['noun' => 'Journal entry', 'route' => 'journal-entries.show', 'status' => 'status'],
        Account::class => ['noun' => 'Account', 'route' => null, 'status' => null],
        // Noisy by nature - most actions write one - but the audit trail really
        // does receive a record, and saying so is the whole point here.
        AuditLog::class => ['noun' => 'Audit trail entry', 'route' => null, 'status' => null],

        Product::class => ['noun' => 'Product', 'route' => 'products.show', 'status' => null],
        Vendor::class => ['noun' => 'Vendor', 'route' => 'vendors.show', 'status' => null],
        Customer::class => ['noun' => 'Customer', 'route' => 'customers.show', 'status' => null],
        User::class => ['noun' => 'User', 'route' => 'users.show', 'status' => null],
    ];

    /** Return transaction types, which move a stock movement onto the Returns menu. */
    private const RETURN_TYPES = ['customer_return', 'vendor_return', 'retailer_return'];

    public static function tracks(Model $model): bool
    {
        return isset(self::MODELS[$model::class]);
    }

    /**
     * Describe what a save means for the sidebar, or null when it means nothing.
     *
     * @param  'created'|'updated'  $verb
     * @return array{keys: list<string>, title: string, detail: string, url: ?string}|null
     */
    public static function classify(Model $model, string $verb): ?array
    {
        $spec = self::MODELS[$model::class] ?? null;

        if (! $spec) {
            return null;
        }

        // An update only counts when the record's state moved. Models with no
        // status column are reportable on create only.
        if ($verb === 'updated' && (! $spec['status'] || ! $model->wasChanged($spec['status']))) {
            return null;
        }

        $keys = self::keysFor($model);

        if ($keys === []) {
            return null;
        }

        return [
            'keys' => $keys,
            'title' => self::title($model),
            'detail' => self::detail($model, $spec['noun'], $spec['status'], $verb),
            'url' => self::url($model, $spec['route']),
        ];
    }

    /**
     * The sidebar entries a record belongs to.
     *
     * @return list<string>
     */
    public static function keysFor(Model $model): array
    {
        return match ($model::class) {
            // A supply is both its own list and the whole content of the
            // Vendors -> Warehouse picking screen.
            PurchaseOrder::class => ['procurement.purchase-orders'],
            Supply::class => ['procurement.supplies', 'picking.vendor-to-warehouse'],
            Grn::class => ['procurement.grns'],
            SupplierBill::class => ['procurement.supplier-bills'],
            SupplierBillPayment::class => ['procurement.supplier-bill-payments'],

            PickingList::class => self::locationKeys($model->from_location_type, $model->to_location_type, ['picking.all']),
            StockTransfer::class => self::locationKeys($model->from_location_type, $model->to_location_type),
            StockTransaction::class => self::stockTransactionKeys($model),

            Order::class => ['sales.orders'],
            Invoice::class => ['sales.invoices'],
            Payment::class => ['sales.payments'],

            CreditNote::class => ['returns.credit-notes'],
            DebitNote::class => ['returns.debit-notes'],

            JournalEntry::class => ['accounting.journal-entries'],
            Account::class => ['accounting.chart-of-accounts'],
            AuditLog::class => ['accounting.audit-logs'],

            Product::class => ['products'],
            Vendor::class => ['vendors'],
            Customer::class => ['customers'],
            User::class => ['admin.users'],

            default => [],
        };
    }

    /**
     * Movements are split across the picking screens by where they run from and
     * to, so the endpoints - not the table - decide which menu row they land on.
     * Posting a GRN raises a Vendor -> Warehouse transfer (GrnService::postStock)
     * while a retailer replenishment raises a Warehouse -> Retailer one, and the
     * two belong on different screens despite sharing a table.
     *
     * @param  list<string>  $always  keys every record of this kind also appears on
     * @return list<string>
     */
    private static function locationKeys(?string $from, ?string $to, array $always = []): array
    {
        $key = match ([$from, $to]) {
            [Vendor::class, Warehouse::class] => 'picking.vendor-to-warehouse',
            [Warehouse::class, Retailer::class] => 'picking.warehouse-to-retailers',
            [Warehouse::class, Customer::class] => 'picking.warehouse-to-customers',
            [Retailer::class, Customer::class] => 'picking.retailer-to-customers',
            default => null,
        };

        return $key ? [$key, ...$always] : $always;
    }

    /**
     * A stock movement is a return when its transaction_type says so, and plain
     * stock otherwise.
     *
     * @return list<string>
     */
    private static function stockTransactionKeys(StockTransaction $transaction): array
    {
        $type = $transaction->transaction_type;

        if (in_array($type, self::RETURN_TYPES, true)) {
            return ['returns.all', 'returns.'.$type];
        }

        return ['stock.stock-management'];
    }

    private static function title(Model $model): string
    {
        // Most records carry the HasFormattedId trait, which renders ids as
        // GRN-0012 - the same identifier the screens show.
        //
        // `code` rather than `formatted_id`: where a model also has a
        // formatted_id column, the stored value shadows the trait's accessor,
        // and it is not always the final one. AccountingService::post() seeds a
        // journal entry with a placeholder uuid and rewrites it with a quiet
        // save afterwards - quiet enough that this listener still holds the
        // uuid. `code` is derived from the key, so it is right either way.
        foreach (['code', 'formatted_id'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        // The few that do not (payments, accounts, users, audit entries) fall
        // back to a readable name rather than the raw class: "Audit Log #12".
        return Str::headline(class_basename($model)).' #'.$model->getKey();
    }

    private static function detail(Model $model, string $noun, ?string $statusColumn, string $verb): string
    {
        $status = $statusColumn ? $model->getAttribute($statusColumn) : null;
        $status = is_string($status) && $status !== '' ? str_replace('_', ' ', $status) : null;

        if ($verb === 'created') {
            return $status ? $noun.' created ('.$status.')' : $noun.' created';
        }

        return $status ? $noun.' is now '.$status : $noun.' updated';
    }

    private static function url(Model $model, ?string $route): ?string
    {
        if (! $route) {
            return null;
        }

        // A detail route can be missing or take parameters this record cannot
        // supply; a broken link is not worth a 500 on an unrelated page.
        try {
            return route($route, $model->getKey());
        } catch (\Throwable) {
            return null;
        }
    }
}

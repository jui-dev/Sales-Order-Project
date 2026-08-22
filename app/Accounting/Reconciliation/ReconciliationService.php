<?php

namespace App\Accounting\Reconciliation;

use App\Accounting\AccountRole;
use App\Accounting\ChartOfAccounts;
use App\Accounting\LedgerService;
use App\Accounting\Money;
use App\Models\Account;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\DebitNote;
use App\Models\Grn;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductStock;
use App\Models\SupplierBill;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * Does the general ledger agree with everything else?
 *
 * A control account is only worth having if somebody checks it against the
 * subsidiary ledger behind it. These are those checks, and they are the reason
 * the party and location dimensions exist: without them, Accounts Receivable
 * is a single number that cannot be argued with, and inventory value per
 * warehouse is not a number at all.
 *
 * Everything here is read-only. It reports what disagrees; it never adjusts.
 */
class ReconciliationService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ChartOfAccounts $chart,
    ) {
    }

    /**
     * @return array<int,Check>
     */
    public function run(?string $asOf = null): array
    {
        return [
            $this->trialBalance($asOf),
            $this->accountingEquation($asOf),
            $this->receivables($asOf),
            $this->payables($asOf),
            $this->goodsReceivedNotInvoiced($asOf),
            $this->inventory($asOf),
        ];
    }

    public function allPassed(?string $asOf = null): bool
    {
        foreach ($this->run($asOf) as $check) {
            if (! $check->passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Roles the chart cannot currently serve.
     *
     * A posting rule that cannot resolve its account fails at the moment
     * somebody tries to use it. Reporting it here means the fault is visible
     * before a delivery or an invoice runs into it.
     *
     * @return array<int,string>
     */
    public function unresolvableRoles(): array
    {
        return $this->chart->unresolvableRoles();
    }

    // ------------------------------------------------------------------
    // Checks
    // ------------------------------------------------------------------

    private function trialBalance(?string $asOf): Check
    {
        $totals = $this->ledger->trialBalanceTotals($asOf);

        return Check::make(
            'trial_balance',
            'Trial balance',
            'Every entry is balanced when it is written and again when it is posted, so the totals are equal by construction. A difference here means rows have been changed behind the application.',
            $totals['debit'],
            $totals['credit'],
        );
    }

    private function accountingEquation(?string $asOf): Check
    {
        $assets = $this->ledger->balanceOf($this->accountsOfType('Asset'), $asOf);

        // Liabilities, equity, revenue and expense together are the other side
        // of the equation. Revenue and expense belong to the owners until the
        // period closes and moves them into retained earnings, so they count
        // here whether or not that has happened yet.
        $others = $this->ledger->balanceOf(
            $this->accountsOfType('Liability', 'Equity', 'Revenue', 'Expense'),
            $asOf,
        );

        return Check::make(
            'accounting_equation',
            'Assets = Liabilities + Equity',
            'Assets carry a debit balance and everything else a credit balance, so the two sides sum to nil. Profit not yet closed into retained earnings is counted on the right, where it belongs.',
            $assets,
            $others->negated(),
        );
    }

    private function receivables(?string $asOf): Check
    {
        $ledgerByParty = $this->ledger->partyBalances(AccountRole::AccountsReceivable, $asOf);
        $expectedByCustomer = $this->expectedReceivables($asOf);

        return $this->compareParties(
            'receivables',
            'Accounts Receivable ties to the sales ledger',
            'What the control account says each customer owes, against what their invoices less payments and credit notes say. These are two different routes to the same number and must agree.',
            AccountRole::AccountsReceivable,
            Customer::class,
            $ledgerByParty,
            $expectedByCustomer,
            $asOf,
        );
    }

    private function payables(?string $asOf): Check
    {
        $ledgerByParty = $this->ledger->partyBalances(AccountRole::AccountsPayable, $asOf);
        $expectedByVendor = $this->expectedPayables($asOf);

        return $this->compareParties(
            'payables',
            'Accounts Payable ties to the purchase ledger',
            'What the control account says is owed to each vendor, against what their posted bills less payments and debit notes say.',
            AccountRole::AccountsPayable,
            Vendor::class,
            $ledgerByParty,
            $expectedByVendor,
            $asOf,
        );
    }

    private function goodsReceivedNotInvoiced(?string $asOf): Check
    {
        $balance = $this->ledger->balance(AccountRole::GoodsReceivedNotInvoiced, $asOf);

        // Everything received, less everything billed. Whatever is left is
        // goods on the shelves that no vendor has invoiced for yet.
        $received = $this->totalReceived($asOf);
        $billed = $this->totalBilled($asOf);

        return Check::make(
            'gr_ir',
            'Goods Received Not Invoiced',
            'The clearing account should hold exactly the value of deliveries that have arrived but not yet been billed. A balance that will not clear points at a delivery whose bill never arrived, or a bill posted for the wrong amount.',
            $balance,
            $received->minus($billed)->negated(),
        );
    }

    private function inventory(?string $asOf): Check
    {
        $ledgerByLocation = $this->ledger->locationBalances(AccountRole::Inventory, $asOf);
        $physical = $this->physicalStockValue();

        $breakdown = [];
        $keys = array_unique(array_merge(array_keys($ledgerByLocation->all()), array_keys($physical)));

        foreach ($keys as $key) {
            $ledgerValue = $ledgerByLocation[$key]['balance'] ?? Money::zero();
            $expected = $physical[$key]['value'] ?? Money::zero();

            $breakdown[] = [
                'label'      => $physical[$key]['label'] ?? $this->labelFor($key),
                'ledger'     => $ledgerValue,
                'expected'   => $expected,
                'difference' => $ledgerValue->minus($expected),
            ];
        }

        return Check::make(
            'inventory',
            'Inventory value ties to stock on hand',
            'The inventory account against the quantity actually on the shelves at its weighted average cost, location by location. Goods are taken in at what they cost and relieved at the average in force when they ship, so the two are the same measurement by two routes - within a penny per line, because the average is carried to four decimal places and the ledger to two.',
            $this->ledger->balance(AccountRole::Inventory, $asOf),
            Money::sum(array_column($physical, 'value')),
            $breakdown,
            $this->inventoryRoundingTolerance(),
        );
    }

    /**
     * A penny for every product held at every location.
     *
     * Inventory is posted at two decimal places from an average cost carried
     * at four, so each product-location line can legitimately differ by half a
     * penny either way. Anything larger is a real difference and is reported.
     */
    private function inventoryRoundingTolerance(): Money
    {
        $lines = ProductStock::where('quantity', '!=', 0)->count();

        return Money::ofMinor(max(1, $lines));
    }

    // ------------------------------------------------------------------
    // Expected values, rebuilt from the documents
    // ------------------------------------------------------------------

    /** @return array<string,array{label:string,balance:Money}> */
    private function expectedReceivables(?string $asOf): array
    {
        $invoiced = $this->sumByKey(
            Invoice::query()->when($asOf, fn ($q) => $q->whereDate('invoice_date', '<=', $asOf)),
            'customer_id',
            'total',
        );

        $paid = $this->sumByKey(
            Payment::query()
                ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                ->when($asOf, fn ($q) => $q->whereDate('payments.payment_date', '<=', $asOf)),
            'invoices.customer_id',
            'payments.amount',
        );

        $credited = $this->sumByKey(
            CreditNote::query()
                ->whereIn('status', [CreditNote::STATUS_POSTED, 'issued'])
                ->when($asOf, fn ($q) => $q->whereDate('issue_date', '<=', $asOf)),
            'customer_id',
            'total_amount',
        );

        return $this->combine(Customer::class, [$invoiced], [$paid, $credited]);
    }

    /** @return array<string,array{label:string,balance:Money}> */
    private function expectedPayables(?string $asOf): array
    {
        $billed = $this->sumByKey(
            SupplierBill::query()
                ->whereIn('status', ['posted', 'paid'])
                ->when($asOf, fn ($q) => $q->whereDate('bill_date', '<=', $asOf)),
            'vendor_id',
            'total_amount',
        );

        $paid = $this->sumByKey(
            SupplierBill::query()
                ->whereNotNull('paid_at')
                ->when($asOf, fn ($q) => $q->whereDate('paid_at', '<=', $asOf)),
            'vendor_id',
            'total_amount',
        );

        $debited = $this->sumByKey(
            DebitNote::query()
                ->whereIn('status', [DebitNote::STATUS_POSTED, 'issued'])
                ->when($asOf, fn ($q) => $q->whereDate('issue_date', '<=', $asOf)),
            'vendor_id',
            'total_amount',
        );

        // A payable is a credit balance, so what is owed comes out negative.
        return $this->combine(Vendor::class, [$paid, $debited], [$billed]);
    }

    private function totalReceived(?string $asOf): Money
    {
        $rows = Grn::query()
            ->where('grns.status', 'posted')
            ->when($asOf, fn ($q) => $q->whereDate('grns.received_date', '<=', $asOf))
            ->join('supplies', 'supplies.id', '=', 'grns.supply_id')
            ->join('supply_items', 'supply_items.supply_id', '=', 'supplies.id')
            ->selectRaw('COALESCE(SUM(supply_items.quantity * supply_items.unit_cost), 0) AS total')
            ->value('total');

        return Money::of((string) ($rows ?? 0));
    }

    private function totalBilled(?string $asOf): Money
    {
        $total = SupplierBill::query()
            ->whereIn('status', ['posted', 'paid'])
            ->when($asOf, fn ($q) => $q->whereDate('bill_date', '<=', $asOf))
            ->sum('total_amount');

        return Money::of((string) $total);
    }

    /** @return array<string,array{label:string,value:Money}> */
    private function physicalStockValue(): array
    {
        $rows = ProductStock::query()
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->leftJoin('product_costs', function ($join) {
                // The cost row in force now for that product: the latest one.
                $join->on('product_costs.product_id', '=', 'product_stocks.product_id')
                    ->whereRaw('product_costs.id = (select max(id) from product_costs pc where pc.product_id = product_stocks.product_id)');
            })
            ->where('product_stocks.quantity', '!=', 0)
            ->selectRaw('product_stocks.location_type, product_stocks.location_id, SUM(product_stocks.quantity * COALESCE(product_costs.unit_cost, products.purchase_price, 0)) AS value')
            ->groupBy('product_stocks.location_type', 'product_stocks.location_id')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $key = $row->location_type . ':' . $row->location_id;

            $out[$key] = [
                'label' => $this->labelFor($key),
                'value' => Money::of((string) $row->value),
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Plumbing
    // ------------------------------------------------------------------

    /**
     * @param \Illuminate\Support\Collection<string,array{party_type:string,party_id:int,balance:Money}> $ledgerByParty
     * @param array<string,array{label:string,balance:Money}> $expected
     */
    private function compareParties(
        string $key,
        string $title,
        string $explanation,
        AccountRole $role,
        string $partyClass,
        $ledgerByParty,
        array $expected,
        ?string $asOf,
    ): Check {
        $breakdown = [];
        $keys = array_unique(array_merge(array_keys($ledgerByParty->all()), array_keys($expected)));

        foreach ($keys as $partyKey) {
            $ledgerValue = $ledgerByParty[$partyKey]['balance'] ?? Money::zero();
            $expectedValue = $expected[$partyKey]['balance'] ?? Money::zero();

            $breakdown[] = [
                'label'      => $expected[$partyKey]['label'] ?? $this->labelFor($partyKey),
                'ledger'     => $ledgerValue,
                'expected'   => $expectedValue,
                'difference' => $ledgerValue->minus($expectedValue),
            ];
        }

        return Check::make(
            $key,
            $title,
            $explanation,
            $this->ledger->balance($role, $asOf),
            Money::sum(array_map(fn (array $row) => $row['balance'], $expected)),
            $breakdown,
        );
    }

    /**
     * @return array<int|string,Money>
     */
    private function sumByKey($query, string $keyColumn, string $valueColumn): array
    {
        return $query
            ->selectRaw(sprintf('%s AS group_key, COALESCE(SUM(%s), 0) AS total', $keyColumn, $valueColumn))
            ->groupBy(DB::raw($keyColumn))
            ->pluck('total', 'group_key')
            ->map(fn ($total) => Money::of((string) $total))
            ->all();
    }

    /**
     * Net several debit-side and credit-side sums into one balance per party.
     *
     * @param array<int,array<int|string,Money>> $debits
     * @param array<int,array<int|string,Money>> $credits
     * @return array<string,array{label:string,balance:Money}>
     */
    private function combine(string $partyClass, array $debits, array $credits): array
    {
        $out = [];

        $apply = function (array $sums, bool $isDebit) use (&$out, $partyClass) {
            foreach ($sums as $id => $amount) {
                if ($id === null || $id === '') {
                    continue;
                }

                $key = $partyClass . ':' . $id;
                $current = $out[$key]['balance'] ?? Money::zero();

                $out[$key] = [
                    'label'   => $this->labelFor($key),
                    'balance' => $isDebit ? $current->plus($amount) : $current->minus($amount),
                ];
            }
        };

        foreach ($debits as $sums) {
            $apply($sums, true);
        }

        foreach ($credits as $sums) {
            $apply($sums, false);
        }

        return $out;
    }

    private function labelFor(string $key): string
    {
        [$class, $id] = array_pad(explode(':', $key, 2), 2, null);

        return class_basename($class) . ' #' . $id;
    }

    /** @return \Illuminate\Support\Collection<int,Account> */
    private function accountsOfType(string ...$types)
    {
        return Account::whereHas('accountType', fn ($q) => $q->whereIn('name', $types))->get();
    }
}

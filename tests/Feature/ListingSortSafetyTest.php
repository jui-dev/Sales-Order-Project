<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supply;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A nonsense sort shows the records, not an empty page.
 *
 * Every listing offers a getSortOptions() whitelist and only
 * PurchaseOrderService consulted it. Everywhere else the query string went
 * straight into orderBy(), where an unknown column is a QueryException and a
 * bad direction an InvalidArgumentException - both caught by
 * getPaginatedOrEmpty() into an empty paginator. So ?sort=nope answered "no
 * records found" over a table that had records in it, with a 200 and no sign
 * anything had gone wrong: which is why each case here looks for the record
 * rather than for the status code.
 */
class ListingSortSafetyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,array{0:string}> */
    public static function listings(): array
    {
        return [
            'supplies' => ['supplies.index'],
            'orders' => ['orders.index'],
            'invoices' => ['invoices.index'],
            'products' => ['products.index'],
            'purchase orders' => ['purchase-orders.index'],
            'payments' => ['payments.index'],
        ];
    }

    /** @dataProvider listings */
    public function test_an_unknown_sort_field_still_lists_the_records(string $route): void
    {
        $needle = $this->seedOneOfEverything()[$route];

        $this->get(route($route, ['sort' => 'nonsense_column', 'direction' => 'sideways']))
            ->assertOk()
            ->assertSee($needle, false);
    }

    /** @dataProvider listings */
    public function test_a_known_sort_field_is_still_honoured(string $route): void
    {
        $needle = $this->seedOneOfEverything()[$route];

        $this->get(route($route, ['sort' => 'id', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee($needle, false);
    }

    /**
     * One record per listing, and the text that proves it was rendered.
     *
     * @return array<string,string>
     */
    private function seedOneOfEverything(): array
    {
        $vendor = Vendor::factory()->create(['name' => 'Sort Test Vendor']);
        $warehouse = Warehouse::factory()->create(['name' => 'Sort Test Warehouse']);
        $customer = Customer::factory()->create(['name' => 'Sort Test Customer']);
        $product = Product::factory()->create(['name' => 'Sort Test Widget', 'purchase_price' => 10]);

        $supply = Supply::create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'supply_date' => now(),
            'status' => 'pending',
            'total_cost' => 100,
        ]);

        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SORT-1',
            'invoice_date' => now(),
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'total' => 100,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => now(),
            'total_cost' => 100,
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 25,
            'payment_date' => now(),
            'method' => 'cash',
        ]);

        return [
            'supplies.index' => $supply->formatted_id,
            // The orders listing prints the raw id rather than the code, so
            // the customer is what identifies the row on that page.
            'orders.index' => 'Sort Test Customer',
            'invoices.index' => 'INV-SORT-1',
            'products.index' => 'Sort Test Widget',
            'purchase-orders.index' => $purchaseOrder->formatted_id,
            'payments.index' => $invoice->invoice_number,
        ];
    }
}

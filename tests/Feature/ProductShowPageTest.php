<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PickingList;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product page renders, and the figures on it are real.
 *
 * Nothing ever loaded this page in a test, which is how it came to spend a
 * while emitting ~490 lines of itself as literal text before dying on an
 * undefined variable. The Blade cause is pinned by BladePhpDirectiveTest; this
 * pins the symptom, because a page that returns 200 while printing "@php" at
 * the reader is not actually working.
 */
class ProductShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_product_page_renders(): void
    {
        $product = Product::factory()->create(['name' => 'Widget', 'sku' => 'WIDGET-1']);

        $this->get(route('products.show', $product->id))
            ->assertOk()
            ->assertSee('Widget')
            ->assertSee('WIDGET-1');
    }

    public function test_the_product_page_leaves_no_blade_uncompiled(): void
    {
        $product = Product::factory()->create();

        $html = $this->get(route('products.show', $product->id))->assertOk()->getContent();

        // The signature of the directive mispairing: raw Blade reaching the
        // browser. Checked on the response rather than the template because
        // that is where it actually hurt.
        $this->assertStringNotContainsString('@php', $html);
        $this->assertStringNotContainsString('@endphp', $html);
        $this->assertStringNotContainsString('@forelse', $html);
        $this->assertStringNotContainsString('<?php(', $html);
    }

    public function test_the_pricing_figures_are_computed_rather_than_printed_raw(): void
    {
        $product = Product::factory()->create();

        // These live in @php blocks in the middle of the swallowed region, so
        // they are the ones that silently stopped being assigned.
        $this->get(route('products.show', $product->id))
            ->assertOk()
            ->assertDontSee('$currentPrice', false)
            ->assertDontSee('$detailPrice', false)
            ->assertDontSee('$returnTransactions', false)
            ->assertDontSee('$totalReturns', false);
    }

    /* ---------------------------------------------------------------------
     | The picking type, which is derived rather than stored
     |---------------------------------------------------------------------*/

    public function test_a_picking_lists_type_comes_from_where_it_runs_from_and_to(): void
    {
        $warehouse = Warehouse::factory()->create();
        $customer = Customer::factory()->create();

        $list = PickingList::create([
            'reference_type' => \App\Models\Order::class,
            'reference_id' => 1,
            'from_location_type' => Warehouse::class,
            'from_location_id' => $warehouse->id,
            'to_location_type' => Customer::class,
            'to_location_id' => $customer->id,
            'picking_date' => now(),
            'status' => 'pending',
        ]);

        $this->assertSame('warehouse_to_customer', $list->picking_type);
    }

    public function test_a_retailer_sourced_pick_reads_as_its_own_type(): void
    {
        $retailer = Retailer::factory()->create();
        $customer = Customer::factory()->create();

        $list = PickingList::create([
            'reference_type' => \App\Models\Order::class,
            'reference_id' => 1,
            'from_location_type' => Retailer::class,
            'from_location_id' => $retailer->id,
            'to_location_type' => Customer::class,
            'to_location_id' => $customer->id,
            'picking_date' => now(),
            'status' => 'pending',
        ]);

        $this->assertSame('retailer_to_customer', $list->picking_type);
    }

    public function test_a_pick_with_an_unknown_end_has_no_type_rather_than_a_wrong_one(): void
    {
        $list = PickingList::create([
            'reference_type' => \App\Models\Order::class,
            'reference_id' => 1,
            'picking_date' => now(),
            'status' => 'pending',
        ]);

        $this->assertNull($list->picking_type);
    }
}

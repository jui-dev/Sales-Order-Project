<?php

namespace Tests\Feature;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Pricing\PriceListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Catalog > Product Pricing screens.
 *
 * The point of the module is that every price change goes through
 * PriceListService, so what the business charged last month stays readable.
 * These pin that the screens honour it rather than writing rows directly.
 */
class ProductPricingModuleTest extends TestCase
{
    use RefreshDatabase;

    private PriceListService $lists;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lists = app(PriceListService::class);
    }

    private function retail(): PriceList
    {
        return $this->lists->defaultFor(PriceList::TYPE_SALE);
    }

    public function test_the_price_list_index_loads(): void
    {
        $this->get(route('product-pricing.index'))
            ->assertOk()
            ->assertSee('Product Pricing');
    }

    public function test_a_list_can_be_created(): void
    {
        $this->post(route('product-pricing.store'), [
            'name' => 'Wholesale',
            'code' => 'wholesale',
            'type' => 'sale',
            'priority' => 50,
        ])->assertRedirect();

        $this->assertDatabaseHas('price_lists', ['code' => 'wholesale', 'type' => 'sale']);
    }

    public function test_a_price_can_be_added_to_a_list(): void
    {
        $product = Product::factory()->create();

        $this->post(route('product-pricing.prices.add', $this->retail()->id), [
            'product_id' => $product->id,
            'unit_price' => 125.50,
        ])->assertRedirect();

        $this->assertDatabaseHas('price_list_items', [
            'product_id' => $product->id,
            'unit_price' => 125.5000,
            'ends_at' => null,
        ]);
    }

    public function test_editing_a_price_closes_the_old_row_rather_than_overwriting_it(): void
    {
        $product = Product::factory()->create();
        $original = $this->lists->setPrice($this->retail(), $product, 100.00);

        $this->put(route('product-pricing.prices.update', $this->retail()->id), [
            'rows' => [$original->id => ['unit_price' => '130.00']],
        ])->assertRedirect();

        $original->refresh();
        $this->assertNotNull($original->ends_at, 'The superseded price must be closed, not rewritten.');
        $this->assertEquals(100.00, $original->unit_price, 'And must still read at its original figure.');

        $current = PriceListItem::where('product_id', $product->id)->whereNull('ends_at')->first();
        $this->assertEquals(130.00, $current->unit_price);
    }

    public function test_a_row_cannot_be_repriced_through_another_lists_form(): void
    {
        $product = Product::factory()->create();
        $other = $this->lists->create(['name' => 'Other', 'code' => 'other', 'type' => PriceList::TYPE_SALE]);
        $row = $this->lists->setPrice($this->retail(), $product, 100.00);

        // Post the retail row's id at the other list's endpoint.
        $this->put(route('product-pricing.prices.update', $other->id), [
            'rows' => [$row->id => ['unit_price' => '1.00']],
        ])->assertRedirect();

        $this->assertEquals(100.00, $row->refresh()->unit_price, 'A tampered form must not reach another list.');
    }

    public function test_removing_a_price_keeps_its_history(): void
    {
        $product = Product::factory()->create();
        $row = $this->lists->setPrice($this->retail(), $product, 100.00);

        $this->delete(route('product-pricing.prices.remove', [$this->retail()->id, $product->id]))
            ->assertRedirect();

        $this->assertNotNull($row->refresh()->ends_at);
        $this->assertDatabaseHas('price_list_items', ['id' => $row->id, 'unit_price' => 100.0000]);
    }

    public function test_the_history_page_shows_what_a_product_has_been_priced_at(): void
    {
        $product = Product::factory()->create(['name' => 'Widget']);
        $this->lists->setPrice($this->retail(), $product, 100.00, 1, now()->subMonths(3));
        $this->lists->setPrice($this->retail(), $product, 130.00);

        $this->get(route('product-pricing.history', $product->id))
            ->assertOk()
            ->assertSee('Widget')
            ->assertSee('100.00')
            ->assertSee('130.00');
    }

    public function test_the_module_is_gated_by_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'warehouse'], ['label' => 'Warehouse']);
        $role->permissions()->sync(Permission::where('name', 'products.view')->pluck('id')->all());

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->forgetCachedPermissions();

        $this->actingAs($user)
            ->get(route('product-pricing.index'))
            ->assertForbidden();
    }

    public function test_viewing_does_not_confer_managing(): void
    {
        $product = Product::factory()->create();

        $role = Role::firstOrCreate(['name' => 'viewer'], ['label' => 'Viewer']);
        $role->permissions()->sync(Permission::where('name', 'product-pricing.view')->pluck('id')->all());

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->forgetCachedPermissions();

        $this->actingAs($user)->get(route('product-pricing.index'))->assertOk();

        $this->actingAs($user)
            ->post(route('product-pricing.prices.add', $this->retail()->id), [
                'product_id' => $product->id,
                'unit_price' => 5.00,
            ])
            ->assertForbidden();
    }
}

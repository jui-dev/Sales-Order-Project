<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Role;
use App\Models\User;
use App\Services\Pricing\PriceListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order form quotes the same price the server will accept.
 *
 * The unit price field is readonly in the browser and nowhere else. It used to
 * be inlined into the option list from products.selling_price and posted back
 * unchecked, so the figure could be stale by the time it was submitted - and
 * anyone able to reach the form could simply name their own.
 */
class OrderPricingFormTest extends TestCase
{
    use RefreshDatabase;

    private PriceListService $lists;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lists = app(PriceListService::class);
    }

    /**
     * A salesperson who may take orders but not set their own prices.
     *
     * The base TestCase signs in as an admin, and an admin holds every
     * permission - including the override. Testing the price check at all
     * therefore means stepping down to someone who does not have it.
     */
    private function actAsSalesperson(): User
    {
        $role = Role::firstOrCreate(['name' => 'sales'], ['label' => 'Sales']);
        $role->permissions()->sync(
            \App\Models\Permission::where('name', 'orders.manage')->pluck('id')->all()
        );

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->forgetCachedPermissions();

        $this->actingAs($user);

        return $user;
    }

    private function retail(): PriceList
    {
        return $this->lists->defaultFor(PriceList::TYPE_SALE);
    }

    private function payload(Product $product, Customer $customer, float $unitPrice, int $qty = 1): array
    {
        return [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'fulfillment_location_id' => Retailer::factory()->create()->id,
                    'fulfillment_location_type' => 'retailer',
                ],
            ],
        ];
    }

    public function test_the_quote_endpoint_returns_the_price_for_this_customer(): void
    {
        $product = Product::factory()->create();
        $group = CustomerGroup::create(['name' => 'Wholesale', 'code' => 'wholesale']);
        $customer = Customer::factory()->create(['customer_group_id' => $group->id]);

        $this->lists->setPrice($this->retail(), $product, 100.00);

        $wholesale = $this->lists->create([
            'name' => 'Wholesale', 'code' => 'ws', 'type' => PriceList::TYPE_SALE, 'priority' => 50,
        ]);
        $this->lists->assignTo($wholesale, $group);
        $this->lists->setPrice($wholesale, $product, 80.00);

        $this->getJson(route('orders.price-quote', [
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'quantity' => 1,
        ]))->assertOk()->assertJson([
            'priced' => true,
            'unit_price' => 80.00,
            'price_list_name' => 'Wholesale',
        ]);
    }

    public function test_the_quote_endpoint_reports_an_unpriced_product_rather_than_quoting_zero(): void
    {
        $product = Product::factory()->create(['purchase_price' => 0, 'selling_price' => 0]);

        $this->getJson(route('orders.price-quote', ['product_id' => $product->id]))
            ->assertOk()
            ->assertJson(['priced' => false]);
    }

    public function test_an_order_at_the_list_price_is_accepted(): void
    {
        $this->actAsSalesperson();
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();
        $this->lists->setPrice($this->retail(), $product, 100.00);

        $this->post(route('orders.store'), $this->payload($product, $customer, 100.00))
            ->assertRedirect();

        $this->assertSame(1, Order::count());
    }

    public function test_an_order_at_a_price_of_the_buyers_choosing_is_rejected(): void
    {
        $this->actAsSalesperson();
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();
        $this->lists->setPrice($this->retail(), $product, 100.00);

        $this->post(route('orders.store'), $this->payload($product, $customer, 1.00))
            ->assertSessionHasErrors('products.0.unit_price');

        $this->assertSame(0, Order::count(), 'Nothing should have been created.');
    }

    public function test_a_user_with_the_override_permission_may_discount(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();
        $this->lists->setPrice($this->retail(), $product, 100.00);

        $role = Role::firstOrCreate(['name' => 'sales-lead'], ['label' => 'Sales Lead']);
        // Overriding a price is authority on top of being allowed to place the
        // order at all, which is what orders.manage is for - so a sales lead
        // holds both. The route only started asking for the second one when
        // every route began checking a permission.
        $role->permissions()->sync(
            \App\Models\Permission::whereIn('name', ['orders.manage', 'orders.override-price'])
                ->pluck('id')->all()
        );

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user->forgetCachedPermissions();

        $this->actingAs($user)
            ->post(route('orders.store'), $this->payload($product, $customer, 85.00))
            ->assertRedirect();

        $this->assertEquals(85.00, Order::first()->items->first()->unit_price);
    }

    public function test_a_quantity_break_is_honoured_by_the_validator(): void
    {
        $this->actAsSalesperson();
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();

        $this->lists->setPrice($this->retail(), $product, 100.00, 1);
        $this->lists->setPrice($this->retail(), $product, 90.00, 10);

        // Ten units earn the break, so 90 is the correct price and 100 is not.
        $this->post(route('orders.store'), $this->payload($product, $customer, 90.00, 10))
            ->assertRedirect();

        $this->post(route('orders.store'), $this->payload($product, $customer, 100.00, 10))
            ->assertSessionHasErrors('products.0.unit_price');
    }

    public function test_an_order_line_records_which_price_row_it_was_charged_from(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();
        $row = $this->lists->setPrice($this->retail(), $product, 100.00);

        $this->post(route('orders.store'), $this->payload($product, $customer, 100.00));

        $this->assertSame(
            $row->id,
            Order::first()->items->first()->price_list_item_id,
            'The line should say which price row produced its figure.'
        );
    }
}

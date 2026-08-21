<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Services\Pricing\PriceListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pricing-facing pages render.
 *
 * Cheap insurance for the Blade edits: the order form lost its inlined
 * data-price and gained an async quote, and the sidebar gained a Catalog group.
 * A broken template there is invisible until someone opens the page.
 */
class PricingPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_order_form_renders_without_inlining_a_price(): void
    {
        $product = Product::factory()->create(['available_stocks' => 10]);
        Customer::factory()->create();
        app(PriceListService::class)->setPrice(
            app(PriceListService::class)->defaultFor(PriceList::TYPE_SALE),
            $product,
            100.00
        );

        $response = $this->get(route('orders.create'));

        $response->assertOk()->assertSee($product->name);
        $response->assertDontSee('data-price=', false);
    }

    public function test_the_products_page_renders(): void
    {
        Product::factory()->count(3)->create();

        $this->get(route('products.index'))->assertOk();
    }

    public function test_the_sidebar_offers_catalog_with_both_submenus(): void
    {
        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Catalog')
            ->assertSee('Product Pricing')
            ->assertSee(route('product-pricing.index'), false);
    }

    public function test_a_price_list_page_renders(): void
    {
        $list = app(PriceListService::class)->defaultFor(PriceList::TYPE_SALE);

        $this->get(route('product-pricing.show', $list->id))->assertOk()->assertSee($list->name);
    }
}

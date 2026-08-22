<?php

namespace Tests\Feature;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductCost;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

/**
 * What the products listing sorts by, and in what order.
 *
 * Most of the columns on that page are not columns on products: the selling
 * price is joined off the default sale list, the purchase price is the latest
 * row in the costing ledger, the category is a row in another table. The
 * listing offered only the ones that happened to be plain columns, and ordered
 * on them alone - so "cheapest first" opened on the products that have no price
 * at all, and a column full of ties handed back a different order for every
 * page it was asked for.
 */
class ProductListingSortTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ProductService::class);
    }

    public function test_every_offered_sort_is_a_column_the_query_can_order_by(): void
    {
        Product::factory()->count(3)->create();

        foreach (array_keys($this->service->getSortOptions()) as $field) {
            foreach (['asc', 'desc'] as $direction) {
                $page = $this->service->getFilteredProducts(
                    ['sort' => $field, 'direction' => $direction],
                );

                // getPaginatedOrEmpty() turns a QueryException into an empty
                // page rather than an error, so a sort the database rejects
                // looks exactly like a catalogue with nothing in it.
                $this->assertSame(
                    3,
                    $page->total(),
                    "Sorting by {$field} {$direction} lost the records.",
                );
            }
        }
    }

    public function test_the_offered_sorts_and_their_directions_describe_the_same_columns(): void
    {
        $this->assertSame(
            array_keys($this->service->getSortOptions()),
            array_keys($this->service->getSortDirections()),
        );
    }

    public function test_the_listing_sorts_by_the_selling_price_it_shows(): void
    {
        $this->priced('Dear', 90.00);
        $this->priced('Cheap', 10.00);
        $this->priced('Middling', 50.00);

        $this->assertSame(
            ['Cheap', 'Middling', 'Dear'],
            $this->names(['sort' => 'selling_price', 'direction' => 'asc']),
        );

        $this->assertSame(
            ['Dear', 'Middling', 'Cheap'],
            $this->names(['sort' => 'selling_price', 'direction' => 'desc']),
        );
    }

    public function test_the_listing_sorts_by_the_purchase_price_it_shows(): void
    {
        $this->costed('Expensive', 80.00);
        $this->costed('Cheap', 5.00);

        $this->assertSame(
            ['Cheap', 'Expensive'],
            $this->names(['sort' => 'purchase_price', 'direction' => 'asc']),
        );
    }

    public function test_the_listing_sorts_by_category_name_not_category_id(): void
    {
        // Created in the order that makes id and name disagree, so an ordering
        // that quietly used category_id would pass by accident.
        $zeta = ProductCategory::create(['name' => 'Zeta']);
        $alpha = ProductCategory::create(['name' => 'Alpha']);

        Product::factory()->create(['name' => 'In Zeta', 'category_id' => $zeta->id]);
        Product::factory()->create(['name' => 'In Alpha', 'category_id' => $alpha->id]);

        $this->assertSame(
            ['In Alpha', 'In Zeta'],
            $this->names(['sort' => 'category', 'direction' => 'asc']),
        );
    }

    public function test_a_product_with_no_price_sorts_last_in_both_directions(): void
    {
        $this->priced('Priced', 25.00);
        Product::factory()->create(['name' => 'Unpriced']);

        // "Not priced" is a missing figure, not a price of nothing. Sorted
        // ascending it used to head the list, so the cheapest-first view opened
        // on the products with no price on file at all.
        $this->assertSame(
            ['Priced', 'Unpriced'],
            $this->names(['sort' => 'selling_price', 'direction' => 'asc']),
        );

        $this->assertSame(
            ['Priced', 'Unpriced'],
            $this->names(['sort' => 'selling_price', 'direction' => 'desc']),
        );
    }

    public function test_pages_do_not_repeat_a_product_when_the_sort_column_ties(): void
    {
        // Every one of these ties on stock level. With nothing to break the tie
        // the database may order the ties differently for each page it is asked
        // for, and the same product turns up twice.
        foreach (range(1, 10) as $n) {
            Product::factory()->create([
                'name' => "Widget {$n}",
                'available_stocks' => 0,
            ]);
        }

        $seen = array_merge(
            $this->names(['sort' => 'available_stocks', 'direction' => 'desc'], 4, 1),
            $this->names(['sort' => 'available_stocks', 'direction' => 'desc'], 4, 2),
            $this->names(['sort' => 'available_stocks', 'direction' => 'desc'], 4, 3),
        );

        $this->assertCount(10, $seen);
        $this->assertSame($seen, array_values(array_unique($seen)));
    }

    public function test_an_unknown_sort_falls_back_rather_than_emptying_the_page(): void
    {
        Product::factory()->create(['name' => 'Still Here']);

        $this->assertSame(
            ['Still Here'],
            $this->names([
                'sort' => 'sort_category.name; drop table products',
                'direction' => 'sideways',
            ]),
        );
    }

    /** @return array<int,string> */
    private function names(array $filters, int $perPage = 20, int $page = 1): array
    {
        Paginator::currentPageResolver(fn () => $page);

        return $this->service->getFilteredProducts($filters, $perPage)
            ->pluck('name')
            ->all();
    }

    private function priced(string $name, float $price): Product
    {
        $product = Product::factory()->create(['name' => $name]);

        $list = PriceList::query()
            ->where('type', PriceList::TYPE_SALE)
            ->where('is_default', true)
            ->firstOrFail();

        PriceListItem::create([
            'price_list_id' => $list->id,
            'product_id' => $product->id,
            'min_quantity' => 1,
            'unit_price' => $price,
            'starts_at' => now()->subDay(),
        ]);

        return $product;
    }

    private function costed(string $name, float $cost): Product
    {
        $product = Product::factory()->create(['name' => $name]);

        ProductCost::create([
            'product_id' => $product->id,
            'unit_cost' => $cost,
            'quantity_on_hand' => 1,
            'effective_at' => now()->subDay(),
        ]);

        return $product;
    }
}

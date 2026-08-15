<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The JSON contract these endpoints actually serve, which is built once in
 * App\Traits\HasApiResponses and shared by every API controller:
 *
 *   empty collection  200  {status: 'empty',   message: 'No <resource> found',
 *                           data: [], total, per_page, current_page, last_page}
 *
 *   full collection   200  {status: 'success', message: '<X> retrieved successfully',
 *                           data: <paginator>, total, per_page, current_page, last_page}
 *
 *   missing record    404  {status: 'error',   message: 'No <resource> found with ID <id>',
 *                           error_code: 404}
 *
 * Pagination sits at the top level and the paginator keeps its own envelope
 * under `data`, so a full collection's rows are at `data.data`.
 */
class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_health_endpoint()
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'Sales Order Management System API is running'
                ]);
    }

    public function test_products_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/products'), 'products');

        Product::factory()->create(['name' => 'Boxed Widget']);

        $response = $this->getJson('/api/products');
        $this->assertPaginatedCollection($response, 'Products retrieved successfully');
        $response->assertJsonPath('data.data.0.name', 'Boxed Widget');

        $this->assertMissingRecord(
            $this->getJson('/api/products/999'),
            'No product found with ID 999'
        );
    }

    public function test_orders_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/orders'), 'orders');

        $this->assertMissingRecord(
            $this->getJson('/api/orders/999'),
            'No order found with ID 999'
        );
    }

    public function test_invoices_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/invoices'), 'invoices');

        $this->assertMissingRecord(
            $this->getJson('/api/invoices/999'),
            'No invoice found with ID 999'
        );
    }

    public function test_returns_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/returns'), 'returns');

        $this->assertMissingRecord(
            $this->getJson('/api/returns/999'),
            'No return found with ID 999'
        );
    }

    public function test_credit_notes_api_endpoints()
    {
        // The resource name reaches the message unformatted, hence the underscore.
        $this->assertEmptyCollection($this->getJson('/api/credit-notes'), 'credit_notes');

        $this->assertMissingRecord(
            $this->getJson('/api/credit-notes/999'),
            'No credit_note found with ID 999'
        );
    }

    public function test_debit_notes_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/debit-notes'), 'debit notes');

        $this->assertMissingRecord(
            $this->getJson('/api/debit-notes/999'),
            'No debit_note found with ID 999'
        );
    }

    public function test_payments_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/payments'), 'payments');

        $this->assertMissingRecord(
            $this->getJson('/api/payments/999'),
            'No payment found with ID 999'
        );
    }

    public function test_stock_management_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/stock-management'), 'stock transactions');

        $this->assertMissingRecord(
            $this->getJson('/api/stock-management/999'),
            'No stock transaction found with ID 999'
        );
    }

    public function test_chart_of_accounts_api_endpoints()
    {
        // Accounts are seeded by migration, so this list is never empty. It is
        // also not paginated - the accounts come back as a plain array.
        $response = $this->getJson('/api/chart-of-accounts');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        '*' => ['id', 'code', 'name', 'account_type_id'],
                    ],
                ])
                ->assertJson([
                    'status'  => 'success',
                    'message' => 'Accounts retrieved successfully',
                ]);

        $this->assertMissingRecord(
            $this->getJson('/api/chart-of-accounts/999'),
            'No account found with ID 999'
        );
    }

    public function test_customers_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/customers'), 'customers');

        Customer::factory()->create();
        $this->assertPlainCollection(
            $this->getJson('/api/customers'),
            'Customers retrieved successfully',
            ['id', 'name', 'email']
        );

        $this->assertMissingRecord(
            $this->getJson('/api/customers/999'),
            'No customer found with ID 999'
        );
    }

    public function test_vendors_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/vendors'), 'vendors');

        Vendor::factory()->create();
        $this->assertPlainCollection(
            $this->getJson('/api/vendors'),
            'Vendors retrieved successfully',
            ['id', 'name', 'email']
        );

        $this->assertMissingRecord(
            $this->getJson('/api/vendors/999'),
            'No vendor found with ID 999'
        );
    }

    public function test_warehouses_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/warehouses'), 'warehouses');

        Warehouse::factory()->create();
        $this->assertPlainCollection(
            $this->getJson('/api/warehouses'),
            'Warehouses retrieved successfully',
            ['id', 'name', 'status']
        );

        $this->assertMissingRecord(
            $this->getJson('/api/warehouses/999'),
            'No warehouse found with ID 999'
        );
    }

    public function test_supplies_api_endpoints()
    {
        $this->assertEmptyCollection($this->getJson('/api/supplies'), 'supplies');

        $this->assertMissingRecord(
            $this->getJson('/api/supplies/999'),
            'No supply found with ID 999'
        );
    }

    public function test_api_endpoints_with_filters()
    {
        Product::factory()->create(['name' => 'Filterable Widget']);

        // A filter that matches nothing still answers with the empty envelope.
        $this->assertEmptyCollection(
            $this->getJson('/api/products?search=nothing-matches-this&per_page=10'),
            'products'
        );

        // One that matches returns the paginated envelope, honouring per_page.
        $response = $this->getJson('/api/products?search=Filterable&per_page=10');
        $this->assertPaginatedCollection($response, 'Products retrieved successfully');
        $response->assertJsonPath('data.per_page', 10)
                 ->assertJsonPath('data.data.0.name', 'Filterable Widget');

        $this->assertEmptyCollection(
            $this->getJson('/api/orders?date_from=2024-01-01&date_to=2024-12-31'),
            'orders'
        );

        $this->assertEmptyCollection(
            $this->getJson('/api/stock-management?transaction_type=supply&per_page=25'),
            'stock transactions'
        );
    }

    public function test_legacy_stock_info_endpoint()
    {
        $response = $this->getJson('/api/stock-info/location/999');
        $response->assertStatus(404); // Should return 404 for non-existent warehouse
    }

    public function test_order_fulfillment_locations_endpoint()
    {
        // No product ids at all.
        $response = $this->getJson('/api/fulfillment-locations');
        $response->assertStatus(200)
                ->assertJson(['available_locations' => []]);

        // A single id arrives as a scalar query string, which still has to be
        // treated as a list of one rather than handed to whereIn as an integer.
        $response = $this->getJson('/api/fulfillment-locations?product_ids=999');
        $response->assertStatus(200)
                ->assertJson(['available_locations' => []]);

        // The array form the order screen sends.
        $response = $this->getJson('/api/fulfillment-locations?product_ids[]=999');
        $response->assertStatus(200)
                ->assertJson(['available_locations' => []]);
    }

    private function assertEmptyCollection(TestResponse $response, string $resource): void
    {
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data',
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ])
                ->assertJson([
                    'status'  => 'empty',
                    'message' => "No {$resource} found",
                    'data'    => [],
                    'total'   => 0,
                ]);
    }

    private function assertPaginatedCollection(TestResponse $response, string $message): void
    {
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'current_page',
                        'data',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ])
                ->assertJson([
                    'status'  => 'success',
                    'message' => $message,
                ]);
    }

    /**
     * Some collections are not paginated - the records come back as a plain
     * array under `data`, with no paginator envelope.
     *
     * @param  array<int,string>  $recordKeys
     */
    private function assertPlainCollection(TestResponse $response, string $message, array $recordKeys): void
    {
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        '*' => $recordKeys,
                    ],
                ])
                ->assertJson([
                    'status'  => 'success',
                    'message' => $message,
                ]);
    }

    private function assertMissingRecord(TestResponse $response, string $message): void
    {
        $response->assertStatus(404)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error_code',
                ])
                ->assertJson([
                    'status'     => 'error',
                    'message'    => $message,
                    'error_code' => 404,
                ]);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test API health endpoint
     */
    public function test_api_health_endpoint()
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'Sales Order Management System API is running'
                ]);
    }

    /**
     * Test Products API endpoints
     */
    public function test_products_api_endpoints()
    {
        // Test empty products list
        $response = $this->getJson('/api/products');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'products',
                        'pagination' => [
                            'current_page',
                            'last_page',
                            'per_page',
                            'total'
                        ]
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Products retrieved successfully'
                ]);

        // Test non-existent product
        $response = $this->getJson('/api/products/999');
        $response->assertStatus(404)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'error'
                ])
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve product'
                ]);
    }

    /**
     * Test Orders API endpoints
     */
    public function test_orders_api_endpoints()
    {
        // Test empty orders list
        $response = $this->getJson('/api/orders');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'orders',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Orders retrieved successfully'
                ]);

        // Test non-existent order
        $response = $this->getJson('/api/orders/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve order'
                ]);
    }

    /**
     * Test Invoices API endpoints
     */
    public function test_invoices_api_endpoints()
    {
        // Test empty invoices list
        $response = $this->getJson('/api/invoices');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'invoices',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Invoices retrieved successfully'
                ]);

        // Test non-existent invoice
        $response = $this->getJson('/api/invoices/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve invoice'
                ]);
    }

    /**
     * Test Returns API endpoints
     */
    public function test_returns_api_endpoints()
    {
        // Test empty returns list
        $response = $this->getJson('/api/returns');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'returns',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Returns retrieved successfully'
                ]);

        // Test non-existent return
        $response = $this->getJson('/api/returns/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve return'
                ]);
    }

    /**
     * Test Credit Notes API endpoints
     */
    public function test_credit_notes_api_endpoints()
    {
        // Test empty credit notes list
        $response = $this->getJson('/api/credit-notes');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'credit_notes',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Credit notes retrieved successfully'
                ]);

        // Test non-existent credit note
        $response = $this->getJson('/api/credit-notes/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve credit note'
                ]);
    }

    /**
     * Test Debit Notes API endpoints
     */
    public function test_debit_notes_api_endpoints()
    {
        // Test empty debit notes list
        $response = $this->getJson('/api/debit-notes');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'debit_notes',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Debit notes retrieved successfully'
                ]);

        // Test non-existent debit note
        $response = $this->getJson('/api/debit-notes/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve debit note'
                ]);
    }

    /**
     * Test Payments API endpoints
     */
    public function test_payments_api_endpoints()
    {
        // Test empty payments list
        $response = $this->getJson('/api/payments');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'payments',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Payments retrieved successfully'
                ]);

        // Test non-existent payment
        $response = $this->getJson('/api/payments/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve payment'
                ]);
    }

    /**
     * Test Stock Management API endpoints
     */
    public function test_stock_management_api_endpoints()
    {
        // Test empty stock transactions list
        $response = $this->getJson('/api/stock-management');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'stock_transactions',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Stock transactions retrieved successfully'
                ]);

        // Test non-existent stock transaction
        $response = $this->getJson('/api/stock-management/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve stock transaction'
                ]);
    }

    /**
     * Test Chart of Accounts API endpoints
     */
    public function test_chart_of_accounts_api_endpoints()
    {
        // Test accounts list (should have default accounts)
        $response = $this->getJson('/api/chart-of-accounts');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'accounts'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Accounts retrieved successfully'
                ]);

        // Test non-existent account
        $response = $this->getJson('/api/chart-of-accounts/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve account'
                ]);
    }

    /**
     * Test Customers API endpoints
     */
    public function test_customers_api_endpoints()
    {
        // Test empty customers list
        $response = $this->getJson('/api/customers');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'customers',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Customers retrieved successfully'
                ]);

        // Test non-existent customer
        $response = $this->getJson('/api/customers/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve customer'
                ]);
    }

    /**
     * Test Vendors API endpoints
     */
    public function test_vendors_api_endpoints()
    {
        // Test empty vendors list
        $response = $this->getJson('/api/vendors');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'vendors',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Vendors retrieved successfully'
                ]);

        // Test non-existent vendor
        $response = $this->getJson('/api/vendors/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve vendor'
                ]);
    }

    /**
     * Test Warehouses API endpoints
     */
    public function test_warehouses_api_endpoints()
    {
        // Test empty warehouses list
        $response = $this->getJson('/api/warehouses');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'warehouses',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Warehouses retrieved successfully'
                ]);

        // Test non-existent warehouse
        $response = $this->getJson('/api/warehouses/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve warehouse'
                ]);
    }

    /**
     * Test Supplies API endpoints
     */
    public function test_supplies_api_endpoints()
    {
        // Test empty supplies list
        $response = $this->getJson('/api/supplies');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'supplies',
                        'pagination'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Supplies retrieved successfully'
                ]);

        // Test non-existent supply
        $response = $this->getJson('/api/supplies/999');
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to retrieve supply'
                ]);
    }

    /**
     * Test API endpoints with filters
     */
    public function test_api_endpoints_with_filters()
    {
        // Test products with search filter
        $response = $this->getJson('/api/products?search=test&per_page=10');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'products',
                        'pagination'
                    ]
                ]);

        // Test orders with date filters
        $response = $this->getJson('/api/orders?date_from=2024-01-01&date_to=2024-12-31');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'orders',
                        'pagination'
                    ]
                ]);

        // Test stock management with transaction type filter
        $response = $this->getJson('/api/stock-management?transaction_type=supply&per_page=25');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'stock_transactions',
                        'pagination'
                    ]
                ]);
    }

    /**
     * Test legacy stock information endpoint
     */
    public function test_legacy_stock_info_endpoint()
    {
        // This test would require a warehouse to exist, but we can test the structure
        $response = $this->getJson('/api/stock-info/location/999');
        $response->assertStatus(404); // Should return 404 for non-existent warehouse
    }

    /**
     * Test order fulfillment locations endpoint
     */
    public function test_order_fulfillment_locations_endpoint()
    {
        // Test with no product IDs
        $response = $this->getJson('/api/fulfillment-locations');
        $response->assertStatus(200)
                ->assertJson([
                    'available_locations' => []
                ]);

        // Test with invalid product IDs
        $response = $this->getJson('/api/fulfillment-locations?product_ids=999');
        $response->assertStatus(200)
                ->assertJson([
                    'available_locations' => []
                ]);
    }
} 
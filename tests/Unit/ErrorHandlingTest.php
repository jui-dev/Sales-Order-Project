<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderService;
use App\Services\ProductService;
use App\Services\InvoiceService;
use App\Exceptions\DataNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected OrderService $orderService;
    protected ProductService $productService;
    protected InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = new OrderService();
        $this->productService = new ProductService();
        $this->invoiceService = new InvoiceService();
    }

    /** @test */
    public function it_returns_empty_paginator_when_no_orders_exist()
    {
        $orders = $this->orderService->list();
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $orders);
        $this->assertEquals(0, $orders->count());
        $this->assertEquals(0, $orders->total());
        $this->assertEquals(25, $orders->perPage());
    }

    /** @test */
    public function it_returns_empty_collection_when_no_products_exist()
    {
        $products = $this->productService->list();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $products);
        $this->assertEquals(0, $products->count());
        $this->assertTrue($products->isEmpty());
    }

    /** @test */
    public function it_returns_empty_paginator_when_no_invoices_exist()
    {
        $invoices = $this->invoiceService->getFilteredInvoices();
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $invoices);
        $this->assertEquals(0, $invoices->count());
        $this->assertEquals(0, $invoices->total());
        $this->assertEquals(20, $invoices->perPage());
    }

    /** @test */
    public function it_throws_exception_when_order_not_found()
    {
        $this->expectException(DataNotFoundException::class);
        $this->expectExceptionMessage('No order found with ID 999');
        
        $this->orderService->get(999);
    }

    /** @test */
    public function it_throws_exception_when_product_not_found()
    {
        $this->expectException(DataNotFoundException::class);
        $this->expectExceptionMessage('No product found with ID 999');
        
        $this->productService->get(999);
    }

    /** @test */
    public function it_throws_exception_when_invoice_not_found()
    {
        $this->expectException(DataNotFoundException::class);
        $this->expectExceptionMessage('No invoice found with ID 999');
        
        $this->invoiceService->getInvoiceWithDetails(999);
    }

    /** @test */
    public function it_returns_empty_paginator_with_proper_structure_for_orders()
    {
        $orders = $this->orderService->list();
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $orders);
        $this->assertTrue($orders->isEmpty());
        $this->assertEquals(0, $orders->total());
        $this->assertEquals(25, $orders->perPage());
        $this->assertEquals(1, $orders->currentPage());
        $this->assertEquals(1, $orders->lastPage());
    }

    /** @test */
    public function it_returns_empty_paginator_with_proper_structure_for_products()
    {
        $products = $this->productService->getFilteredProducts();
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $products);
        $this->assertTrue($products->isEmpty());
        $this->assertEquals(0, $products->total());
        $this->assertEquals(20, $products->perPage());
        $this->assertEquals(1, $products->currentPage());
        $this->assertEquals(1, $products->lastPage());
    }

    /** @test */
    public function it_returns_empty_paginator_with_proper_structure_for_invoices()
    {
        $invoices = $this->invoiceService->getFilteredInvoices();
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $invoices);
        $this->assertTrue($invoices->isEmpty());
        $this->assertEquals(0, $invoices->total());
        $this->assertEquals(20, $invoices->perPage());
        $this->assertEquals(1, $invoices->currentPage());
        $this->assertEquals(1, $invoices->lastPage());
    }

    /** @test */
    public function it_handles_filtered_orders_with_no_results()
    {
        $filters = ['search' => 'nonexistent'];
        $orders = $this->orderService->getFilteredOrders($filters);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $orders);
        $this->assertTrue($orders->isEmpty());
        $this->assertEquals(0, $orders->total());
    }

    /** @test */
    public function it_handles_filtered_products_with_no_results()
    {
        $filters = ['search' => 'nonexistent'];
        $products = $this->productService->getFilteredProducts($filters);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $products);
        $this->assertTrue($products->isEmpty());
        $this->assertEquals(0, $products->total());
    }

    /** @test */
    public function it_handles_filtered_invoices_with_no_results()
    {
        $filters = ['search' => 'nonexistent'];
        $invoices = $this->invoiceService->getFilteredInvoices($filters);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $invoices);
        $this->assertTrue($invoices->isEmpty());
        $this->assertEquals(0, $invoices->total());
    }

    /** @test */
    public function it_returns_proper_filter_options_for_orders()
    {
        $filterOptions = $this->orderService->getFilterOptions();
        
        $this->assertIsArray($filterOptions);
        $this->assertArrayHasKey('status', $filterOptions);
        $this->assertArrayHasKey('customer_id', $filterOptions);
        $this->assertArrayHasKey('date_from', $filterOptions);
        $this->assertArrayHasKey('date_to', $filterOptions);
        
        // Check structure
        $this->assertEquals('select', $filterOptions['status']['type']);
        $this->assertEquals('Status', $filterOptions['status']['label']);
        $this->assertArrayHasKey('options', $filterOptions['status']);
    }

    /** @test */
    public function it_returns_proper_filter_options_for_products()
    {
        $filterOptions = $this->productService->getFilterOptions();
        
        $this->assertIsArray($filterOptions);
        $this->assertArrayHasKey('search', $filterOptions);
        $this->assertArrayHasKey('price_min', $filterOptions);
        $this->assertArrayHasKey('price_max', $filterOptions);
        $this->assertArrayHasKey('stock_min', $filterOptions);
        $this->assertArrayHasKey('stock_max', $filterOptions);
        
        // Check structure
        $this->assertEquals('text', $filterOptions['search']['type']);
        $this->assertEquals('Search', $filterOptions['search']['label']);
        $this->assertArrayHasKey('placeholder', $filterOptions['search']);
    }

    /** @test */
    public function it_returns_proper_sort_options_for_orders()
    {
        $sortOptions = $this->orderService->getSortOptions();
        
        $this->assertIsArray($sortOptions);
        $this->assertArrayHasKey('id', $sortOptions);
        $this->assertArrayHasKey('created_at', $sortOptions);
        $this->assertArrayHasKey('total_amount', $sortOptions);
        $this->assertArrayHasKey('status', $sortOptions);
    }

    /** @test */
    public function it_returns_proper_sort_options_for_products()
    {
        $sortOptions = $this->productService->getSortOptions();
        
        $this->assertIsArray($sortOptions);
        $this->assertArrayHasKey('id', $sortOptions);
        $this->assertArrayHasKey('name', $sortOptions);
        $this->assertArrayHasKey('sku', $sortOptions);
        $this->assertArrayHasKey('selling_price', $sortOptions);
        $this->assertArrayHasKey('available_stocks', $sortOptions);
    }
} 
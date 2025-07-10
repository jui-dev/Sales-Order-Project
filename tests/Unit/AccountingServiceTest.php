<?php

namespace Tests\Unit;

use App\Services\AccountingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->service = app(AccountingService::class);
    }

    /** @test */
    public function post_throws_exception_when_entry_not_balanced(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->post([
            ['account_code' => '1000', 'debit' => 100, 'credit' => 0],
            ['account_code' => '2000', 'debit' => 0,    'credit' => 50], // imbalance
        ]);
    }

    /** @test */
    public function post_throws_exception_when_totals_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->post([
            ['account_code' => '1000', 'debit' => 0, 'credit' => 0],
            ['account_code' => '2000', 'debit' => 0, 'credit' => 0],
        ]);
    }

    /** @test */
    public function post_throws_exception_when_account_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->post([
            ['account_code' => '9999', 'debit' => 50, 'credit' => 0], // non-existent
            ['account_code' => '1000', 'debit' => 0,  'credit' => 50],
        ]);
    }
} 
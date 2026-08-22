<?php

namespace Tests\Unit;

use App\Services\AccountingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * 2100 Sales Tax Payable stands in for "some liability account" throughout.
 * 2000 Accounts Payable is a control account, so every line against it has to
 * name the vendor it belongs to - which is the point of a control account, and
 * not what these tests are about.
 */
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
            ['account_code' => '2100', 'debit' => 0,    'credit' => 50], // imbalance
        ]);
    }

    /** @test */
    public function post_throws_exception_when_totals_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->post([
            ['account_code' => '1000', 'debit' => 0, 'credit' => 0],
            ['account_code' => '2100', 'debit' => 0, 'credit' => 0],
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

    /** @test */
    public function trial_balance_returns_correct_structure(): void
    {
        // Create a simple journal entry with posted status
        $entry = $this->service->post([
            ['account_code' => '1000', 'debit' => 100, 'credit' => 0],
            ['account_code' => '2100', 'debit' => 0,  'credit' => 100],
        ], null, null, null, 'posted');

        $trialBalance = $this->service->trialBalance();

        $this->assertIsObject($trialBalance);
        $this->assertTrue($trialBalance->has('1000'));
        $this->assertTrue($trialBalance->has('2100'));
        $this->assertEquals(100, $trialBalance['1000']['debit']);
        $this->assertEquals(100, $trialBalance['2100']['credit']);
    }
} 
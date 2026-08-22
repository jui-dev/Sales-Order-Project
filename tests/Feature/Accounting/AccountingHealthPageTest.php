<?php

namespace Tests\Feature\Accounting;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingHealthPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_it_reports_a_clean_ledger(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        Invoice::create([
            'invoice_number' => 'INV-HEALTH-1',
            'order_id'       => $order->id,
            'customer_id'    => $customer->id,
            'invoice_date'   => now(),
            'subtotal'       => 400,
            'tax'            => 60,
            'discount'       => 0,
            'total'          => 460,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->get(route('accounting.health'));

        $response->assertOk();
        $response->assertSee('Everything ties out.');
        $response->assertSee('Accounts Receivable ties to the sales ledger');
        $response->assertSee('Goods Received Not Invoiced');
        $response->assertSee('Assets = Liabilities + Equity');

        // A chart that cannot serve a posting rule is reported before a
        // document runs into it, not after.
        $response->assertDontSee('cannot serve every posting rule');
    }

    public function test_it_reports_a_backlog_of_unreviewed_manual_entries(): void
    {
        app(\App\Services\AccountingService::class)->post([
            ['account_code' => '1000', 'debit' => 100, 'credit' => 0],
            ['account_code' => '2100', 'debit' => 0, 'credit' => 100],
        ], null, 'Awaiting review');

        $response = $this->get(route('accounting.health'));

        $response->assertOk();
        // Not a fault, but it explains a statement that looks emptier than it
        // should - which is the whole reason the backlog is surfaced.
        $response->assertSee('still awaiting review');
    }
}

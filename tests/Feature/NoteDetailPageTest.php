<?php

namespace Tests\Feature;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\DebitNote;
use App\Models\Grn;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Models\Supply;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\CreditNoteService;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The credit and debit note pages both turn on a two-step posting flow -
 * posting the note writes a draft journal entry, posting that entry moves the
 * accounts - and the page has to keep those apart. These render each step.
 *
 * Notes are built through the services rather than factories, so the fixtures
 * match what approving a return actually produces.
 */
class NoteDetailPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST001',
            'purchase_price' => 50.00,
        ]);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
    }

    /**
     * An approved customer return, which raises a credit note on the way.
     */
    private function creditNote(): CreditNote
    {
        $customer = Customer::factory()->create(['name' => 'Alice Johnson']);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 125.00,
        ]);

        $return = app(ReturnService::class)->createCustomerReturn([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'return_reason' => 'defective_product',
            'return_location_type' => 'warehouse',
            'return_location_id' => $this->warehouse->id,
            'return_date' => now(),
        ]);

        app(ReturnService::class)->approveReturn($return);

        return CreditNote::where('return_transaction_id', $return->id)->firstOrFail();
    }

    /**
     * An approved vendor return, which raises a debit note on the way.
     */
    private function debitNote(): DebitNote
    {
        $vendor = Vendor::factory()->create(['name' => 'Global Tech Supplies']);
        $supply = Supply::factory()->create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $grn = Grn::factory()->create(['supply_id' => $supply->id, 'status' => 'posted']);
        $bill = SupplierBill::factory()->create([
            'vendor_id' => $vendor->id,
            'grn_id' => $grn->id,
            'status' => 'posted',
        ]);
        SupplierBillItem::create([
            'supplier_bill_id' => $bill->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_cost' => 100.00,
            'subtotal' => 2000.00,
        ]);

        // Stock has to be on hand for the return to be approvable. A customer
        // return built in the same test may already have created this row.
        \App\Models\ProductStock::updateOrCreate(
            [
                'product_id' => $this->product->id,
                'location_id' => $this->warehouse->id,
                'location_type' => Warehouse::class,
            ],
            ['quantity' => 100]
        );

        $return = app(ReturnService::class)->createVendorReturn([
            'supplier_bill_id' => $bill->id,
            'vendor_id' => $vendor->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'return_reason' => 'defective_product',
            'return_date' => now(),
        ]);

        app(ReturnService::class)->approveReturn($return);

        return DebitNote::where('return_transaction_id', $return->id)->firstOrFail();
    }

    /** @test */
    public function it_renders_an_issued_credit_note_before_it_is_posted()
    {
        $note = $this->creditNote();

        $response = $this->get(route('credit-notes.show', $note));

        $response->assertOk();
        $response->assertSee('Credit Note ' . $note->formatted_id);
        // No journal entry yet, so the page must not imply any financial effect.
        $response->assertSee('no effect on the');
        $response->assertSee('No journal entry yet. It is created when the credit note is posted.');
        $response->assertSee('Post Credit Note');
    }

    /** @test */
    public function it_shows_the_draft_ledger_once_a_credit_note_is_posted()
    {
        $note = $this->creditNote();

        app(CreditNoteService::class)->postCreditNote($note);

        $response = $this->get(route('credit-notes.show', $note->fresh()));

        $response->assertOk();
        // A draft entry exists but must be reported as having no effect yet.
        $response->assertSee('has not been posted');
        $response->assertSee('Post Journal Entry');
    }

    /** @test */
    public function it_renders_an_issued_debit_note()
    {
        $note = $this->debitNote();

        $response = $this->get(route('debit-notes.show', $note));

        $response->assertOk();
        $response->assertSee('Debit Note ' . $note->formatted_id);
        $response->assertSee('Global Tech Supplies');
        // Vendor returns never touch cost of goods sold.
        $response->assertSee('Post Debit Note');
    }

    /** @test */
    public function both_note_pages_carry_the_workflow_rail_of_their_return()
    {
        $credit = $this->creditNote();
        $debit = $this->debitNote();

        $this->get(route('credit-notes.show', $credit))
            ->assertOk()
            ->assertSee('Record Return')
            ->assertSee('Post to Ledger');

        $this->get(route('debit-notes.show', $debit))
            ->assertOk()
            ->assertSee('Record Return')
            ->assertSee('Post to Ledger');
    }

    /** @test */
    public function a_credit_note_can_be_cancelled()
    {
        // Regression: credit_notes lost cancelled_at/cancelled_by/cancellation_reason
        // in a cleanup migration while the service kept writing them, so this
        // action failed on a missing column.
        $note = $this->creditNote();

        $response = $this->post(route('credit-notes.cancel', $note));

        $response->assertRedirect(route('credit-notes.show', $note));
        $response->assertSessionHas('success');

        $note->refresh();

        $this->assertEquals('cancelled', $note->status);
        $this->assertNotNull($note->cancelled_at);
        $this->assertEquals($this->user->id, $note->cancelled_by);
    }

    /** @test */
    public function a_cancelled_credit_note_says_so_and_offers_no_posting()
    {
        $note = $this->creditNote();
        app(CreditNoteService::class)->cancelCreditNote($note, 'Raised in error');

        $response = $this->get(route('credit-notes.show', $note->fresh()));

        $response->assertOk();
        $response->assertSee('This credit note has been cancelled.');
        $response->assertSee('Raised in error');
        $response->assertDontSee('Post Credit Note');
    }

    /** @test */
    public function a_note_whose_return_has_gone_missing_still_renders()
    {
        $note = $this->creditNote();

        // Dangling on purpose: the rail has no chain to draw, and the page has
        // to degrade rather than fatal.
        $note->returnTransaction->delete();

        $response = $this->get(route('credit-notes.show', $note->fresh()));

        $response->assertOk();
        $response->assertSee('No return recorded');
        $response->assertDontSee('Record Return');
    }
}

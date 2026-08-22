<?php

namespace Tests\Feature;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\DebitNote;
use App\Models\Grn;
use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\SupplierBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every document reference comes from the primary key, and only from there.
 *
 * Three schemes used to write the same idea: a placeholder replaced by
 * str_pad(id, 6), a count() + 1, and a digits-stripped increment of the last
 * row - into columns that HasFormattedId's accessor shadowed, so none of them
 * could be read back. Two of the three duplicate a value the moment a row is
 * deleted, on columns that are unique.
 */
class DocumentReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_supplier_bill_payment_survives_a_deleted_predecessor(): void
    {
        $service = app(SupplierBillService::class);

        $first = $this->supplierBill();
        $second = $this->supplierBill();

        $service->postSupplierBill($first);
        $service->postSupplierBill($second);

        // Deleting an earlier row is what used to be fatal: count() + 1 then
        // points back at a number the surviving row still holds, and the unique
        // index refuses the insert - taking the whole posting down with it.
        SupplierBillPayment::where('supplier_bill_id', $first->id)->delete();

        $third = $this->supplierBill();

        $service->postSupplierBill($third);

        $payment = $third->fresh()->payment;

        $this->assertNotNull($payment);
        $this->assertSame(
            'SBP-' . str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT),
            $payment->formatted_id,
        );

        // Two live payments, two distinct references.
        $this->assertNotSame($second->fresh()->payment->formatted_id, $payment->formatted_id);
    }

    public function test_a_supplier_bill_reference_comes_from_its_key(): void
    {
        $bill = $this->supplierBill();

        $this->assertSame('SB-' . str_pad((string) $bill->id, 4, '0', STR_PAD_LEFT), $bill->formatted_id);

        // The column is no longer written, so nothing can disagree with the
        // reference the whole application shows.
        $this->assertNull($bill->getRawOriginal('formatted_id'));
    }

    public function test_note_numbers_survive_a_deleted_predecessor(): void
    {
        $customer = Customer::factory()->create();

        $first = CreditNote::create([
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => now(),
            'total_amount' => 10,
        ]);

        $this->assertSame('CN-' . str_pad((string) $first->id, 6, '0', STR_PAD_LEFT), $first->fresh()->credit_note_number);

        $first->delete();

        $second = CreditNote::create([
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => now(),
            'total_amount' => 20,
        ]);

        $this->assertSame(
            'CN-' . str_pad((string) $second->id, 6, '0', STR_PAD_LEFT),
            $second->fresh()->credit_note_number,
        );
    }

    public function test_a_debit_note_can_be_raised_at_all(): void
    {
        // The creating hook called a generateFormattedId() this class does not
        // have. It survived only because the accessor made the empty() guard
        // in front of it false, so the call was never reached.
        $vendor = Vendor::factory()->create();

        $note = DebitNote::create([
            'vendor_id' => $vendor->id,
            'status' => 'issued',
            'issue_date' => now(),
            'total_amount' => 30,
        ]);

        $this->assertSame('DN-' . str_pad((string) $note->id, 6, '0', STR_PAD_LEFT), $note->fresh()->debit_note_number);
        $this->assertSame('DN-' . str_pad((string) $note->id, 4, '0', STR_PAD_LEFT), $note->formatted_id);
    }

    private function supplierBill(): SupplierBill
    {
        $vendor = Vendor::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['purchase_price' => 10]);

        $supply = Supply::create([
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'supply_date' => now(),
            'status' => 'pending',
            'total_cost' => 100,
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 10,
            'subtotal' => 100,
        ]);

        $grn = Grn::create([
            'supply_id' => $supply->id,
            'received_date' => now(),
            'status' => 'draft',
        ]);

        // Posting the GRN is what raises the bill, through GrnObserver.
        $grn->update(['status' => 'posted']);

        return SupplierBill::where('grn_id', $grn->id)->firstOrFail();
    }
}

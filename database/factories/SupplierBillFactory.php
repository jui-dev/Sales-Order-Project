<?php

namespace Database\Factories;

use App\Models\Grn;
use App\Models\SupplierBill;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierBill>
 */
class SupplierBillFactory extends Factory
{
    protected $model = SupplierBill::class;

    public function definition(): array
    {
        return [
            'formatted_id' => 'SB-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 4, '0', STR_PAD_LEFT),
            'grn_id' => Grn::factory(),
            'vendor_id' => Vendor::factory(),
            'bill_date' => now()->toDateString(),
            'description' => null,
            'total_amount' => $this->faker->randomFloat(2, 100, 5000),
            'status' => 'draft',
        ];
    }

    /**
     * A posted bill — what is owed to the vendor is confirmed.
     *
     * This sets the status directly and so skips
     * SupplierBillService::postSupplierBill(), which is the only thing that
     * raises the bill's purchase journal and fills purchase_journal_id. The
     * bill therefore looks posted but has no journal behind it.
     *
     * Anything that reverses that journal — a vendor return, a debit note —
     * must build the bill as draft and post it through the service instead, or
     * it fails with "Original purchase journal not found". See
     * ReturnAccountingIntegrityTest::postedBill() for the pattern.
     */
    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'posted',
            'posted_at' => now(),
        ]);
    }
}

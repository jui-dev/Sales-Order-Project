<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $updates = [
            '1000' => 'Physical cash on hand available for immediate use.',
            '1100' => 'Money owed by customers for goods or services delivered on credit.',
            '1200' => 'Value of products or raw materials held for sale or production.',
            '2000' => 'Amounts the business owes to suppliers for goods or services received.',
            '3000' => 'Owner’s initial and additional investment in the business.',
            '4000' => 'Income earned from selling goods or services to customers.',
            '5000' => 'Direct costs attributable to the production or purchase of goods sold.',
            '5100' => 'Cost incurred for buying raw materials or goods for resale.',
            '5200' => 'Reductions in revenue due to product returns or sales discounts granted.',
        ];

        foreach ($updates as $code => $desc) {
            DB::table('accounts')->where('code', $code)->update(['description' => $desc]);
        }
    }

    public function down(): void
    {
        // Revert descriptions to null to avoid data loss assumptions
        DB::table('accounts')->whereIn('code', ['1000','1100','1200','2000','3000','4000','5000','5100','5200'])
            ->update(['description' => null]);
    }
}; 
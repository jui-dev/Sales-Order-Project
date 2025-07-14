<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Fetch the equity account type id
        $equityTypeId = DB::table('account_types')->where('name', 'Equity')->value('id');
        if (!$equityTypeId) {
            $equityTypeId = DB::table('account_types')->insertGetId([
                'name'       => 'Equity',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Guard: if account already exists we update description
        DB::table('accounts')->updateOrInsert([
            'code' => '3010',
        ], [
            'name'            => 'Retained Earnings',
            'description'     => 'Accumulated net income not distributed as dividends',
            'account_type_id' => $equityTypeId,
            'parent_id'       => null,
            'opening_balance' => 0,
            'is_contra'       => false,
            'updated_at'      => now(),
            'created_at'      => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '3010')->delete();
    }
}; 
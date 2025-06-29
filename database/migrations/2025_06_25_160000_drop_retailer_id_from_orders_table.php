<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'retailer_id')) {
                $table->dropForeign(['retailer_id']);
                $table->dropColumn('retailer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'retailer_id')) {
                $table->foreignId('retailer_id')->nullable()->constrained('retailers')->nullOnDelete();
            }
        });
    }
}; 
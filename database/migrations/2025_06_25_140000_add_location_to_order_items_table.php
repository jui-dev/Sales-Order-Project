<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('order_items', 'location_type')) {
                $table->string('location_type')->nullable()->after('location_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'location_type')) {
                $table->dropColumn('location_type');
            }
            if (Schema::hasColumn('order_items', 'location_id')) {
                $table->dropColumn('location_id');
            }
        });
    }
}; 
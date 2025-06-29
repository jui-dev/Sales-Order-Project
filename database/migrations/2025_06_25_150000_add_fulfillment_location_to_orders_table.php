<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'fulfillment_location_id')) {
                $table->unsignedBigInteger('fulfillment_location_id')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('orders', 'fulfillment_location_type')) {
                $table->string('fulfillment_location_type')->nullable()->after('fulfillment_location_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'fulfillment_location_type')) {
                $table->dropColumn('fulfillment_location_type');
            }
            if (Schema::hasColumn('orders', 'fulfillment_location_id')) {
                $table->dropColumn('fulfillment_location_id');
            }
        });
    }
}; 
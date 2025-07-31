<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('id');
            $table->string('location_source')->nullable()->after('location_id'); // 'warehouse' or 'retailer'
            
            // Add index for better performance
            $table->index(['location_id', 'location_source'], 'stock_locations_location_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->dropIndex('stock_locations_location_index');
            $table->dropColumn(['location_id', 'location_source']);
        });
    }
};

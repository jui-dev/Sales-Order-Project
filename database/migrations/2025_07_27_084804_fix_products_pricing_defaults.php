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
        // First, update existing NULL values to 0.00
        DB::table('products')
            ->whereNull('purchase_price')
            ->update(['purchase_price' => 0.00]);
            
        DB::table('products')
            ->whereNull('gross_profit')
            ->update(['gross_profit' => 0.00]);
        
        // Then modify the columns to have default values and NOT NULL
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->default(0.00)->change();
            $table->decimal('gross_profit', 10, 2)->default(0.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->nullable()->change();
            $table->decimal('gross_profit', 10, 2)->nullable()->change();
        });
    }
};

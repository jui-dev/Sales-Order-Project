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
        Schema::table('products', function (Blueprint $table) {
            // Check if profit_margin exists and markup doesn't exist
            if (Schema::hasColumn('products', 'profit_margin') && !Schema::hasColumn('products', 'markup')) {
                $table->renameColumn('profit_margin', 'markup');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Check if markup exists and profit_margin doesn't exist
            if (Schema::hasColumn('products', 'markup') && !Schema::hasColumn('products', 'profit_margin')) {
                $table->renameColumn('markup', 'profit_margin');
            }
        });
    }
};

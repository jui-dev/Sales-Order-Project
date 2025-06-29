<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Rename existing columns only if they exist
            if (Schema::hasColumn('products', 'price')) {
                $table->renameColumn('price', 'selling_price');
            }
            if (Schema::hasColumn('products', 'quantity')) {
                $table->renameColumn('quantity', 'available_stocks');
            }

            // Add new pricing and profitability related columns
            $table->decimal('purchase_price', 10, 2)->nullable()->after('selling_price');
            $table->decimal('gross_profit', 10, 2)->nullable()->after('purchase_price');
            $table->decimal('profit_margin', 5, 2)->nullable()->comment('Percentage')->after('gross_profit');
            $table->boolean('auto_pricing_enabled')->default(false)->after('profit_margin');
            $table->timestamp('last_price_update')->nullable()->after('auto_pricing_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Revert column name changes
            if (Schema::hasColumn('products', 'selling_price')) {
                $table->renameColumn('selling_price', 'price');
            }
            if (Schema::hasColumn('products', 'available_stocks')) {
                $table->renameColumn('available_stocks', 'quantity');
            }

            // Drop the newly added columns
            $table->dropColumn([
                'purchase_price',
                'gross_profit',
                'profit_margin',
                'auto_pricing_enabled',
                'last_price_update',
            ]);
        });
    }
}; 
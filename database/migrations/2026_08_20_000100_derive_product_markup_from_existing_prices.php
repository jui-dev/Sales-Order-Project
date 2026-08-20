<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move pricing from "typed in by hand" to "derived from cost + markup".
     *
     * Selling price used to be entered on the product form. It is now worked
     * out as purchase_price + markup% when goods are received, so the markup
     * has to be recovered from the prices already on file - otherwise every
     * existing product would silently reprice the next time it was received.
     *
     * auto_pricing_enabled also flips to true by default. It was false, and
     * only the (now deleted) SupplyItemObserver ever turned it on; leaving it
     * false would mean no product ever derived a price again.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('auto_pricing_enabled')->default(true)->change();
        });

        $default = (float) config('pricing.default_markup', 25);

        // Recover the markup each product was effectively already selling at.
        DB::table('products')
            ->where('purchase_price', '>', 0)
            ->whereNull('markup')
            ->update([
                'markup' => DB::raw('ROUND((selling_price - purchase_price) / purchase_price * 100, 2)'),
            ]);

        // Nothing to derive from: fall back to the configured default.
        DB::table('products')
            ->whereNull('markup')
            ->update(['markup' => $default]);

        // A negative markup would mean the product was being sold below cost.
        // Keep the recorded selling price, but do not let the formula reproduce
        // a loss on the next receipt.
        DB::table('products')->where('markup', '<', 0)->update(['markup' => $default]);

        // Existing rows keep deriving; the column default only covers new ones.
        DB::table('products')->update(['auto_pricing_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('auto_pricing_enabled')->default(false)->change();
        });
    }
};

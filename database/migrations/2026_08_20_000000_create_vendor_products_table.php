<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which vendors can supply which products, and at what cost.
     *
     * Products are generic - the same product may be bought from several
     * vendors at different prices. Purchase cost therefore belongs to the
     * vendor/product pair rather than to the product, which is what lets a
     * purchase order price its lines from the vendor it is addressed to.
     */
    public function up(): void
    {
        Schema::create('vendor_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Nullable: a product can be assigned to a vendor before anyone has
            // agreed a price with them, and a PO line must not silently read
            // that gap as free.
            $table->decimal('unit_cost', 12, 2)->nullable();
            // The vendor's own code for the product, quoted back on orders.
            $table->string('vendor_sku')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vendor_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_products');
    }
};

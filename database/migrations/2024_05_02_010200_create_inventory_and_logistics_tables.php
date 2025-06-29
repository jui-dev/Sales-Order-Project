<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /* Warehouses */
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->timestamps();
        });

        /* Retailers */
        Schema::create('retailers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        /* Product stocks per location (warehouse or retailer) */
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Polymorphic location: warehouse_id or retailer_id via morphs
            $table->morphs('location'); // creates location_id and location_type
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'location_id', 'location_type'], 'product_location_unique');
        });

        /* Orders */
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retailer_id')->nullable()->constrained('retailers')->nullOnDelete();
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled'])->default('pending');
            $table->date('order_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        /* Order Items */
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        /* Supplies */
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->date('supply_date');
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        /* Supply Items */
        Schema::create('supply_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained('supplies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        /* GRNs (Goods Receipt Notes) */
        Schema::create('grns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained('supplies')->cascadeOnDelete();
            $table->date('received_date');
            $table->enum('status', ['draft', 'verified', 'posted'])->default('draft');
            $table->timestamps();
        });

        /* Stock Transfers */
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_location_id');
            $table->foreignId('to_location_id');
            $table->string('from_location_type');
            $table->string('to_location_type');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->date('transfer_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        /* Stock Transfer Items */
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->timestamps();
        });

        /* Picking Lists */
        Schema::create('picking_lists', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->unsignedBigInteger('picker_id')->nullable()->index();
            $table->enum('status', ['open', 'picked', 'verified', 'closed'])->default('open');
            $table->date('picking_date');
            $table->timestamps();
        });

        /* Picking List Items */
        Schema::create('picking_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picking_list_id')->constrained('picking_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->enum('status', ['pending', 'picked', 'short'])->default('pending');
            $table->timestamps();
        });

        /* Returns */
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->enum('status', ['initiated', 'received', 'completed'])->default('initiated');
            $table->date('return_date');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        /* Return Items */
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        /* Stock Ledger */
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->morphs('location'); // location_id, location_type
            $table->integer('quantity');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->date('transaction_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('picking_list_items');
        Schema::dropIfExists('picking_lists');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('grns');
        Schema::dropIfExists('supply_items');
        Schema::dropIfExists('supplies');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('retailers');
        Schema::dropIfExists('warehouses');
    }
}; 
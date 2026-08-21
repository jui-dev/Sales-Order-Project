<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prices become records with a lifetime instead of columns on a product.
     *
     * A product has never really had "a price". It has a cost that differs per
     * vendor, a sale price that differs per customer, group, channel and
     * quantity, and both change over time. Holding one mutable number per
     * concept on the products row meant every change rewrote the past.
     *
     * Two ideas here, deliberately kept apart:
     *
     *  - Resolution: which price applies right now, to this buyer, at this
     *    quantity. That is price_lists + price_list_assignments.
     *  - History: what a price was on a given date. That is the starts_at /
     *    ends_at pair on price_list_items, which are never UPDATEd - a change
     *    closes the current row and opens a new one.
     *
     * Historical correctness on a transaction still comes from snapshotting
     * onto its own lines. Effective dating is what lets the catalogue answer
     * "what were we charging in March", not what protects a placed order.
     *
     * String columns rather than enums for `type` and `pricing_mode`, matching
     * the move away from enum() that purchase_orders already made - see
     * 2025_08_01_000000_relax_status_enums_on_non_mysql_drivers.
     */
    public function up(): void
    {
        /* ------------------------------------------------------------------
         | Who a price can be for
         |-----------------------------------------------------------------*/

        // Retailer / wholesale / distributor pricing is a property of the kind
        // of customer, not a column per kind on the product.
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->nullable()->after('name')
                ->constrained()->nullOnDelete();
        });

        // The same product sold through a different route can carry a
        // different price - a shop counter, a marketplace, a phone order.
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('sales_channel_id')->nullable()->after('customer_id')
                ->constrained()->nullOnDelete();
        });

        /* ------------------------------------------------------------------
         | The price lists themselves
         |-----------------------------------------------------------------*/

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            // 'sale' or 'purchase'. What we charge and what we are charged are
            // different things that happen to share a shape; keeping them in
            // one table with one resolver avoids two parallel mechanisms.
            $table->string('type')->index();
            $table->char('currency', 3)->default('USD');
            // Highest priority among the lists that match wins. Convention:
            // customer-specific 100, group/wholesale 50, base retail 0.
            $table->unsignedInteger('priority')->default(0);
            // The fallback for its type when nothing more specific matches.
            // At most one per type - MariaDB has no partial unique index, so
            // PriceListService enforces it.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            // A whole list can be seasonal - a promotion runs for a fortnight
            // and then stops applying without anyone having to delete it.
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        /* ------------------------------------------------------------------
         | The prices - append-only
         |-----------------------------------------------------------------*/

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Four decimal places: a unit price is multiplied by a quantity, so
            // rounding it to two here compounds into the line total.
            $table->decimal('unit_price', 12, 4);
            // Quantity breaks. min_quantity 1 is the ordinary price; a row at
            // 100 is what a hundred-up order pays. Wholesale tiers for free.
            $table->unsignedInteger('min_quantity')->default(1);
            $table->dateTime('starts_at');
            // NULL means "in force now". A price change closes the standing row
            // by stamping this, then inserts the replacement.
            $table->dateTime('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['price_list_id', 'product_id', 'starts_at'], 'pli_list_product_start_idx');
            $table->index(['product_id', 'starts_at']);
        });

        /* ------------------------------------------------------------------
         | What makes a list apply
         |-----------------------------------------------------------------*/

        Schema::create('price_list_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            // Customer, CustomerGroup, SalesChannel, Warehouse, Retailer, Vendor.
            // A list with NO assignment rows applies to everyone - that is how
            // the base retail list and each vendor's cost list differ.
            //
            // Polymorphic on purpose: scoping prices by something new later
            // needs a row here, not a schema change.
            $table->nullableMorphs('assignable');
            $table->timestamps();

            $table->unique(
                ['price_list_id', 'assignable_type', 'assignable_id'],
                'pla_list_assignable_unique'
            );
        });

        /* ------------------------------------------------------------------
         | What the stock on hand is worth
         |-----------------------------------------------------------------*/

        // products.purchase_price conflated two different questions: what a
        // vendor charges (a quote, and per vendor) and what the goods in the
        // warehouse are worth (a costing, and one number). This is the second.
        //
        // Append-only, so "cost at time T" is answerable: the latest row with
        // effective_at <= T. That is what returns and ledger entries need in
        // order to stop being rewritten by the next delivery.
        Schema::create('product_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Moving average after this receipt, NOT the receipt's own price.
            // Taking the last receipt's cost outright is what let 5 units at
            // 200 reprice 50 units that had cost 400.
            $table->decimal('unit_cost', 12, 4);
            // The quantity the average was struck over, so the next receipt can
            // weight against it without replaying the whole ledger.
            $table->integer('quantity_on_hand')->default(0);
            $table->dateTime('effective_at');
            // The GRN that caused it, where there was one.
            $table->nullableMorphs('source');
            $table->timestamps();

            $table->index(['product_id', 'effective_at']);
        });

        /* ------------------------------------------------------------------
         | Product-level pricing policy
         |-----------------------------------------------------------------*/

        Schema::table('products', function (Blueprint $table) {
            // 'cost_plus_markup' keeps the existing behaviour: receiving goods
            // re-derives the sale price from the new cost. 'manual' means a
            // receipt moves cost only and never touches what we charge.
            //
            // Defaulting to cost_plus_markup so nothing reprices differently
            // the day this ships.
            $table->string('pricing_mode')->default('cost_plus_markup')->after('markup');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Provenance: which price row produced this line's unit_price.
            // Answers "why was it charged that?" long after the fact.
            $table->foreignId('price_list_item_id')->nullable()->after('unit_cost')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_list_item_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('pricing_mode');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_channel_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_group_id');
        });

        Schema::dropIfExists('product_costs');
        Schema::dropIfExists('price_list_assignments');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('sales_channels');
        Schema::dropIfExists('customer_groups');
    }
};

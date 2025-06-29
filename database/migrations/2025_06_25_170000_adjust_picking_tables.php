<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /* -------------------------------------------------------------
         | picking_lists table adjustments
         |-------------------------------------------------------------- */
        Schema::table('picking_lists', function (Blueprint $table) {
            // Unique human-readable reference
            if (! Schema::hasColumn('picking_lists', 'picking_number')) {
                $table->string('picking_number')->nullable()->unique()->after('id');
            }

            // From / To polymorphic columns (source → destination)
            if (! Schema::hasColumn('picking_lists', 'from_location_id')) {
                $table->unsignedBigInteger('from_location_id')->nullable()->after('picker_id');
            }
            if (! Schema::hasColumn('picking_lists', 'from_location_type')) {
                $table->string('from_location_type')->nullable()->after('from_location_id');
            }
            if (! Schema::hasColumn('picking_lists', 'to_location_id')) {
                $table->unsignedBigInteger('to_location_id')->nullable()->after('from_location_type');
            }
            if (! Schema::hasColumn('picking_lists', 'to_location_type')) {
                $table->string('to_location_type')->nullable()->after('to_location_id');
            }

            if (! Schema::hasColumn('picking_lists', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('picking_date');
            }
        });

        /* -------------------------------------------------------------
         | picking_list_items table adjustments
         |-------------------------------------------------------------- */
        Schema::table('picking_list_items', function (Blueprint $table) {
            if (Schema::hasColumn('picking_list_items', 'quantity') && ! Schema::hasColumn('picking_list_items', 'quantity_requested')) {
                $table->renameColumn('quantity', 'quantity_requested');
            }

            if (! Schema::hasColumn('picking_list_items', 'quantity_picked')) {
                $table->integer('quantity_picked')->default(0)->after('quantity_requested');
            }
        });
    }

    public function down(): void
    {
        Schema::table('picking_list_items', function (Blueprint $table) {
            if (Schema::hasColumn('picking_list_items', 'quantity_picked')) {
                $table->dropColumn('quantity_picked');
            }
            if (Schema::hasColumn('picking_list_items', 'quantity_requested') && ! Schema::hasColumn('picking_list_items', 'quantity')) {
                $table->renameColumn('quantity_requested', 'quantity');
            }
        });

        Schema::table('picking_lists', function (Blueprint $table) {
            if (Schema::hasColumn('picking_lists', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
            if (Schema::hasColumn('picking_lists', 'to_location_type')) {
                $table->dropColumn('to_location_type');
            }
            if (Schema::hasColumn('picking_lists', 'to_location_id')) {
                $table->dropColumn('to_location_id');
            }
            if (Schema::hasColumn('picking_lists', 'from_location_type')) {
                $table->dropColumn('from_location_type');
            }
            if (Schema::hasColumn('picking_lists', 'from_location_id')) {
                $table->dropColumn('from_location_id');
            }
            if (Schema::hasColumn('picking_lists', 'picking_number')) {
                $table->dropColumn('picking_number');
            }
        });
    }
}; 
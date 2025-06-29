<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /* Warehouses */
        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('address');
            }
            if (!Schema::hasColumn('warehouses', 'contact_number')) {
                $table->string('contact_number')->nullable()->after('contact_person');
            }
            if (!Schema::hasColumn('warehouses', 'email')) {
                $table->string('email')->nullable()->after('contact_number');
            }
            if (!Schema::hasColumn('warehouses', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('email');
            }
            if (!Schema::hasColumn('warehouses', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('status');
            }
        });

        /* Retailers */
        Schema::table('retailers', function (Blueprint $table) {
            if (!Schema::hasColumn('retailers', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('address');
            }
            if (!Schema::hasColumn('retailers', 'contact_number')) {
                $table->string('contact_number')->nullable()->after('contact_person');
            }
            if (!Schema::hasColumn('retailers', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('contact_number');
            }
            if (!Schema::hasColumn('retailers', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('status');
            }
        });

        /* Stock Locations */
        Schema::table('stock_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_locations', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('address');
            }
            if (!Schema::hasColumn('stock_locations', 'contact_number')) {
                $table->string('contact_number')->nullable()->after('contact_person');
            }
            if (!Schema::hasColumn('stock_locations', 'email')) {
                $table->string('email')->nullable()->after('contact_number');
            }
            if (!Schema::hasColumn('stock_locations', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('email');
            }
            if (!Schema::hasColumn('stock_locations', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'contact_number', 'email', 'status', 'is_default']);
        });
        Schema::table('retailers', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'contact_number', 'status', 'is_default']);
        });
        Schema::table('stock_locations', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'contact_number', 'email', 'status', 'is_default']);
        });
    }
}; 
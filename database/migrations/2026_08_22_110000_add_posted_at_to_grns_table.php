<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give GrnService::postStock() the column its guard was already reading.
 *
 * The method opens with `if ($grn->posted_at ?? false) { return; }` and ends by
 * stamping posted_at only `if (... hasColumn(...))`. There is no such column on
 * grns, so the read was always null, the stamp never ran, and the guard against
 * posting a delivery's stock twice had never once fired - while costing a
 * schema-introspection query on every post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            $table->timestamp('posted_at')->nullable()->after('status');
        });

        // A GRN already at "posted" has had its stock booked, so it is stamped
        // as posted rather than left open to being booked a second time.
        DB::table('grns')->where('status', 'posted')->update([
            'posted_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            $table->dropColumn('posted_at');
        });
    }
};

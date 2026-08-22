<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stop four documents storing a reference nothing ever reads back.
 *
 * HasFormattedId's accessor shares its name with the real formatted_id column
 * and therefore shadows it: every read of $bill->formatted_id returned SB-0012
 * derived from the primary key, whatever the column held. So the values these
 * tables stored were write-only - and one of them, the SBP-000001 that
 * SupplierBillService built from count() + 1, would collide on its own unique
 * index the moment a payment row was deleted.
 *
 * The reference is derived from the primary key now, in one place, so the
 * columns are made nullable and left to stop being written. They are not
 * dropped: the values are historical record, and reading them back is a
 * decision for whoever wants the column gone.
 *
 * The note numbers move the same way. CN-000006 came from stripping the digits
 * out of the last row's number and adding one, which duplicates an existing
 * number as soon as a note is deleted - on a unique column, so the next note
 * cannot be raised at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->string('formatted_id')->nullable()->change();
        });

        Schema::table('supplier_bill_payments', function (Blueprint $table) {
            $table->string('formatted_id')->nullable()->change();
        });

        // Stamped from the primary key once it exists, so the column has to
        // tolerate the moment between the insert and the stamp.
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->string('credit_note_number')->nullable()->change();
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->string('debit_note_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Backfill before tightening, or a row written while the column was
        // nullable would fail the constraint on the way back.
        foreach ([
            'supplier_bills' => ['formatted_id', 'SB'],
            'supplier_bill_payments' => ['formatted_id', 'SBP'],
            'credit_notes' => ['credit_note_number', 'CN'],
            'debit_notes' => ['debit_note_number', 'DN'],
        ] as $table => [$column, $prefix]) {
            foreach (\Illuminate\Support\Facades\DB::table($table)->whereNull($column)->pluck('id') as $id) {
                \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->update([
                    $column => $prefix . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                ]);
            }
        }

        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->string('formatted_id')->nullable(false)->change();
        });

        Schema::table('supplier_bill_payments', function (Blueprint $table) {
            $table->string('formatted_id')->nullable(false)->change();
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->string('credit_note_number')->nullable(false)->change();
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->string('debit_note_number')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace the placeholder references left on generated journal entries.
 *
 * AccountingService reserved the unique `formatted_id` slot with a UUID while it
 * waited for the primary key, then tested `empty()` on the value it had just
 * filled, so the placeholder was never swapped for the real reference. The
 * column therefore held a UUID while the page displayed a code derived from the
 * id - which is why searching for the reference on screen never matched, and why
 * the uniqueness rule guarded a string no user had seen.
 *
 * The service now writes the reference properly; this brings existing rows in
 * line. Only UUID-shaped values are touched, so references typed by hand and
 * rows already corrected are left exactly as they are.
 */
return new class extends Migration
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function up(): void
    {
        DB::table('journal_entries')
            ->select('id', 'formatted_id')
            ->orderBy('id')
            ->chunk(200, function ($entries) {
                foreach ($entries as $entry) {
                    if (! $this->isPlaceholder($entry->formatted_id)) {
                        continue;
                    }

                    // Same shape the model's accessor derives, so the stored
                    // reference and the displayed one agree from here on.
                    $pad       = max(4, strlen((string) $entry->id));
                    $reference = 'JE-' . str_pad((string) $entry->id, $pad, '0', STR_PAD_LEFT);

                    // The column is unique. If something already holds the
                    // reference, leave the placeholder rather than fail the
                    // migration - a duplicate here is a data problem to look at,
                    // not something to paper over.
                    $taken = DB::table('journal_entries')
                        ->where('formatted_id', $reference)
                        ->where('id', '!=', $entry->id)
                        ->exists();

                    if ($taken) {
                        continue;
                    }

                    DB::table('journal_entries')
                        ->where('id', $entry->id)
                        ->update(['formatted_id' => $reference]);
                }
            });
    }

    public function down(): void
    {
        // The placeholders carried no meaning, so there is nothing worth
        // restoring and no way to tell which rows had one.
    }

    private function isPlaceholder(?string $value): bool
    {
        return $value !== null && preg_match(self::UUID, $value) === 1;
    }
};

<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Grn;
use App\Models\JournalEntry;
use App\Models\PickingList;
use App\Models\Retailer;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Support\Nav\EffectClassifier;
use App\Support\Nav\NavCatalog;
use Tests\TestCase;

/**
 * The sidebar does not map one-to-one onto tables: three picking screens share
 * the picking_lists table, the Returns rows are stock movements, and the
 * Vendors -> Warehouse screen has no records of its own at all. These are the
 * cases the classifier exists to get right.
 */
class EffectClassifierTest extends TestCase
{
    public function test_a_supply_lights_up_both_the_supplies_list_and_the_vendor_picking_screen(): void
    {
        $keys = EffectClassifier::keysFor(new Supply);

        $this->assertSame(['procurement.supplies', 'picking.vendor-to-warehouse'], $keys);
    }

    public function test_a_warehouse_to_retailer_picking_list_lands_on_that_screen_and_the_combined_index(): void
    {
        $list = new PickingList([
            'from_location_type' => Warehouse::class,
            'to_location_type' => Retailer::class,
        ]);

        $this->assertSame(
            ['picking.warehouse-to-retailers', 'picking.all'],
            EffectClassifier::keysFor($list),
        );
    }

    public function test_a_retailer_to_customer_picking_list_lands_on_a_different_screen(): void
    {
        $list = new PickingList([
            'from_location_type' => Retailer::class,
            'to_location_type' => Customer::class,
        ]);

        $this->assertSame(
            ['picking.retailer-to-customers', 'picking.all'],
            EffectClassifier::keysFor($list),
        );
    }

    public function test_a_picking_list_with_unrecognised_endpoints_still_reaches_the_combined_index(): void
    {
        $this->assertSame(['picking.all'], EffectClassifier::keysFor(new PickingList));
    }

    /**
     * GrnService::postStock() raises a Vendor -> Warehouse transfer, which
     * belongs on the vendor picking screen rather than the retailer one it
     * shares a table with.
     */
    public function test_an_inbound_transfer_is_told_apart_from_a_retailer_transfer(): void
    {
        $inbound = new StockTransfer([
            'from_location_type' => Vendor::class,
            'to_location_type' => Warehouse::class,
        ]);

        $outbound = new StockTransfer([
            'from_location_type' => Warehouse::class,
            'to_location_type' => Retailer::class,
        ]);

        $this->assertSame(['picking.vendor-to-warehouse'], EffectClassifier::keysFor($inbound));
        $this->assertSame(['picking.warehouse-to-retailers'], EffectClassifier::keysFor($outbound));
    }

    public function test_a_return_movement_goes_to_the_returns_menu_not_the_stock_ledger(): void
    {
        $return = new StockTransaction(['transaction_type' => 'customer_return']);

        $keys = EffectClassifier::keysFor($return);

        $this->assertSame(['returns.all', 'returns.customer_return'], $keys);
        $this->assertNotContains('stock.stock-management', $keys);
    }

    public function test_an_ordinary_movement_goes_to_the_stock_ledger(): void
    {
        $movement = new StockTransaction(['transaction_type' => 'stock_in']);

        $this->assertSame(['stock.stock-management'], EffectClassifier::keysFor($movement));
    }

    public function test_line_item_tables_are_not_tracked(): void
    {
        $this->assertFalse(EffectClassifier::tracks(new SupplyItem));
        $this->assertTrue(EffectClassifier::tracks(new Grn));
    }

    public function test_an_update_is_only_reported_when_the_status_moved(): void
    {
        $grn = new Grn(['status' => 'draft']);
        $grn->id = 1;

        // wasChanged() reports nothing on a model that has not been saved, which
        // is exactly the "incidental save" case the guard exists for.
        $this->assertNull(EffectClassifier::classify($grn, 'updated'));
        $this->assertNotNull(EffectClassifier::classify($grn, 'created'));
    }

    /**
     * AccountingService::post() seeds a journal entry with a placeholder uuid
     * in formatted_id and rewrites it with a quiet save, which fires no event.
     * Reading the stored column would name the record by that placeholder.
     */
    public function test_a_record_is_named_by_its_derived_code_not_a_placeholder_column(): void
    {
        $entry = new JournalEntry(['formatted_id' => 'd265-4274-904a-6607feed8890', 'status' => 'draft']);
        $entry->id = 30;

        $this->assertSame('JE-0030', EffectClassifier::classify($entry, 'created')['title']);
    }

    public function test_a_record_without_a_code_is_named_readably(): void
    {
        $log = new AuditLog(['action' => 'supplier_bill_posted']);
        $log->id = 45;

        $effect = EffectClassifier::classify($log, 'created');

        $this->assertSame('Audit Log #45', $effect['title']);
        $this->assertSame(['accounting.audit-logs'], $effect['keys']);
    }

    public function test_every_key_the_classifier_can_produce_exists_in_the_catalogue(): void
    {
        $models = [
            new Supply,
            new Grn,
            new PickingList(['from_location_type' => Warehouse::class, 'to_location_type' => Customer::class]),
            new StockTransfer(['from_location_type' => Vendor::class, 'to_location_type' => Warehouse::class]),
            new StockTransaction(['transaction_type' => 'vendor_return']),
            new StockTransaction(['transaction_type' => 'retailer_return']),
            new StockTransaction(['transaction_type' => 'adjustment']),
        ];

        foreach ($models as $model) {
            foreach (EffectClassifier::keysFor($model) as $key) {
                $this->assertTrue(
                    NavCatalog::has($key),
                    $model::class." classified to unknown menu key [{$key}]",
                );
            }
        }
    }
}

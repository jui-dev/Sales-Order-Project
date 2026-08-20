<?php

namespace App\Support;

use App\Models\Grn;
use App\Models\PurchaseOrder;
use App\Models\Supply;
use App\Models\SupplierBill;

/**
 * Single source of truth for a purchase's position in the supplies workflow:
 *
 *     Purchase Order -> Record Supply -> Receive Goods -> Supplier Bill -> Payment
 *
 * Every detail page in the chain renders the same five stages, so the state
 * rules live here rather than being re-derived in each Blade template.
 *
 * The first stage is optional in practice: a supply can still be recorded
 * without an order behind it (a walk-in delivery, or one entered before
 * purchase orders existed), in which case that stage reads "Not ordered".
 *
 * Returns a list of stages shaped for <x-workflow-rail>:
 *   ['name' => string, 'state' => done|current|todo, 'meta' => string, 'url' => ?string]
 */
final class SuppliesWorkflow
{
    public const DONE    = 'done';
    public const CURRENT = 'current';
    public const TODO    = 'todo';

    /** Stage positions, used to suppress the self-link on the current page. */
    public const STAGE_ORDER   = 0;
    public const STAGE_SUPPLY  = 1;
    public const STAGE_RECEIVE = 2;
    public const STAGE_BILL    = 3;
    public const STAGE_PAYMENT = 4;

    /**
     * Stages as seen from the purchase order detail page.
     */
    public static function forPurchaseOrder(PurchaseOrder $order): array
    {
        // An order can be delivered across several supplies; the rail follows
        // the most recent one, which is the delivery the reader just made.
        $supply = $order->supplies->sortByDesc('id')->first();
        $grn = $supply?->grn;

        $stages = self::build(
            order: $order,
            supply: $supply,
            grn: $grn,
            bill: $grn?->supplierBill,
            selfStage: self::STAGE_ORDER,
        );

        // build() marks any existing order as done, which is right once the
        // reader is further along. On this page an order that has not been
        // fully received is the stage they are standing on.
        if (! in_array($order->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED], true)) {
            $stages[self::STAGE_ORDER]['state'] = self::CURRENT;
        }

        return $stages;
    }

    /**
     * Stages as seen from the supply detail page.
     */
    public static function forSupply(Supply $supply): array
    {
        $grn = $supply->grn;

        $stages = self::build(
            order: $supply->purchaseOrder,
            supply: $supply,
            grn: $grn,
            bill: $grn?->supplierBill,
            selfStage: self::STAGE_SUPPLY,
        );

        // build() marks any recorded supply as done, which is right when the reader is
        // further along the chain. On this page a supply that is still pending is the
        // stage they are actually standing on.
        if ($supply->status !== 'completed') {
            $stages[self::STAGE_SUPPLY]['state'] = self::CURRENT;
        }

        return $stages;
    }

    /**
     * Stages as seen from the GRN detail page.
     */
    public static function forGrn(Grn $grn): array
    {
        return self::build(
            order: $grn->supply?->purchaseOrder,
            supply: $grn->supply,
            grn: $grn,
            bill: $grn->supplierBill,
            selfStage: self::STAGE_RECEIVE,
        );
    }

    /**
     * Stages as seen from a supplier bill page.
     *
     * $selfStage lets the bill view suppress its own link, and the payment
     * view suppress the payment link.
     */
    public static function forBill(SupplierBill $bill, int $selfStage = self::STAGE_BILL): array
    {
        $grn = $bill->grn;

        return self::build(
            order: $grn?->supply?->purchaseOrder,
            supply: $grn?->supply,
            grn: $grn,
            bill: $bill,
            selfStage: $selfStage,
        );
    }

    private static function build(
        ?PurchaseOrder $order,
        ?Supply $supply,
        ?Grn $grn,
        ?SupplierBill $bill,
        int $selfStage
    ): array {
        // 'paid' is not a bill status - the enum is draft|posted only (see
        // 2025_07_17_103924_update_supplier_bills_status_enum_remove_paid).
        // Settlement lives on the payment record.
        $received   = $grn && $grn->status === 'posted';
        $billPosted = $bill && $bill->status === 'posted';
        $billPaid   = $bill && $bill->isPaid();

        $stages = [
            [
                'name'  => 'Purchase Order',
                'state' => $order ? self::DONE : self::TODO,
                'meta'  => $order
                    ? (PurchaseOrder::statuses()[$order->status] ?? ucfirst($order->status))
                    : 'Not ordered',
                'url'   => $order ? route('purchase-orders.show', $order->id) : null,
            ],
            [
                'name'  => 'Record Supply',
                'state' => $supply ? self::DONE : self::TODO,
                'meta'  => $supply
                    ? (optional($supply->supply_date)->format('M d, Y') ?: 'Recorded')
                    : 'Not recorded',
                'url'   => $supply ? route('supplies.show', $supply->id) : null,
            ],
            [
                'name'  => 'Receive Goods',
                'state' => $received ? self::DONE : ($grn ? self::CURRENT : self::TODO),
                'meta'  => $received
                    ? (optional($grn->received_date)->format('M d, Y') ?: 'Received')
                    : ($grn ? 'Awaiting posting' : 'Not received'),
                'url'   => $grn ? route('grns.show', $grn->id) : null,
            ],
            [
                'name'  => 'Supplier Bill',
                'state' => $billPosted ? self::DONE : ($bill ? self::CURRENT : self::TODO),
                'meta'  => $bill
                    ? ($bill->formatted_id ?? '#' . $bill->id)
                    : 'Created when the GRN is posted',
                'url'   => $bill ? route('supplier-bills.show', $bill) : null,
            ],
            [
                'name'  => 'Payment',
                'state' => $billPaid ? self::DONE : ($billPosted ? self::CURRENT : self::TODO),
                'meta'  => $billPaid
                    ? (optional($bill->payment?->paid_at)->format('M d, Y') ?: 'Paid')
                    : ($billPosted ? 'Due to vendor' : 'Not yet due'),
                'url'   => $billPosted ? route('supplier-bills.payment-info', $bill) : null,
            ],
        ];

        // Never link a stage to the page the reader is already on.
        if (isset($stages[$selfStage])) {
            $stages[$selfStage]['url'] = null;
        }

        return $stages;
    }
}

<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Order;

/**
 * Single source of truth for a sales order's position in the fulfilment chain:
 *
 *     Record Order -> Confirm Order -> Pick & Dispatch -> Invoice -> Payment
 *
 * The sibling of App\Support\SuppliesWorkflow, for the selling side. Returns a
 * list of stages shaped for <x-workflow-rail>:
 *   ['name' => string, 'state' => done|current|todo, 'meta' => string, 'url' => ?string]
 */
final class OrderWorkflow
{
    public const DONE    = 'done';
    public const CURRENT = 'current';
    public const TODO    = 'todo';

    /** Stage positions, used to suppress links back to the current page. */
    public const STAGE_ORDER   = 0;
    public const STAGE_CONFIRM = 1;
    public const STAGE_PICK    = 2;
    public const STAGE_INVOICE = 3;
    public const STAGE_PAYMENT = 4;

    /**
     * Stages for an order, seen from the page named by $selfStage.
     */
    public static function forOrder(Order $order, int $selfStage = self::STAGE_ORDER): array
    {
        $picking = $order->pickingList;
        $invoice = $order->invoice;

        $cancelled = $order->status === 'cancelled';
        $confirmed = in_array($order->status, ['confirmed', 'processing', 'completed'], true);

        // Stock leaves the fulfilment location when the picking list completes -
        // PickingListObserver posts the movement, so the picking list is what
        // says whether the goods have actually gone, not the order status.
        $picked = $picking && in_array($picking->status, ['completed', 'closed', 'verified'], true);
        $paid   = $invoice && $invoice->payment_status === 'paid';

        $orderUrl   = route('orders.show', $order->id);
        $pickingUrl = $picking ? route('warehouse-to-customer-picking.show', $picking->id) : null;
        $invoiceUrl = $invoice ? route('invoices.show', $invoice->id) : null;

        $stages = [
            [
                'name'  => 'Record Order',
                'state' => self::DONE,
                'meta'  => optional($order->order_date)->format('M d, Y') ?: 'Recorded',
                'url'   => $orderUrl,
            ],
            [
                // Confirming reserves stock and raises the picking list, and it is
                // done from the order page, so this stage points back there.
                'name'  => 'Confirm Order',
                'state' => $confirmed ? self::DONE : ($cancelled ? self::TODO : self::CURRENT),
                'meta'  => $confirmed
                    ? ($picking ? 'Stock reserved' : 'Confirmed')
                    : ($cancelled ? 'Order cancelled' : 'Awaiting confirmation'),
                'url'   => $orderUrl,
            ],
            [
                'name'  => 'Pick & Dispatch',
                'state' => $picked ? self::DONE : ($picking ? self::CURRENT : self::TODO),
                'meta'  => $picked
                    ? (optional($picking->completed_at)->format('M d, Y') ?: 'Picked')
                    : ($picking ? 'Awaiting picking' : 'Not started'),
                'url'   => $pickingUrl,
            ],
            [
                'name'  => 'Invoice',
                'state' => $invoice ? self::DONE : ($picked ? self::CURRENT : self::TODO),
                'meta'  => $invoice
                    ? ($invoice->invoice_number ?: $invoice->formatted_id)
                    : 'Raised when picking completes',
                'url'   => $invoiceUrl,
            ],
            [
                // The payment form lives on the invoice, so both money stages
                // share a destination.
                'name'  => 'Payment',
                'state' => $paid ? self::DONE : ($invoice ? self::CURRENT : self::TODO),
                'meta'  => $paid
                    ? (optional($invoice->paid_at)->format('M d, Y') ?: 'Paid')
                    : ($invoice ? self::outstandingMeta($invoice) : 'Not yet due'),
                'url'   => $invoiceUrl,
            ],
        ];

        // Never link a stage to the page the reader is already on. Several stages
        // can share one page (confirming happens on the order, paying on the
        // invoice), so match on the destination rather than the index.
        $selfUrl = $stages[$selfStage]['url'] ?? null;

        if ($selfUrl !== null) {
            foreach ($stages as $index => $stage) {
                if ($stage['url'] === $selfUrl) {
                    $stages[$index]['url'] = null;
                }
            }
        }

        return $stages;
    }

    /**
     * What is still owed on a raised invoice.
     *
     * PaymentService writes 'partially_paid' once a payment lands that does not
     * cover the total, so a part-paid invoice does not read as untouched.
     */
    private static function outstandingMeta(Invoice $invoice): string
    {
        return $invoice->payment_status === 'partially_paid'
            ? 'Part paid'
            : 'Due from customer';
    }
}

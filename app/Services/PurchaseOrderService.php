<?php

namespace App\Services;

use App\Exceptions\DataNotFoundException;
use App\Models\PurchaseOrder;
use App\Models\Supply;
use App\Models\VendorProduct;
use App\Traits\HasErrorHandling;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * The warehouse side of procurement: asking a vendor for goods.
 *
 * Deliberately does nothing to stock or pricing. An order is a statement of
 * intent - stock arrives at the GRN, and a product's cost is only rewritten
 * there too (GrnService::applyReceivedCost).
 */
class PurchaseOrderService
{
    use HasErrorHandling;

    public function list(array $filters = []): Collection
    {
        return $this->handleServiceOperation(
            function () use ($filters) {
                return PurchaseOrder::with(['vendor', 'warehouse', 'items'])
                    ->when(
                        ! empty($filters['status']),
                        fn ($query) => $query->where('status', $filters['status'])
                    )
                    ->when(
                        ! empty($filters['vendor_id']),
                        fn ($query) => $query->where('vendor_id', $filters['vendor_id'])
                    )
                    ->latest()
                    ->get();
            },
            'purchase orders'
        );
    }

    /**
     * The orders that have been sent to a vendor and are still waiting on goods.
     *
     * Drives the "Requested Purchase Orders" list on the supplies side, where a
     * delivery is actually recorded against one.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function awaitingSupply(): Collection
    {
        return $this->handleServiceOperation(
            function () {
                return PurchaseOrder::awaitingSupply()
                    ->with(['vendor', 'warehouse', 'items.product'])
                    ->latest()
                    ->get();
            },
            'purchase orders'
        );
    }

    public function get(int $id): PurchaseOrder
    {
        return $this->handleServiceOperation(
            function () use ($id) {
                $order = PurchaseOrder::with([
                    'vendor', 'warehouse', 'items.product', 'supplies.grn', 'creator', 'approver',
                ])->find($id);

                if (! $order) {
                    $this->logMissingData('purchase order', $id);
                    throw new DataNotFoundException('purchase order', $id);
                }

                return $order;
            },
            'purchase order',
            $id
        );
    }

    /**
     * Create an order with its lines.
     *
     * @param  array<string, mixed>  $data
     */
    public function createWithItems(array $data): PurchaseOrder
    {
        return $this->handleServiceOperation(
            function () use ($data) {
                $items = $data['products'] ?? [];
                unset($data['products']);

                return DB::transaction(function () use ($data, $items) {
                    $data['status'] = PurchaseOrder::STATUS_DRAFT;
                    $data['created_by'] = auth()->id();
                    $data['total_cost'] = 0;

                    /** @var PurchaseOrder $order */
                    $order = PurchaseOrder::create($data);

                    $this->replaceItems($order, $items);

                    return $order->load(['vendor', 'warehouse', 'items.product']);
                });
            },
            'purchase order'
        );
    }

    /**
     * Update a draft order. Approved orders are frozen - their figures are
     * what was signed off on.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateWithItems(int $id, array $data): PurchaseOrder
    {
        return $this->handleServiceOperation(
            function () use ($id, $data) {
                $items = $data['products'] ?? [];
                unset($data['products']);

                return DB::transaction(function () use ($id, $data, $items) {
                    /** @var PurchaseOrder $order */
                    $order = $this->findOrFail(PurchaseOrder::class, $id, 'purchase order');

                    if (! $order->isEditable()) {
                        throw new \Exception('Only a draft purchase order can be edited.');
                    }

                    $order->update($data);
                    $this->replaceItems($order, $items);

                    return $order->load(['vendor', 'warehouse', 'items.product']);
                });
            },
            'purchase order',
            $id
        );
    }

    public function approve(int $id): PurchaseOrder
    {
        return $this->transition(
            $id,
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_APPROVED,
            ['approved_by' => auth()->id(), 'approved_at' => now()],
            'Only a draft purchase order can be approved.'
        );
    }

    public function send(int $id): PurchaseOrder
    {
        return $this->transition(
            $id,
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_SENT,
            [],
            'A purchase order must be approved before it can be sent.'
        );
    }

    public function cancel(int $id): PurchaseOrder
    {
        return $this->handleServiceOperation(
            function () use ($id) {
                /** @var PurchaseOrder $order */
                $order = $this->findOrFail(PurchaseOrder::class, $id, 'purchase order');

                if (! $order->isCancellable()) {
                    throw new \Exception('A received or already cancelled purchase order cannot be cancelled.');
                }

                $order->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

                return $order;
            },
            'purchase order',
            $id
        );
    }

    /**
     * Record what arrived against this order.
     *
     * Creates an ordinary Supply, so the rest of the chain (GRN, supplier bill,
     * payment) is unchanged and unaware that an order came first. Received
     * quantities are then rolled up so the order can close or stay open.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordSupply(int $id, array $data, SupplyService $supplies): Supply
    {
        return $this->handleServiceOperation(
            function () use ($id, $data, $supplies) {
                return DB::transaction(function () use ($id, $data, $supplies) {
                    /** @var PurchaseOrder $order */
                    $order = $this->findOrFail(PurchaseOrder::class, $id, 'purchase order');

                    if (! $order->isReceivable()) {
                        throw new \Exception('Only a sent purchase order can have a supply recorded against it.');
                    }

                    $supply = $supplies->createWithItems([
                        'purchase_order_id' => $order->id,
                        // Vendor and destination come from the order, never the
                        // form - a delivery cannot arrive from someone else.
                        'vendor_id' => $order->vendor_id,
                        'warehouse_id' => $order->warehouse_id,
                        'supply_date' => $data['supply_date'],
                        'status' => 'pending',
                        'notes' => $data['notes'] ?? null,
                        'products' => $data['products'] ?? [],
                    ]);

                    $this->refreshReceivedQuantities($order);

                    return $supply;
                });
            },
            'purchase order',
            $id
        );
    }

    /**
     * Recount what has been received and move the order's status to match.
     *
     * Counted from the supplies themselves rather than incremented, so it stays
     * correct if a supply is later edited or removed.
     */
    public function refreshReceivedQuantities(PurchaseOrder $order): void
    {
        $order->loadMissing('items');

        $receivedByProduct = DB::table('supply_items')
            ->join('supplies', 'supply_items.supply_id', '=', 'supplies.id')
            ->where('supplies.purchase_order_id', $order->id)
            ->groupBy('supply_items.product_id')
            ->selectRaw('supply_items.product_id, SUM(supply_items.quantity) as received')
            ->pluck('received', 'product_id');

        foreach ($order->items as $item) {
            $item->update([
                'quantity_received' => (int) ($receivedByProduct[$item->product_id] ?? 0),
            ]);
        }

        // Only advance a live order - a cancelled one stays cancelled, and a
        // draft has nothing to receive against.
        if (! $order->isReceivable()) {
            return;
        }

        $order->update([
            'status' => $order->items()->get()->contains(fn ($item) => $item->outstanding() > 0)
                ? PurchaseOrder::STATUS_PARTIALLY_RECEIVED
                : PurchaseOrder::STATUS_RECEIVED,
        ]);
    }

    /**
     * The products this vendor carries, with their agreed cost, for the line
     * editor. An order can only ask for what the vendor actually sells.
     *
     * @return SupportCollection<int, object>
     */
    public function assignableProducts(int $vendorId): SupportCollection
    {
        $vendor = \App\Models\Vendor::find($vendorId);

        // Carriage comes from the pivot; the cost comes from the vendor's
        // purchase price list, where it is dated. Two stores for one number is
        // how the prices in this system drifted apart in the first place.
        $costs = $vendor ? app(VendorService::class)->currentVendorCosts($vendor) : [];

        return VendorProduct::with('product')
            ->where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (VendorProduct $row) => $row->product !== null)
            ->map(fn (VendorProduct $row) => (object) [
                'id' => $row->product->id,
                'name' => $row->product->name,
                'sku' => $row->product->sku,
                // Absent means no price agreed - deliberately not zero, which a
                // purchase order line would happily accept as free.
                'unit_cost' => $costs[$row->product_id] ?? null,
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * Rewrite an order's lines and its total in one go.
     *
     * @param  array<int, array{product_id: int, quantity: int, unit_cost: float}>  $items
     */
    private function replaceItems(PurchaseOrder $order, array $items): void
    {
        $order->items()->delete();

        $total = 0.0;
        $resolver = app(\App\Services\Pricing\PriceResolver::class);
        $vendor = $order->vendor;

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitCost = (float) $item['unit_cost'];
            $subtotal = $quantity * $unitCost;
            $total += $subtotal;

            // Which quote this line was priced from. The line keeps its own
            // unit_cost either way - this is provenance, and it is what makes a
            // quote answerable about whether it has actually been used.
            $quote = ($vendor && $product = \App\Models\Product::find($item['product_id']))
                ? $resolver->forPurchase($product, $vendor)
                : null;

            $order->items()->create([
                'product_id' => $item['product_id'],
                'quantity_ordered' => $quantity,
                'quantity_received' => 0,
                'unit_cost' => $unitCost,
                // Only when the line was actually placed at the quoted figure.
                // A hand-edited cost was not taken from that row, so linking it
                // would lock a price the order never used.
                'price_list_item_id' => ($quote && round($quote->unitPrice, 2) === round($unitCost, 2))
                    ? $quote->priceListItemId
                    : null,
                'subtotal' => $subtotal,
            ]);
        }

        $order->update(['total_cost' => $total]);
    }

    /**
     * Move an order from one specific status to the next, refusing anything else.
     *
     * @param  array<string, mixed>  $extra
     */
    private function transition(int $id, string $from, string $to, array $extra, string $message): PurchaseOrder
    {
        return $this->handleServiceOperation(
            function () use ($id, $from, $to, $extra, $message) {
                /** @var PurchaseOrder $order */
                $order = $this->findOrFail(PurchaseOrder::class, $id, 'purchase order');

                if ($order->status !== $from) {
                    throw new \Exception($message);
                }

                $order->update(array_merge(['status' => $to], $extra));

                return $order;
            },
            'purchase order',
            $id
        );
    }
}

@extends('layouts.app')
@section('page-header')
<div class="mb-4">
    <h1>Record Supply for {{ $purchaseOrder->code }}</h1>
</div>
@endsection

@section('content')

<div class="supply-form">

    <div class="supply-notice">
        <i class="bi bi-clipboard-check"></i>
        <div>
            This delivery is against
            <a href="{{ route('purchase-orders.show', $purchaseOrder->id) }}">{{ $purchaseOrder->code }}</a>.
            The vendor, the receiving warehouse and the items below all come from the order and cannot be
            changed here. Remove any line that did not arrive — it stays outstanding on the order.
        </div>
    </div>

    <div class="supply-notice">
        <i class="bi bi-info-circle"></i>
        <div>
            Supplies are created as <strong>pending</strong>. Stock reaches the warehouse only once the goods are
            received — recording a supply does not change inventory.
        </div>
    </div>

    <form action="{{ route('supplies.store') }}" method="POST">
        @csrf
        <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">

        {{-- Section 1: Supply details --}}
        <div class="card supply-card mb-4">
            <div class="card-header supply-card__header">
                <span class="supply-card__step">1</span>
                <div>
                    <h2 class="supply-card__title">Supply Details</h2>
                    <p class="supply-card__subtitle">Who you are buying from, and where the goods are headed.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="supply-panel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="vendor_id" class="form-label">Vendor <span class="text-danger">*</span></label>
                        {{-- Pinned to the order: a delivery cannot arrive from a different vendor. --}}
                        <select id="vendor_id" class="form-select" disabled>
                            <option value="{{ $purchaseOrder->vendor_id }}">{{ $purchaseOrder->vendor->name ?? 'Unknown vendor' }}</option>
                        </select>
                        <input type="hidden" name="vendor_id" value="{{ $purchaseOrder->vendor_id }}">
                        @error('vendor_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="warehouse_id" class="form-label">Receiving Warehouse <span class="text-danger">*</span></label>
                        {{-- Pinned to the order: the goods land where they were ordered to. --}}
                        <select id="warehouse_id" class="form-select" disabled>
                            <option value="{{ $purchaseOrder->warehouse_id }}">{{ $purchaseOrder->warehouse->name ?? 'Unknown warehouse' }}</option>
                        </select>
                        <input type="hidden" name="warehouse_id" value="{{ $purchaseOrder->warehouse_id }}">
                        @error('warehouse_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="supply_date" class="form-label">Supply Date <span class="text-danger">*</span></label>
                        <input type="date" name="supply_date" id="supply_date" class="form-control @error('supply_date') is-invalid @enderror"
                            value="{{ old('supply_date', date('Y-m-d')) }}" required>
                        @error('supply_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea name="notes" id="notes" class="form-control @error('notes') is-invalid @enderror" rows="3"
                            placeholder="Anything worth remembering about this purchase (optional)">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Products --}}
        <div class="card supply-card mb-4">
            <div class="card-header supply-card__header">
                <span class="supply-card__step">2</span>
                <div>
                    <h2 class="supply-card__title">Supply Items <span class="text-danger">*</span></h2>
                    <p class="supply-card__subtitle">Everything {{ $purchaseOrder->code }} is still waiting on. Remove anything that did not arrive.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="supply-panel">

                @if (empty($prefillLines))
                    <div class="supply-empty">
                        <i class="bi bi-inbox"></i>
                        <p>This order has nothing left outstanding, so there is nothing to record against it.</p>
                        <a href="{{ route('purchase-orders.show', $purchaseOrder->id) }}" class="btn btn-outline-primary">
                            Back to {{ $purchaseOrder->code }}
                        </a>
                    </div>
                @else
                <div id="supply-items">
                    @foreach ($prefillLines as $index => $line)
                        @php $lineSubtotal = $line['quantity'] * (float) $line['unit_cost']; @endphp
                        <div class="supply-item" data-subtotal="{{ number_format($lineSubtotal, 2, '.', '') }}">
                            <div class="supply-item__bar">
                                <span class="supply-item__label">Item</span>
                                <button type="button" class="remove-item" title="This item did not arrive — remove it" aria-label="Remove this item">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>

                            {{-- The line is the order's. It is shown, not asked for; these
                                 hidden fields are what actually gets submitted. --}}
                            <input type="hidden" name="products[{{ $index }}][product_id]" value="{{ $line['product_id'] }}">
                            <input type="hidden" name="products[{{ $index }}][quantity]" value="{{ $line['quantity'] }}">
                            <input type="hidden" name="products[{{ $index }}][unit_cost]" value="{{ $line['unit_cost'] }}">

                            <div class="row g-3">
                                <div class="col-lg-5">
                                    <span class="supply-field__label">Product</span>
                                    <p class="supply-field__value">{{ $line['product_name'] }}</p>
                                </div>
                                <div class="col-lg-2 col-sm-4">
                                    <span class="supply-field__label">Quantity</span>
                                    <p class="supply-field__value">
                                        {{ $line['quantity'] }}
                                        @if ($line['quantity'] !== $line['quantity_ordered'])
                                            <small class="text-muted">of {{ $line['quantity_ordered'] }} ordered</small>
                                        @endif
                                    </p>
                                </div>
                                <div class="col-lg-2 col-sm-4">
                                    <span class="supply-field__label">Unit Cost</span>
                                    <p class="supply-field__value">${{ number_format((float) $line['unit_cost'], 2) }}</p>
                                </div>
                                <div class="col-lg-3 col-sm-4">
                                    <span class="supply-field__label">Subtotal</span>
                                    <p class="supply-field__value supply-field__value--total">${{ number_format($lineSubtotal, 2) }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @endif

                </div>
            </div>
        </div>

        {{-- Section 3: Review & submit --}}
        <div class="card supply-card supply-summary mb-4">
            <div class="card-body">
                <div class="supply-summary__inner">
                    <div>
                        <span class="supply-summary__label">Total Cost</span>
                        {{-- Every figure comes from the order, so the total is known before the page is sent. --}}
                        <span class="supply-summary__value">$<span id="supply-total">{{ number_format(collect($prefillLines)->sum(fn ($line) => $line['quantity'] * (float) $line['unit_cost']), 2) }}</span></span>
                    </div>
                    <div class="supply-summary__actions">
                        <a href="{{ route('supplies.index') }}" class="btn btn-danger">Cancel</a>
                        <button type="submit" class="btn btn-primary" @disabled(empty($prefillLines))>
                            <i class="bi bi-check2-circle"></i> Record Supply
                        </button>
                    </div>
                </div>
                <p class="supply-summary__hint">
                    The supply is saved as <strong>pending</strong> until the goods are received.
                </p>
            </div>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const list = document.getElementById('supply-items');

        // Nothing outstanding: the form rendered its empty state instead.
        if (!list) {
            return;
        }

        const totalEl = document.getElementById('supply-total');

        // Each row carries its own subtotal, so the total is a sum of what is
        // still on the page rather than anything recalculated from inputs.
        function updateTotal() {
            const total = Array.from(list.querySelectorAll('.supply-item'))
                .reduce((sum, row) => sum + (parseFloat(row.dataset.subtotal) || 0), 0);

            totalEl.textContent = total.toFixed(2);
        }

        // Removing a line is the only edit this form allows: it means the item
        // did not arrive, and it stays outstanding on the order. A supply needs
        // at least one line, so the last row will not go.
        list.addEventListener('click', function (event) {
            const button = event.target.closest('.remove-item');

            if (!button || list.querySelectorAll('.supply-item').length <= 1) {
                return;
            }

            const row = button.closest('.supply-item');
            row.style.opacity = '0.5';

            setTimeout(function () {
                row.remove();
                updateTotal();
            }, 150);
        });
    });
</script>
<style>
    /* ---- Page shell ------------------------------------------------- */
    .supply-form {
        width: 100%;
    }

    .supply-notice {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        padding: 0.85rem 1.1rem;
        margin-bottom: 1.5rem;
        border-radius: 6px;
        border: 1px solid #e3e8e4;
        background-color: #f8f9fa;
        color: var(--dark-text);
        font-size: 0.925rem;
        line-height: 1.5;
    }

    .supply-notice .bi {
        color: #6c757d;
        font-size: 1.05rem;
        line-height: 1.4;
    }

    /* ---- Section cards ---------------------------------------------- */
    .supply-card:hover {
        /* keep sections calm; no lift on a form page */
        box-shadow: var(--card-shadow);
    }

    .supply-card__header {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        border-bottom: 0;
        padding-bottom: 0.35rem;
    }

    /* Light inner surface for a section's main content */
    .supply-panel {
        padding: 1.1rem;
        border-radius: 6px;
        background-color: #f5f8f6;
    }

    .supply-card__step {
        flex: 0 0 auto;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background-color: var(--primary);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-top: 1px;
    }

    .supply-card__title {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 600;
        color: var(--dark-text);
        letter-spacing: 0.01em;
    }

    .supply-card__subtitle {
        margin: 0.15rem 0 0;
        font-size: 0.825rem;
        font-weight: 400;
        color: #6c757d;
    }

    /* ---- Item rows --------------------------------------------------- */
    #supply-items {
        counter-reset: supply-item;
    }

    .supply-item {
        position: relative;
        padding: 1rem 1.1rem 1.1rem;
        margin-bottom: 1rem;
        border: 1px solid #e3e8e4;
        border-radius: 6px;
        background-color: #fff;
        transition: opacity 0.3s ease, border-color 0.2s ease;
    }

    .supply-item:focus-within {
        border-color: var(--primary-light);
    }

    .supply-item__bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.85rem;
    }

    .supply-item__label {
        counter-increment: supply-item;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: var(--primary);
    }

    .supply-item__label::after {
        content: " " counter(supply-item);
    }

    .supply-item .remove-item {
        border: 0;
        background: transparent;
        color: #9aa0a6;
        line-height: 1;
        padding: 0.3rem 0.4rem;
        border-radius: 4px;
        font-size: 0.8rem;
        transition: color 0.2s ease, background-color 0.2s ease;
    }

    .supply-item .remove-item:hover {
        color: var(--danger);
        background-color: rgba(231, 111, 81, 0.1);
    }

    /* ---- Read-only line fields --------------------------------------- */
    .supply-field__label {
        display: block;
        font-size: 0.78rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    .supply-field__value {
        font-size: 0.95rem;
        font-weight: 500;
        color: #212529;
        margin-bottom: 0;
    }

    .supply-field__value small {
        font-weight: 400;
        margin-left: 0.3rem;
    }

    .supply-field__value--total {
        font-weight: 600;
    }

    /* ---- Nothing left to record -------------------------------------- */
    .supply-empty {
        text-align: center;
        padding: 2rem 1rem;
        color: #6c757d;
    }

    .supply-empty .bi {
        font-size: 1.75rem;
        display: block;
        margin-bottom: 0.6rem;
    }

    .supply-empty p {
        margin-bottom: 1rem;
    }

    /* ---- Summary card ------------------------------------------------ */
    .supply-summary__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .supply-summary__label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: #6c757d;
    }

    .supply-summary__value {
        display: block;
        margin-top: 0.15rem;
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--dark-text);
    }

    #supply-total {
        transition: all 0.3s ease;
    }

    .supply-summary__actions {
        display: flex;
        gap: 0.6rem;
    }

    .supply-summary__hint {
        margin: 0.9rem 0 0;
        padding-top: 0.9rem;
        border-top: 1px solid #e3e8e4;
        font-size: 0.82rem;
        color: #6c757d;
    }

    /* ---- Required-field cues ----------------------------------------- */
    .text-danger {
        color: var(--danger) !important;
    }

    .form-label .text-danger {
        font-weight: 600;
        margin-left: 2px;
    }

    .form-control:required:focus,
    .form-select:required:focus {
        border-color: var(--danger);
        box-shadow: 0 0 0 0.2rem rgba(231, 111, 81, 0.25);
    }

    .supply-card__title .text-danger {
        font-size: 0.8em;
        vertical-align: super;
        margin-left: 2px;
    }

    @media (max-width: 768px) {
        .supply-item {
            padding: 0.9rem;
        }

        .supply-summary__inner {
            align-items: flex-start;
            flex-direction: column;
        }

        .supply-summary__actions {
            width: 100%;
        }

        .supply-summary__actions .btn {
            flex: 1;
        }

        .form-label .text-danger {
            font-size: 0.9em;
        }
    }
</style>
@endsection 
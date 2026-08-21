@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">{{ $product->name }}</h1>
        <p class="text-muted mb-0">Pricing &middot; {{ $product->sku }}</p>
    </div>
    <div>
        <a href="{{ route('product-pricing.history', $product->id) }}" class="btn btn-outline-secondary">History</a>
        <a href="{{ route('product-pricing.index') }}" class="btn btn-secondary">Back to Product Pricing</a>
    </div>
</div>
@endsection

@section('content')
<x-breadcrumb :items="[
    ['label' => 'Product Pricing', 'url' => route('product-pricing.index')],
    ['label' => $product->name],
]" />

{{-- Step 1. Deliberately first: a selling price is set against a cost, and
     showing them the other way round invites pricing below it. --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h6 mb-0">
                <span class="badge bg-primary me-1">1</span>
                Purchase Price List
                <span class="fw-normal text-muted">(what a vendor charges you)</span>
            </h2>
        </div>
        @if($stockCost !== null)
            <span class="small text-muted">
                Stock on hand is carried at
                <strong>${{ number_format($stockCost, 2) }}</strong>
                <i class="bi bi-info-circle" data-bs-toggle="tooltip"
                   title="The weighted average of what the units you hold actually cost."></i>
            </span>
        @endif
    </div>

    @if($vendors->isEmpty())
        <div class="card-body">
            <p class="text-muted mb-2">No vendor is recorded as supplying this product yet.</p>
            <a href="{{ route('products.show', $product->id) }}" class="btn btn-sm btn-outline-primary">
                Add a vendor on the product page
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('product-pricing.purchase.update', $product->id) }}">
            @csrf
            @method('PUT')
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th style="width: 16rem;">Unit cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vendors as $vendor)
                        <tr class="locked-row" data-locked="{{ $vendor->is_locked ? '1' : '0' }}">
                            <td class="fw-semibold">{{ $vendor->name }}</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    {{-- A quote already charged on a purchase order is
                                         locked. It can be superseded by a new price from
                                         today, but the figure itself is a matter of record
                                         and PriceListItem refuses to alter it. --}}
                                    <input type="number" step="0.01" min="0"
                                           name="vendors[{{ $vendor->id }}][unit_cost]"
                                           class="form-control purchase-cost lockable"
                                           data-vendor="{{ $vendor->id }}"
                                           data-basis-id="{{ $vendor->price_row->id ?? '' }}"
                                           value="{{ $vendor->current_cost !== null ? number_format($vendor->current_cost, 2, '.', '') : '' }}"
                                           placeholder="No price agreed"
                                           @if($vendor->is_locked) readonly @endif
                                           @cannot('product-pricing.manage') disabled @endcannot>
                                </div>
                                @if($vendor->is_locked)
                                    @can('product-pricing.manage')
                                    <button type="button" class="btn btn-link btn-sm p-0 unlock-btn">
                                        Change price
                                    </button>
                                    @endcan
                                @endif
                            </td>
                            <td>
                                @if($vendor->is_locked)
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-lock-fill me-1"></i>In use
                                    </span>
                                    <div class="small text-muted">
                                        Charged on {{ $vendor->locked_by }}
                                    </div>
                                @elseif($vendor->current_cost !== null)
                                    <span class="small text-muted">
                                        Since {{ $vendor->price_row->starts_at?->format('d M Y') }}
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark">No price agreed</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @can('product-pricing.manage')
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="small text-muted">
                    Clearing a box means no price agreed - not free. Saving starts a new cost from now and keeps the old one on file.
                </span>
                <button type="submit" class="btn btn-primary">Save purchase prices</button>
            </div>
            @endcan
        </form>
    @endif
</div>

{{-- Step 2. --}}
<div class="card mb-4">
    <div class="card-header">
        <h2 class="h6 mb-0">
            <span class="badge bg-primary me-1">2</span>
            Sale Price List
            <span class="fw-normal text-muted">(what you charge to customer)</span>
        </h2>
    </div>

    <form method="POST" action="{{ route('product-pricing.sale.update', $product->id) }}">
        @csrf
        @method('PUT')
        <div class="card-body">
            <p class="small text-muted">
                A price per fulfilment location kind. Moving stock from a warehouse to a retailer store is not a sale -
                this is what the <em>customer</em> pays, depending on where their order is fulfilled from.
            </p>

            @foreach($saleKinds as $key => $kind)
            @php($vendorOptions = $vendors->filter(fn ($v) => $v->price_row !== null))
            <div class="border rounded p-3 mb-3 sale-row" data-kind="{{ $key }}"
                 data-locked="{{ $kind['is_locked'] ? '1' : '0' }}">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="form-check form-switch">
                        <input type="hidden" name="sale[{{ $key }}][enabled]" value="0">
                        <input class="form-check-input sale-enabled" type="checkbox" role="switch"
                               id="enabled-{{ $key }}" name="sale[{{ $key }}][enabled]" value="1"
                               {{ $kind['row'] ? 'checked' : '' }}
                               @cannot('product-pricing.manage') disabled @endcannot>
                        <label class="form-check-label fw-semibold" for="enabled-{{ $key }}">
                            Fulfilment location: {{ $kind['label'] }}
                        </label>
                    </div>

                    {{-- A price already charged on an order is locked. Changing it
                         starts a NEW price from today; the charged one stays on
                         file, and PriceListItem refuses to alter it. --}}
                    @if($kind['is_locked'])
                    <div class="text-end">
                        <span class="badge bg-secondary">
                            <i class="bi bi-lock-fill me-1"></i>In use
                        </span>
                        <div class="small text-muted">Charged on {{ $kind['locked_by'] }}</div>
                        @can('product-pricing.manage')
                        <button type="button" class="btn btn-link btn-sm p-0 unlock-btn">Change price</button>
                        @endcan
                    </div>
                    @endif
                </div>

                <div class="row g-3 sale-fields">
                    <div class="col-lg-4">
                        <label class="form-label small">Based on purchase price</label>
                        <select name="sale[{{ $key }}][basis_price_list_item_id]" class="form-select form-select-sm sale-basis"
                                @cannot('product-pricing.manage') disabled @endcannot>
                            <option value="">Not based on a vendor cost</option>
                            @foreach($vendorOptions as $vendor)
                                <option value="{{ $vendor->price_row->id }}"
                                        data-cost="{{ (float) $vendor->price_row->unit_price }}"
                                        {{ (int) $kind['basis_id'] === (int) $vendor->price_row->id ? 'selected' : '' }}>
                                    {{ $vendor->name }} &mdash; ${{ number_format($vendor->current_cost, 2) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">What it was bought for, so the margin is visible.</div>
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label small">Markup %</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" min="0" class="form-control sale-markup"
                                   name="sale[{{ $key }}][markup_percent]"
                                   value="{{ $kind['markup_percent'] }}"
                                   @cannot('product-pricing.manage') disabled @endcannot>
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-check mt-1">
                            <input type="hidden" name="sale[{{ $key }}][is_auto_derived]" value="0">
                            <input class="form-check-input sale-auto" type="checkbox"
                                   id="auto-{{ $key }}" name="sale[{{ $key }}][is_auto_derived]" value="1"
                                   {{ $kind['is_auto_derived'] ? 'checked' : '' }}
                                   @cannot('product-pricing.manage') disabled @endcannot>
                            <label class="form-check-label small" for="auto-{{ $key }}">
                                Apply markup automatically
                            </label>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <label class="form-label small">Selling price</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0" class="form-control sale-price"
                                   name="sale[{{ $key }}][unit_price]"
                                   value="{{ $kind['unit_price'] !== null ? number_format($kind['unit_price'], 2, '.', '') : '' }}"
                                   @cannot('product-pricing.manage') disabled @endcannot>
                        </div>
                        <div class="form-text">Untick the box to type your own.</div>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label small">Gross profit</label>
                        <div class="fs-6 fw-semibold sale-gp py-1">&mdash;</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @can('product-pricing.manage')
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="small text-muted">
                Turning a row off stops that price applying; what it used to be stays on file.
            </span>
            <button type="submit" class="btn btn-primary">Save selling prices</button>
        </div>
        @endcan
    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    // Keep the derived price and the margin in step with what is on screen.
    // This is a convenience only - the server recomputes an auto-derived price
    // from the basis and the markup, so nothing here decides what is charged.
    function refresh(row) {
        const basis  = row.querySelector('.sale-basis');
        const markup = row.querySelector('.sale-markup');
        const price  = row.querySelector('.sale-price');
        const auto   = row.querySelector('.sale-auto');
        const gp     = row.querySelector('.sale-gp');
        const fields = row.querySelector('.sale-fields');
        const on     = row.querySelector('.sale-enabled').checked;
        // A locked row shows what was charged and stays put until the reader
        // explicitly asks to set a new price.
        const locked = row.dataset.locked === '1';

        fields.style.opacity = on ? '1' : '0.45';
        [basis, markup, price, auto].forEach(el => { if (el) el.disabled = !on || locked; });

        if (locked) {
            gp.textContent = gp.textContent || '—';
            return;
        }

        const option = basis.options[basis.selectedIndex];
        const cost = option && option.dataset.cost ? parseFloat(option.dataset.cost) : null;

        if (auto.checked && cost !== null) {
            const pct = parseFloat(markup.value) || 0;
            price.value = (cost * (1 + pct / 100)).toFixed(2);
            price.readOnly = true;
        } else {
            price.readOnly = false;
        }

        const sell = parseFloat(price.value);
        if (cost !== null && !isNaN(sell)) {
            const margin = sell - cost;
            gp.textContent = '$' + margin.toFixed(2);
            gp.className = 'fs-6 fw-semibold sale-gp py-1 ' + (margin < 0 ? 'text-danger' : 'text-success');
        } else {
            gp.textContent = '—';
            gp.className = 'fs-6 fw-semibold sale-gp py-1 text-muted';
        }
    }

    document.querySelectorAll('.sale-row').forEach(function (row) {
        ['.sale-basis', '.sale-markup', '.sale-price', '.sale-auto', '.sale-enabled'].forEach(function (sel) {
            const el = row.querySelector(sel);
            if (el) el.addEventListener('input', () => refresh(row));
            if (el) el.addEventListener('change', () => refresh(row));
        });
        refresh(row);
    });

    // "Change price" on a locked row. Deliberately an explicit action rather
    // than an editable field: what is on screen is what was charged, and typing
    // over it should feel like starting a new price, because that is what it
    // does. The old row is closed and kept, never rewritten.
    document.querySelectorAll('.unlock-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const container = button.closest('.sale-row') || button.closest('tr');
            if (!container) return;

            const confirmed = window.confirm(
                'This price has already been charged on a real order.\n\n' +
                'It cannot be altered. Continuing starts a NEW price that applies from ' +
                'today onwards - the charged one stays on file exactly as it was.\n\n' +
                'Set a new price?'
            );
            if (!confirmed) return;

            container.dataset.locked = '0';
            container.querySelectorAll('input, select').forEach(function (el) {
                el.disabled = false;
                el.readOnly = false;
            });

            button.remove();

            if (container.classList.contains('sale-row')) refresh(container);
        });
    });
})();
</script>
@endsection

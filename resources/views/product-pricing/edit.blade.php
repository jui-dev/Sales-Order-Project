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
        <h2 class="h6 mb-0">
            <span class="badge bg-primary me-1">1</span>
            Purchase Price List
            <span class="fw-normal text-muted">(what a vendor charges you)</span>
        </h2>
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
                                           class="form-control purchase-cost"
                                           value="{{ $vendor->current_cost !== null ? number_format($vendor->current_cost, 2, '.', '') : '' }}"
                                           placeholder="No price agreed"
                                           @if($vendor->is_locked) readonly @endif
                                           @cannot('product-pricing.manage') disabled @endcannot>
                                </div>
                                @if($vendor->is_locked)
                                    @can('product-pricing.manage')
                                    <button type="button" class="btn btn-link btn-sm p-0 unlock-btn">Change price</button>
                                    @endcan
                                @endif
                            </td>
                            <td>
                                @if($vendor->is_locked)
                                    <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i>In use</span>
                                    <div class="small text-muted">Charged on {{ $vendor->locked_by }}</div>
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
                A price per fulfilment location kind. Moving stock from a warehouse to a retailer store is not a
                sale &mdash; this is what the <em>customer</em> pays, depending on where their order is fulfilled from.
                Within each, you can price against every vendor cost; the one marked <strong>Charge this one</strong>
                is what an order actually pays, because pooled stock gives a sold unit no vendor identity.
            </p>

            @foreach($saleKinds as $key => $kind)
            @php($anyRow = collect($kind['lines'])->contains(fn ($l) => $l['row'] !== null))
            <div class="border rounded p-3 mb-3 sale-kind" data-kind="{{ $key }}">
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="sale[{{ $key }}][enabled]" value="0">
                    <input class="form-check-input sale-enabled" type="checkbox" role="switch"
                           id="enabled-{{ $key }}" name="sale[{{ $key }}][enabled]" value="1"
                           {{ $anyRow ? 'checked' : '' }}
                           @cannot('product-pricing.manage') disabled @endcannot>
                    <label class="form-check-label fw-semibold" for="enabled-{{ $key }}">
                        Fulfilment location: {{ $kind['label'] }}
                    </label>
                </div>

                @if(empty($kind['lines']))
                    <p class="text-muted small mb-0">
                        Set a purchase price above first &mdash; a selling price is set against what the goods cost.
                    </p>
                @else
                <div class="table-responsive sale-fields">
                    {{-- A line per vendor cost: the same product bought at 400 and at
                         200 justifies two different selling prices, and seeing both is
                         how the margin on each becomes legible. --}}
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 22%;">Based on purchase price</th>
                                <th style="width: 18%;">Markup %</th>
                                <th style="width: 20%;">Selling price</th>
                                <th style="width: 15%;">Gross profit</th>
                                <th style="width: 25%;">Charged on orders</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kind['lines'] as $line)
                            @php($basisKey = $line['basis_id'] ?? 'none')
                            <tr class="sale-line" data-locked="{{ $line['is_locked'] ? '1' : '0' }}"
                                data-cost="{{ $line['cost'] !== null ? $line['cost'] : '' }}">
                                <td>
                                    <div class="fw-semibold small">{{ $line['vendor_name'] }}</div>
                                    <div class="small text-muted">
                                        {{ $line['cost'] !== null ? '$' . number_format($line['cost'], 2) : 'no cost basis' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0" class="form-control sale-markup"
                                               name="sale[{{ $key }}][lines][{{ $basisKey }}][markup_percent]"
                                               value="{{ $line['markup_percent'] }}"
                                               @if($line['is_locked']) readonly @endif
                                               @cannot('product-pricing.manage') disabled @endcannot>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-check mt-1">
                                        <input type="hidden" name="sale[{{ $key }}][lines][{{ $basisKey }}][is_auto_derived]" value="0">
                                        <input class="form-check-input sale-auto" type="checkbox"
                                               id="auto-{{ $key }}-{{ $basisKey }}"
                                               name="sale[{{ $key }}][lines][{{ $basisKey }}][is_auto_derived]" value="1"
                                               {{ $line['is_auto_derived'] ? 'checked' : '' }}
                                               @if($line['is_locked']) disabled @endif
                                               @cannot('product-pricing.manage') disabled @endcannot>
                                        <label class="form-check-label small" for="auto-{{ $key }}-{{ $basisKey }}">
                                            Apply automatically
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control sale-price"
                                               name="sale[{{ $key }}][lines][{{ $basisKey }}][unit_price]"
                                               value="{{ $line['unit_price'] !== null ? number_format($line['unit_price'], 2, '.', '') : '' }}"
                                               placeholder="Not priced"
                                               @if($line['is_locked']) readonly @endif
                                               @cannot('product-pricing.manage') disabled @endcannot>
                                    </div>
                                </td>
                                <td class="sale-gp fw-semibold small">&mdash;</td>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input sale-charged" type="radio"
                                               id="charged-{{ $key }}-{{ $basisKey }}"
                                               name="sale[{{ $key }}][charged_basis]" value="{{ $basisKey }}"
                                               {{ $line['is_charged'] ? 'checked' : '' }}
                                               @cannot('product-pricing.manage') disabled @endcannot>
                                        <label class="form-check-label small" for="charged-{{ $key }}-{{ $basisKey }}">
                                            Charge this one
                                        </label>
                                    </div>
                                    @if($line['is_locked'])
                                        <span class="badge bg-secondary mt-1">
                                            <i class="bi bi-lock-fill me-1"></i>In use
                                        </span>
                                        <div class="small text-muted">Charged on {{ $line['locked_by'] }}</div>
                                        @can('product-pricing.manage')
                                        <button type="button" class="btn btn-link btn-sm p-0 unlock-btn">Change price</button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        @can('product-pricing.manage')
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="small text-muted">
                Turning a kind off stops all its prices applying; what they used to be stays on file.
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
    // Keep each line's derived price and margin in step with what is on screen.
    // A convenience only - the server recomputes an auto-derived price from the
    // basis and the markup, so nothing here decides what is charged.
    function refreshLine(line, enabled) {
        const markup = line.querySelector('.sale-markup');
        const price  = line.querySelector('.sale-price');
        const auto   = line.querySelector('.sale-auto');
        const gp     = line.querySelector('.sale-gp');
        const charged = line.querySelector('.sale-charged');
        const locked = line.dataset.locked === '1';
        const cost   = line.dataset.cost === '' ? null : parseFloat(line.dataset.cost);

        [markup, price, auto].forEach(function (el) {
            if (el) el.disabled = !enabled || locked;
        });
        if (charged) charged.disabled = !enabled;

        if (!locked && auto && auto.checked && cost !== null) {
            const pct = parseFloat(markup.value) || 0;
            price.value = (cost * (1 + pct / 100)).toFixed(2);
            price.readOnly = true;
        } else if (!locked) {
            price.readOnly = false;
        }

        const sell = parseFloat(price.value);
        if (cost !== null && !isNaN(sell)) {
            const margin = sell - cost;
            gp.textContent = '$' + margin.toFixed(2);
            gp.className = 'sale-gp fw-semibold small ' + (margin < 0 ? 'text-danger' : 'text-success');
        } else {
            gp.textContent = '—';
            gp.className = 'sale-gp fw-semibold small text-muted';
        }
    }

    function refreshKind(kind) {
        const enabled = kind.querySelector('.sale-enabled').checked;
        const fields = kind.querySelector('.sale-fields');
        if (fields) fields.style.opacity = enabled ? '1' : '0.45';

        kind.querySelectorAll('.sale-line').forEach(function (line) {
            refreshLine(line, enabled);
        });
    }

    document.querySelectorAll('.sale-kind').forEach(function (kind) {
        kind.addEventListener('input', function () { refreshKind(kind); });
        kind.addEventListener('change', function () { refreshKind(kind); });
        refreshKind(kind);
    });

    // "Change price" on a locked row. Deliberately an explicit action rather
    // than an editable field: what is on screen is what was charged, and typing
    // over it should feel like starting a new price, because that is what it
    // does. The old row is closed and kept, never rewritten.
    document.querySelectorAll('.unlock-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const container = button.closest('.sale-line') || button.closest('tr');
            if (!container) return;

            const confirmed = window.confirm(
                'This price has already been charged on a real order.\n\n' +
                'It cannot be altered. Continuing starts a NEW price that applies from ' +
                'today onwards - the charged one stays on file exactly as it was.\n\n' +
                'Set a new price?'
            );
            if (!confirmed) return;

            container.dataset.locked = '0';
            container.querySelectorAll('input').forEach(function (el) {
                el.disabled = false;
                el.readOnly = false;
            });

            button.remove();

            const kind = container.closest('.sale-kind');
            if (kind) refreshKind(kind);
        });
    });
})();
</script>
@endsection

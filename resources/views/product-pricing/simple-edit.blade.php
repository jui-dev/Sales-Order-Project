@extends('layouts.app')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">{{ $product->name }}</h1>
        <p class="text-muted mb-0">Pricing &middot; {{ $product->sku }}</p>
    </div>
    <a href="{{ route('product-pricing.history', $product->id) }}" class="btn btn-outline-secondary">
        <i class="bi bi-clock-history me-1"></i>History
    </a>
</div>
@endsection

@section('content')
<x-breadcrumb :items="[
    ['label' => 'Product Pricing', 'url' => route('product-pricing.index')],
    ['label' => $product->name],
]" />

<div class="row">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h6 mb-0">Price</h2>
            </div>

            <form method="POST" action="{{ route('product-pricing.update', $product->id) }}" id="simple-price-form">
                @csrf
                @method('PUT')

                <div class="card-body">
                    <p class="text-muted">
                        One purchase price, whoever supplies it. The selling price follows from it at a
                        fixed markup, so there is a single number to decide and a single number to check.
                    </p>

                    <div class="row g-3 align-items-end mb-4">
                        <div class="col-sm-6">
                            <label for="unit_cost" class="form-label fw-semibold">Purchase price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" inputmode="decimal"
                                       class="form-control form-control-lg @error('unit_cost') is-invalid @enderror"
                                       id="unit_cost" name="unit_cost"
                                       value="{{ old('unit_cost', $snapshot['cost'] !== null ? number_format($snapshot['cost'], 2, '.', '') : '') }}"
                                       placeholder="No price agreed"
                                       @cannot('product-pricing.manage') disabled @endcannot>
                                @error('unit_cost')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">What you pay for one unit.</div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Markup</label>
                            {{-- Not a field: simple mode prices the whole catalogue at one
                                 figure, and it lives in config/pricing.php. --}}
                            <div class="form-control form-control-lg bg-light text-muted"
                                 id="markup-display" data-markup="{{ $snapshot['markup'] }}">
                                {{ rtrim(rtrim(number_format($snapshot['markup'], 2, '.', ''), '0'), '.') }}%
                            </div>
                            <div class="form-text">Fixed for every product.</div>
                        </div>
                    </div>

                    <div class="border rounded bg-light p-3">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="text-uppercase small fw-semibold text-muted">Selling price</div>
                                <div class="fs-3 fw-bold text-success" id="selling-display">
                                    {{ $snapshot['derived'] !== null ? '$' . number_format($snapshot['derived'], 2) : '—' }}
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-uppercase small fw-semibold text-muted">Gross profit</div>
                                <div class="fs-3 fw-bold text-success" id="gp-display">
                                    {{ $snapshot['derived'] !== null && $snapshot['cost'] !== null
                                        ? '+$' . number_format($snapshot['derived'] - $snapshot['cost'], 2)
                                        : '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="small text-muted mt-2 mb-0" id="preview-note">
                            Selling price = purchase price + markup. Gross profit = selling price &minus; purchase price.
                        </div>
                    </div>

                    @if(! $snapshot['in_step'] && $snapshot['selling'] !== null)
                    {{-- A price set under the full per-vendor editor need not match
                         the markup. Saying so beats showing the derived figure as
                         though it were what orders currently pay. --}}
                    <div class="alert alert-warning d-flex align-items-start small mt-3 mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <div>
                            Orders currently charge <strong>${{ number_format($snapshot['selling'], 2) }}</strong>,
                            which is not what this markup gives. Saving will set it to the figure above.
                        </div>
                    </div>
                    @endif
                </div>

                @can('product-pricing.manage')
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span class="small text-muted">
                        Clearing the box means no price agreed &mdash; not free.
                    </span>
                    <button type="submit" class="btn btn-primary">Save price</button>
                </div>
                @endcan
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h6 mb-0">Who supplies this</h2>
            </div>
            <div class="card-body">
                @forelse($vendors as $vendor)
                    <span class="badge bg-secondary-subtle text-secondary-emphasis me-1 mb-1 p-2">
                        <i class="bi bi-building me-1"></i>{{ $vendor->name }}
                    </span>
                @empty
                    <p class="text-warning mb-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        No vendor is recorded as supplying this product, so it cannot be ordered yet.
                    </p>
                @endforelse

                <p class="small text-muted mb-0 mt-3">
                    @if($vendors->isNotEmpty())
                        A purchase order to any of them prices its lines at the figure above.
                    @endif
                    Vendors are set on the
                    <a href="{{ route('products.edit', $product->id) }}">product page</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    // A convenience only. The server works the selling price out from the cost
    // and the configured markup when the form is saved, so nothing here decides
    // what is charged.
    const cost   = document.getElementById('unit_cost');
    const markup = document.getElementById('markup-display');
    const selling = document.getElementById('selling-display');
    const gp      = document.getElementById('gp-display');

    if (!cost || !markup || !selling || !gp) return;

    function money(value) {
        return value.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function refresh() {
        const c = parseFloat(cost.value);
        const pct = parseFloat(markup.dataset.markup) || 0;

        if (isNaN(c) || c <= 0) {
            selling.innerHTML = '&mdash;';
            gp.innerHTML = '&mdash;';
            return;
        }

        const sell = Math.round(c * (1 + pct / 100) * 100) / 100;

        selling.textContent = '$' + money(sell);
        gp.textContent = '+$' + money(sell - c);
    }

    cost.addEventListener('input', refresh);
    refresh();
})();
</script>
@endsection

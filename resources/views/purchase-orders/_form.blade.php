{{--
    Shared by create and edit.

    Laid out as three steps in the order the decisions are actually made:
    pick the vendor, choose from what they carry, then say where it goes.
    Steps 2 and 3 stay hidden until a vendor is chosen, because neither
    question can be answered before then.

    Expects: $order (null when creating), $vendors, $warehouses.
--}}
@php
    // A global composer in AppServiceProvider rewrites every null view variable
    // into an empty collection, and objects are always truthy in PHP - so a
    // plain null check would pass and then blow up on ->items. Insist on the
    // actual model instead.
    $order = ($order ?? null) instanceof \App\Models\PurchaseOrder ? $order : null;
    $existingLines = old('products', $order
        ? $order->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity_ordered,
            'unit_cost' => $item->unit_cost,
        ])->values()->all()
        : []);
@endphp

{{-- Step 1: who you are buying from --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="badge rounded-pill bg-primary">1</span>
        <div>
            <h2 class="h5 mb-0">Vendor</h2>
            <small class="text-muted">Who you are buying from. This decides what you can order and what it costs.</small>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="vendor_id" class="form-label">Vendor <span class="text-danger">*</span></label>
                <select name="vendor_id" id="vendor_id"
                        class="form-select @error('vendor_id') is-invalid @enderror" required>
                    <option value="">Select a vendor…</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}"
                            @selected(old('vendor_id', $order?->vendor_id) == $vendor->id)>{{ $vendor->name }}</option>
                    @endforeach
                </select>
                @error('vendor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

{{-- Step 2: what they carry --}}
<div class="card mb-4 d-none" id="items-section">
    <div class="card-header d-flex justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill bg-primary">2</span>
            <div>
                <h2 class="h5 mb-0">Items</h2>
                <small class="text-muted">Only products this vendor carries. Costs come from their price list.</small>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-line" disabled>
            <i class="bi bi-plus-lg me-1"></i>Add Item
        </button>
    </div>
    <div class="card-body">
        @error('products')<div class="alert alert-danger">{{ $message }}</div>@enderror

        <div id="empty-catalogue-hint" class="alert alert-warning d-none">
            This vendor has no products on their price list yet.
            Add some on <a href="{{ route('vendors.index') }}">their page</a> before ordering from them.
        </div>

        <table class="table align-middle d-none" id="lines-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th style="width: 8rem;">Quantity</th>
                    <th style="width: 10rem;">Unit Cost</th>
                    <th style="width: 9rem;">Subtotal</th>
                    <th style="width: 4rem;"></th>
                </tr>
            </thead>
            <tbody id="lines-body"></tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Total</th>
                    <th>$<span id="order-total">0.00</span></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Step 3: where it goes --}}
<div class="card mb-4 d-none" id="delivery-section">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="badge rounded-pill bg-primary">3</span>
        <div>
            <h2 class="h5 mb-0">Deliver To</h2>
            <small class="text-muted">Where the goods should arrive. This does not affect the price.</small>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="warehouse_id" class="form-label">Warehouse <span class="text-danger">*</span></label>
                <select name="warehouse_id" id="warehouse_id"
                        class="form-select @error('warehouse_id') is-invalid @enderror" required>
                    <option value="">Select a warehouse…</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}"
                            @selected(old('warehouse_id', $order?->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label for="expected_date" class="form-label">Expected Date</label>
                <input type="date" name="expected_date" id="expected_date"
                       class="form-control @error('expected_date') is-invalid @enderror"
                       value="{{ old('expected_date', $order?->expected_date?->format('Y-m-d')) }}">
                @error('expected_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="notes" class="form-label">Notes</label>
                <textarea name="notes" id="notes" rows="2" class="form-control">{{ old('notes', $order?->notes) }}</textarea>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const vendorSelect    = document.getElementById('vendor_id');
    const addButton       = document.getElementById('add-line');
    const table           = document.getElementById('lines-table');
    const body            = document.getElementById('lines-body');
    const emptyHint       = document.getElementById('empty-catalogue-hint');
    const totalOut        = document.getElementById('order-total');
    const itemsSection    = document.getElementById('items-section');
    const deliverySection = document.getElementById('delivery-section');
    const submitButton    = document.querySelector('[data-order-submit]');
    const priceListUrl    = "{{ route('purchase-orders.vendor-products', ['vendor' => '__VENDOR__']) }}";

    // Lines already on the order (or repopulated after a validation error).
    const existingLines = @json($existingLines);

    let catalogue = [];
    let index = 0;

    function money(value) {
        return (Math.round(value * 100) / 100).toFixed(2);
    }

    /**
     * Show or hide a step.
     *
     * Its fields are disabled while hidden: a hidden `required` input makes the
     * browser refuse to submit with an error it cannot show the user, and a
     * disabled field is skipped by both constraint validation and submission.
     */
    function setStepVisible(section, visible) {
        section.classList.toggle('d-none', !visible);
        section.querySelectorAll('input, select, textarea').forEach(field => {
            field.disabled = !visible;
        });
    }

    function recalculate() {
        let total = 0;
        body.querySelectorAll('tr').forEach(row => {
            const qty  = parseFloat(row.querySelector('.line-qty').value) || 0;
            const cost = parseFloat(row.querySelector('.line-cost').value) || 0;
            const sub  = qty * cost;
            total += sub;
            row.querySelector('.line-subtotal').textContent = money(sub);
        });
        totalOut.textContent = money(total);
    }

    function addLine(line) {
        if (!catalogue.length) return;

        const i = index++;
        const options = catalogue.map(p =>
            `<option value="${p.id}" data-cost="${p.unit_cost ?? ''}"
                     ${line && String(line.product_id) === String(p.id) ? 'selected' : ''}>
                ${p.name} (${p.sku})
             </option>`
        ).join('');

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select name="products[${i}][product_id]" class="form-select line-product" required>
                    ${options}
                </select>
                <small class="text-danger line-unpriced d-none">No agreed cost for this vendor yet.</small>
            </td>
            <td><input type="number" name="products[${i}][quantity]" class="form-control line-qty"
                       min="1" value="${line ? line.quantity : 1}" required></td>
            <td>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="products[${i}][unit_cost]" class="form-control line-cost"
                           min="0" step="0.01" value="${line ? line.unit_cost : ''}" required>
                </div>
            </td>
            <td>$<span class="line-subtotal">0.00</span></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger line-remove">&times;</button></td>
        `;
        body.appendChild(row);

        // A brand-new line takes the vendor's agreed cost as its starting point.
        if (!line) {
            applyCatalogueCost(row);
        }
        flagUnpriced(row);
        recalculate();
    }

    function applyCatalogueCost(row) {
        const option = row.querySelector('.line-product').selectedOptions[0];
        const cost = option ? option.dataset.cost : '';
        if (cost !== '' && cost !== 'null') {
            row.querySelector('.line-cost').value = parseFloat(cost).toFixed(2);
        }
    }

    function flagUnpriced(row) {
        const option = row.querySelector('.line-product').selectedOptions[0];
        const unpriced = !option || option.dataset.cost === '' || option.dataset.cost === 'null';
        row.querySelector('.line-unpriced').classList.toggle('d-none', !unpriced);
    }

    function collapseSteps() {
        catalogue = [];
        body.innerHTML = '';
        index = 0;
        table.classList.add('d-none');
        emptyHint.classList.add('d-none');
        addButton.disabled = true;
        setStepVisible(itemsSection, false);
        setStepVisible(deliverySection, false);
        if (submitButton) submitButton.disabled = true;
        recalculate();
    }

    function loadCatalogue(vendorId, thenAddLines) {
        if (!vendorId) {
            collapseSteps();
            return;
        }

        fetch(priceListUrl.replace('__VENDOR__', vendorId), {
            headers: { 'Accept': 'application/json' }
        })
            .then(response => response.json())
            .then(products => {
                catalogue = products;
                body.innerHTML = '';
                index = 0;

                // Step 2 opens either way, so the reason for the hold-up is visible.
                setStepVisible(itemsSection, true);

                if (!catalogue.length) {
                    emptyHint.classList.remove('d-none');
                    table.classList.add('d-none');
                    addButton.disabled = true;
                    // Nothing orderable, so there is nothing to deliver anywhere.
                    setStepVisible(deliverySection, false);
                    if (submitButton) submitButton.disabled = true;
                    return;
                }

                emptyHint.classList.add('d-none');
                table.classList.remove('d-none');
                addButton.disabled = false;

                (thenAddLines || []).forEach(addLine);
                if (!body.children.length) addLine(null);

                setStepVisible(deliverySection, true);
                if (submitButton) submitButton.disabled = false;
            })
            .catch(() => {
                setStepVisible(itemsSection, true);
                emptyHint.textContent = "Could not load this vendor's price list.";
                emptyHint.classList.remove('d-none');
            });
    }

    vendorSelect.addEventListener('change', () => loadCatalogue(vendorSelect.value, []));
    addButton.addEventListener('click', () => addLine(null));

    body.addEventListener('input', recalculate);
    body.addEventListener('change', function (event) {
        const row = event.target.closest('tr');
        if (event.target.classList.contains('line-product')) {
            applyCatalogueCost(row);
            flagUnpriced(row);
            recalculate();
        }
    });
    body.addEventListener('click', function (event) {
        if (event.target.classList.contains('line-remove')) {
            event.target.closest('tr').remove();
            recalculate();
        }
    });

    // Editing an order, or coming back from a validation error, starts with a
    // vendor already chosen - open every step straight away.
    if (vendorSelect.value) {
        loadCatalogue(vendorSelect.value, existingLines);
    } else {
        collapseSteps();
    }
});
</script>
@endpush

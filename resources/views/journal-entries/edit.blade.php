@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4">Edit Journal Entry {{ $journalEntry->formatted_id }}</h1>

    <form method="POST" action="{{ route('journal-entries.update', $journalEntry) }}" id="journalEditForm">
        @csrf
        @method('PATCH')

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Journal Date</label>
                <input type="date" name="entry_date" class="form-control" value="{{ old('entry_date', $journalEntry->entry_date->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Reference</label>
                <input type="text" class="form-control" value="{{ $journalEntry->formatted_id }}" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label">Description / Memo</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description', $journalEntry->description) }}</textarea>
            </div>
        </div>

        <!-- Line items table for all journal types -->
        <h4 class="mt-4">Line Items</h4>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="linesTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:24%">Account</th>
                        <th style="width:18%">Customer / Vendor / Location</th>
                        <th style="width:13%" class="text-end">Debit</th>
                        <th style="width:13%" class="text-end">Credit</th>
                        <th>Description</th>
                        <th style="width:5%"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($journalEntry->lines as $idx=>$line)
                        <tr>
                            <td>
                                <select name="lines[{{ $idx }}][account_id]" class="form-select account-select" required>
                                    <option value="">Select account...</option>
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}" @selected($acc->id==$line->account_id)>{{ $acc->code }} – {{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $idx }}][party]" class="form-select form-select-sm party-select d-none"
                                        data-selected="{{ $line->party_id ? strtolower(class_basename($line->party_type)).':'.$line->party_id : '' }}"></select>
                                <select name="lines[{{ $idx }}][location]" class="form-select form-select-sm location-select d-none"
                                        data-selected="{{ $line->location_id ? strtolower(class_basename($line->location_type)).':'.$line->location_id : '' }}"></select>
                                <span class="text-muted small dimension-none">Not required</span>
                            </td>
                            <td class="text-end">
                                <input type="number" step="0.01" min="0" name="lines[{{ $idx }}][debit]" class="form-control text-end debit-input" value="{{ $line->debit>0?$line->debit:'' }}">
                            </td>
                            <td class="text-end">
                                <input type="number" step="0.01" min="0" name="lines[{{ $idx }}][credit]" class="form-control text-end credit-input" value="{{ $line->credit>0?$line->credit:'' }}">
                            </td>
                            <td>
                                <input type="text" name="lines[{{ $idx }}][description]" class="form-control" value="{{ $line->description }}">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger remove-line"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th class="text-end" colspan="2">Totals</th>
                        <th class="text-end" id="totalDebit">0.00</th>
                        <th class="text-end" id="totalCredit">0.00</th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addLineBtn"><i class="bi bi-plus"></i> Add Line</button>
        </div>

        <div class="mt-4 d-flex justify-content-between">
            <a href="{{ route('journal-entries.index') }}" class="btn btn-secondary">Back</a>
            <button type="submit" class="btn btn-primary" id="saveBtn">Save Changes</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function(){
    // control_of and requires_location are what the ledger enforces on every
    // line; the form asks for them rather than letting the save be refused.
    const accounts = @json($accounts->map(fn($a)=>[
        'id'=>$a->id,
        'code'=>$a->code,
        'name'=>$a->name,
        'control_of'=>$a->control_of,
        'requires_location'=>(bool) $a->requires_location,
    ]));
    const partyOptions    = @json($dimensions['parties']);
    const locationOptions = @json($dimensions['locations']);
    const tbody    = document.querySelector('#linesTable tbody');
    const addBtn   = document.getElementById('addLineBtn');
    const totalDebitEl  = document.getElementById('totalDebit');
    const totalCreditEl = document.getElementById('totalCredit');
    const saveBtn  = document.getElementById('saveBtn');

    function format(num){ return parseFloat(num||0).toFixed(2); }

    function recalcTotals(){
        let debit=0,credit=0;
        tbody.querySelectorAll('tr').forEach(row=>{
            debit  += parseFloat(row.querySelector('.debit-input').value||0);
            credit += parseFloat(row.querySelector('.credit-input').value||0);
        });
        totalDebitEl.textContent  = format(debit);
        totalCreditEl.textContent = format(credit);
    }

    function reindexRows(){
        tbody.querySelectorAll('tr').forEach((row,idx)=>{
            row.querySelectorAll('input,select').forEach(el=>{
                const name = el.getAttribute('name');
                if(!name) return;
                const updated = name.replace(/lines\[\d+\]/, `lines[${idx}]`);
                el.setAttribute('name', updated);
            });
        });
    }

    function buildAccountOptions(selectedId){
        return accounts.map(a=>`<option value="${a.id}" ${selectedId==a.id?'selected':''}>${a.code} – ${a.name}</option>`).join('');
    }

    /**
     * Show the dimension the chosen account actually requires.
     *
     * Accounts Receivable and Payable name a party, inventory names a location,
     * everything else neither. A line missing a required dimension is refused
     * by the ledger, so the select is marked required rather than left to fail
     * on save.
     */
    function syncDimensions(row){
        const account  = accounts.find(a => String(a.id) === row.querySelector('.account-select').value);
        const party    = row.querySelector('.party-select');
        const location = row.querySelector('.location-select');
        const none     = row.querySelector('.dimension-none');

        const wantsParty    = !!(account && account.control_of);
        const wantsLocation = !!(account && account.requires_location);

        toggle(party, wantsParty, partyOptions, account ? account.control_of : null);
        toggle(location, wantsLocation, locationOptions, null);

        none.classList.toggle('d-none', wantsParty || wantsLocation);
    }

    function toggle(select, wanted, options, limitTo){
        select.classList.toggle('d-none', !wanted);
        select.required = wanted;
        select.disabled = !wanted;

        if(!wanted){ select.innerHTML = ''; return; }

        // data-selected carries what the line was saved with, so re-opening the
        // form does not quietly drop a dimension it already had.
        const keep = select.value || select.dataset.selected || '';
        const entries = Object.entries(options)
            .filter(([value]) => !limitTo || value.startsWith(limitTo + ':'));

        select.innerHTML = '<option value="">Select...</option>' + entries
            .map(([value,label]) => `<option value="${value}" ${keep===value?'selected':''}>${label}</option>`)
            .join('');
    }

    function attachRowEvents(row){
        const accountSelect = row.querySelector('.account-select');
        accountSelect.addEventListener('change', ()=> syncDimensions(row));
        syncDimensions(row);

        row.querySelectorAll('.debit-input,.credit-input').forEach(inp=>{
            inp.addEventListener('input', recalcTotals);
        });
        row.querySelector('.remove-line').addEventListener('click', ()=>{
            row.remove();
            reindexRows();
            recalcTotals();
        });
    }

    // Attach existing row events
    tbody.querySelectorAll('tr').forEach(attachRowEvents);
    recalcTotals();

    function addRow(){
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="lines[${tbody.children.length}][account_id]" class="form-select account-select" required>
                    <option value="">Select account...</option>
                    ${buildAccountOptions('')}
                </select>
            </td>
            <td>
                <select name="lines[${tbody.children.length}][party]" class="form-select form-select-sm party-select d-none" data-selected=""></select>
                <select name="lines[${tbody.children.length}][location]" class="form-select form-select-sm location-select d-none" data-selected=""></select>
                <span class="text-muted small dimension-none">Not required</span>
            </td>
            <td class="text-end">
                <input type="number" step="0.01" min="0" name="lines[${tbody.children.length}][debit]" class="form-control text-end debit-input">
            </td>
            <td class="text-end">
                <input type="number" step="0.01" min="0" name="lines[${tbody.children.length}][credit]" class="form-control text-end credit-input">
            </td>
            <td><input type="text" name="lines[${tbody.children.length}][description]" class="form-control"></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger remove-line"><i class="bi bi-trash"></i></button>
            </td>`;
        tbody.appendChild(tr);
        attachRowEvents(tr);
        reindexRows();
        recalcTotals();
    }

    addBtn && addBtn.addEventListener('click', addRow);

})();
</script>
@endpush 
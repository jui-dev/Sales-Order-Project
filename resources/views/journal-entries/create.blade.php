@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4">New Journal Entry</h1>

    <form method="POST" action="{{ route('journal-entries.store') }}" id="journalForm">
        @csrf
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Journal Date</label>
                <input type="date" name="entry_date" class="form-control" value="{{ old('entry_date', date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Reference Number</label>
                <input type="text" name="reference" class="form-control" placeholder="Auto-generated if blank" value="{{ old('reference') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Description / Memo</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
            </div>
        </div>

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
                    <!-- Rows inserted via JS -->
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
            <a href="{{ route('journal-entries.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" id="saveBtn" disabled>
                <i class="bi bi-save me-1"></i>Save Journal Entry
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    (function(){
        // control_of and requires_location are what the ledger enforces on
        // every line; the form asks for them so a line is not built that the
        // ledger will only refuse afterwards.
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

            const balanced = debit>0 && (Math.abs(debit-credit) < 0.01);
            saveBtn.disabled = !balanced || tbody.querySelectorAll('tr').length < 2;
            if(balanced){
                saveBtn.classList.remove('btn-danger');
                saveBtn.classList.add('btn-primary');
            }else{
                saveBtn.classList.remove('btn-primary');
                saveBtn.classList.add('btn-danger');
            }
        }

        /**
         * Reassign sequential indexes (0..n) to all input names so that Laravel
         * receives a clean, gap-free lines array. This avoids situations where
         * removing rows leaves duplicate or missing indices that can cause some
         * lines to be dropped on the server side.
         */
        function reindexRows(){
            tbody.querySelectorAll('tr').forEach((row,idx)=>{
                row.querySelectorAll('input,select').forEach(el=>{
                    const name = el.getAttribute('name');
                    if(!name) return;
                    // Replace the numeric index inside lines[<index>]
                    const updated = name.replace(/lines\[\d+\]/, `lines[${idx}]`);
                    el.setAttribute('name', updated);
                });
            });
        }

        function buildAccountOptions(selectedId){
            return accounts.map(a=>`<option value="${a.id}" ${selectedId==a.id?'selected':''}>${a.code} – ${a.name}</option>`).join('');
        }

        function addRow(data={}){
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select name="lines[${tbody.children.length}][account_id]" class="form-select account-select" required>
                        <option value="">Select account...</option>
                        ${buildAccountOptions(data.account_id)}
                    </select>
                </td>
                <td>
                    <select name="lines[${tbody.children.length}][party]" class="form-select form-select-sm party-select d-none"></select>
                    <select name="lines[${tbody.children.length}][location]" class="form-select form-select-sm location-select d-none"></select>
                    <span class="text-muted small dimension-none">Not required</span>
                </td>
                <td class="text-end">
                    <input type="number" step="0.01" min="0" name="lines[${tbody.children.length}][debit]" class="form-control text-end debit-input" value="${data.debit||''}">
                </td>
                <td class="text-end">
                    <input type="number" step="0.01" min="0" name="lines[${tbody.children.length}][credit]" class="form-control text-end credit-input" value="${data.credit||''}">
                </td>
                <td>
                    <input type="text" name="lines[${tbody.children.length}][description]" class="form-control" value="${data.description||''}">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger remove-line"><i class="bi bi-trash"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
            attachRowEvents(tr);
            reindexRows();
            recalcTotals();
        }

        /**
         * Show the dimension the chosen account actually requires.
         *
         * Accounts Receivable and Payable name a party, inventory names a
         * location, and everything else names neither. A line that leaves a
         * required dimension empty is refused by the ledger, so the select is
         * marked required rather than left to fail on submit.
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

            const keep = select.value;
            const entries = Object.entries(options)
                // A receivable names a customer and a payable a vendor; showing
                // both would let a line be filed against the wrong ledger.
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

        // Initial two rows
        addRow();
        addRow();

        addBtn.addEventListener('click', ()=> addRow());

        // Validate before submit to avoid server round-trip if unbalanced
        document.getElementById('journalForm').addEventListener('submit', function(e){
            reindexRows(); // Finalize indices before serializing form
            recalcTotals();
            if(saveBtn.disabled){
                e.preventDefault();
                alert('Journal entry must be balanced and contain at least two lines.');
            }
        });
    })();
</script>
@endpush 
@extends('layouts.app')

@section('content')
<div class="container">
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
                        <th style="width:28%">Account</th>
                        <th style="width:15%" class="text-end">Debit</th>
                        <th style="width:15%" class="text-end">Credit</th>
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
                        <th class="text-end">Totals</th>
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
    const accounts = @json($accounts->map(fn($a)=>['id'=>$a->id,'code'=>$a->code,'name'=>$a->name]));
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

    function attachRowEvents(row){
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
@extends('layouts.app')

@section('page-header')
<h1 class="mb-4">Journal Entries</h1>
@endsection

@section('content')
<div class="container-fluid">

    <div class="d-flex flex-wrap justify-content-between mb-3">
        <div class="btn-group mb-2" role="group">
            <a href="{{ route('journal-entries.create') }}" class="btn btn-success">
                <i class="bi bi-plus-circle me-1"></i> New Journal Entry
            </a>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
        </div>

        <form method="GET" id="sortForm" class="ms-auto mb-2 d-flex align-items-center">
            @foreach(request()->except('sort','direction','page') as $k=>$v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <label class="me-2 mb-0">Sort by</label>
            <select name="sort" id="sortSelect" class="form-select me-2" style="width:auto">
                @php $sortOptions=['id'=>'ID','date'=>'Date','status'=>'Status','amount'=>'Amount','account_type'=>'Account Type']; @endphp
                @foreach($sortOptions as $val=>$label)
                    <option value="{{ $val }}" @selected(request('sort','id')==$val)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="direction" id="directionSelect" class="form-select" style="width:auto">
                <option value="asc" @selected(request('direction','asc')==='asc')>Asc</option>
                <option value="desc" @selected(request('direction')==='desc')>Desc</option>
            </select>
        </form>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filter Journal Entries</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="GET" id="filterForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" class="form-control" placeholder="Description or ID" value="{{ request('reference') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account</label>
                            <select name="account_id" class="form-select">
                                <option value="">-- Any --</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                                        {{ $account->code }} – {{ $account->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Journal Type</label>
                            <select name="journal_type" class="form-select">
                                <option value="">-- Any --</option>
                                @php $types = ['manual'=>'Manual','sales'=>'Sales','purchase'=>'Purchase','stock'=>'Stock','payment'=>'Payment']; @endphp
                                @foreach($types as $key=>$label)
                                    <option value="{{ $key }}" @selected(request('journal_type')==$key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">-- Any --</option>
                                @foreach(['draft'=>'Draft','posted'=>'Posted','approved'=>'Approved','rejected'=>'Rejected'] as $val=>$label)
                                    <option value="{{ $val }}" @selected(request('status')==$val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <a href="{{ route('journal-entries.index') }}" class="btn btn-outline-secondary">Clear Filters</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row" id="spinnerRow" style="display:none;">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
        </div>
    </div>

    <div class="accordion" id="journalAccordion">
        @forelse($journalEntries as $entry)
            @php
                $isManual = $entry->source_type === app(\App\Models\JournalEntry::class)->getMorphClass();
                $bgClass  = $isManual ? 'bg-light' : '';
                $entryId  = 'entry'.$entry->id;
                // Determine journal type based on source and description
                $typeLabel = 'Manual';
                if ($entry->source_type === \App\Models\Invoice::class) {
                    $typeLabel = 'Sales';
                } elseif ($entry->source_type === \App\Models\StockTransfer::class) {
                    $typeLabel = 'Stock';
                } elseif ($entry->source_type === \App\Models\Payment::class) {
                    $typeLabel = 'Payment';
                } elseif ($entry->source_type === \App\Models\SupplierBill::class) {
                    // Check if this is a payment journal entry or purchase journal entry
                    if (str_contains(strtolower($entry->description), 'payment')) {
                        $typeLabel = 'Payment';
                    } else {
                        $typeLabel = 'Purchase';
                    }
                }
                $statusLabel = ucfirst($entry->status);
            @endphp
            <div class="accordion-item {{ $bgClass }} mb-2">
                <h2 class="accordion-header" id="h{{ $entryId }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c{{ $entryId }}" aria-expanded="false" aria-controls="c{{ $entryId }}">
                        <div class="d-flex flex-wrap w-100 justify-content-between">
                            <span>{{ $entry->entry_date->format('Y-m-d') }}</span>
                            <span class="ms-3 fw-bold">{{ $entry->formatted_id }}</span>
                            <span class="ms-3 badge bg-secondary">{{ $typeLabel }}</span>
                            <span class="ms-3 badge bg-info">{{ $statusLabel }}</span>
                            <span class="ms-3 flex-grow-1">{{ $entry->description }}</span>
                            <span class="ms-auto text-success">{{ number_format($entry->totalDebit(),2) }}</span>
                            <span class="ms-2 text-danger">{{ number_format($entry->totalCredit(),2) }}</span>
                        </div>
                    </button>
                </h2>
                <div id="c{{ $entryId }}" class="accordion-collapse collapse" aria-labelledby="h{{ $entryId }}" data-bs-parent="#journalAccordion">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Account</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="ps-5">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entry->lines as $line)
                                    <tr>
                                        <td>{{ $line->account->code }} – {{ $line->account->name }}</td>
                                        <td class="text-end text-success">{{ $line->debit > 0 ? number_format($line->debit,2) : '' }}</td>
                                        <td class="text-end text-danger">{{ $line->credit > 0 ? number_format($line->credit,2) : '' }}</td>
                                        <td class="ps-5">{{ $line->description }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="p-3 border-top d-flex justify-content-between align-items-center">
                            <div>
                                @if($entry->source)
                                    @php
                                        $cls = get_class($entry->source);
                                        $srcLabelMap = [
                                            \App\Models\Invoice::class       => 'Invoice',
                                            \App\Models\SupplierBill::class  => 'Supplier Bill',
                                            \App\Models\StockTransfer::class=> 'Stock Transfer',
                                            \App\Models\Payment::class      => 'Payment',
                                        ];
                                        $srcLabel = $srcLabelMap[$cls] ?? '';
                                        $routeMap = [
                                            \App\Models\Invoice::class       => route('invoices.show',$entry->source_id),
                                            \App\Models\SupplierBill::class  => route('supplier-bills.show',$entry->source_id),
                                        ];
                                        $route = $routeMap[$cls] ?? null;
                                    @endphp
                                    @if($route)
                                        <a href="{{ $route }}" class="btn btn-link p-0">View {{ $srcLabel }} {{ $entry->source->invoice_number ?? $entry->source->id }}</a>
                                    @endif
                                @endif
                            </div>

                            @if(in_array($entry->status,['draft','rejected']))
                                <div>
                                    <a href="{{ route('journal-entries.edit',$entry) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
                                    <form method="POST" action="{{ route('journal-entries.post',$entry) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Post</button>
                                    </form>
                                    <form method="POST" action="{{ route('journal-entries.reject',$entry) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-x me-1"></i>Reject</button>
                                    </form>
                                </div>
                            @elseif($entry->status === 'posted')
                                <div>
                                    <form method="POST" action="{{ route('journal-entries.approve',$entry) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check me-1"></i>Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('journal-entries.reject',$entry) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-x me-1"></i>Reject</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="alert alert-info">No entries found.</div>
        @endforelse
    </div>

    <x-pagination :paginator="$journalEntries" />
</div>

@push('scripts')
<script>
    // show spinner on filter submit
    document.getElementById('filterForm').addEventListener('submit', function(){
        document.getElementById('spinnerRow').style.display='block';
    });

    // Sort change trigger
    ['sortSelect','directionSelect'].forEach(function(id){
        const el=document.getElementById(id);
        if(el){
            el.addEventListener('change',function(){ document.getElementById('sortForm').submit(); });
        }
    });

    // Scroll to new entry if session provides ID
    const newEntryId = "{{ session('newEntryId') }}";
    if(newEntryId){
        const target = document.getElementById('hentry'+newEntryId)?.querySelector('button');
        if(target){
            target.scrollIntoView({behavior:'smooth',block:'center'});
            // Optionally temporarily highlight
            target.classList.add('border','border-success');
            setTimeout(()=>target.classList.remove('border','border-success'),3000);
        }
    }
</script>
@endpush
@endsection 
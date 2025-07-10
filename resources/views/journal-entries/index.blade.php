@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Journal Entries</h1>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-2">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Reference</label>
            <input type="text" name="reference" class="form-control" placeholder="Description or ID" value="{{ request('reference') }}">
        </div>
        <div class="col-md-3">
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
        <div class="col-md-2 align-self-end text-end">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('journal-entries.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>ID</th>
                    <th>Description</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($journalEntries as $entry)
                    <tr>
                        <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                        <td>{{ $entry->formatted_id }}</td>
                        <td>{{ $entry->description }}</td>
                        <td class="text-end">{{ number_format($entry->totalDebit(), 2) }}</td>
                        <td class="text-end">{{ number_format($entry->totalCredit(), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="p-0">
                            <table class="table mb-0 small">
                                @foreach($entry->lines as $line)
                                    <tr>
                                        <td>{{ $line->account->code }} - {{ $line->account->name }}</td>
                                        <td class="text-end">{{ number_format($line->debit, 2) }}</td>
                                        <td class="text-end">{{ number_format($line->credit, 2) }}</td>
                                        <td>{{ $line->description }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No entries found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $journalEntries->links() }}
</div>
@endsection 
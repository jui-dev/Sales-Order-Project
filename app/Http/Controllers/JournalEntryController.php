<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $query = JournalEntry::with(['lines.account'])
            ->when($request->filled('start_date'), fn($q) => $q->whereDate('entry_date', '>=', $request->start_date))
            ->when($request->filled('end_date'),   fn($q) => $q->whereDate('entry_date', '<=', $request->end_date))
            ->when($request->filled('reference'),  function ($q) use ($request) {
                $ref = $request->reference;
                $q->where(function($sub) use ($ref) {
                    $sub->where('description', 'like', "%$ref%")
                        ->orWhere('formatted_id', 'like', "%$ref%");
                });
            })
            ->when($request->filled('account_id'), fn($q) => $q->whereHas('lines', fn($l) => $l->where('account_id', $request->account_id)))
            ->orderByDesc('entry_date');

        $journalEntries = $query->paginate(20)->withQueryString();
        $accounts = Account::orderBy('code')->get();

        return view('journal-entries.index', compact('journalEntries', 'accounts'));
    }
} 
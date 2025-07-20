<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $query = JournalEntry::with(['lines.account'])
            ->when($request->filled('start_date'), fn($q) => $q->whereDate('entry_date', '>=', $request->start_date))
            ->when($request->filled('end_date'),   fn($q) => $q->whereDate('entry_date', '<=', $request->end_date))
            ->when($request->filled('journal_type'), function ($q) use ($request) {
                $type = $request->journal_type;
                $map = [
                    // Manual entries self-reference their model class
                    'manual'   => JournalEntry::class,
                    'sales'    => \App\Models\Invoice::class,
                    'purchase' => \App\Models\SupplierBill::class,
                    'stock'    => \App\Models\StockTransfer::class,
                    'payment'  => \App\Models\Payment::class,
                ];

                if (array_key_exists($type, $map)) {
                    $sourceCls = $map[$type];
                    $q->where('source_type', $sourceCls);
                }
            })
            ->when($request->filled('reference'),  function ($q) use ($request) {
                $ref = $request->reference;
                $q->where(function($sub) use ($ref) {
                    $sub->where('description', 'like', "%$ref%")
                        ->orWhere('formatted_id', 'like', "%$ref%");
                });
            })
            ->when($request->filled('account_id'), fn($q) => $q->whereHas('lines', fn($l) => $l->where('account_id', $request->account_id)))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status));

        // --------------------------------------------------------------
        // Sorting
        // --------------------------------------------------------------
        $sort = $request->input('sort', 'id');
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'date':
                $query->orderBy('entry_date', $direction);
                break;
            case 'status':
                $query->orderBy('status', $direction);
                break;
            case 'amount':
                // Sort by total debit (same as credit)
                $query->withSum('lines as total_debit', 'debit')->orderBy('total_debit', $direction);
                break;
            case 'account_type':
                // Fallback to ID sorting for now
                $query->orderBy('id', $direction);
                break;
            default:
                $query->orderBy('id', $direction);
        }

        $journalEntries = $query->paginate(20)->withQueryString();
        $accounts = Account::orderBy('code')->get();

        return view('journal-entries.index', compact('journalEntries', 'accounts'));
    }

    /**
     * Show form to create a manual journal entry
     */
    public function create()
    {
        $accounts = Account::orderBy('code')->get();
        return view('journal-entries.create', compact('accounts'));
    }

    /**
     * Store a newly created manual journal entry
     */
    public function store(Request $request)
    {
        $request->validate([
            'entry_date'                 => ['required', 'date'],
            'reference'                  => ['nullable', 'string', 'max:255', 'unique:journal_entries,formatted_id'],
            'description'                => ['nullable', 'string', 'max:1000'],
            'lines'                      => ['required', 'array', 'min:2'],
            'lines.*.account_id'         => ['required', 'exists:accounts,id'],
            'lines.*.debit'              => ['required_without:lines.*.credit', 'nullable'],
            'lines.*.credit'             => ['required_without:lines.*.debit', 'nullable'],
            'lines.*.description'        => ['nullable', 'string', 'max:255'],
        ]);

        $nonEmptyLines = [];
        foreach ($request->lines as $idx => $line) {
            $accountId  = $line['account_id'] ?? null;
            $debitRaw   = $line['debit']  ?? '';
            $creditRaw  = $line['credit'] ?? '';

            $hasDebit   = ($debitRaw !== '' && $debitRaw !== null);
            $hasCredit  = ($creditRaw !== '' && $creditRaw !== null);

            // Skip completely blank rows (no amount & no account)
            if (! $hasDebit && ! $hasCredit && empty($accountId)) {
                continue; // unused template row
            }

            // Validate presence of account when any amount entered
            if (($hasDebit || $hasCredit) && ! $accountId) {
                return back()->withInput()->withErrors(["lines.$idx.account_id" => 'Please select an account for this line.']);
            }

            // If account selected but both amounts blank
            if ($accountId && ! $hasDebit && ! $hasCredit) {
                return back()->withInput()->withErrors(["lines.$idx.debit" => 'Please enter a debit or credit amount for the selected account.']);
            }

            // Numeric checks
            if ($hasDebit && ! is_numeric($debitRaw)) {
                return back()->withInput()->withErrors(["lines.$idx.debit" => 'Debit amount must be a valid number.']);
            }
            if ($hasCredit && ! is_numeric($creditRaw)) {
                return back()->withInput()->withErrors(["lines.$idx.credit" => 'Credit amount must be a valid number.']);
            }

            $debit  = $hasDebit  ? floatval($debitRaw)  : 0.0;
            $credit = $hasCredit ? floatval($creditRaw) : 0.0;

            // Allow only one side populated (zero counts as populated if explicitly set)
            if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                return back()->withInput()->withErrors(["lines.$idx.debit" => 'Each line must contain either a debit or a credit amount, not both.']);
            }

            // If a zero value is provided, ensure the opposite side is non-zero
            if (($debit === 0 && $hasDebit) && $credit === 0) {
                return back()->withInput()->withErrors(["lines.$idx.debit" => 'Debit cannot be zero unless credit has a non-zero value.']);
            }
            if (($credit === 0 && $hasCredit) && $debit === 0) {
                return back()->withInput()->withErrors(["lines.$idx.credit" => 'Credit cannot be zero unless debit has a non-zero value.']);
            }

            $nonEmptyLines[] = [
                'account_id'  => $accountId,
                'debit'       => $debit,
                'credit'      => $credit,
                'description' => $line['description'] ?? null,
            ];
        }

        if (count($nonEmptyLines) < 2) {
            return back()->withInput()->with('error', 'At least two line items with amounts are required.');
        }

        $totalDebit  = array_sum(array_column($nonEmptyLines, 'debit'));
        $totalCredit = array_sum(array_column($nonEmptyLines, 'credit'));

        if (round($totalDebit,2) !== round($totalCredit,2)) {
            return back()->withInput()->with('error', 'Debits and credits do not balance.');
        }

        // ------------------------------------------------------------------
        // Auto‐generate memo/description if none provided by user
        // ------------------------------------------------------------------
        $autoDescription = null;
        if (! $request->filled('description')) {
            $parts = [];
            foreach ($nonEmptyLines as $line) {
                $account = Account::find($line['account_id']);
                if (! $account) {
                    continue;
                }
                $side  = $line['debit'] > 0 ? 'Dr' : 'Cr';
                $value = $line['debit'] > 0 ? $line['debit'] : $line['credit'];
                $parts[] = $account->code . ' ' . $side . ' ' . number_format($value, 2);
            }
            $autoDescription = implode(', ', $parts);
        }

        $createdEntryId = null;
        DB::transaction(function() use ($request, $nonEmptyLines, $autoDescription, &$createdEntryId) {
            /** @var JournalEntry $entry */
            $entry = JournalEntry::create([
                'entry_date'   => $request->entry_date,
                'description'  => $request->description ?: $autoDescription,
                // Use provided reference, otherwise a temporary UUID placeholder to satisfy NOT NULL
                'formatted_id' => $request->reference ?: ('TMP-'.Str::uuid()),
                'status'       => JournalEntry::STATUS_DRAFT, // Default to draft
                'source_type'  => '',
                'source_id'    => 0,
            ]);

            // Generate formatted ID if none was supplied (i.e., we used a TMP placeholder)
            if (!$request->reference) {
                $entry->formatted_id = 'JE-' . str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT);
                // Ensure uniqueness just in case, though unlikely to clash
                $entry->saveQuietly();
            }

            foreach ($nonEmptyLines as $line) {
                $entry->lines()->create([
                    'account_id'  => $line['account_id'],
                    'debit'       => $line['debit'],
                    'credit'      => $line['credit'],
                    'description' => $line['description'],
                ]);
            }

            // Ensure non-empty source columns reference the entry itself (avoids blank morph errors)
            $entry->updateQuietly([
                'source_type' => $entry->getMorphClass(),
                'source_id'   => $entry->getKey(),
            ]);

            // Audit log only for posted or approved
            if (in_array($entry->status, [JournalEntry::STATUS_POSTED, JournalEntry::STATUS_APPROVED])) {
                AuditLog::create([
                    'user_id'      => auth()->id(),
                    'action'       => 'journal_created',
                    'description'  => 'Manual Journal Entry ' . $entry->formatted_id . ' created with status ' . ucfirst($entry->status) . '.',
                    'subject_type' => $entry->getMorphClass(),
                    'subject_id'   => $entry->getKey(),
                ]);
            }

            $createdEntryId = $entry->id;
        });

        return redirect()->route('journal-entries.index')
            ->with('success', 'Journal entry created successfully.')
            ->with('newEntryId', $createdEntryId);
    }

    /**
     * Edit an existing journal entry.
     */
    public function edit(JournalEntry $journalEntry)
    {
        $accounts = Account::orderBy('code')->get();
        return view('journal-entries.edit', compact('journalEntry', 'accounts'));
    }

    /**
     * Update an existing journal entry.
     */
    public function update(Request $request, JournalEntry $journalEntry)
    {
        // Allow same validation as store but without formatted_id uniqueness rule clash for same entry
        $request->validate([
            'entry_date'                 => ['required', 'date'],
            'description'                => ['nullable', 'string', 'max:1000'],
            'lines'                      => ['required', 'array', 'min:2'],
            'lines.*.account_id'         => ['required', 'exists:accounts,id'],
            'lines.*.debit'              => ['required_without:lines.*.credit', 'nullable'],
            'lines.*.credit'             => ['required_without:lines.*.debit', 'nullable'],
            'lines.*.description'        => ['nullable', 'string', 'max:255'],
        ]);

        // Same nonEmptyLines build logic as store();
        $nonEmptyLines = [];
        foreach ($request->lines as $idx => $line) {
            // replicate validation from store
            $accountId  = $line['account_id'] ?? null;
            $debitRaw   = $line['debit']  ?? '';
            $creditRaw  = $line['credit'] ?? '';
            $hasDebit   = ($debitRaw !== '' && $debitRaw !== null);
            $hasCredit  = ($creditRaw !== '' && $creditRaw !== null);

            if (! $hasDebit && ! $hasCredit && empty($accountId)) {
                continue;
            }
            if (($hasDebit || $hasCredit) && ! $accountId) {
                return back()->withInput()->withErrors(["lines.$idx.account_id" => 'Please select an account for this line.']);
            }
            if ($accountId && ! $hasDebit && ! $hasCredit) {
                return back()->withInput()->withErrors(["lines.$idx.debit" => 'Please enter a debit or credit amount.']);
            }
            if ($hasDebit && ! is_numeric($debitRaw)) {
                return back()->withInput()->withErrors(["lines.$idx.debit" => 'Debit amount must be numeric.']);
            }
            if ($hasCredit && ! is_numeric($creditRaw)) {
                return back()->withInput()->withErrors(["lines.$idx.credit" => 'Credit amount must be numeric.']);
            }
            $debit  = $hasDebit  ? floatval($debitRaw)  : 0.0;
            $credit = $hasCredit ? floatval($creditRaw) : 0.0;
            if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                return back()->withInput()->withErrors(["lines.$idx.debit" => 'Each line must contain either a debit or a credit amount, not both.']);
            }
            $nonEmptyLines[] = [
                'account_id'  => $accountId,
                'debit'       => $debit,
                'credit'      => $credit,
                'description' => $line['description'] ?? null,
            ];
        }

        if (count($nonEmptyLines) < 2) {
            return back()->withInput()->with('error', 'At least two line items with amounts are required.');
        }

        $totalDebit  = array_sum(array_column($nonEmptyLines, 'debit'));
        $totalCredit = array_sum(array_column($nonEmptyLines, 'credit'));
        if (round($totalDebit,2) !== round($totalCredit,2)) {
            return back()->withInput()->with('error', 'Debits and credits do not balance.');
        }

        DB::transaction(function() use($journalEntry, $request, $nonEmptyLines) {
            $journalEntry->update([
                'entry_date'  => $request->entry_date,
                'description' => $request->description,
            ]);

            // Sync lines: simple approach – delete & recreate
            $journalEntry->lines()->delete();
            foreach ($nonEmptyLines as $line) {
                $journalEntry->lines()->create($line);
            }
        });

        return redirect()->route('journal-entries.index')->with('success', 'Journal entry updated.');
    }

    /**
     * Approve a journal entry.
     */
    public function approve(JournalEntry $journalEntry)
    {
        $journalEntry->approve();

        \App\Models\AuditLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'journal_approved',
            'description'  => 'Journal Entry '.$journalEntry->formatted_id.' approved.',
            'subject_type' => $journalEntry->getMorphClass(),
            'subject_id'   => $journalEntry->getKey(),
        ]);

        return back()->with('success', 'Journal approved successfully.');
    }

    /**
     * Reject a journal entry.
     */
    public function reject(JournalEntry $journalEntry)
    {
        $journalEntry->reject();

        \App\Models\AuditLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'journal_rejected',
            'description'  => 'Journal Entry '.$journalEntry->formatted_id.' rejected.',
            'subject_type' => $journalEntry->getMorphClass(),
            'subject_id'   => $journalEntry->getKey(),
        ]);

        return back()->with('success', 'Journal entry rejected.');
    }

    /**
     * Post a journal entry (draft → posted)
     */
    public function post(JournalEntry $journalEntry)
    {
        if (! $journalEntry->isBalanced()) {
            return back()->with('error', 'Journal entry is not balanced and cannot be posted.');
        }

        $journalEntry->post();

        AuditLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'journal_posted',
            'description'  => 'Journal Entry ' . $journalEntry->formatted_id . ' posted.',
            'subject_type' => $journalEntry->getMorphClass(),
            'subject_id'   => $journalEntry->getKey(),
        ]);

        return back()->with('success', 'Journal status updated to Posted.');
    }
} 
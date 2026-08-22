<?php

namespace App\Http\Controllers;

use App\Accounting\ManualEntryDraft;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class JournalEntryController extends Controller
{
    public function __construct(private readonly JournalEntryService $journalEntryService)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'journal_type' => $request->journal_type,
            'reference' => $request->reference,
            'account_id' => $request->account_id,
            'status' => $request->status,
            'origin' => $request->origin,
            'sort' => $request->sort,
            'direction' => $request->direction,
        ];

        $journalEntries = $this->journalEntryService->getFilteredJournalEntries($filters, 20);
        $accounts = Account::orderBy('code')->get();

        return view('journal-entries.index', compact('journalEntries', 'accounts'));
    }

    /**
     * Show form to create a manual journal entry
     */
    public function create(): View
    {
        $accounts = $this->postableAccounts();
        $dimensions = ManualEntryDraft::options();

        return view('journal-entries.create', compact('accounts', 'dimensions'));
    }

    /**
     * The accounts a line may name.
     *
     * Rollup accounts refuse direct posting - AccountResolver has always
     * refused one to a posting rule - so the form must not offer them either.
     */
    private function postableAccounts()
    {
        return Account::where('is_postable', true)->orderBy('code')->get();
    }

    /**
     * Store a newly created manual journal entry
     */
    public function store(Request $request): RedirectResponse
    {
        // Keep the return value: `validated()` does not exist on a plain Request,
        // so reaching for it threw BadMethodCallException, which the catch below
        // swallowed into a flash message - manual entries could never be saved.
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255', 'unique:journal_entries,formatted_id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.debit' => ['required_without:lines.*.credit', 'nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['required_without:lines.*.debit', 'nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            // A control account has to name the customer or vendor it belongs
            // to, and inventory the location - the ledger refuses the line
            // otherwise, so the form has to be able to say.
            'lines.*.party' => ['nullable', 'string', 'regex:/^(customer|vendor):[0-9]+$/'],
            'lines.*.location' => ['nullable', 'string', 'regex:/^(warehouse|retailer):[0-9]+$/'],
        ]);

        try {
            $journalEntry = $this->journalEntryService->createManualEntry($validated);
            return redirect()->route('journal-entries.show', $journalEntry)
                ->with('success', "Journal entry #{$journalEntry->formatted_id} created successfully.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified journal entry
     */
    public function show(JournalEntry $journalEntry): View
    {
        $journalEntry->load([
            'lines.account',
            'reversesJournal',
            'reverseJournals',
            'linkedDebitNote',
            'linkedCreditNote',
            'source'
        ]);
        return view('journal-entries.show', compact('journalEntry'));
    }

    /**
     * Show form to edit a journal entry
     */
    public function edit(JournalEntry $journalEntry): View
    {
        $accounts = $this->postableAccounts();
        $dimensions = ManualEntryDraft::options();
        $journalEntry->load(['lines.account', 'lines.party', 'lines.location']);

        return view('journal-entries.edit', compact('journalEntry', 'accounts', 'dimensions'));
    }

    /**
     * Update the specified journal entry
     */
    public function update(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255', 'unique:journal_entries,formatted_id,' . $journalEntry->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:accounts,id'],
            'lines.*.debit' => ['required_without:lines.*.credit', 'nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['required_without:lines.*.debit', 'nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            // A control account has to name the customer or vendor it belongs
            // to, and inventory the location - the ledger refuses the line
            // otherwise, so the form has to be able to say.
            'lines.*.party' => ['nullable', 'string', 'regex:/^(customer|vendor):[0-9]+$/'],
            'lines.*.location' => ['nullable', 'string', 'regex:/^(warehouse|retailer):[0-9]+$/'],
        ]);

        try {
            $journalEntry = $this->journalEntryService->updateEntry($journalEntry, $validated);
            return redirect()->route('journal-entries.show', $journalEntry)
                ->with('success', "Journal entry #{$journalEntry->formatted_id} updated successfully.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Approve a journal entry
     */
    public function approve(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $this->journalEntryService->approveEntry($journalEntry);
            return back()->with('success', "Journal entry #{$journalEntry->formatted_id} approved successfully.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject a journal entry
     */
    public function reject(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $this->journalEntryService->rejectEntry($journalEntry);
            return back()->with('success', "Journal entry #{$journalEntry->formatted_id} rejected successfully.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Post a journal entry
     */
    public function post(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $this->journalEntryService->postEntry($journalEntry);
            return back()->with('success', "Journal entry #{$journalEntry->formatted_id} posted successfully.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
} 
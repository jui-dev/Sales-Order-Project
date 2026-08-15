<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\StockTransaction;
use App\Services\CreditNoteService;
use App\Traits\HasApiResponses;
use App\Exceptions\DataNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class CreditNoteController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly CreditNoteService $creditNoteService)
    {
    }

    /**
     * Display a listing of credit notes
     */
    public function index(Request $request): View
    {
        try {
            $filters = [
                'customer_id' => $request->get('customer_id'),
                'status' => $request->get('status'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'search' => $request->get('search'),
            ];

            $perPage = $request->get('per_page', 20);
            $creditNotes = $this->creditNoteService->getAllCreditNotes($filters, $perPage);
            $filterOptions = $this->creditNoteService->getFilterOptions();
            $sortOptions = $this->creditNoteService->getSortOptions();

            // Get statistics for the dashboard
            $statistics = $this->creditNoteService->getStatistics();

            // Get additional data for filters
            $customers = \App\Models\Customer::orderBy('name')->get();
            $statusOptions = [
                'draft' => 'Draft',
                'pending' => 'Pending',
                'issued' => 'Issued',
                'posted' => 'Posted',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
            ];

            return view('credit-notes.index', compact(
                'creditNotes',
                'filterOptions',
                'sortOptions',
                'filters',
                'statistics',
                'customers',
                'statusOptions'
            ));
        } catch (\Exception $e) {
            \Log::error('Error loading credit notes: ' . $e->getMessage());
            
            // Return empty paginated result with proper structure
            $emptyCreditNotes = \App\Models\CreditNote::paginate(20);
            $emptyCreditNotes->setCollection(collect());
            
            // Provide default statistics in case of error
            $statistics = [
                'total' => 0,
                'pending' => 0,
                'issued' => 0,
                'posted' => 0,
                'total_amount' => 0,
                'posted_amount' => 0,
            ];
            
            // Provide default data for filters
            $customers = collect();
            $statusOptions = [
                'draft' => 'Draft',
                'pending' => 'Pending',
                'issued' => 'Issued',
                'posted' => 'Posted',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
            ];
            
            // Flashed for this request only. View::with() would bind an $error
            // view variable, but the layout reads the message off the session,
            // so the toast never rendered.
            session()->now('error', 'Unable to load credit notes. Please try again later.');

            return view('credit-notes.index', [
                'creditNotes' => $emptyCreditNotes,
                'filterOptions' => $this->creditNoteService->getFilterOptions(),
                'sortOptions' => $this->creditNoteService->getSortOptions(),
                'filters' => $filters ?? [],
                'statistics' => $statistics,
                'customers' => $customers,
                'statusOptions' => $statusOptions
            ]);
        }
    }

    /**
     * API endpoint for credit notes listing
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handleApiOperation(
            function() use ($request) {
                $filters = [
                    'customer_id' => $request->get('customer_id'),
                    'status' => $request->get('status'),
                    'date_from' => $request->get('date_from'),
                    'date_to' => $request->get('date_to'),
                    'search' => $request->get('search'),
                ];

                $perPage = $request->get('per_page', 20);
                return $this->creditNoteService->getAllCreditNotes($filters, $perPage);
            },
            'credit_notes',
            'Credit notes retrieved successfully'
        );
    }

    /**
     * Display the specified credit note
     */
    public function show(CreditNote $creditNote): View|RedirectResponse
    {
        try {
            // journalEntry.lines.account feeds <x-journal-ledger>; without it the
            // ledger issues a query per line.
            $creditNote->load([
                'customer',
                'invoice',
                'returnTransaction.product',
                'items.product',
                'journalEntry.lines.account',
                'createdBy',
                'approvedBy',
                'cancelledBy',
            ]);
            
            return view('credit-notes.show', compact('creditNote'));
        } catch (DataNotFoundException $e) {
            return redirect()->route('credit-notes.index')
                           ->with('error', 'Credit note not found.');
        } catch (\Exception $e) {
            \Log::error('Error loading credit note: ' . $e->getMessage());
            return redirect()->route('credit-notes.index')
                           ->with('error', 'Unable to load credit note. Please try again later.');
        }
    }

    /**
     * API endpoint for credit note details
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleApiOperation(
            function() use ($id) {
                return $this->creditNoteService->getCreditNote($id);
            },
            'credit_note',
            'Credit note retrieved successfully'
        );
    }

    /**
     * Generate credit note for a return
     */
    public function generateForReturn(StockTransaction $return): RedirectResponse
    {
        try {
            $creditNote = $this->creditNoteService->generateCreditNote($return);
            
            return redirect()->route('credit-notes.show', $creditNote)
                           ->with('success', "Credit note #{$creditNote->credit_note_number} generated successfully for return #{$return->formatted_id}.");
        } catch (DataNotFoundException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            \Log::error('Error generating credit note: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to generate credit note. Please try again later.']);
        }
    }

    /**
     * Cancel a credit note
     */
    public function cancel(CreditNote $creditNote): RedirectResponse
    {
        try {
            $creditNote = $this->creditNoteService->cancelCreditNote($creditNote);
            
            return redirect()->route('credit-notes.show', $creditNote)
                           ->with('success', "Credit note #{$creditNote->credit_note_number} cancelled successfully.");
        } catch (DataNotFoundException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            \Log::error('Error cancelling credit note: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to cancel credit note. Please try again later.']);
        }
    }

    /**
     * Post credit note (create journal entry with draft status)
     */
    public function post(CreditNote $creditNote): RedirectResponse
    {
        try {
            $creditNote = $this->creditNoteService->postCreditNote($creditNote);
            
            return redirect()->route('credit-notes.show', $creditNote)
                           ->with('success', "Credit Note successfully posted. A corresponding journal entry has been generated with draft status for further review.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Approve the journal entry (change from draft to approved)
     */
    public function approveJournalEntry(CreditNote $creditNote): RedirectResponse
    {
        try {
            $creditNote = $this->creditNoteService->approveJournalEntry($creditNote);

            return redirect()->route('credit-notes.show', $creditNote)
                           ->with('success', "Journal entry for credit note #{$creditNote->credit_note_number} has been approved. Post it to put the reversal on the ledger.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to approve journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Post the journal entry (change from approved to posted)
     */
    public function postJournalEntry(CreditNote $creditNote): RedirectResponse
    {
        try {
            $creditNote = $this->creditNoteService->postJournalEntry($creditNote);
            
            return redirect()->route('credit-notes.show', $creditNote)
                           ->with('success', "Journal entry for credit note #{$creditNote->credit_note_number} has been posted successfully.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to post journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Download credit note as PDF
     */
    public function download(CreditNote $creditNote)
    {
        $creditNote->load(['customer', 'invoice', 'returnTransaction.product', 'createdBy']);
        
        // For now, return a simple view. You can implement PDF generation later
        return view('credit-notes.pdf', compact('creditNote'));
    }
} 
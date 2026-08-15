<?php

namespace App\Http\Controllers;

use App\Models\DebitNote;
use App\Models\StockTransaction;
use App\Services\DebitNoteService;
use App\Traits\HasApiResponses;
use App\Exceptions\DataNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class DebitNoteController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly DebitNoteService $debitNoteService)
    {
    }

    /**
     * Display a listing of debit notes
     */
    public function index(Request $request): View
    {
        try {
            $filters = [
                'vendor_id' => $request->get('vendor_id'),
                'status' => $request->get('status'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'search' => $request->get('search'),
            ];

            $perPage = $request->get('per_page', 20);
            $debitNotes = $this->debitNoteService->getAllDebitNotes($filters, $perPage);
            $statistics = $this->debitNoteService->getDebitNoteStatistics();

            // Get filter options
            $vendors = \App\Models\Vendor::orderBy('name')->get();
            $statusOptions = [
                'draft' => 'Draft',
                'pending' => 'Pending',
                'issued' => 'Issued',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
            ];

            return view('debit-notes.index', compact(
                'debitNotes',
                'statistics',
                'vendors',
                'statusOptions',
                'filters'
            ));
        } catch (\Exception $e) {
            \Log::error('Error in debit notes index: ' . $e->getMessage());
            
            // Return empty data with error message
            $debitNotes = \App\Models\DebitNote::paginate(20);
            $debitNotes->setCollection(collect());
            
            $statistics = [
                'total' => 0,
                'pending' => 0,
                'issued' => 0,
                'cancelled' => 0,
                'expired' => 0,
                'total_amount' => 0,
                'remaining_amount' => 0,
                'applied_amount' => 0,
            ];
            
            // Ensure vendors is always a proper collection
            try {
                $vendors = \App\Models\Vendor::orderBy('name')->get();
            } catch (\Exception $vendorException) {
                \Log::error('Error loading vendors: ' . $vendorException->getMessage());
                $vendors = collect();
            }
            
            $statusOptions = [
                'draft' => 'Draft',
                'pending' => 'Pending',
                'issued' => 'Issued',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
            ];
            
            // Flashed for this request only. View::with() would bind an $error
            // view variable, but the layout reads the message off the session,
            // so the toast never rendered.
            session()->now('error', 'Unable to load debit notes. Please try again later.');

            return view('debit-notes.index', compact(
                'debitNotes',
                'statistics',
                'vendors',
                'statusOptions',
                'filters'
            ));
        }
    }

    /**
     * API endpoint to get all debit notes
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handlePaginatedApiOperation(
            function() use ($request) {
                $filters = [
                    'vendor_id' => $request->get('vendor_id'),
                    'status' => $request->get('status'),
                    'date_from' => $request->get('date_from'),
                    'date_to' => $request->get('date_to'),
                    'search' => $request->get('search'),
                ];

                $perPage = $request->get('per_page', 20);
                return $this->debitNoteService->getAllDebitNotes($filters, $perPage);
            },
            'debit notes',
            'Debit notes retrieved successfully'
        );
    }

    /**
     * Display the specified debit note
     */
    public function show(DebitNote $debitNote): View
    {
        // journalEntry.lines.account feeds <x-journal-ledger>; without it the
        // ledger issues a query per line.
        $debitNote->load([
            'vendor',
            'supplierBill',
            'returnTransaction.product',
            'items.product',
            'journalEntry.lines.account',
            'createdBy',
            'approvedBy',
            'cancelledBy',
        ]);
        
        return view('debit-notes.show', compact('debitNote'));
    }

    /**
     * API endpoint to get a specific debit note
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                return $this->debitNoteService->getDebitNote($id);
            },
            'debit note',
            'Debit note retrieved successfully'
        );
    }

    /**
     * Generate debit note for a return
     */
    public function generateForReturn(StockTransaction $return): RedirectResponse
    {
        try {
            $debitNote = $this->debitNoteService->generateDebitNote($return);
            
            return redirect()->route('debit-notes.show', $debitNote)
                           ->with('success', "Debit note #{$debitNote->debit_note_number} generated successfully for return #{$return->formatted_id}.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to generate debit note: ' . $e->getMessage()]);
        }
    }

    /**
     * Cancel a debit note
     */
    public function cancel(DebitNote $debitNote): RedirectResponse
    {
        try {
            $debitNote = $this->debitNoteService->cancelDebitNote($debitNote);
            
            return redirect()->route('debit-notes.show', $debitNote)
                           ->with('success', "Debit note #{$debitNote->debit_note_number} cancelled successfully.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to cancel debit note: ' . $e->getMessage()]);
        }
    }

    /**
     * Post debit note (create journal entry with draft status)
     */
    public function post(DebitNote $debitNote): RedirectResponse
    {
        try {
            $debitNote = $this->debitNoteService->postDebitNote($debitNote);
            
            return redirect()->route('debit-notes.show', $debitNote)
                           ->with('success', "Debit Note successfully posted. A corresponding journal entry has been generated with draft status for further review.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Approve the journal entry (change from draft to approved)
     */
    public function approveJournalEntry(DebitNote $debitNote): RedirectResponse
    {
        try {
            $debitNote = $this->debitNoteService->approveJournalEntry($debitNote);

            return redirect()->route('debit-notes.show', $debitNote)
                           ->with('success', "Journal entry for debit note #{$debitNote->debit_note_number} has been approved. Post it to put the reversal on the ledger.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to approve journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Post the journal entry (change from approved to posted)
     */
    public function postJournalEntry(DebitNote $debitNote): RedirectResponse
    {
        try {
            $debitNote = $this->debitNoteService->postJournalEntry($debitNote);
            
            return redirect()->route('debit-notes.show', $debitNote)
                           ->with('success', "Journal entry for debit note #{$debitNote->debit_note_number} has been posted successfully.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to post journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Download debit note as PDF
     */
    public function download(DebitNote $debitNote)
    {
        $debitNote->load(['vendor', 'supplierBill', 'returnTransaction.product', 'createdBy']);
        
        // For now, return a simple view. You can implement PDF generation later
        return view('debit-notes.pdf', compact('debitNote'));
    }
} 
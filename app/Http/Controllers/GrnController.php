<?php

namespace App\Http\Controllers;

use App\Models\Grn;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Services\GrnService;

class GrnController extends Controller
{
    public function __construct(private readonly GrnService $service)
    {
    }

    public function index(): View
    {
        $grns = Grn::with(['supply.vendor'])->latest()->get();
        return view('grns.index', compact('grns'));
    }

    public function updateStatus(int $id): RedirectResponse
    {
        $grn   = Grn::findOrFail($id);
        if ($grn->status === 'posted') {
            return back()->with('info', "GRN #{$grn->id} is already posted.");
        }
        $next  = $grn->status === 'draft' ? 'posted' : 'posted';

        // Delegate heavy lifting to the service so that when we hit
        // "posted" the stock gets updated automatically.
        $this->service->transitionStatus($grn->id, $next);

        // If GRN was posted, redirect to the supplier bill page
        if ($next === 'posted' && $grn->supplierBill) {
            return redirect()->route('supplier-bills.show', $grn->supplierBill)
                ->with('success', "GRN #{$grn->id} marked as {$next}. Supplier Bill generated.");
        }

        return back()->with('success', "GRN #{$grn->id} marked as {$next}.");
    }

    public function show(int $id): View
    {
        $grn = Grn::with(['supply.vendor', 'supply.warehouse', 'supply.items.product'])->findOrFail($id);
        return view('grns.show', compact('grn'));
    }
} 
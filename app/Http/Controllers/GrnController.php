<?php

namespace App\Http\Controllers;

use App\Models\Grn;
use App\Services\GrnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GrnController extends Controller
{
    public function __construct(private readonly GrnService $service) {}

    public function index(): View
    {
        // Paginated like every other listing. This loaded every GRN ever
        // received on one page.
        $grns = Grn::with(['supply.vendor'])->latest()->paginate(20);

        return view('grns.index', compact('grns'));
    }

    public function updateStatus(int $id): RedirectResponse
    {
        $grn = Grn::findOrFail($id);
        if ($grn->status === 'posted') {
            return back()->with('info', "GRN #{$grn->id} is already posted.");
        }
        // Receiving is the only transition this button makes. Both arms of the
        // ternary that used to stand here said 'posted'.
        $this->service->transitionStatus($grn->id, 'posted');

        if ($bill = $grn->fresh()->supplierBill) {
            return redirect()->route('supplier-bills.show', $bill)
                ->with('success', "GRN #{$grn->id} marked as posted. Supplier Bill generated.");
        }

        return back()->with('success', "GRN #{$grn->id} marked as posted.");
    }

    public function show(int $id): View
    {
        $grn = Grn::with([
            'supply.vendor',
            'supply.warehouse',
            'supply.items.product',
            'supplierBill.payment',
        ])->findOrFail($id);

        return view('grns.show', compact('grn'));
    }

    public function edit(int $id): View
    {
        $grn = Grn::with(['supply.vendor', 'supply.warehouse', 'supply.items.product'])->findOrFail($id);

        return view('grns.edit', compact('grn'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $grn = Grn::findOrFail($id);

        // Only allow deletion if GRN is in draft status
        if ($grn->status !== 'draft') {
            return back()->with('error', 'Cannot delete a posted GRN.');
        }

        $grn->delete();

        return redirect()->route('grns.index')
            ->with('success', "GRN #{$grn->id} has been deleted.");
    }
}

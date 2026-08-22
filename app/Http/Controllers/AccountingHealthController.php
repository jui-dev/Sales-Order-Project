<?php

namespace App\Http\Controllers;

use App\Accounting\Reconciliation\ReconciliationService;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Does the ledger agree with everything else that knows the same numbers?
 *
 * A control account is only worth having if somebody checks it against the
 * subsidiary ledger behind it. This is where that happens.
 */
class AccountingHealthController extends Controller
{
    public function __construct(
        private readonly ReconciliationService $reconciliation,
    ) {
    }

    public function index(Request $request): View
    {
        $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        $asOf = $request->as_of ?: now()->toDateString();

        $checks = $this->reconciliation->run($asOf);

        return view('accounting.health', [
            'asOf'              => $asOf,
            'checks'            => $checks,
            'allPassed'         => collect($checks)->every(fn ($check) => $check->passed),
            'unresolvableRoles' => $this->reconciliation->unresolvableRoles(),
            // A backlog of unposted entries is not a fault, but it does explain
            // a statement that looks emptier than it should.
            'pendingManual'     => JournalEntry::whereIn('status', [
                JournalEntry::STATUS_DRAFT,
                JournalEntry::STATUS_APPROVED,
            ])->count(),
            'periods'           => FiscalPeriod::orderByDesc('starts_on')->limit(6)->get(),
        ]);
    }
}

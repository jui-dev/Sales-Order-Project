<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class ChartOfAccountsController extends Controller
{
    /**
     * Display a listing of the chart of accounts.
     */
    public function index(Request $request)
    {
        // ------------------------------------------------------------------
        // Auto-seed default Chart of Accounts
        // ------------------------------------------------------------------
        // New installations or freshly migrated databases might not have any
        // accounts yet. To provide a functional UI out-of-the-box we run the
        // ChartOfAccountsSeeder the first time the table is found empty.
        if (Account::count() === 0) {
            // Running the seeder programmatically avoids requiring the user
            // to remember an additional `db:seed` step after migrations.
            app(\Database\Seeders\ChartOfAccountsSeeder::class)->run();
        }

        // Fetch all accounts with their types for display & dropdowns
        $accounts = Account::with('accountType')->orderBy('code')->get();

        // Types list for dropdown (id => name)
        $types = \App\Models\AccountType::orderBy('name')->pluck('name', 'id');

        return view('accounts.chart-of-accounts', [
            'accounts' => $accounts,
            'types'    => $types,
        ]);
    }

    /**
     * Store a newly created account in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'            => ['required', 'string', 'max:255', 'unique:accounts,code'],
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'account_type_id' => ['required', 'exists:account_types,id'],
            'parent_id'       => ['nullable', 'exists:accounts,id'],
        ]);

        Account::create($validated);

        return redirect()
            ->route('accounting.chart-of-accounts')
            ->with('success', 'Account created successfully.');
    }
} 
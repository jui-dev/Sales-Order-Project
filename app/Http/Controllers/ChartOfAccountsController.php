<?php

namespace App\Http\Controllers;

use App\Services\ChartOfAccountsService;
use App\Traits\HasApiResponses;
use App\Exceptions\DataNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class ChartOfAccountsController extends Controller
{
    use HasApiResponses;

    public function __construct(private readonly ChartOfAccountsService $chartOfAccountsService)
    {
    }

    /**
     * Display a listing of the chart of accounts.
     */
    public function index(Request $request): View
    {
        try {
            \Log::info('Starting to load chart of accounts...');
            
            $this->chartOfAccountsService->ensureDefaultAccounts();
            
            \Log::info('Default accounts ensured, getting all accounts...');
            
            $accounts = $this->chartOfAccountsService->getAllAccounts();
            
            \Log::info('Accounts loaded successfully. Count: ' . $accounts->count());

            return view('accounts.chart-of-accounts', [
                'accounts' => $accounts,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading chart of accounts: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return a simple error view instead of throwing
            return view('accounts.chart-of-accounts', [
                'accounts' => collect(),
                'error' => 'Unable to load chart of accounts. Please try again later.'
            ]);
        }
    }

    /**
     * API endpoint to get all accounts
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return $this->handleApiOperation(
            function() use ($request) {
                $this->chartOfAccountsService->ensureDefaultAccounts();
                return $this->chartOfAccountsService->getAllAccounts();
            },
            'accounts',
            'Accounts retrieved successfully'
        );
    }

    /**
     * API endpoint to get a specific account
     */
    public function apiShow(int $id): JsonResponse
    {
        return $this->handleSingleItemApiOperation(
            function() use ($id) {
                return $this->chartOfAccountsService->getAccountWithDetails($id);
            },
            'account',
            'Account retrieved successfully'
        );
    }

    /**
     * Show the form for creating a new account.
     */
    public function create(): View
    {
        $types = $this->chartOfAccountsService->getAccountTypesForDropdown();
        $accounts = $this->chartOfAccountsService->getAllAccounts();

        return view('accounts.create', [
            'types' => $types,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a newly created account in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'            => ['required', 'string', 'max:255', 'unique:accounts,code'],
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'account_type_id' => ['required', 'exists:account_types,id'],
            'parent_id'       => ['nullable', 'exists:accounts,id'],
        ]);

        $this->chartOfAccountsService->createAccount($validated);

        return redirect()
            ->route('accounting.chart-of-accounts')
            ->with('success', 'Account created successfully.');
    }
} 
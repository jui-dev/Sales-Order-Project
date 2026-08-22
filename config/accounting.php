<?php

/*
|--------------------------------------------------------------------------
| Accounting
|--------------------------------------------------------------------------
|
| The single source of truth for the chart of accounts. Business logic never
| names an account code: it names a *role* (App\Accounting\AccountRole) and
| the resolver looks the code up here. Renumbering the chart is therefore a
| change to this file alone, and an account that no rule refers to cannot be
| silently depended on from somewhere else.
|
| Each entry is keyed by the role's backed value.
|
|   code               the account code, unique across the chart
|   name               what it is called on screen and in reports
|   type               Asset | Liability | Equity | Revenue | Expense
|   contra             carries the opposite balance to its type
|   control_of         customer | vendor - every line against this account
|                      must carry that party, so the account can be
|                      reconciled against its subsidiary ledger
|   requires_location  every line must say which stock location it belongs to
|   postable           false for rollup/parent accounts
|
*/

return [

    'base_currency' => env('ACCOUNTING_BASE_CURRENCY', 'USD'),

    /*
    | Money is held as integer minor units everywhere inside the accounting
    | code. Two decimal places is the ledger's scale; unit costs are carried
    | at four and rounded to this when they become a journal amount.
    */
    'scale' => 2,

    'chart' => [

        // ------------------------------------------------------------------
        // Assets
        // ------------------------------------------------------------------
        'cash' => [
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'Asset',
            'description' => 'Cash on hand and in bank.',
        ],

        'accounts_receivable' => [
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => 'Asset',
            'control_of' => 'customer',
            'description' => 'Amounts owed by customers. Control account for the customer subsidiary ledger.',
        ],

        'inventory' => [
            'code' => '1200',
            'name' => 'Inventory',
            'type' => 'Asset',
            'requires_location' => true,
            'description' => 'Goods held for sale, at cost. Analysed by stock location.',
        ],

        'input_tax_recoverable' => [
            'code' => '1250',
            'name' => 'Input Tax Recoverable',
            'type' => 'Asset',
            'description' => 'Tax paid to vendors and recoverable from the tax authority.',
        ],

        // ------------------------------------------------------------------
        // Liabilities
        // ------------------------------------------------------------------
        'accounts_payable' => [
            'code' => '2000',
            'name' => 'Accounts Payable',
            'type' => 'Liability',
            'control_of' => 'vendor',
            'description' => 'Amounts owed to vendors. Control account for the vendor subsidiary ledger.',
        ],

        'goods_received_not_invoiced' => [
            'code' => '2050',
            'name' => 'Goods Received Not Invoiced',
            'type' => 'Liability',
            'description' => 'Clearing account. Holds the value of goods received from the moment they arrive until the vendor bill for them is posted.',
        ],

        'sales_tax_payable' => [
            'code' => '2100',
            'name' => 'Sales Tax Payable',
            'type' => 'Liability',
            'description' => 'Tax collected from customers and owed to the tax authority. Never revenue.',
        ],

        // ------------------------------------------------------------------
        // Equity
        // ------------------------------------------------------------------
        'owners_equity' => [
            'code' => '3000',
            'name' => "Owner's Equity",
            'type' => 'Equity',
            'description' => 'Capital introduced by the owner.',
        ],

        'retained_earnings' => [
            'code' => '3010',
            'name' => 'Retained Earnings',
            'type' => 'Equity',
            'description' => 'Accumulated profit of closed periods. Written only by the period close.',
        ],

        'opening_balance_equity' => [
            'code' => '3100',
            'name' => 'Opening Balance Equity',
            'type' => 'Equity',
            'description' => 'The contra side of the opening balance entry. Should be zero once the books are fully opened.',
        ],

        // ------------------------------------------------------------------
        // Revenue
        // ------------------------------------------------------------------
        'sales_revenue' => [
            'code' => '4000',
            'name' => 'Sales Revenue',
            'type' => 'Revenue',
            'description' => 'Income from sales, net of tax.',
        ],

        'sales_discount' => [
            'code' => '4100',
            'name' => 'Sales Discounts',
            'type' => 'Revenue',
            'contra' => true,
            'description' => 'Discounts granted to customers. Reduces revenue rather than being an expense.',
        ],

        'sales_returns' => [
            'code' => '4200',
            'name' => 'Sales Returns & Allowances',
            'type' => 'Revenue',
            'contra' => true,
            'description' => 'Revenue reversed by customer returns. Kept separate so gross sales stay visible.',
        ],

        // ------------------------------------------------------------------
        // Expense
        // ------------------------------------------------------------------
        'cost_of_goods_sold' => [
            'code' => '5000',
            'name' => 'Cost of Goods Sold',
            'type' => 'Expense',
            'description' => 'Cost of the goods that have shipped, relieved from inventory.',
        ],

        'purchase_returns' => [
            'code' => '5100',
            'name' => 'Purchase Returns',
            'type' => 'Expense',
            'contra' => true,
            'description' => 'Price adjustments on goods returned to vendors. Reduces cost.',
        ],

    ],
];

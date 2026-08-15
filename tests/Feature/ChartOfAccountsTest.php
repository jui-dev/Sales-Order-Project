<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_of_accounts_index_page_loads_without_form()
    {
        // Tests\TestCase seeds the chart of accounts, so Asset and 1000 Cash
        // are already there - creating them again trips the unique constraints.
        $this->assertNotNull(AccountType::where('name', 'Asset')->first());
        $this->assertNotNull(Account::where('code', '1000')->first());

        $response = $this->get(route('accounting.chart-of-accounts'));

        $response->assertStatus(200);
        $response->assertViewIs('accounts.chart-of-accounts');
        
        // Should not contain the create form. assertSee escapes its needle by
        // default, so raw markup has to be matched with escaping turned off -
        // otherwise this passes whether the form is on the page or not.
        $response->assertDontSee('Create Account Form');
        $response->assertDontSee('form method="POST"', false);
        
        // Should contain the Create Account button
        $response->assertSee('Create Account');
        $response->assertSee(route('accounting.chart-of-accounts.create'));
    }

    public function test_create_account_page_loads_with_form()
    {
        $response = $this->get(route('accounting.chart-of-accounts.create'));

        $response->assertStatus(200);
        $response->assertViewIs('accounts.create');
        
        // Should contain the create form
        $response->assertSee('Create New Account');
        $response->assertSee('form method="POST"', false);
        $response->assertSee(route('accounting.chart-of-accounts.store'));
        
        // Should contain form fields
        $response->assertSee('Code');
        $response->assertSee('Account Name');
        $response->assertSee('Description');
        $response->assertSee('Account Type');
        $response->assertSee('Parent Account');
    }

    public function test_can_create_new_account()
    {
        $assetType = AccountType::where('name', 'Asset')->firstOrFail();

        // 1500 is outside the seeded range, so this exercises creation rather
        // than colliding with an account the seeder already owns.
        $response = $this->post(route('accounting.chart-of-accounts.store'), [
            'code' => '1500',
            'name' => 'Prepaid Expenses',
            'description' => 'Costs paid ahead of the period they belong to',
            'account_type_id' => $assetType->id,
            'parent_id' => null,
        ]);

        $response->assertRedirect(route('accounting.chart-of-accounts'));
        $response->assertSessionHas('success', 'Account created successfully.');

        $this->assertDatabaseHas('accounts', [
            'code' => '1500',
            'name' => 'Prepaid Expenses',
            'description' => 'Costs paid ahead of the period they belong to',
            'account_type_id' => $assetType->id,
        ]);
    }

    public function test_create_account_validation_errors()
    {
        $response = $this->post(route('accounting.chart-of-accounts.store'), [
            'code' => '',
            'name' => '',
            'account_type_id' => '',
        ]);

        $response->assertSessionHasErrors(['code', 'name', 'account_type_id']);
    }
} 
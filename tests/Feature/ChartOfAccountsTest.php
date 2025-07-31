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
        // Create some account types and accounts for testing
        $assetType = AccountType::create(['name' => 'Asset']);
        $account = Account::create([
            'code' => '1000',
            'name' => 'Cash',
            'account_type_id' => $assetType->id,
        ]);

        $response = $this->get(route('accounting.chart-of-accounts'));

        $response->assertStatus(200);
        $response->assertViewIs('accounts.chart-of-accounts');
        
        // Should not contain the create form
        $response->assertDontSee('Create Account Form');
        $response->assertDontSee('form method="POST"');
        
        // Should contain the Create Account button
        $response->assertSee('Create Account');
        $response->assertSee(route('accounting.chart-of-accounts.create'));
    }

    public function test_create_account_page_loads_with_form()
    {
        // Create some account types and accounts for testing
        $assetType = AccountType::create(['name' => 'Asset']);
        $account = Account::create([
            'code' => '1000',
            'name' => 'Cash',
            'account_type_id' => $assetType->id,
        ]);

        $response = $this->get(route('accounting.chart-of-accounts.create'));

        $response->assertStatus(200);
        $response->assertViewIs('accounts.create');
        
        // Should contain the create form
        $response->assertSee('Create New Account');
        $response->assertSee('form method="POST"');
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
        $assetType = AccountType::create(['name' => 'Asset']);

        $response = $this->post(route('accounting.chart-of-accounts.store'), [
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'description' => 'Money owed by customers',
            'account_type_id' => $assetType->id,
            'parent_id' => null,
        ]);

        $response->assertRedirect(route('accounting.chart-of-accounts'));
        $response->assertSessionHas('success', 'Account created successfully.');

        $this->assertDatabaseHas('accounts', [
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'description' => 'Money owed by customers',
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
<?php

namespace App\Accounting;

use App\Accounting\Exceptions\UnresolvedAccountRole;

/**
 * What an account is *for*, as opposed to what it is numbered.
 *
 * Posting rules name a role; config/accounting.php maps the role to a code.
 * Nothing outside this class and that config file may mention an account code,
 * which is what makes the chart renumberable and stops the same account being
 * referred to by two different literals in two different files - the mistake
 * that once let supplier bills and vendor returns post to different payables.
 */
enum AccountRole: string
{
    case Cash                     = 'cash';
    case AccountsReceivable       = 'accounts_receivable';
    case Inventory                = 'inventory';
    case InputTaxRecoverable      = 'input_tax_recoverable';

    case AccountsPayable          = 'accounts_payable';
    case GoodsReceivedNotInvoiced = 'goods_received_not_invoiced';
    case SalesTaxPayable          = 'sales_tax_payable';

    case OwnersEquity             = 'owners_equity';
    case RetainedEarnings         = 'retained_earnings';
    case OpeningBalanceEquity     = 'opening_balance_equity';

    case SalesRevenue             = 'sales_revenue';
    case SalesDiscount            = 'sales_discount';
    case SalesReturns             = 'sales_returns';

    case CostOfGoodsSold          = 'cost_of_goods_sold';
    case PurchaseReturns          = 'purchase_returns';

    /**
     * The chart definition behind this role.
     *
     * @return array<string,mixed>
     */
    public function definition(): array
    {
        $definition = config('accounting.chart.' . $this->value);

        if (! is_array($definition) || ! isset($definition['code'])) {
            throw UnresolvedAccountRole::undefined($this);
        }

        return $definition;
    }

    public function code(): string
    {
        return (string) $this->definition()['code'];
    }

    public function label(): string
    {
        return (string) $this->definition()['name'];
    }

    /** 'customer', 'vendor', or null when this is not a control account. */
    public function controlOf(): ?string
    {
        return $this->definition()['control_of'] ?? null;
    }

    public function requiresLocation(): bool
    {
        return (bool) ($this->definition()['requires_location'] ?? false);
    }

    public function isContra(): bool
    {
        return (bool) ($this->definition()['contra'] ?? false);
    }
}

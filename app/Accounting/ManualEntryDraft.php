<?php

namespace App\Accounting;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Retailer;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Turns what a person typed on the journal entry form into a JournalDraft.
 *
 * Manual entries used to be written straight to journal_entries and
 * journal_entry_lines by JournalEntryService, which is the only thing in the
 * application that ever wrote to the ledger from outside app/Accounting. Four
 * guarantees the rest of the ledger relies on were missing as a result: the
 * period guard, the exact Money balance check, the requirement that a control
 * account names its party, and the refusal to post to a rollup account.
 *
 * Building a draft here means an entry a person typed is checked by exactly the
 * same code as an entry a posting rule raised.
 */
final class ManualEntryDraft
{
    /**
     * The models a line may point at, keyed by the value the form submits.
     *
     * A whitelist rather than a class-string off the request: the dimension
     * columns are morphs, and letting a form name the class would let it name
     * any class.
     *
     * @var array<string,class-string<Model>>
     */
    private const PARTIES = [
        'customer' => Customer::class,
        'vendor' => Vendor::class,
    ];

    /** @var array<string,class-string<Model>> */
    private const LOCATIONS = [
        'warehouse' => Warehouse::class,
        'retailer' => Retailer::class,
    ];

    /**
     * @param array{entry_date:string,description?:?string,lines:array<int,array<string,mixed>>} $data
     */
    public static function from(array $data): JournalDraft
    {
        $draft = JournalDraft::for(null)
            ->on($data['entry_date'])
            ->describedAs($data['description'] ?? null);

        foreach ($data['lines'] ?? [] as $line) {
            $account = Account::find($line['account_id'] ?? null);

            if (! $account) {
                throw new InvalidArgumentException('A line names an account that does not exist.');
            }

            // The chart says a rollup account is not postable, and posting
            // rules have always been refused one by AccountResolver. Typing an
            // entry was the way round it.
            if (! $account->is_postable) {
                throw new InvalidArgumentException(sprintf(
                    '%s (%s) is a rollup account and cannot be posted to directly.',
                    $account->name,
                    $account->code,
                ));
            }

            $debit = Money::of((string) ($line['debit'] ?? 0));
            $credit = Money::of((string) ($line['credit'] ?? 0));

            $draft->add(
                $account,
                $debit->minus($credit),
                array_filter([
                    'party' => self::resolve($line['party'] ?? null, self::PARTIES),
                    'location' => self::resolve($line['location'] ?? null, self::LOCATIONS),
                ]),
                $line['description'] ?? null,
            );
        }

        return $draft;
    }

    /**
     * Read a "customer:12" style dimension back into the model it names.
     *
     * @param array<string,class-string<Model>> $allowed
     */
    private static function resolve(?string $value, array $allowed): ?Model
    {
        if ($value === null || $value === '') {
            return null;
        }

        [$type, $id] = array_pad(explode(':', $value, 2), 2, null);

        if (! isset($allowed[$type]) || ! is_numeric($id)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a dimension a line can carry.', $value));
        }

        return $allowed[$type]::find((int) $id);
    }

    /**
     * The dimension choices the form offers, as value => label.
     *
     * @return array{parties:array<string,string>,locations:array<string,string>}
     */
    public static function options(): array
    {
        $parties = [];
        $locations = [];

        foreach (self::PARTIES as $key => $class) {
            foreach ($class::orderBy('name')->get() as $model) {
                $parties[$key . ':' . $model->getKey()] = ucfirst($key) . ' - ' . $model->name;
            }
        }

        foreach (self::LOCATIONS as $key => $class) {
            foreach ($class::orderBy('name')->get() as $model) {
                $locations[$key . ':' . $model->getKey()] = ucfirst($key) . ' - ' . $model->name;
            }
        }

        return ['parties' => $parties, 'locations' => $locations];
    }
}

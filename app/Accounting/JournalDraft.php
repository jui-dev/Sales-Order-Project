<?php

namespace App\Accounting;

use App\Accounting\Exceptions\MissingDimension;
use App\Accounting\Exceptions\UnbalancedEntry;
use App\Models\Account;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An entry being built, before anything is written.
 *
 * Posting rules describe what happened in terms of roles and amounts and hand
 * back one of these; the engine is what turns it into rows. Keeping the two
 * apart is what makes a rule testable without a database, and it is why the
 * dimension requirements below can be enforced at build time rather than
 * discovered as an unexplainable variance months later.
 */
final class JournalDraft
{
    /** @var array<int,array<string,mixed>> */
    private array $lines = [];

    private ?CarbonInterface $date = null;

    private ?string $description = null;

    private function __construct(
        public readonly ?Model $source,
        /**
         * Which rule wrote this, or null for an entry a person typed.
         *
         * Null rather than a placeholder on purpose: the uniqueness rule that
         * stops a rule running twice for the same document is a unique index,
         * and repeated nulls do not collide in one. A manual entry has no rule
         * behind it and must not be limited to one per source.
         */
        public readonly ?string $ruleKey,
    ) {
    }

    public static function for(?Model $source, ?string $ruleKey = null): self
    {
        return new self($source, $ruleKey);
    }

    // ------------------------------------------------------------------
    // Header
    // ------------------------------------------------------------------

    public function on(CarbonInterface|string|null $date): self
    {
        $this->date = $date === null ? null : Carbon::parse($date);

        return $this;
    }

    public function describedAs(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function date(): CarbonInterface
    {
        return $this->date ?? Carbon::now();
    }

    public function description(): ?string
    {
        return $this->description;
    }

    // ------------------------------------------------------------------
    // Lines
    // ------------------------------------------------------------------

    /**
     * @param array{party?:Model|null,location?:Model|null,product?:Model|int|null} $dimensions
     */
    public function debit(AccountRole|Account $role, Money $amount, array $dimensions = [], ?string $description = null): self
    {
        return $this->add($role, $amount, $dimensions, $description);
    }

    /**
     * @param array{party?:Model|null,location?:Model|null,product?:Model|int|null} $dimensions
     */
    public function credit(AccountRole|Account $role, Money $amount, array $dimensions = [], ?string $description = null): self
    {
        return $this->add($role, $amount->negated(), $dimensions, $description);
    }

    /**
     * Add a signed amount: positive debits, negative credits.
     *
     * Taking the sign rather than the side is what lets a rule post a
     * difference that could fall either way - a vendor crediting more or less
     * than the goods cost - without branching at the call site.
     *
     * @param array{party?:Model|null,location?:Model|null,product?:Model|int|null} $dimensions
     */
    public function add(AccountRole|Account $role, Money $signed, array $dimensions = [], ?string $description = null): self
    {
        // A zero line says nothing and would only clutter the entry. Rules can
        // therefore post tax or discount unconditionally and let an absent one
        // fall away.
        if ($signed->isZero()) {
            return $this;
        }

        $this->assertDimensions($role, $dimensions);

        $product = $dimensions['product'] ?? null;

        $this->lines[] = [
            'role'          => $role,
            'debit'         => $signed->isPositive() ? $signed : Money::zero(),
            'credit'        => $signed->isNegative() ? $signed->absolute() : Money::zero(),
            'description'   => $description ?? $this->description,
            'party'         => $dimensions['party'] ?? null,
            'location'      => $dimensions['location'] ?? null,
            'product_id'    => $product instanceof Model ? $product->getKey() : $product,
        ];

        return $this;
    }

    /**
     * @param array<string,mixed> $dimensions
     */
    private function assertDimensions(AccountRole|Account $role, array $dimensions): void
    {
        // An account handed in directly carries the same requirements as a
        // role. The closing and opening entries reach accounts by object
        // rather than by role, and must not become a way around this.
        $controlOf = $role instanceof AccountRole ? $role->controlOf() : $role->control_of;
        $needsLocation = $role instanceof AccountRole ? $role->requiresLocation() : (bool) $role->requires_location;
        $name = $role instanceof AccountRole
            ? $role->label() . ' (' . $role->code() . ')'
            : $role->name . ' (' . $role->code . ')';

        if ($controlOf !== null && ! $this->isRealModel($dimensions['party'] ?? null)) {
            throw MissingDimension::party($name, $controlOf);
        }

        if ($needsLocation && ! $this->isRealModel($dimensions['location'] ?? null)) {
            throw MissingDimension::location($name);
        }
    }

    /**
     * A dimension has to point at a row that exists.
     *
     * belongsTo(...)->withDefault() hands back an unsaved model rather than
     * null, which passes an instanceof check and would write a party_type with
     * no party_id - a line that looks reconcilable and is not.
     */
    private function isRealModel(mixed $value): bool
    {
        return $value instanceof Model && $value->getKey() !== null;
    }

    // ------------------------------------------------------------------
    // Totals
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function totalDebit(): Money
    {
        return Money::sum(array_column($this->lines, 'debit'));
    }

    public function totalCredit(): Money
    {
        return Money::sum(array_column($this->lines, 'credit'));
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit()->equals($this->totalCredit());
    }

    /**
     * Throw unless this draft is fit to be written.
     *
     * An empty draft is legitimate - a picking list of products with no
     * recorded cost has nothing to say - and the engine treats it as nothing
     * to do rather than as a failure.
     */
    public function assertValid(): void
    {
        if ($this->isEmpty()) {
            return;
        }

        if (! $this->isBalanced()) {
            throw UnbalancedEntry::of($this->totalDebit(), $this->totalCredit(), $this->context());
        }
    }

    public function context(): string
    {
        return $this->source
            ? sprintf('%s #%s (%s)', class_basename($this->source), $this->source->getKey(), $this->ruleKey ?? 'manual')
            : ($this->ruleKey ?? 'manual entry');
    }
}

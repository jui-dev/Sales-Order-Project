<?php

namespace App\Accounting\Posting;

use Illuminate\Database\Eloquent\Model;

/**
 * Every posting rule the system knows, indexed by the document it reads.
 *
 * One place to look to answer "what does this document post?" - a question
 * that previously needed a grep for account codes across observers and
 * services.
 */
class PostingRuleRegistry
{
    /** @var array<int,PostingRule> */
    private array $rules = [];

    /** @param iterable<PostingRule> $rules */
    public function __construct(iterable $rules = [])
    {
        foreach ($rules as $rule) {
            $this->register($rule);
        }
    }

    public function register(PostingRule $rule): self
    {
        $this->rules[$rule->key()] = $rule;

        return $this;
    }

    /** @return array<int,PostingRule> */
    public function all(): array
    {
        return array_values($this->rules);
    }

    public function byKey(string $key): ?PostingRule
    {
        return $this->rules[$key] ?? null;
    }

    /**
     * The rules that read this kind of document, whatever state it is in.
     *
     * @return array<int,PostingRule>
     */
    public function forDocument(Model $document): array
    {
        return array_values(array_filter(
            $this->rules,
            fn (PostingRule $rule) => $document instanceof ($rule->documentType()),
        ));
    }

    /**
     * The rules that apply to this document as it stands right now.
     *
     * @return array<int,PostingRule>
     */
    public function applicableTo(Model $document): array
    {
        return array_values(array_filter(
            $this->forDocument($document),
            fn (PostingRule $rule) => $rule->appliesTo($document),
        ));
    }
}

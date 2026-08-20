<?php

namespace App\Support\Nav;

use Illuminate\Database\Eloquent\Model;

/**
 * Collects, for the length of one request, the records an action created or
 * moved on - so the next page can say what a button press actually did.
 *
 * Registered as a singleton and fed by a wildcard Eloquent listener (see
 * ServiceServiceProvider), which is why no service or observer in the
 * application needs to know this class exists.
 *
 * It starts switched off. TrackTriggeredEffects turns it on for state-changing
 * web requests only, so seeders, artisan commands and the test suite are
 * unaffected by its presence.
 */
final class EffectRecorder
{
    private bool $recording = false;

    /**
     * Recorded effects keyed by "class:id", so a record saved twice in one
     * request is reported once, under whichever verb happened first.
     *
     * @var array<string, array{keys: list<string>, title: string, detail: string, url: ?string}>
     */
    private array $effects = [];

    /**
     * Begin a request with nothing collected.
     *
     * Under php-fpm each request gets a fresh container anyway, but the test
     * suite reuses one application across several requests - so the scope has
     * to be stated here rather than inherited from the process.
     */
    public function reset(): void
    {
        $this->recording = false;
        $this->effects = [];
    }

    public function start(): void
    {
        $this->recording = true;
    }

    public function stop(): void
    {
        $this->recording = false;
    }

    public function isRecording(): bool
    {
        return $this->recording;
    }

    /**
     * Handle one wildcard Eloquent event, e.g. "eloquent.created: App\Models\Grn".
     *
     * @param  array<int, mixed>  $payload
     */
    public function observe(string $eventName, array $payload): void
    {
        $model = $payload[0] ?? null;

        if (! $this->recording || ! $model instanceof Model || ! EffectClassifier::tracks($model)) {
            return;
        }

        $verb = str_starts_with($eventName, 'eloquent.created') ? 'created' : 'updated';
        $identity = $model::class.':'.$model->getKey();

        if (isset($this->effects[$identity])) {
            return;
        }

        if ($effect = EffectClassifier::classify($model, $verb)) {
            $this->effects[$identity] = $effect;
        }
    }

    public function isEmpty(): bool
    {
        return $this->effects === [];
    }

    /**
     * @return list<array{keys: list<string>, title: string, detail: string, url: ?string}>
     */
    public function effects(): array
    {
        return array_values($this->effects);
    }

    /**
     * How many records landed on each sidebar entry.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach ($this->effects as $effect) {
            foreach ($effect['keys'] as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }
}

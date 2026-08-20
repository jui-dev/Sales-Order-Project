<?php

namespace App\Providers;

use App\Support\Nav\EffectRecorder;
use App\Support\Nav\NavEffects;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One recorder per request, shared by the model listener below and the
        // TrackTriggeredEffects middleware that persists what it collected.
        $this->app->singleton(EffectRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->captureTriggeredEffects();
        $this->shareTriggeredEffects();
    }

    /**
     * Watch every model save so an action can report what it set off.
     *
     * A wildcard listener rather than an observer per model: the side effects
     * of one button are spread across a dozen services and observers, and this
     * way none of them has to know the notifications exist. The recorder is
     * switched off unless the middleware turned it on for a state-changing web
     * request, so seeders, artisan commands and tests are unaffected.
     */
    private function captureTriggeredEffects(): void
    {
        $recorder = $this->app->make(EffectRecorder::class);

        Event::listen(
            ['eloquent.created: *', 'eloquent.updated: *'],
            fn (string $event, array $payload) => $recorder->observe($event, $payload),
        );
    }

    /**
     * Feed the "what this triggered" panel.
     *
     * Composed onto the layout only, and under a name of its own, so it stays
     * clear of the shared view defaults in AppServiceProvider. The sidebar
     * badges are not shared this way: they render inside a Blade component,
     * whose scope is isolated from the view that includes it, so <x-nav-badge>
     * reads its own count from NavEffects instead.
     */
    private function shareTriggeredEffects(): void
    {
        View::composer('layouts.app', function ($view) {
            $view->with('triggeredEffects', NavEffects::panel(request()));
        });
    }
}

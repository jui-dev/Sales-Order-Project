<?php

namespace App\Http\Middleware;

use App\Support\Nav\ActionCatalog;
use App\Support\Nav\EffectRecorder;
use App\Support\Nav\NavCatalog;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Carries "what did that button just do" across the redirect that follows it.
 *
 * Runs inside StartSession (it is appended to the web group), so writing to the
 * session after $next() still lands before the session is saved.
 *
 * Two session slots:
 *   COUNTS  persists. A badge raised by an action stays on its menu item until
 *           the reader actually opens that screen, so it survives navigating
 *           elsewhere in between.
 *   DETAIL  is flashed. The panel naming the individual records is only shown
 *           on the page the action lands on.
 */
class TrackTriggeredEffects
{
    public const COUNTS = 'nav.effect_counts';

    public const DETAIL = 'nav.triggered_effects';

    public function __construct(private readonly EffectRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->recorder->reset();

        // Opening a screen spends the badge on it - the reader is looking at
        // the thing the badge was pointing them to.
        if ($request->isMethodSafe()) {
            $this->clearVisited($request);
        } else {
            $this->recorder->start();
        }

        $response = $next($request);

        $this->recorder->stop();

        if (! $this->recorder->isEmpty() && $request->hasSession()) {
            $this->store($request, $response);
        }

        return $response;
    }

    private function clearVisited(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $counts = $request->session()->get(self::COUNTS, []);

        if (! $counts) {
            return;
        }

        foreach (NavCatalog::keysVisitedBy($request) as $key) {
            unset($counts[$key]);
        }

        $request->session()->put(self::COUNTS, $counts);
    }

    private function store(Request $request, Response $response): void
    {
        $session = $request->session();
        $counts = $session->get(self::COUNTS, []);

        foreach ($this->recorder->counts() as $key => $count) {
            $counts[$key] = ($counts[$key] ?? 0) + $count;
        }

        $session->put(self::COUNTS, $counts);

        // The detail panel belongs on the page the user is sent to. An action
        // answered with JSON has no such page, and flashing there would only
        // let some later request consume the panel out of context.
        if ($response instanceof RedirectResponse) {
            $session->flash(self::DETAIL, [
                'action' => ActionCatalog::labelFor($request->route()?->getName()),
                'effects' => $this->recorder->effects(),
            ]);
        }
    }
}

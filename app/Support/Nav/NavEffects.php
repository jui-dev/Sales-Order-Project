<?php

namespace App\Support\Nav;

use App\Http\Middleware\TrackTriggeredEffects;
use Illuminate\Http\Request;

/**
 * Reads what the middleware stored and shapes it for the layout.
 *
 * Everything here is filtered through the same ability the sidebar gates the
 * menu item with, so a badge or a panel row can never point somebody at a
 * screen they are not allowed to open.
 */
final class NavEffects
{
    /** Where the per-request memo of counts() is parked. */
    private const MEMO = 'nav.effect_counts.resolved';

    /**
     * Badge count per menu key, including a total on each collapsible group.
     *
     * Every badge in the sidebar asks for this, and a Blade component cannot
     * see the layout's data, so the answer is memoised on the request rather
     * than recomputed - and its permission checks re-run - thirty-odd times.
     *
     * @return array<string, int>
     */
    public static function counts(Request $request): array
    {
        if ($request->attributes->has(self::MEMO)) {
            return $request->attributes->get(self::MEMO);
        }

        $counts = self::resolveCounts($request);

        $request->attributes->set(self::MEMO, $counts);

        return $counts;
    }

    /** @return array<string, int> */
    private static function resolveCounts(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $counts = [];

        foreach ($request->session()->get(TrackTriggeredEffects::COUNTS, []) as $key => $count) {
            if (is_int($count) && $count > 0 && self::visible($key)) {
                $counts[$key] = $count;
            }
        }

        if (! $counts) {
            return [];
        }

        foreach (NavCatalog::groups() as $group) {
            $total = 0;

            foreach (NavCatalog::childrenOf($group) as $child) {
                $total += $counts[$child] ?? 0;
            }

            if ($total > 0) {
                $counts[$group] = $total;
            }
        }

        return $counts;
    }

    /**
     * The records one action produced, for the panel on the landing page.
     *
     * The success flash is carried along and becomes the panel's headline. The
     * layout then drops the toast that would otherwise repeat it on top of the
     * panel - one action, one notice. Actions that raise no effects (deletes,
     * mostly) still get their toast, because there is no panel to carry them.
     *
     * Returns an empty array rather than null when there is nothing to show:
     * AppServiceProvider composes every view and rewrites null values to an
     * empty Collection, which would reach the component as the wrong shape.
     *
     * @return array{}|array{action: string|null, message: string|null, rows: list<array{title: string, detail: string, url: string|null, where: list<string>}>}
     */
    public static function panel(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $flashed = $request->session()->get(TrackTriggeredEffects::DETAIL);

        if (! is_array($flashed) || empty($flashed['effects'])) {
            return [];
        }

        $rows = [];

        foreach ($flashed['effects'] as $effect) {
            $where = array_values(array_map(
                fn (string $key) => NavCatalog::path($key),
                array_filter($effect['keys'], self::visible(...)),
            ));

            // Every menu this record landed on is one the reader cannot open,
            // so there is nothing useful to tell them about it.
            if ($where === []) {
                continue;
            }

            $rows[] = [
                'title' => $effect['title'],
                'detail' => $effect['detail'],
                'url' => $effect['url'],
                'where' => $where,
            ];
        }

        $message = $request->session()->get('success');

        return $rows === [] ? [] : [
            'action' => $flashed['action'] ?? null,
            'message' => is_string($message) && $message !== '' ? $message : null,
            'rows' => $rows,
        ];
    }

    private static function visible(string $key): bool
    {
        $permission = NavCatalog::permission($key);

        if (! $permission) {
            return false;
        }

        return (bool) auth()->user()?->can($permission);
    }
}

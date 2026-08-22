<?php

namespace App\Http\Middleware;

use App\Support\RoutePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the RoutePermissions table to every authenticated request.
 *
 * EnsurePermission gates one route at a time and is right where a route has a
 * requirement of its own. It could never be the whole answer, though, because
 * it only guards the routes somebody remembered to put it on - and for most of
 * this application nobody had, so the permission catalogue described authority
 * that nothing enforced.
 *
 * This runs after auth on every route instead, and denies by table rather than
 * by omission. A route that already declares its own permission: middleware is
 * left to it.
 */
class EnforceRoutePermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if (! $route || RoutePermissions::declaresItsOwn($route)) {
            return $next($request);
        }

        $required = RoutePermissions::for($route, $request->method());

        if ($required === []) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        foreach ($required as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}

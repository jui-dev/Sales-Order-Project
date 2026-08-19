<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Allow the request through when the user holds ANY of the listed permissions.
     *
     * Usage: ->middleware('permission:users.view,users.manage')
     *
     * Several permissions are treated as alternatives rather than requirements
     * because a "manage" holder should always be able to reach the matching
     * "view" screens without both being spelled out on every route.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}

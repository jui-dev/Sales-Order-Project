<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Every route file is registered here and nowhere else. RouteServiceProvider
        // used to load web.php a second time; because duplicate registrations collapse
        // silently (last one wins), middleware attached at only one of the two sites
        // could be dropped without any visible sign - so the whole application would
        // have stayed reachable without logging in.
        then: function () {
            // Login lives outside the auth group, but still needs session + CSRF.
            Route::middleware('web')->group(base_path('routes/auth.php'));

            // EnforceRoutePermissions runs after auth on every route, applying
            // the App\Support\RoutePermissions table. Gating route by route
            // only ever guarded the routes somebody remembered, and most of
            // this application had been forgotten.
            Route::middleware(['web', 'auth', \App\Http\Middleware\EnforceRoutePermissions::class])
                ->group(base_path('routes/web.php'));

            // The api routes are called by fetch() from Blade pages, so they need
            // the session cookie to authenticate - otherwise they would be an
            // unauthenticated way around everything above.
            //
            // Session middleware is listed explicitly rather than reusing the "web"
            // group, because that group also appends GlobalErrorHandler, which
            // rewrites empty-listing JSON payloads and would change every API
            // response body these endpoints already return.
            Route::middleware([
                'api',
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                // Now that a session cookie rides along, state-changing API calls
                // need CSRF protection they did not previously require.
                \App\Http\Middleware\VerifyCsrfToken::class,
                'auth',
                // The API mirrors the web modules, so it is gated by the same
                // table - otherwise it is a way around every gate on them.
                \App\Http\Middleware\EnforceRoutePermissions::class,
            ])
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register global error handler middleware
        $middleware->alias([
            'global.error.handler' => \App\Http\Middleware\GlobalErrorHandler::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);
        
        // Add global error handler to web middleware group.
        //
        // TrackTriggeredEffects is appended rather than prepended on purpose:
        // it writes to the session after the response is built, and only an
        // inner middleware still gets to do that before StartSession saves.
        $middleware->web(append: [
            \App\Http\Middleware\GlobalErrorHandler::class,
            \App\Http\Middleware\TrackTriggeredEffects::class,
        ]);
        
        // The permission check has to happen before route-model binding, or a
        // user with no rights to a record learns whether it exists: binding
        // 404s on a missing id before the gate ever runs, so the same request
        // answers 404 or 403 depending on what is in the database.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnforceRoutePermissions::class,
        );

        // Replace the default VerifyCsrfToken middleware with our custom one
        $middleware->replace(
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\VerifyCsrfToken::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Register custom exception handler for DataNotFoundException
        $exceptions->renderable(function (\App\Exceptions\DataNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'error_code' => $e->getHttpCode(),
                    'resource' => $e->getResource(),
                    'identifier' => $e->getIdentifier(),
                ], $e->getHttpCode());
            }
        });
    })->create();

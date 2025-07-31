<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register global error handler middleware
        $middleware->alias([
            'global.error.handler' => \App\Http\Middleware\GlobalErrorHandler::class,
        ]);
        
        // Add global error handler to web middleware group
        $middleware->web(append: [
            \App\Http\Middleware\GlobalErrorHandler::class,
        ]);
        
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

<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Laravel always registers a default guest-redirect callback of
        // fn () => route('login'), even when this closure is empty. For
        // api/* requests that call is evaluated EAGERLY inside
        // Authenticate::unauthenticated() (before AuthenticationException
        // is even thrown), and since this project has no 'login' route,
        // it throws RouteNotFoundException instead of a clean 401.
        // Returning null for api/* requests here prevents route('login')
        // from ever being called on that path.
        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('api/*') ? null : route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Belt-and-suspenders: with the redirect fixed above, Laravel's
        // own unauthenticated() already returns clean JSON for api/*
        // (via shouldRenderJsonWhen), but this keeps the response body
        // shape consistent with the rest of this API ({success, message}
        // instead of the framework's bare {message}).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    })->create();

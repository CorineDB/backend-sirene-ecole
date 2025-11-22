<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        using: function () {
            RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
                return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
            });

            // ⚡ Charger les routes API avec préfixe 'api' et middleware 'api'
            \Illuminate\Support\Facades\Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api([
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\ForceJsonResponse::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Register ESP8266 authentication middleware alias
        $middleware->alias([
            'auth.esp8266' => \App\Http\Middleware\AuthenticateEsp8266::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // This ensures default logging continues to work.
        $exceptions->reportable(function (Throwable $e) {
            // You can add custom reporting logic here if needed in the future.
            return true; // Returning true allows default reporting to proceed.
        });

        // This customizes the HTTP response for exceptions.
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
                }

                $statusCode = match (true) {
                    method_exists($e, 'getStatusCode') => $e->getStatusCode(),
                    $e instanceof ValidationException => 422,
                    default => 500,
                };

                $response = [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];

                if ($e instanceof ValidationException) {
                    $response['message'] = 'Validation failed.';
                    $response['errors'] = $e->errors();
                }

                // For security, don't expose detailed error messages in production
                if ($statusCode >= 500 && app()->isProduction()) {
                    $response['message'] = 'Server Error';
                }

                return response()->json($response, $statusCode);
            }
        });
    })
    ->withSchedule(function ($schedule) {
        $schedule->command('abonnements:renouveler-auto')->daily();
    })
    ->create();

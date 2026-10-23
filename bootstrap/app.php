<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\ResolveEstateContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            SecurityHeaders::class,
        ]);
        // The estate context must exist before route-model binding so estate-scoped models
        // resolve only inside the user's current estate.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveEstateContext::class);
        $middleware->alias([
            'estate' => ResolveEstateContext::class,
            'platform' => EnsurePlatformAdmin::class,
        ]);
        // Payment providers call these server-to-server; they are authenticated by signature / re-verification.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token', 'code']);
    })->create();

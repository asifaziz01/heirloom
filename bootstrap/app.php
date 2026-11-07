<?php

use App\Http\Middleware\ResolveFamily;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['family' => ResolveFamily::class]);
        $middleware->redirectGuestsTo('/login');

        // Behind a load balancer or reverse proxy (Forge, Vapor, Fly, Render, Cloudflare...) the app
        // only sees the proxy's address and plain HTTP. TRUSTED_PROXIES="*" trusts every proxy, or
        // give a comma-separated list of proxy IPs/CIDRs. Without it, generated URLs and secure
        // cookies are wrong and rate limiting keys on the proxy's address instead of the client's.
        if ($proxies = trim((string) env('TRUSTED_PROXIES', ''))) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

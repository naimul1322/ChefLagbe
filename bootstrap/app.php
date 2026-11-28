<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
return Application::configure(basePath: dirname(__DIR__))->withRouting(web: __DIR__ . '/../routes/web.php', api: __DIR__ . '/../routes/api.php', commands: __DIR__ . '/../routes/console.php', health: '/up')->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*');
    $middleware->statefulApi();
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    $middleware->alias(['role' => \App\Http\Middleware\RequireRole::class, 'active' => \App\Http\Middleware\EnsureActive::class]);
    $middleware->redirectGuestsTo(fn(Request $request) => $request->is('admin/*') ? '/admin/login': '/login');
})->withExceptions(function (Exceptions $exceptions) {
    $exceptions->shouldRenderJsonWhen(fn(Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());
})->create();

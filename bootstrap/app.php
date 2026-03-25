<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\NovaResourceLocaleRedirectMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(EncryptCookies::class);
        $middleware->append(AddQueuedCookiesToResponse::class);
        $middleware->append(StartSession::class);
        $middleware->append(ShareErrorsFromSession::class);
        $middleware->append(LocaleMiddleware::class);
        $middleware->append(NovaResourceLocaleRedirectMiddleware::class);
        $middleware->redirectGuestsTo(fn() => url(rtrim(config('nova.path'), '/') . '/login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
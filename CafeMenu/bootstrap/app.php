<?php

use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetSecurityHeaders;
use App\Support\FirstRunEnvironment;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// First web request of a never-installed upload: create .env / APP_KEY so
// the installer can be reached without shell access (see the class).
if (PHP_SAPI !== 'cli') {
    FirstRunEnvironment::prepare(dirname(__DIR__));
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', SetSecurityHeaders::class);
        $middleware->appendToGroup('web', RedirectToInstaller::class);
        $middleware->appendToGroup('web', SetLocale::class);

        // Payment gateways POST back to this route cross-origin with no
        // CSRF token of ours to send; the callback's own security instead
        // comes from the gateway-issued token lookup + server-side verify()
        // call in PaymentCallbackController, never from the request alone.
        $middleware->validateCsrfTokens(except: ['payments/callback/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

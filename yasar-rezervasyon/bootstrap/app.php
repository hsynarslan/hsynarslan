<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureKvkkConsent;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class, EnsureActiveUser::class, SecurityHeaders::class]);
        $middleware->alias(['kvkk' => EnsureKvkkConsent::class, 'role' => RequireRole::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('calendar'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Hata ayrıntıları kullanıcıya gösterilmez; APP_DEBUG üretimde kapalı olmalıdır (ISO 27001 A.8.28).
        $exceptions->dontFlash(['password']);
    })->create();

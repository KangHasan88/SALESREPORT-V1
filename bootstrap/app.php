<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // <<< TAMBAHKAN INI
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    
    /*
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })*/
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\AuditActionMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'audit.action' => \App\Http\Middleware\AuditActionMiddleware::class,
        ]);
    })
    
    
    
    
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('logout')) {
                return redirect()->route('logout.success');
            }

            if ($request->is('login')) {
                return redirect()
                    ->route('login')
                    ->with('status', 'Sesi login kedaluwarsa. Silakan coba masuk kembali.');
            }

            return null;
        });

        $exceptions->respond(function ($response, $e, Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            if ($request->is('logout')) {
                return redirect()->route('logout.success');
            }

            if ($request->is('login')) {
                return redirect()
                    ->route('login')
                    ->with('status', 'Sesi login kedaluwarsa. Silakan coba masuk kembali.');
            }

            return $response;
        });
    })->create();

<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->validateCsrfTokens(except: [
            'paystack/webhook',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if (! $request->routeIs('student-joins.verify')) {
                return null;
            }

            if ($request->user()?->studentJoinRequest()->exists()) {
                return to_route('student-joins.status')->with('error', 'This verification link is invalid or has expired. Check your request for next steps.');
            }

            return Inertia::render('student-email-verification', [
                'status' => 'invalid',
                'signedInAsAnotherAccount' => $request->user() !== null,
            ])->toResponse($request)->setStatusCode(403);
        });
    })->create();

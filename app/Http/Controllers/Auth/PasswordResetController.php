<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PasswordResetController extends Controller
{
    public function createRequest(Request $request): InertiaResponse
    {
        return Inertia::render('forgot-password');
    }

    public function storeRequest(
        RequestPasswordResetRequest $request,
        PasswordResetService $passwordResetService,
    ): RedirectResponse {
        $passwordResetService->requestReset($request->string('email')->toString());

        return back()->with(
            'success',
            'If this email belongs to an active account, a password reset link has been sent.',
        );
    }

    public function createReset(Request $request, string $token): InertiaResponse
    {
        return Inertia::render('reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function storeReset(
        ResetPasswordRequest $request,
        PasswordResetService $passwordResetService,
    ): RedirectResponse {
        $wasReset = $passwordResetService->reset(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        if (! $wasReset) {
            throw ValidationException::withMessages([
                'email' => 'This password reset link is invalid or has expired. Request a new link and try again.',
            ]);
        }

        return to_route('home')->with('success', 'Password changed. You can now log in.');
    }
}

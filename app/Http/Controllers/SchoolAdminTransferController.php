<?php

namespace App\Http\Controllers;

use App\Actions\AcceptSchoolAdminTransferAction;
use App\Http\Requests\AcceptSchoolAdminTransferRequest;
use App\Models\SchoolAdminTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SchoolAdminTransferController extends Controller
{
    public function create(string $token): InertiaResponse
    {
        $transfer = $this->transferForToken($token);

        if (! $transfer instanceof SchoolAdminTransfer || ! $transfer->isPending() || $transfer->isExpired()) {
            return Inertia::render('ownership-transfer', [
                'transfer' => null,
                'statusMessage' => 'This ownership invite is no longer active. Ask the current school admin for a new invite.',
            ]);
        }

        $transfer->loadMissing('school');

        return Inertia::render('ownership-transfer', [
            'transfer' => [
                'token' => $token,
                'schoolName' => $transfer->school->name,
                'schoolAbbreviation' => $transfer->school->abbreviation,
                'recipientEmail' => $transfer->recipient_email,
                'recipientName' => $transfer->recipient_name,
                'expiresAt' => $transfer->expires_at->toDayDateTimeString(),
            ],
            'statusMessage' => null,
        ]);
    }

    public function store(
        AcceptSchoolAdminTransferRequest $request,
        AcceptSchoolAdminTransferAction $acceptSchoolAdminTransfer,
        string $token,
    ): RedirectResponse {
        $transfer = $this->transferForToken($token);

        if (! $transfer instanceof SchoolAdminTransfer) {
            throw ValidationException::withMessages([
                'access_code' => 'This ownership invite is no longer active. Ask the current school admin for a new invite.',
            ]);
        }

        $newAdmin = $acceptSchoolAdminTransfer->execute($transfer, $request->validated());

        Auth::login($newAdmin);
        $request->session()->regenerate();

        return to_route('dashboard')->with('success', 'Ownership transferred. You are now the school admin.');
    }

    private function transferForToken(string $token): ?SchoolAdminTransfer
    {
        return SchoolAdminTransfer::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }
}

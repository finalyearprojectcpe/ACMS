<?php

namespace App\Http\Controllers;

use App\Actions\AcceptFacultyHeadTransferAction;
use App\Http\Requests\AcceptFacultyHeadTransferRequest;
use App\Models\FacultyHeadTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FacultyHeadTransferController extends Controller
{
    public function create(string $token): InertiaResponse
    {
        $transfer = $this->transferForToken($token);

        if (! $transfer instanceof FacultyHeadTransfer || ! $transfer->isPending() || $transfer->isExpired()) {
            return Inertia::render('ownership-transfer', [
                'transfer' => null,
                'statusMessage' => 'This faculty head invite is no longer active. Ask the current faculty head for a new invite.',
            ]);
        }

        $transfer->loadMissing('faculty.school');

        return Inertia::render('ownership-transfer', [
            'transfer' => [
                'token' => $token,
                'kind' => 'faculty_head',
                'schoolName' => $transfer->faculty->school->name,
                'schoolAbbreviation' => $transfer->faculty->school->abbreviation,
                'facultyName' => $transfer->faculty->name,
                'facultyAbbreviation' => $transfer->faculty->abbreviation,
                'recipientEmail' => $transfer->recipient_email,
                'recipientName' => $transfer->recipient_name,
                'expiresAt' => $transfer->expires_at->toDayDateTimeString(),
            ],
            'statusMessage' => null,
        ]);
    }

    public function store(
        AcceptFacultyHeadTransferRequest $request,
        AcceptFacultyHeadTransferAction $acceptFacultyHeadTransfer,
        string $token,
    ): RedirectResponse {
        $transfer = $this->transferForToken($token);

        if (! $transfer instanceof FacultyHeadTransfer) {
            throw ValidationException::withMessages([
                'access_code' => 'This faculty head invite is no longer active. Ask the current faculty head for a new invite.',
            ]);
        }

        $newFacultyHead = $acceptFacultyHeadTransfer->execute($transfer, $request->validated());

        Auth::login($newFacultyHead);
        $request->session()->regenerate();

        return to_route('dashboard')->with('success', 'Faculty head access transferred. You are now the faculty head.');
    }

    private function transferForToken(string $token): ?FacultyHeadTransfer
    {
        return FacultyHeadTransfer::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }
}

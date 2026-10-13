<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadStudentSetLogoRequest;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentSetLogoController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function show(Request $request, string $departmentSlug, string $entrySession): StreamedResponse
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        abort_unless($set->logo_path, 404);
        $logoDisk = (string) config('filesystems.due_receipt_disk', 'local');
        if (Storage::disk($logoDisk)->exists($set->logo_path)) {
            return Storage::disk($logoDisk)->response($set->logo_path, headers: ['Cache-Control' => 'private, max-age=300']);
        }

        abort_unless($logoDisk !== 'local' && Storage::disk('local')->exists($set->logo_path), 404);

        return Storage::disk('local')->response($set->logo_path, headers: ['Cache-Control' => 'private, max-age=300']);
    }

    public function store(UploadStudentSetLogoRequest $request, string $departmentSlug, string $entrySession): RedirectResponse
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $logoDisk = (string) config('filesystems.due_receipt_disk', 'local');
        $path = $request->file('logo')->store('set-logos/'.$set->id, $logoDisk);
        if (! is_string($path)) {
            throw new RuntimeException('The logo could not be stored.');
        }

        try {
            $set->forceFill(['logo_path' => $path])->save();
        } catch (\Throwable $exception) {
            Storage::disk($logoDisk)->delete($path);

            throw $exception;
        }

        return to_route('student-sets.edit', $set->routeParameters())->with('success', 'Set logo saved. New receipts will use it.');
    }

    public function destroy(Request $request, string $departmentSlug, string $entrySession): RedirectResponse
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $set->forceFill(['logo_path' => null])->save();

        return to_route('student-sets.edit', $set->routeParameters())->with('success', 'Set logo removed. Issued receipts stay unchanged.');
    }
}

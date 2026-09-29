<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function show(Request $request, User $user): StreamedResponse
    {
        $viewer = $request->user();
        $canView = $viewer?->id === $user->id
            || ($viewer?->isAdviser() === true && $viewer->advisorProfile->students()->where('user_id', $user->id)->exists())
            || ($viewer?->isStudent() === true && $viewer->studentProfile->advisor_profile_id === $user->advisorProfile?->id);
        abort_unless($canView, 404);

        $path = $user->profile_photo_path;
        $disk = Storage::disk(User::profilePhotoDisk());
        abort_unless(is_string($path) && $path !== '' && $disk->exists($path), 404);

        return $disk->response($path, headers: ['Cache-Control' => 'private, max-age=300']);
    }
}

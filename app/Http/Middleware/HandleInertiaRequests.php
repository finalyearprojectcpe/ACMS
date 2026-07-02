<?php

namespace App\Http\Middleware;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $school = $request->attributes->get('school');
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'account_type' => $user->account_type,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->profilePhotoUrl(),
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'school' => $school instanceof School ? [
                'id' => $school->id,
                'name' => $school->name,
                'abbreviation' => $school->abbreviation,
                'slug' => $school->slug,
                'domainKey' => Str::lower($school->abbreviation),
            ] : null,
        ];
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\AccountStatus;
use App\AccountType;
use App\Honorific;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAdvisorRequest;
use App\Models\AdvisorProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RegisteredUserController extends Controller
{
    public function createAdvisor(): InertiaResponse
    {
        return Inertia::render('signup', [
            'honorifics' => Honorific::options(),
        ]);
    }

    public function storeAdvisor(RegisterAdvisorRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'account_type' => AccountType::Adviser,
                'title' => $validated['title'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'email_verified_at' => now(),
                'status' => AccountStatus::Active,
            ]);

            AdvisorProfile::query()->create(['user_id' => $user->id]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}

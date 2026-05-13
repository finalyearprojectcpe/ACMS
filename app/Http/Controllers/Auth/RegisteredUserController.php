<?php

namespace App\Http\Controllers\Auth;

use App\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Level;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RegisteredUserController extends Controller
{
    public function create(): InertiaResponse
    {
        return Inertia::render('signup', [
            'accountTypes' => collect(AccountType::cases())
                ->map(fn (AccountType $accountType): array => [
                    'label' => $accountType->label(),
                    'value' => $accountType->value,
                ])
                ->values(),
            'departments' => Faculty::query()
                ->with(['departments' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (Faculty $faculty): array => [
                    'id' => $faculty->id,
                    'name' => $faculty->name,
                    'abbreviation' => $faculty->abbreviation,
                    'departments' => $faculty->departments
                        ->map(fn (Department $department): array => [
                            'id' => $department->id,
                            'name' => $department->name,
                            'abbreviation' => $department->abbreviation,
                        ])
                        ->values(),
                ])
                ->values(),
        ]);
    }

    public function store(RegisterUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $accountType = AccountType::from($validated['account_type']);

        $user = DB::transaction(function () use ($accountType, $validated): User {
            $user = User::query()->create([
                'account_type' => $accountType,
                'name' => "{$validated['surname']} {$validated['other_names']}",
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            match ($accountType) {
                AccountType::Student => $user->studentProfile()->create([
                    'department_id' => $validated['department_id'],
                    'current_level_id' => Level::query()->where('slug', '100-level')->value('id'),
                    'registration_number' => $validated['registration_number'],
                ]),
                AccountType::Staff => $user->staffProfile()->create([
                    'department_id' => $validated['department_id'],
                ]),
            };

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}

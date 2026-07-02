<?php

namespace App\Actions;

use App\AccountType;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProvisionSchoolAction
{
    /**
     * @param  array{
     *     name: string,
     *     abbreviation: string,
     *     slug?: string|null,
     *     admin_name: string,
     *     admin_email: string,
     *     admin_password: string,
     *     seed_academic_sessions?: bool
     * }  $attributes
     * @return array{school: School, admin: User, academic_sessions_seeded: int}
     */
    public function execute(array $attributes): array
    {
        $validated = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:20'],
            'slug' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
            'seed_academic_sessions' => ['sometimes', 'boolean'],
        ])->validate();

        $schoolName = Str::squish($validated['name']);
        $schoolAbbreviation = Str::upper(Str::squish($validated['abbreviation']));
        $schoolSlug = $this->schoolSlug($schoolName, $validated['slug'] ?? null);
        $adminEmail = Str::lower(Str::squish($validated['admin_email']));
        $seedAcademicSessions = (bool) ($validated['seed_academic_sessions'] ?? false);

        return DB::transaction(function () use (
            $schoolName,
            $schoolAbbreviation,
            $schoolSlug,
            $validated,
            $adminEmail,
            $seedAcademicSessions,
        ): array {
            $school = $this->school($schoolAbbreviation, $schoolSlug);
            $school->fill([
                'name' => $schoolName,
                'abbreviation' => $schoolAbbreviation,
                'slug' => $schoolSlug,
            ])->save();

            $admin = User::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'email' => $adminEmail,
                ],
                [
                    'account_type' => AccountType::SchoolAdmin,
                    'name' => Str::squish($validated['admin_name']),
                    'password' => $validated['admin_password'],
                    'access_code' => null,
                    'access_code_used_at' => null,
                    'activated_at' => now(),
                ],
            );

            $academicSessionsSeeded = $seedAcademicSessions
                ? $this->seedDefaultAcademicSessions($school)
                : 0;

            return [
                'school' => $school->refresh(),
                'admin' => $admin->refresh(),
                'academic_sessions_seeded' => $academicSessionsSeeded,
            ];
        });
    }

    private function schoolSlug(string $schoolName, ?string $explicitSlug): string
    {
        $slugSource = filled($explicitSlug) ? $explicitSlug : $schoolName;
        $slug = Str::slug($slugSource);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'slug' => 'Enter a school name that can be used in a domain, or pass --slug with a short domain-safe value.',
            ]);
        }

        return $slug;
    }

    private function school(string $schoolAbbreviation, string $schoolSlug): School
    {
        $schoolByAbbreviation = School::query()
            ->where('abbreviation', $schoolAbbreviation)
            ->first();
        $schoolBySlug = School::query()
            ->where('slug', $schoolSlug)
            ->first();

        if ($schoolBySlug instanceof School
            && (! ($schoolByAbbreviation instanceof School) || ! $schoolByAbbreviation->is($schoolBySlug))) {
            throw ValidationException::withMessages([
                'slug' => "The slug is already used by {$schoolBySlug->name}. Choose another slug for {$schoolAbbreviation}.",
            ]);
        }

        return $schoolByAbbreviation ?? new School;
    }

    private function seedDefaultAcademicSessions(School $school): int
    {
        foreach ($this->defaultAcademicSessions() as $academicSession) {
            AcademicSession::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'name' => $academicSession['name'],
                ],
                [
                    'starts_on' => $academicSession['starts_on'],
                    'ends_on' => $academicSession['ends_on'],
                ],
            );
        }

        return count($this->defaultAcademicSessions());
    }

    /**
     * @return array<int, array{name: string, starts_on: string, ends_on: string}>
     */
    private function defaultAcademicSessions(): array
    {
        return [
            ['name' => '2019/2020', 'starts_on' => '2019-09-01', 'ends_on' => '2020-08-31'],
            ['name' => '2020/2021', 'starts_on' => '2020-09-01', 'ends_on' => '2021-08-31'],
            ['name' => '2021/2022', 'starts_on' => '2021-09-01', 'ends_on' => '2022-08-31'],
            ['name' => '2022/2023', 'starts_on' => '2022-09-01', 'ends_on' => '2023-08-31'],
            ['name' => '2023/2024', 'starts_on' => '2023-09-01', 'ends_on' => '2024-08-31'],
            ['name' => '2024/2025', 'starts_on' => '2024-09-01', 'ends_on' => '2025-08-31'],
            ['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31'],
            ['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31'],
            ['name' => '2027/2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-08-31'],
        ];
    }
}

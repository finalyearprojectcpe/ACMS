<?php

use App\AccountType;
use App\Actions\ProvisionSchoolAction;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\User;
use Database\Seeders\SchoolAdminSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

test('school provisioning defaults the slug from the school name', function () {
    $result = app(ProvisionSchoolAction::class)->execute([
        'name' => 'Federal University of Technology Owerri',
        'abbreviation' => 'FUTO',
        'admin_name' => 'Okoro Chinyere',
        'admin_email' => 'schooladmin@futo.edu',
        'admin_password' => 'password',
        'seed_academic_sessions' => true,
    ]);

    $school = $result['school'];
    $admin = $result['admin'];

    expect($school->slug)->toBe('federal-university-of-technology-owerri')
        ->and($school->abbreviation)->toBe('FUTO')
        ->and($admin->school_id)->toBe($school->id)
        ->and($admin->account_type)->toBe(AccountType::SchoolAdmin)
        ->and(Hash::check('password', $admin->password))->toBeTrue()
        ->and($result['academic_sessions_seeded'])->toBe(9)
        ->and($school->academicSessions()->count())->toBe(9);
});

test('school provisioning command is idempotent', function () {
    $options = [
        '--name' => 'Federal University of Technology Owerri',
        '--abbreviation' => 'FUTO',
        '--admin-name' => 'Okoro Chinyere',
        '--admin-email' => 'schooladmin@futo.edu',
        '--admin-password' => 'password',
        '--seed-sessions' => true,
    ];

    $this->artisan('school:provision', $options)->assertSuccessful();
    $this->artisan('school:provision', $options)->assertSuccessful();

    expect(School::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'schooladmin@futo.edu')->count())->toBe(1)
        ->and(AcademicSession::query()->count())->toBe(9);
});

test('school provisioning rejects slug collisions', function () {
    $provisionSchool = app(ProvisionSchoolAction::class);

    $provisionSchool->execute([
        'name' => 'Massachusetts Institute of Technology',
        'abbreviation' => 'MIT',
        'admin_name' => 'Admin User',
        'admin_email' => 'admin@mit.edu',
        'admin_password' => 'password',
    ]);

    expect(fn () => $provisionSchool->execute([
        'name' => 'Federal University of Technology Owerri',
        'abbreviation' => 'FUTO',
        'slug' => 'massachusetts-institute-of-technology',
        'admin_name' => 'Okoro Chinyere',
        'admin_email' => 'schooladmin@futo.edu',
        'admin_password' => 'password',
    ]))->toThrow(ValidationException::class);
});

test('school admin seeder uses the provisioning workflow', function () {
    $this->seed(SchoolAdminSeeder::class);

    $school = School::query()->where('abbreviation', 'FUTO')->firstOrFail();
    $schoolAdmin = User::query()->where('email', 'schooladmin@futo.edu')->firstOrFail();

    expect($school->slug)->toBe('federal-university-of-technology-owerri')
        ->and($schoolAdmin->account_type)->toBe(AccountType::SchoolAdmin)
        ->and($school->academicSessions()->count())->toBe(9);
});

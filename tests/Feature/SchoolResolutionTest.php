<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\School;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('local requests resolve the configured default school', function () {
    config(['app.default_school_abbreviation' => 'FUTO']);

    $school = School::factory()->create([
        'name' => 'Federal University of Technology Owerri',
        'abbreviation' => 'FUTO',
        'slug' => 'federal-university-of-technology-owerri',
    ]);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'name' => 'Electrical Systems and Engineering Technology',
        'abbreviation' => 'SESET',
        'slug' => 'electrical-systems-and-engineering-technology',
    ]);

    Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
        'slug' => 'computer-engineering',
    ]);

    $response = $this->get(route('signup.student'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('signup')
        ->where('accountType', 'student')
        ->where('school.abbreviation', 'FUTO')
        ->where('school.slug', 'federal-university-of-technology-owerri')
        ->where('school.domainKey', 'futo')
        ->etc());
});

test('school subdomain overrides the local default school', function () {
    config([
        'app.default_school_abbreviation' => 'FUTO',
        'app.school_domain' => 'site.edu',
    ]);

    School::factory()->create([
        'name' => 'Federal University of Technology Owerri',
        'abbreviation' => 'FUTO',
        'slug' => 'federal-university-of-technology-owerri',
    ]);
    $school = School::factory()->create([
        'name' => 'Massachusetts Institute of Technology',
        'abbreviation' => 'MIT',
        'slug' => 'massachusetts-institute-of-technology',
    ]);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'name' => 'School of Engineering',
        'abbreviation' => 'SOE',
        'slug' => 'school-of-engineering',
    ]);

    Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Electrical Engineering and Computer Science',
        'abbreviation' => 'EECS',
        'slug' => 'electrical-engineering-and-computer-science',
    ]);

    $response = $this->get('http://mit.site.edu/signup/staff');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('signup')
        ->where('accountType', 'staff')
        ->where('school.abbreviation', 'MIT')
        ->where('school.slug', 'massachusetts-institute-of-technology')
        ->where('school.domainKey', 'mit')
        ->etc());
});

test('unknown school subdomains return not found', function () {
    config([
        'app.default_school_abbreviation' => 'FUTO',
        'app.school_domain' => 'site.edu',
    ]);

    School::factory()->create([
        'name' => 'Federal University of Technology Owerri',
        'abbreviation' => 'FUTO',
        'slug' => 'federal-university-of-technology-owerri',
    ]);

    $this->get('http://mit.site.edu/signup/student')
        ->assertNotFound();
});

test('student activation rejects provisioned accounts outside the resolved school', function () {
    config(['app.default_school_abbreviation' => 'FUTO']);

    School::factory()->create([
        'name' => 'Federal University of Technology Owerri',
        'abbreviation' => 'FUTO',
        'slug' => 'federal-university-of-technology-owerri',
    ]);
    $mitSchool = School::factory()->create([
        'name' => 'Massachusetts Institute of Technology',
        'abbreviation' => 'MIT',
        'slug' => 'massachusetts-institute-of-technology',
    ]);
    $mitFaculty = Faculty::factory()->create([
        'school_id' => $mitSchool->id,
    ]);
    $mitDepartment = Department::factory()->create([
        'faculty_id' => $mitFaculty->id,
    ]);
    $mitUser = User::factory()->student()->pendingActivation('MIT-CODE')->create([
        'school_id' => $mitSchool->id,
        'email' => null,
    ]);
    StudentProfile::factory()->create([
        'school_id' => $mitSchool->id,
        'user_id' => $mitUser->id,
        'department_id' => $mitDepartment->id,
        'registration_number' => '2021001234',
    ]);

    $response = $this->from(route('signup.student'))->post(route('signup.student.verify'), [
        'registration_number' => '2021001234',
        'access_code' => 'MIT-CODE',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('registration_number');
});

test('login rejects users from another resolved school', function () {
    config(['app.default_school_abbreviation' => 'FUTO']);

    School::factory()->create([
        'name' => 'Federal University of Technology Owerri',
        'abbreviation' => 'FUTO',
        'slug' => 'federal-university-of-technology-owerri',
    ]);
    $mitSchool = School::factory()->create([
        'name' => 'Massachusetts Institute of Technology',
        'abbreviation' => 'MIT',
        'slug' => 'massachusetts-institute-of-technology',
    ]);
    $mitFaculty = Faculty::factory()->create([
        'school_id' => $mitSchool->id,
    ]);
    $mitDepartment = Department::factory()->create([
        'faculty_id' => $mitFaculty->id,
    ]);
    $user = User::factory()->staff()->create([
        'school_id' => $mitSchool->id,
        'email' => 'advisor@mit.edu',
        'password' => Hash::make('password'),
        'activated_at' => now(),
    ]);

    StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $mitDepartment->id,
    ]);

    $response = $this->from(route('home'))->post(route('login.store'), [
        'email' => 'advisor@mit.edu',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

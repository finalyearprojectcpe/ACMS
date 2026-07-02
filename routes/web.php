<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacultyHeadTransferController;
use App\Http\Controllers\SchoolAdminTransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('home');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::redirect('/signup', '/signup/student')->name('signup');
    Route::get('/signup/student', [RegisteredUserController::class, 'createStudent'])->name('signup.student');
    Route::post('/signup/student/verify', [RegisteredUserController::class, 'verifyStudent'])->name('signup.student.verify');
    Route::post('/signup/student', [RegisteredUserController::class, 'storeStudent'])->name('signup.student.store');
    Route::get('/signup/staff', [RegisteredUserController::class, 'createStaff'])->name('signup.staff');
    Route::post('/signup/staff/verify', [RegisteredUserController::class, 'verifyStaff'])->name('signup.staff.verify');
    Route::post('/signup/staff', [RegisteredUserController::class, 'storeStaff'])->name('signup.staff.store');
    Route::get('/ownership-transfer/{token}', [SchoolAdminTransferController::class, 'create'])->name('ownership-transfer.create');
    Route::post('/ownership-transfer/{token}', [SchoolAdminTransferController::class, 'store'])->name('ownership-transfer.store');
    Route::get('/faculty-head-transfer/{token}', [FacultyHeadTransferController::class, 'create'])->name('faculty-head-transfer.create');
    Route::post('/faculty-head-transfer/{token}', [FacultyHeadTransferController::class, 'store'])->name('faculty-head-transfer.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'home'])->name('dashboard');
    Route::get('/dashboard/academic-sessions/create', [DashboardController::class, 'createAcademicSession'])
        ->name('dashboard.academic-sessions.create');
    Route::post('/dashboard/academic-sessions', [DashboardController::class, 'storeAcademicSession'])
        ->name('dashboard.academic-sessions.store');
    Route::get('/dashboard/academic-sessions/{academicSession}/edit', [DashboardController::class, 'editAcademicSession'])
        ->whereNumber('academicSession')
        ->name('dashboard.academic-sessions.edit');
    Route::patch('/dashboard/academic-sessions/{academicSession}', [DashboardController::class, 'updateAcademicSession'])
        ->whereNumber('academicSession')
        ->name('dashboard.academic-sessions.update');
    Route::get('/dashboard/structure/faculties/create', [DashboardController::class, 'createSchoolFaculty'])
        ->name('dashboard.structure.faculties.create');
    Route::post('/dashboard/structure/faculties', [DashboardController::class, 'storeSchoolFaculty'])
        ->name('dashboard.structure.faculties.store');
    Route::get('/dashboard/structure/faculties/{faculty}/edit', [DashboardController::class, 'editSchoolFaculty'])
        ->whereNumber('faculty')
        ->name('dashboard.structure.faculties.edit');
    Route::patch('/dashboard/structure/faculties/{faculty}', [DashboardController::class, 'updateSchoolFaculty'])
        ->whereNumber('faculty')
        ->name('dashboard.structure.faculties.update');
    Route::get('/dashboard/structure/departments/create', [DashboardController::class, 'createSchoolDepartment'])
        ->name('dashboard.structure.departments.create');
    Route::post('/dashboard/structure/departments', [DashboardController::class, 'storeSchoolDepartment'])
        ->name('dashboard.structure.departments.store');
    Route::get('/dashboard/structure/departments/{department}/edit', [DashboardController::class, 'editSchoolDepartment'])
        ->whereNumber('department')
        ->name('dashboard.structure.departments.edit');
    Route::patch('/dashboard/structure/departments/{department}', [DashboardController::class, 'updateSchoolDepartment'])
        ->whereNumber('department')
        ->name('dashboard.structure.departments.update');
    Route::get('/dashboard/faculty-heads/create', [DashboardController::class, 'createSchoolFacultyHead'])
        ->name('dashboard.faculty-heads.create');
    Route::post('/dashboard/faculty-heads', [DashboardController::class, 'storeSchoolFacultyHead'])
        ->name('dashboard.faculty-heads.store');
    Route::patch('/dashboard/settings/password', [DashboardController::class, 'updateSchoolAdminPassword'])
        ->name('dashboard.settings.password.update');
    Route::post('/dashboard/settings/ownership-transfer', [DashboardController::class, 'storeSchoolAdminTransfer'])
        ->name('dashboard.settings.ownership-transfer.store');
    Route::delete('/dashboard/settings/ownership-transfer/{schoolAdminTransfer}', [DashboardController::class, 'destroySchoolAdminTransfer'])
        ->whereNumber('schoolAdminTransfer')
        ->name('dashboard.settings.ownership-transfer.destroy');
    Route::patch('/dashboard/settings/faculty-head-password', [DashboardController::class, 'updateFacultyHeadPassword'])
        ->name('dashboard.settings.faculty-head-password.update');
    Route::patch('/dashboard/settings/hod-password', [DashboardController::class, 'updateHodPassword'])
        ->name('dashboard.settings.hod-password.update');
    Route::patch('/dashboard/settings/profile-photo', [DashboardController::class, 'updateProfilePhoto'])
        ->name('dashboard.settings.profile-photo.update');
    Route::post('/dashboard/settings/faculty-head-transfer', [DashboardController::class, 'storeFacultyHeadTransfer'])
        ->name('dashboard.settings.faculty-head-transfer.store');
    Route::delete('/dashboard/settings/faculty-head-transfer/{facultyHeadTransfer}', [DashboardController::class, 'destroyFacultyHeadTransfer'])
        ->whereNumber('facultyHeadTransfer')
        ->name('dashboard.settings.faculty-head-transfer.destroy');
    Route::get('/dashboard/hod-management/create', [DashboardController::class, 'createFacultyHod'])
        ->name('dashboard.hod-management.create');
    Route::post('/dashboard/hod-management', [DashboardController::class, 'storeFacultyHod'])
        ->name('dashboard.hod-management.store');
    Route::get('/dashboard/hod-management/import', [DashboardController::class, 'importFacultyHods'])
        ->name('dashboard.hod-management.import');
    Route::post('/dashboard/hod-management/import', [DashboardController::class, 'storeImportedFacultyHods'])
        ->name('dashboard.hod-management.import.store');
    Route::get('/dashboard/departments/{department}/hod', [DashboardController::class, 'createDepartmentHod'])
        ->whereNumber('department')
        ->name('dashboard.departments.hod');
    Route::get('/dashboard/staff/create', [DashboardController::class, 'createFacultyStaff'])
        ->name('dashboard.staff.create');
    Route::post('/dashboard/staff', [DashboardController::class, 'storeFacultyStaff'])
        ->name('dashboard.staff.store');
    Route::get('/dashboard/staff/import', [DashboardController::class, 'importFacultyStaff'])
        ->name('dashboard.staff.import');
    Route::post('/dashboard/staff/import', [DashboardController::class, 'storeImportedFacultyStaff'])
        ->name('dashboard.staff.import.store');
    Route::get('/dashboard/department-staff/import', [DashboardController::class, 'importHodStaff'])
        ->name('dashboard.department-staff.import');
    Route::post('/dashboard/department-staff/import', [DashboardController::class, 'storeImportedHodStaff'])
        ->name('dashboard.department-staff.import.store');
    Route::redirect('/dashboard/faculty-courses', '/dashboard/sessions-courses');
    Route::post('/dashboard/sessions-courses/{academicSession}/copy-courses', [DashboardController::class, 'copyFacultyCourseOfferings'])
        ->whereNumber('academicSession')
        ->name('dashboard.sessions-courses.copy-courses');
    Route::post('/dashboard/sessions-courses/{academicSession}/{level}/{semester}/copy-courses', [DashboardController::class, 'copyFacultyCourseSemesterOfferings'])
        ->whereNumber('academicSession')
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->name('dashboard.sessions-courses.copy-semester-courses');
    Route::get('/dashboard/sessions-courses/{academicSession}/{level}/{semester}/create', [DashboardController::class, 'createFacultyCourseOffering'])
        ->whereNumber('academicSession')
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->name('dashboard.sessions-courses.create');
    Route::post('/dashboard/sessions-courses/{academicSession}/{level}/{semester}', [DashboardController::class, 'storeFacultyCourseOffering'])
        ->whereNumber('academicSession')
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->name('dashboard.sessions-courses.store');
    Route::get('/dashboard/sessions-courses/{academicSession}/{level}/{semester}/{offering}/edit', [DashboardController::class, 'editFacultyCourseOffering'])
        ->whereNumber('academicSession')
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->name('dashboard.sessions-courses.edit');
    Route::patch('/dashboard/sessions-courses/{academicSession}/{level}/{semester}/{offering}', [DashboardController::class, 'updateFacultyCourseOffering'])
        ->whereNumber('academicSession')
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->name('dashboard.sessions-courses.update');
    Route::delete('/dashboard/sessions-courses/{academicSession}/{level}/{semester}/{offering}', [DashboardController::class, 'deleteFacultyCourseOffering'])
        ->whereNumber('academicSession')
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->name('dashboard.sessions-courses.destroy');
    Route::get('/dashboard/courses/{level}/{semester}/create', [DashboardController::class, 'createCourseOffering'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->name('dashboard.courses.create');
    Route::post('/dashboard/courses/{level}/{semester}', [DashboardController::class, 'storeCourseOffering'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->name('dashboard.courses.store');
    Route::get('/dashboard/courses/{level}/{semester}/{offering}/edit', [DashboardController::class, 'editCourseOffering'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->name('dashboard.courses.edit');
    Route::patch('/dashboard/courses/{level}/{semester}/{offering}', [DashboardController::class, 'updateCourseOffering'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->name('dashboard.courses.update');
    Route::get('/dashboard/students/{studentProfile}', [DashboardController::class, 'showStudentProfile'])
        ->whereNumber('studentProfile')
        ->name('dashboard.students.show');
    Route::get('/dashboard/{section}/{level}', [DashboardController::class, 'level'])
        ->whereIn('section', ['courses', 'grades', 'dues', 'results'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->name('dashboard.level');
    Route::get('/dashboard/{section}', [DashboardController::class, 'section'])
        ->whereIn('section', [
            'departments',
            'sessions-courses',
            'academic-sessions',
            'structure',
            'faculty-heads',
            'staff',
            'students',
            'courses',
            'grades',
            'dues',
            'reports',
            'approvals',
            'settings',
            'results',
            'student-results',
            'course-schedules',
            'profile',
        ])
        ->name('dashboard.section');
    Route::redirect('/profile', '/dashboard/profile');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

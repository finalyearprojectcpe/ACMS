<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('home');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/signup', [RegisteredUserController::class, 'create'])->name('signup');
    Route::post('/signup', [RegisteredUserController::class, 'store'])->name('signup.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'home'])->name('dashboard');
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
    Route::get('/dashboard/courses/{level}/{semester}/{offering}/timetable', [DashboardController::class, 'courseTimetable'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->name('dashboard.courses.timetable');
    Route::post('/dashboard/courses/{level}/{semester}/{offering}/timetable', [DashboardController::class, 'storeCourseTimetableSlot'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->name('dashboard.courses.timetable.store');
    Route::patch('/dashboard/courses/{level}/{semester}/{offering}/timetable/{slot}', [DashboardController::class, 'updateCourseTimetableSlot'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->whereNumber('slot')
        ->name('dashboard.courses.timetable.update');
    Route::delete('/dashboard/courses/{level}/{semester}/{offering}/timetable/{slot}', [DashboardController::class, 'deleteCourseTimetableSlot'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->whereNumber('slot')
        ->name('dashboard.courses.timetable.destroy');
    Route::get('/dashboard/{section}/{level}', [DashboardController::class, 'level'])
        ->whereIn('section', ['courses', 'grades', 'dues', 'results'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->name('dashboard.level');
    Route::get('/dashboard/{section}', [DashboardController::class, 'section'])
        ->whereIn('section', [
            'departments',
            'staff',
            'students',
            'courses',
            'grades',
            'dues',
            'reports',
            'approvals',
            'settings',
            'results',
            'course-schedules',
            'profile',
            'staff-assignments',
            'student-sets',
            'academic-sessions',
        ])
        ->name('dashboard.section');
    Route::redirect('/profile', '/dashboard/profile');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

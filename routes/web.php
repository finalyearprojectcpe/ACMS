<?php

use App\Http\Controllers\AcademicSetupController;
use App\Http\Controllers\AdviserAccountController;
use App\Http\Controllers\AdviserStudentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CourseOfferingController;
use App\Http\Controllers\CourseRegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DueController;
use App\Http\Controllers\ReceiptVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('home');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'createRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'storeRequest'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'createReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'storeReset'])->middleware('throttle:5,1')->name('password.update');

    Route::redirect('/signup', '/signup/student')->name('signup');
    Route::get('/signup/student', [RegisteredUserController::class, 'createStudent'])->name('signup.student');
    Route::post('/signup/student/verify', [RegisteredUserController::class, 'verifyStudent'])->middleware('throttle:10,1')->name('signup.student.verify');
    Route::post('/signup/student', [RegisteredUserController::class, 'storeStudent'])->middleware('throttle:5,1')->name('signup.student.store');
    Route::get('/signup/adviser', [RegisteredUserController::class, 'createAdvisor'])->name('signup.advisor');
    Route::post('/signup/adviser', [RegisteredUserController::class, 'storeAdvisor'])->middleware('throttle:5,1')->name('signup.advisor.store');
});

Route::post('/paystack/webhook', [DueController::class, 'webhook'])->name('paystack.webhook');
Route::get('/receipts/{paystackTransactionId}/verify', [ReceiptVerificationController::class, 'show'])
    ->whereNumber('paystackTransactionId')
    ->name('receipts.verify');
Route::get('/receipts/{paystackTransactionId}/download', [ReceiptVerificationController::class, 'download'])
    ->whereNumber('paystackTransactionId')
    ->middleware('signed:relative')
    ->name('receipts.download');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'home'])->name('dashboard');

    Route::get('/dashboard/students/create', [DashboardController::class, 'createAdvisedStudent'])->name('dashboard.students.create');
    Route::post('/dashboard/students', [AdviserStudentController::class, 'store'])->name('dashboard.students.store');
    Route::get('/dashboard/students/import', [DashboardController::class, 'importAdvisedStudents'])->name('dashboard.students.import');
    Route::post('/dashboard/students/import', [AdviserStudentController::class, 'import'])->name('dashboard.students.import.store');
    Route::get('/dashboard/students/classlist/export', [AdviserStudentController::class, 'exportClasslist'])->name('dashboard.students.classlist');
    Route::get('/dashboard/students/{registrationNumber}', [DashboardController::class, 'showStudentProfile'])
        ->where('registrationNumber', '[^/]+')
        ->name('dashboard.students.show');

    Route::get('/dashboard/courses/{level}/{semester}/create', [DashboardController::class, 'createCourseOffering'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->name('dashboard.courses.create');
    Route::post('/dashboard/courses/{level}/{semester}', [CourseOfferingController::class, 'store'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->name('dashboard.courses.store');
    Route::get('/dashboard/courses/{level}/{semester}/{offering}/edit', [DashboardController::class, 'editCourseOffering'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->name('dashboard.courses.edit');
    Route::patch('/dashboard/courses/{level}/{semester}/{offering}', [CourseOfferingController::class, 'update'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->whereIn('semester', ['harmattan', 'rain'])
        ->whereNumber('offering')
        ->name('dashboard.courses.update');

    Route::post('/dashboard/course-registration', [CourseRegistrationController::class, 'store'])->name('dashboard.course-registration.store');
    Route::get('/dashboard/course-registration/{courseRegistration}', [DashboardController::class, 'showCourseRegistrationReview'])->whereNumber('courseRegistration')->name('dashboard.course-registration.show');
    Route::patch('/dashboard/course-registration/{courseRegistration}/approve', [CourseRegistrationController::class, 'approve'])->whereNumber('courseRegistration')->name('dashboard.course-registration.approve');
    Route::patch('/dashboard/course-registration/{courseRegistration}/return', [CourseRegistrationController::class, 'returnForCorrection'])->whereNumber('courseRegistration')->name('dashboard.course-registration.return');

    Route::get('/dashboard/dues/create', [DashboardController::class, 'createAdviserDue'])->name('dashboard.dues.create');
    Route::post('/dashboard/dues', [DueController::class, 'store'])->name('dashboard.dues.store');
    Route::get('/dashboard/dues/{due}/edit', [DashboardController::class, 'editAdviserDue'])->whereNumber('due')->name('dashboard.dues.edit');
    Route::patch('/dashboard/dues/{due}', [DueController::class, 'update'])->whereNumber('due')->name('dashboard.dues.update');
    Route::patch('/dashboard/dues/{due}/archive', [DueController::class, 'archive'])->whereNumber('due')->name('dashboard.dues.archive');
    Route::patch('/dashboard/dues/{due}/restore', [DueController::class, 'restore'])->whereNumber('due')->name('dashboard.dues.restore');
    Route::get('/dashboard/dues/{due}/export', [DueController::class, 'export'])->whereNumber('due')->name('dashboard.dues.export');
    Route::get('/dashboard/dues/{due}/assignments/{studentDueAssignment}/waive', [DashboardController::class, 'createStudentDueWaiver'])->whereNumber('due')->whereNumber('studentDueAssignment')->name('dashboard.dues.assignments.waive.create');
    Route::patch('/dashboard/dues/{due}/assignments/{studentDueAssignment}/waive', [DueController::class, 'waive'])->whereNumber('due')->whereNumber('studentDueAssignment')->name('dashboard.dues.assignments.waive');
    Route::patch('/dashboard/dues/{due}/assignments/{studentDueAssignment}/restore', [DueController::class, 'restoreAssignment'])->whereNumber('due')->whereNumber('studentDueAssignment')->name('dashboard.dues.assignments.restore');
    Route::get('/dashboard/dues/{due}', [DashboardController::class, 'showAdviserDue'])->whereNumber('due')->name('dashboard.dues.show');

    Route::post('/dashboard/dues/assignments/{studentDueAssignment}/pay', [DueController::class, 'startPayment'])->whereNumber('studentDueAssignment')->name('dashboard.dues.payments.store');
    Route::get('/dashboard/dues/payments/{duePayment}/callback', [DueController::class, 'callback'])->whereNumber('duePayment')->name('dashboard.dues.payments.callback');
    Route::get('/dashboard/dues/payments/{paystackTransactionId}/receipt', [DueController::class, 'downloadReceipt'])->whereNumber('paystackTransactionId')->name('dashboard.dues.payments.receipt');
    Route::post('/dashboard/dues/payments/{paystackTransactionId}/receipt/retry', [DueController::class, 'retryReceipt'])->whereNumber('paystackTransactionId')->name('dashboard.dues.payments.receipt.retry');
    Route::post('/dashboard/dues/payments/{paystackTransactionId}/receipt/email', [DueController::class, 'resendReceiptEmail'])->whereNumber('paystackTransactionId')->name('dashboard.dues.payments.receipt.email');

    Route::get('/dashboard/payment-settings/create', [DashboardController::class, 'createAdviserPaymentAccount'])->name('dashboard.payment-settings.create');
    Route::post('/dashboard/payment-settings', [AdviserAccountController::class, 'storePaymentAccount'])->name('dashboard.payment-settings.store');
    Route::patch('/dashboard/profile-settings/password', [AdviserAccountController::class, 'updatePassword'])->name('dashboard.profile-settings.password.update');
    Route::patch('/dashboard/profile-settings/photo', [AdviserAccountController::class, 'updateProfilePhoto'])->name('dashboard.profile-settings.photo.update');
    Route::patch('/dashboard/profile-settings', [AdviserAccountController::class, 'updateStudentProfile'])->name('dashboard.profile-settings.update');
    Route::post('/dashboard/profile-settings/academic-sessions', [AcademicSetupController::class, 'storeSession'])->name('dashboard.profile-settings.academic-sessions.store');
    Route::patch('/dashboard/profile-settings/academic-sessions/{academicSession}', [AcademicSetupController::class, 'updateSession'])->whereNumber('academicSession')->name('dashboard.profile-settings.academic-sessions.update');
    Route::post('/dashboard/profile-settings/student-sets', [AcademicSetupController::class, 'storeStudentSet'])->name('dashboard.profile-settings.student-sets.store');
    Route::patch('/dashboard/profile-settings/student-sets/{studentSet}', [AcademicSetupController::class, 'updateStudentSet'])->whereNumber('studentSet')->name('dashboard.profile-settings.student-sets.update');

    Route::get('/dashboard/{section}', [DashboardController::class, 'section'])
        ->whereIn('section', ['students', 'courses', 'course-registration', 'dues', 'payment-settings', 'profile-settings'])
        ->name('dashboard.section');
    Route::redirect('/dashboard/profile', '/dashboard/profile-settings');
    Route::redirect('/dashboard/course-schedules', '/dashboard/course-registration');
    Route::redirect('/profile', '/dashboard/profile-settings');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

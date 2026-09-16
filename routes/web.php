<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
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

Route::post('/paystack/webhook', [DashboardController::class, 'handlePaystackWebhook'])->name('paystack.webhook');
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
    Route::post('/dashboard/students', [DashboardController::class, 'storeAdvisedStudent'])->name('dashboard.students.store');
    Route::get('/dashboard/students/import', [DashboardController::class, 'importAdvisedStudents'])->name('dashboard.students.import');
    Route::post('/dashboard/students/import', [DashboardController::class, 'storeImportedAdvisedStudents'])->name('dashboard.students.import.store');
    Route::get('/dashboard/students/classlist/export', [DashboardController::class, 'exportStudentClasslist'])->name('dashboard.students.classlist');
    Route::get('/dashboard/students/{registrationNumber}/statement-of-results', [DashboardController::class, 'downloadStudentStatement'])
        ->where('registrationNumber', '[^/]+')
        ->name('dashboard.students.statement');
    Route::get('/dashboard/students/{registrationNumber}', [DashboardController::class, 'showStudentProfile'])
        ->where('registrationNumber', '[^/]+')
        ->name('dashboard.students.show');

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

    Route::get('/dashboard/results/import', [DashboardController::class, 'createResultSheetImport'])->name('dashboard.results.import');
    Route::post('/dashboard/results/import', [DashboardController::class, 'storeResultSheetImport'])->name('dashboard.results.import.store');
    Route::get('/dashboard/results/{resultSheet}/export', [DashboardController::class, 'exportResultSheet'])->whereNumber('resultSheet')->name('dashboard.results.export');
    Route::get('/dashboard/results/{resultSheet}/source-file', [DashboardController::class, 'downloadResultSheetSourceFile'])->whereNumber('resultSheet')->name('dashboard.results.source-file');
    Route::get('/dashboard/results/{resultSheet}', [DashboardController::class, 'showResultSheet'])->whereNumber('resultSheet')->name('dashboard.results.show');

    Route::post('/dashboard/course-registration', [DashboardController::class, 'storeCourseRegistration'])->name('dashboard.course-registration.store');
    Route::get('/dashboard/course-registration/{courseRegistration}', [DashboardController::class, 'showCourseRegistrationReview'])->whereNumber('courseRegistration')->name('dashboard.course-registration.show');
    Route::patch('/dashboard/course-registration/{courseRegistration}/approve', [DashboardController::class, 'approveCourseRegistration'])->whereNumber('courseRegistration')->name('dashboard.course-registration.approve');
    Route::patch('/dashboard/course-registration/{courseRegistration}/return', [DashboardController::class, 'returnCourseRegistration'])->whereNumber('courseRegistration')->name('dashboard.course-registration.return');

    Route::get('/dashboard/dues/create', [DashboardController::class, 'createStaffDue'])->name('dashboard.dues.create');
    Route::post('/dashboard/dues', [DashboardController::class, 'storeStaffDue'])->name('dashboard.dues.store');
    Route::get('/dashboard/dues/{due}/edit', [DashboardController::class, 'editStaffDue'])->whereNumber('due')->name('dashboard.dues.edit');
    Route::patch('/dashboard/dues/{due}', [DashboardController::class, 'updateStaffDue'])->whereNumber('due')->name('dashboard.dues.update');
    Route::patch('/dashboard/dues/{due}/archive', [DashboardController::class, 'archiveStaffDue'])->whereNumber('due')->name('dashboard.dues.archive');
    Route::patch('/dashboard/dues/{due}/restore', [DashboardController::class, 'restoreStaffDue'])->whereNumber('due')->name('dashboard.dues.restore');
    Route::get('/dashboard/dues/{due}/export', [DashboardController::class, 'exportStaffDue'])->whereNumber('due')->name('dashboard.dues.export');
    Route::get('/dashboard/dues/{due}/assignments/{studentDueAssignment}/waive', [DashboardController::class, 'createStudentDueWaiver'])->whereNumber('due')->whereNumber('studentDueAssignment')->name('dashboard.dues.assignments.waive.create');
    Route::patch('/dashboard/dues/{due}/assignments/{studentDueAssignment}/waive', [DashboardController::class, 'waiveStudentDue'])->whereNumber('due')->whereNumber('studentDueAssignment')->name('dashboard.dues.assignments.waive');
    Route::patch('/dashboard/dues/{due}/assignments/{studentDueAssignment}/restore', [DashboardController::class, 'restoreStudentDue'])->whereNumber('due')->whereNumber('studentDueAssignment')->name('dashboard.dues.assignments.restore');
    Route::get('/dashboard/dues/{due}', [DashboardController::class, 'showStaffDue'])->whereNumber('due')->name('dashboard.dues.show');

    Route::post('/dashboard/dues/assignments/{studentDueAssignment}/pay', [DashboardController::class, 'startDuePayment'])->whereNumber('studentDueAssignment')->name('dashboard.dues.payments.store');
    Route::get('/dashboard/dues/payments/{duePayment}/callback', [DashboardController::class, 'handleDuePaymentCallback'])->whereNumber('duePayment')->name('dashboard.dues.payments.callback');
    Route::get('/dashboard/dues/payments/{paystackTransactionId}/receipt', [DashboardController::class, 'downloadDuePaymentReceipt'])->whereNumber('paystackTransactionId')->name('dashboard.dues.payments.receipt');
    Route::post('/dashboard/dues/payments/{paystackTransactionId}/receipt/retry', [DashboardController::class, 'retryDuePaymentReceipt'])->whereNumber('paystackTransactionId')->name('dashboard.dues.payments.receipt.retry');
    Route::post('/dashboard/dues/payments/{paystackTransactionId}/receipt/email', [DashboardController::class, 'resendDuePaymentReceiptEmail'])->whereNumber('paystackTransactionId')->name('dashboard.dues.payments.receipt.email');

    Route::get('/dashboard/payment-settings/create', [DashboardController::class, 'createStaffPaymentAccount'])->name('dashboard.payment-settings.create');
    Route::post('/dashboard/payment-settings', [DashboardController::class, 'storeStaffPaymentAccount'])->name('dashboard.payment-settings.store');
    Route::patch('/dashboard/profile-settings/password', [DashboardController::class, 'updateStaffPassword'])->name('dashboard.profile-settings.password.update');
    Route::patch('/dashboard/profile-settings/photo', [DashboardController::class, 'updateProfilePhoto'])->name('dashboard.profile-settings.photo.update');
    Route::patch('/dashboard/profile-settings', [DashboardController::class, 'updateStudentProfile'])->name('dashboard.profile-settings.update');

    Route::get('/dashboard/results/{level}', [DashboardController::class, 'level'])
        ->whereIn('level', ['100-level', '200-level', '300-level', '400-level', '500-level', '600-level', '700-level'])
        ->name('dashboard.level');
    Route::get('/dashboard/{section}', [DashboardController::class, 'section'])
        ->whereIn('section', ['students', 'courses', 'result-management', 'course-registration', 'dues', 'payment-settings', 'results', 'profile-settings'])
        ->name('dashboard.section');
    Route::redirect('/dashboard/profile', '/dashboard/profile-settings');
    Route::redirect('/dashboard/course-schedules', '/dashboard/course-registration');
    Route::redirect('/profile', '/dashboard/profile-settings');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

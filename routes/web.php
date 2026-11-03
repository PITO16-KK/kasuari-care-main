<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceRecordController;
use App\Http\Controllers\PackageCatalogController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientIntakeSubmissionController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SessionReportController;
use App\Http\Controllers\TherapistController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Auth::check() ? redirect()->route('dashboard') : redirect()->route('login'));
Route::get('/login', [AuthController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware(['guest', 'throttle:6,1'])->name('login.store');
Route::get('/daftar', [PublicRegistrationController::class, 'create'])->name('registration.public');
Route::post('/daftar', [PublicRegistrationController::class, 'store'])->middleware('throttle:5,1')->name('registration.public.store');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)
        ->middleware('role:owner|front_office|therapist|finance')
        ->name('dashboard');

    Route::middleware('role:owner|front_office')->group(function () {
        Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations.index');
        Route::post('/registrations/{registration}/convert', [RegistrationController::class, 'convert'])->name('registrations.convert');
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{patient}/details', [PatientController::class, 'details'])->name('patients.details');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
        Route::put('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
        Route::delete('/patients/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy');
        Route::patch('/patients/{patient}/restore', [PatientController::class, 'restore'])->name('patients.restore');
        Route::get('/therapists', [TherapistController::class, 'index'])->name('therapists.index');
        Route::post('/therapists', [TherapistController::class, 'store'])->name('therapists.store');
        Route::put('/therapists/{therapist}', [TherapistController::class, 'update'])->name('therapists.update');
        Route::get('/packages', [PackageCatalogController::class, 'index'])->name('packages.index');
        Route::post('/packages', [PackageCatalogController::class, 'store'])->name('packages.store');
        Route::patch('/packages/{packageCatalog}/status', [PackageCatalogController::class, 'toggleStatus'])->name('packages.status');
        Route::get('/scheduling', [AppointmentController::class, 'index'])->name('scheduling.index');
        Route::post('/scheduling', [AppointmentController::class, 'store'])->name('scheduling.store');
        Route::put('/scheduling/{appointment}', [AppointmentController::class, 'update'])->name('scheduling.update');
        Route::patch('/scheduling/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('scheduling.cancel');
        Route::patch('/assessments/{assessment}/schedule', [AssessmentController::class, 'reschedule'])->name('assessments.schedule.update');
        Route::patch('/patient-intakes/{submission}/status', [PatientIntakeSubmissionController::class, 'updateVerificationStatus'])->name('patient-intakes.status.update');
        Route::post('/patient-intakes/{submission}/link', [PatientIntakeSubmissionController::class, 'linkPatient'])->name('patient-intakes.link');
        Route::post('/patient-intakes/{submission}/create-patient', [PatientIntakeSubmissionController::class, 'createPatient'])->name('patient-intakes.create-patient');
    });

    Route::middleware('role:owner|front_office|therapist')->group(function () {
        Route::get('/patient-intakes', [PatientIntakeSubmissionController::class, 'index'])->name('patient-intakes.index');
        Route::get('/patient-intakes/{submission}', [PatientIntakeSubmissionController::class, 'show'])->name('patient-intakes.show');
    });

    Route::middleware('role:owner|front_office|therapist')->group(function () {
        Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
        Route::get('/assessments/create', [AssessmentController::class, 'create'])->name('assessments.create');
        Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('/assessments/{assessment}/complete', [AssessmentController::class, 'complete'])->name('assessments.complete');
        Route::put('/assessments/{assessment}/complete', [AssessmentController::class, 'update'])->name('assessments.update');
        Route::get('/assessments/{assessment}/pdf', [AssessmentController::class, 'pdf'])->name('assessments.pdf');
    });

    Route::middleware('role:owner|finance')->group(function () {
        Route::get('/finance/records', [FinanceRecordController::class, 'index'])->name('finance.records');
        Route::post('/finance/expenses', [FinanceRecordController::class, 'storeExpense'])->name('finance.expenses.store');
        Route::get('/finance/expenses/{clinicExpense}/proof', [FinanceRecordController::class, 'expenseProof'])->name('finance.expenses.proof');
        Route::get('/bills', [SaleController::class, 'bills'])->name('bills.index');
        Route::redirect('/assessment-payments', '/bills')->name('assessments.payments');
        Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::post('/sales/{sale}/payment', [SaleController::class, 'pay'])->name('sales.pay');
        Route::get('/sales/{sale}/pdf', [SaleController::class, 'pdf'])->name('sales.pdf');
        Route::get('/payments/{payment}/proof', [SaleController::class, 'paymentProof'])->name('payments.proof');
        Route::get('/payment-history', [PaymentHistoryController::class, 'index'])->name('payments.history');
        Route::get('/payment-history/export', [PaymentHistoryController::class, 'export'])->name('payments.export');
    });

    Route::middleware('role:owner')->group(function () {
        Route::post('/finance/payroll', [FinanceRecordController::class, 'storePayroll'])->name('finance.payroll.store');
        Route::get('/finance/payroll/{therapistPayroll}/proof', [FinanceRecordController::class, 'payrollProof'])->name('finance.payroll.proof');
    });

    Route::middleware('role:owner|therapist')->group(function () {
        Route::get('/sessions', [SessionReportController::class, 'index'])->name('sessions.index');
        Route::get('/sessions/export', [SessionReportController::class, 'export'])->name('sessions.export');
        Route::get('/sessions/{appointment}/report/photo', [SessionReportController::class, 'photo'])->name('sessions.report.photo');
        Route::post('/sessions/{appointment}/report', [SessionReportController::class, 'store'])->name('sessions.report.store');
        Route::put('/sessions/{appointment}/report', [SessionReportController::class, 'update'])->name('sessions.report.update');
    });
});

<?php

namespace App\Http\Controllers;

use App\Models\ClinicExpense;
use App\Models\TherapistPayroll;
use App\Models\TherapistProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FinanceRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $canManagePayroll = $request->user()->hasRole('owner');
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $now = now('Asia/Jakarta');
        $accountingPeriodMonth = $validated['month'] ?? ($now->day >= 20
            ? $now->copy()->addMonth()->format('Y-m')
            : $now->format('Y-m'));
        $periodMonthStart = Carbon::parse($accountingPeriodMonth.'-01', 'Asia/Jakarta');
        $periodStart = $periodMonthStart->copy()->subMonth()->day(20)->startOfDay();
        $periodEnd = $periodMonthStart->copy()->day(20)->startOfDay();
        $expensesQuery = ClinicExpense::query()
            ->where('expense_date', '>=', $periodStart->toDateString())
            ->where('expense_date', '<', $periodEnd->toDateString());
        $expenses = (clone $expensesQuery)
            ->with('createdBy:id,name')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'expense_page')
            ->withQueryString()
            ->through(fn (ClinicExpense $expense): array => [
                'id' => $expense->id,
                'name' => $expense->name,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->toDateString(),
                'created_by' => $expense->createdBy?->name,
                'proof_url' => $expense->proof_path !== null
                    ? route('finance.expenses.proof', $expense, false)
                    : null,
            ]);

        $payrollsQuery = $canManagePayroll
            ? TherapistPayroll::query()
                ->where('payment_date', '>=', $periodStart->toDateString())
                ->where('payment_date', '<', $periodEnd->toDateString())
            : null;
        $payrolls = $payrollsQuery !== null
            ? (clone $payrollsQuery)
                ->with(['therapist.user:id,name', 'createdBy:id,name'])
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'payroll_page')
                ->withQueryString()
                ->through(fn (TherapistPayroll $payroll): array => [
                    'id' => $payroll->id,
                    'therapist_name' => $payroll->therapist?->user?->name,
                    'payroll_period' => $payroll->payroll_period->format('Y-m'),
                    'amount' => $payroll->amount,
                    'payment_date' => $payroll->payment_date->toDateString(),
                    'created_by' => $payroll->createdBy?->name,
                    'proof_url' => $payroll->proof_path !== null
                        ? route('finance.payroll.proof', $payroll, false)
                        : null,
                ])
            : null;

        $therapists = $canManagePayroll
            ? TherapistProfile::query()
                ->with('user:id,name')
                ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
                ->get(['id', 'user_id'])
                ->map(fn (TherapistProfile $therapist): array => [
                    'id' => $therapist->id,
                    'name' => $therapist->user?->name,
                ])
                ->sortBy('name')
                ->values()
            : [];

        return Inertia::render('Finance/Records', [
            'expenses' => $expenses,
            'payrolls' => $payrolls,
            'expenseSummary' => [
                'count' => (clone $expensesQuery)->count(),
                'amount' => (clone $expensesQuery)->sum('amount'),
            ],
            'payrollSummary' => $payrollsQuery !== null ? [
                'count' => (clone $payrollsQuery)->count(),
                'amount' => (clone $payrollsQuery)->sum('amount'),
            ] : null,
            'therapists' => $therapists,
            'canManagePayroll' => $canManagePayroll,
            'today' => $now->toDateString(),
            'accountingPeriodMonth' => $accountingPeriodMonth,
            'currentPayrollPeriod' => $accountingPeriodMonth,
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $proofPath = $this->storeProof($request, 'finance/expenses');

        try {
            ClinicExpense::create([
                'name' => $data['name'],
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'proof_path' => $proofPath,
                'created_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }

        return redirect()->route('finance.records')->with('success', 'Pengeluaran klinik berhasil dicatat.');
    }

    public function storePayroll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'therapist_profile_id' => ['required', 'integer', 'exists:therapist_profiles,id'],
            'payroll_period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $proofPath = $this->storeProof($request, 'finance/payroll');

        try {
            TherapistPayroll::create([
                'therapist_profile_id' => $data['therapist_profile_id'],
                'payroll_period' => $data['payroll_period'].'-01',
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'proof_path' => $proofPath,
                'created_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }

        return redirect()->route('finance.records')->with('success', 'Penggajian terapis berhasil dicatat.');
    }

    public function expenseProof(ClinicExpense $clinicExpense): BinaryFileResponse
    {
        return $this->privateProofResponse($clinicExpense->proof_path);
    }

    public function payrollProof(TherapistPayroll $therapistPayroll): BinaryFileResponse
    {
        return $this->privateProofResponse($therapistPayroll->proof_path);
    }

    private function storeProof(Request $request, string $directory): ?string
    {
        $proofPath = $request->file('proof')?->store($directory, 'local');

        if ($proofPath === false) {
            throw ValidationException::withMessages(['proof' => 'Bukti tidak dapat disimpan.']);
        }

        return $proofPath;
    }

    private function privateProofResponse(?string $proofPath): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($proofPath !== null && $disk->exists($proofPath), 404);

        $response = response()->file($disk->path($proofPath), [
            'Content-Type' => $disk->mimeType($proofPath) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}

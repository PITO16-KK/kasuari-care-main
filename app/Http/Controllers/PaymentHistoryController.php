<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\TherapistProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $query = $this->filteredPayments($filters);

        return Inertia::render('Payments/History', [
            'filters' => $filters,
            'payments' => (clone $query)
                ->with([
                    'sale.patient:id,patient_code,name',
                    'sale.cashier:id,name',
                    'sale.therapist.user:id,name',
                    'sale.lines:id,sale_id,description',
                    'receiver:id,name',
                ])
                ->orderByDesc('received_at')
                ->paginate(25)
                ->withQueryString(),
            'summary' => [
                'count' => (clone $query)->count(),
                'amount' => (clone $query)->sum('amount'),
            ],
            'therapists' => TherapistProfile::query()
                ->with('user:id,name')
                ->get(['id', 'user_id'])
                ->sortBy('user.name')
                ->values(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $payments = $this->filteredPayments($filters)
            ->with([
                'sale.patient:id,patient_code,name',
                'sale.cashier:id,name',
                'sale.therapist.user:id,name',
                'sale.lines:id,sale_id,description',
            ])
            ->orderByDesc('received_at');
        $reference = $filters['period'] === 'monthly' ? str_replace('-', '', $filters['month']) : str_replace('-', '', $filters['date']);
        $filename = "riwayat-pembayaran-{$filters['period']}-{$reference}.csv";

        return response()->streamDownload(function () use ($payments): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tanggal pembayaran (WIB)', 'Nomor invoice', 'Kode pasien', 'Nama pasien', 'Item', 'Terapis', 'Metode pembayaran', 'Bank pengirim', 'Nomor referensi', 'Jumlah', 'Kasir']);

            $payments->chunk(500, function (EloquentCollection $paymentBatch) use ($output): void {
                foreach ($paymentBatch as $payment) {
                    $sale = $payment->sale;
                    $method = [
                        'cash' => 'Tunai',
                        'bank_transfer' => 'Transfer bank',
                        'qris' => 'QRIS',
                    ][$payment->method] ?? $payment->method;

                    fputcsv($output, [
                        $payment->received_at->copy()->setTimezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB',
                        $this->spreadsheetSafeText($sale->invoice_number),
                        $this->spreadsheetSafeText($sale->patient->patient_code),
                        $this->spreadsheetSafeText($sale->patient->name),
                        $this->spreadsheetSafeText($sale->lines->pluck('description')->implode('; ')),
                        $this->spreadsheetSafeText($sale->therapist?->user?->name ?? 'Tanpa terapis'),
                        $method,
                        $this->spreadsheetSafeText($payment->bank_name),
                        $this->spreadsheetSafeText($payment->reference_number),
                        $payment->amount,
                        $this->spreadsheetSafeText($sale->cashier->name),
                    ]);
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{period: string, date: string, month: string, therapist_id: int|string, method: string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'therapist_id' => ['nullable', 'integer', 'exists:therapist_profiles,id'],
            'method' => ['nullable', 'in:cash,bank_transfer,qris'],
        ]);

        $now = now('Asia/Jakarta');

        return [
            'period' => $validated['period'] ?? 'daily',
            'date' => $validated['date'] ?? now('Asia/Jakarta')->toDateString(),
            'month' => $validated['month'] ?? ($now->day >= 20 ? $now->copy()->addMonth()->format('Y-m') : $now->format('Y-m')),
            'therapist_id' => $validated['therapist_id'] ?? '',
            'method' => $validated['method'] ?? '',
        ];
    }

    /**
     * @param  array{period: string, date: string, month: string, therapist_id: int|string, method: string}  $filters
     * @return Builder<Payment>
     */
    private function filteredPayments(array $filters): Builder
    {
        [$startsAt, $endsAt] = $this->periodRange($filters);

        return Payment::query()
            ->where('received_at', '>=', $startsAt)
            ->where('received_at', '<', $endsAt)
            ->whereHas('sale', function (Builder $query) use ($filters): void {
                $query->where('status', 'paid')
                    ->when($filters['therapist_id'] !== '', fn (Builder $query) => $query->where('therapist_profile_id', $filters['therapist_id']));
            })
            ->when($filters['method'] !== '', fn (Builder $query) => $query->where('method', $filters['method']));
    }

    /**
     * @param  array{period: string, date: string, month: string, therapist_id: int|string, method: string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(array $filters): array
    {
        $startsAt = match ($filters['period']) {
            'weekly' => Carbon::parse($filters['date'], 'Asia/Jakarta')->startOfWeek(Carbon::MONDAY)->startOfDay(),
            'monthly' => Carbon::parse($filters['month'].'-01', 'Asia/Jakarta')->subMonth()->day(20)->startOfDay(),
            default => Carbon::parse($filters['date'], 'Asia/Jakarta')->startOfDay(),
        };
        $endsAt = match ($filters['period']) {
            'weekly' => $startsAt->copy()->addWeek(),
            'monthly' => Carbon::parse($filters['month'].'-01', 'Asia/Jakarta')->day(20)->startOfDay(),
            default => $startsAt->copy()->addDay(),
        };

        return [$startsAt->copy()->utc(), $endsAt->copy()->utc()];
    }

    private function spreadsheetSafeText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }
}

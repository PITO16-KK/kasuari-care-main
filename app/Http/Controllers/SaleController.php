<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\PackageEntitlement;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\Payment;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SaleController extends Controller
{
    public function bills(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:all,assessment,therapy'],
        ]);
        $type = $validated['type'] ?? 'all';
        $baseQuery = Sale::query()
            ->whereHas('patient')
            ->where('status', 'pending')
            ->whereHas('lines', fn (Builder $query) => $query->whereIn('line_type', ['assessment', 'package', 'session']));
        $filterByType = static function (Builder $query, string $type): Builder {
            if ($type === 'assessment') {
                return $query->whereHas('lines', fn (Builder $lineQuery) => $lineQuery->where('line_type', 'assessment'));
            }

            if ($type === 'therapy') {
                return $query->whereHas('lines', fn (Builder $lineQuery) => $lineQuery->whereIn('line_type', ['package', 'session']));
            }

            return $query;
        };

        return Inertia::render('Sales/Bills', [
            'filters' => ['type' => $type],
            'counts' => [
                'all' => (clone $baseQuery)->count(),
                'assessment' => $filterByType(clone $baseQuery, 'assessment')->count(),
                'therapy' => $filterByType(clone $baseQuery, 'therapy')->count(),
            ],
            'sales' => $filterByType(clone $baseQuery, $type)
                ->with([
                    'patient:id,patient_code,name',
                    'lines:id,sale_id,line_type,description,quantity,unit_price,line_total',
                    'assessment:id,sale_id,scheduled_at',
                    'appointment:id,starts_at,ends_at',
                ])
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sales/Create', [
            'patients' => Patient::where('is_active', true)->orderBy('name')->get(['id', 'patient_code', 'name']),
            'packages' => DB::table('package_catalog')->where('is_active', true)
                ->orderBy('session_duration_minutes')->orderBy('session_quantity')->orderBy('price')->get(),
            'patientPackages' => DB::table('patient_packages')
                ->join('package_catalog', 'package_catalog.id', '=', 'patient_packages.package_catalog_id')
                ->where('patient_packages.status', 'active')
                ->select([
                    'patient_packages.patient_id',
                    'patient_packages.expires_at',
                    'package_catalog.name',
                ])
                ->selectSub(
                    DB::table('duration_package_entitlements')
                        ->selectRaw('COALESCE(SUM(remaining_sessions), 0)')
                        ->whereColumn('patient_package_id', 'patient_packages.id'),
                    'duration_remaining_sessions',
                )
                ->selectSub(
                    DB::table('package_entitlements')
                        ->selectRaw('COALESCE(SUM(remaining_sessions), 0)')
                        ->whereColumn('patient_package_id', 'patient_packages.id'),
                    'legacy_remaining_sessions',
                )
                ->get(),
            'services' => DB::table('service_catalog')->where('is_active', true)->orderBy('name')->get(),
            'products' => DB::table('products')->where('is_active', true)->where('stock_quantity', '>', 0)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'therapist_profile_id' => ['nullable', 'exists:therapist_profiles,id'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.line_type' => ['required', 'in:assessment,package,session,product'],
            'items.*.item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'discount_type' => ['required', 'in:fixed,percent'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,bank_transfer,qris'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100', 'required_if:payment_method,bank_transfer'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $cartItems = collect($data['items'])
            ->groupBy(fn (array $item): string => $item['line_type'].':'.$item['item_id'])
            ->map(function ($items): array {
                $item = $items->first();

                return [
                    'line_type' => $item['line_type'],
                    'item_id' => (int) $item['item_id'],
                    'quantity' => (int) $items->sum('quantity'),
                ];
            })
            ->values();

        foreach ($cartItems as $index => $item) {
            if ($item['quantity'] > 50) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Jumlah untuk item yang sama maksimal 50.',
                ]);
            }

            if ($item['line_type'] === 'assessment' && $item['quantity'] !== 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Assessment hanya dapat dimasukkan satu kali per transaksi.',
                ]);
            }
        }

        if ($cartItems->where('line_type', 'assessment')->count() > 1) {
            throw ValidationException::withMessages([
                'items' => 'Satu transaksi hanya dapat memuat satu assessment yang menunggu pembayaran.',
            ]);
        }

        $proofPath = $request->file('payment_proof')?->store('payment-proofs', 'local');

        if ($proofPath === false) {
            throw ValidationException::withMessages(['payment_proof' => 'Bukti pembayaran gagal disimpan.']);
        }

        try {
            $sale = DB::transaction(function () use ($data, $request, $proofPath, $cartItems) {
                $patient = Patient::whereKey($data['patient_id'])->lockForUpdate()->firstOrFail();
                $resolvedLines = [];
                $subtotal = 0;
                $assessmentForCheckout = null;
                $assessmentSaleForCheckout = null;

                foreach ($cartItems as $index => $item) {
                    $assessment = null;
                    $assessmentSale = null;

                    if ($item['line_type'] === 'assessment') {
                        $assessment = Assessment::query()
                            ->whereKey($item['item_id'])
                            ->where('patient_id', $patient->id)
                            ->lockForUpdate()
                            ->first();

                        if ($assessment === null || $assessment->status !== 'awaiting_payment' || $assessment->sale_id === null) {
                            throw ValidationException::withMessages([
                                "items.{$index}.item_id" => 'Assessment ini tidak lagi menunggu pembayaran.',
                            ]);
                        }

                        $assessmentSale = Sale::query()
                            ->whereKey($assessment->sale_id)
                            ->where('patient_id', $patient->id)
                            ->where('status', 'pending')
                            ->lockForUpdate()
                            ->first();

                        if ($assessmentSale === null || $assessmentSale->payment()->exists()) {
                            throw ValidationException::withMessages([
                                "items.{$index}.item_id" => 'Tagihan assessment tidak lagi tersedia untuk digabungkan.',
                            ]);
                        }

                        $assessmentLines = $assessmentSale->lines()->lockForUpdate()->get();
                        $assessmentLine = $assessmentLines->firstWhere('line_type', 'assessment');

                        if ($assessmentLine === null || $assessmentLines->count() !== 1) {
                            throw ValidationException::withMessages([
                                "items.{$index}.item_id" => 'Tagihan assessment ini sudah terhubung dengan transaksi lain.',
                            ]);
                        }

                        $catalog = (object) [
                            'name' => $assessmentLine->description,
                            'price' => $assessmentLine->unit_price,
                        ];
                        $assessmentForCheckout = $assessment;
                        $assessmentSaleForCheckout = $assessmentSale;
                    } else {
                        $catalog = match ($item['line_type']) {
                            'package' => DB::table('package_catalog')->where('is_active', true)->where('id', $item['item_id'])->first(),
                            'session' => DB::table('service_catalog')->where('is_active', true)->where('service_type', 'session')->where('id', $item['item_id'])->first(),
                            'product' => DB::table('products')->where('is_active', true)->where('id', $item['item_id'])->lockForUpdate()->first(),
                        };
                    }

                    if (! $catalog) {
                        throw ValidationException::withMessages(["items.{$index}.item_id" => 'Item yang dipilih tidak tersedia.']);
                    }

                    if ($item['line_type'] === 'product' && $catalog->stock_quantity < $item['quantity']) {
                        throw ValidationException::withMessages(["items.{$index}.quantity" => 'Stok produk tidak mencukupi.']);
                    }

                    $unitPrice = (int) $catalog->price;
                    $lineTotal = $unitPrice * $item['quantity'];
                    $subtotal += $lineTotal;
                    $resolvedLines[] = [
                        ...$item,
                        'catalog' => $catalog,
                        'assessment' => $assessment,
                        'assessment_sale' => $assessmentSale,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                if (collect($resolvedLines)->contains(fn (array $item): bool => $item['line_type'] === 'package')) {
                    $activePackages = PatientPackage::where('patient_id', $patient->id)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->get();

                    foreach ($activePackages as $activePackage) {
                        $this->hasUsablePackageSessions($activePackage);
                    }
                }

                $discount = $data['discount_type'] === 'percent'
                    ? (int) round($subtotal * min(100, (float) $data['discount_value']) / 100)
                    : (int) min($subtotal, (float) $data['discount_value']);
                $grandTotal = $subtotal - $discount;

                if ($grandTotal < 1) {
                    throw ValidationException::withMessages(['discount_value' => 'Total transaksi harus lebih dari nol.']);
                }

                if ($assessmentSaleForCheckout !== null) {
                    $sale = $assessmentSaleForCheckout;
                    $sale->update([
                        'cashier_id' => $request->user()->id,
                        'status' => 'paid',
                        'subtotal' => $subtotal,
                        'discount_total' => $discount,
                        'grand_total' => $grandTotal,
                        'paid_at' => now(),
                    ]);
                } else {
                    $sale = Sale::create([
                        'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                        'patient_id' => $patient->id,
                        'cashier_id' => $request->user()->id,
                        'therapist_profile_id' => $data['therapist_profile_id'] ?? null,
                        'status' => 'paid',
                        'subtotal' => $subtotal,
                        'discount_total' => $discount,
                        'grand_total' => $grandTotal,
                        'paid_at' => now(),
                    ]);
                }

                foreach ($resolvedLines as $item) {
                    if ($item['line_type'] === 'assessment') {
                        continue;
                    }

                    $catalog = $item['catalog'];
                    $sale->lines()->create([
                        'line_type' => $item['line_type'],
                        'package_catalog_id' => $item['line_type'] === 'package' ? $catalog->id : null,
                        'service_catalog_id' => $item['line_type'] === 'session' ? $catalog->id : null,
                        'product_id' => $item['line_type'] === 'product' ? $catalog->id : null,
                        'description' => $catalog->name,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'line_total' => $item['line_total'],
                    ]);

                    if ($item['line_type'] === 'package') {
                        $patientPackage = PatientPackage::create([
                            'patient_id' => $patient->id,
                            'package_catalog_id' => $catalog->id,
                            'sale_id' => $sale->id,
                            'status' => 'active',
                            'starts_at' => now(),
                            'expires_at' => Carbon::now()->addDays($catalog->validity_days),
                        ]);

                        $packageItems = DB::table('package_catalog_items')->where('package_catalog_id', $catalog->id)->get();
                        if ($catalog->session_duration_minutes && $catalog->session_quantity) {
                            $grantedSessions = $catalog->session_quantity * $item['quantity'];
                            $entitlementId = DB::table('duration_package_entitlements')->insertGetId([
                                'patient_package_id' => $patientPackage->id,
                                'duration_minutes' => $catalog->session_duration_minutes,
                                'granted_sessions' => $grantedSessions,
                                'remaining_sessions' => $grantedSessions,
                            ]);

                            DB::table('duration_package_ledger')->insert([
                                'duration_package_entitlement_id' => $entitlementId,
                                'quantity_delta' => $grantedSessions,
                                'reason' => 'package_purchase',
                                'created_by' => $request->user()->id,
                                'created_at' => now(),
                            ]);
                        } else {
                            if ($packageItems->isEmpty()) {
                                throw ValidationException::withMessages(['items' => 'Paket belum memiliki jenis terapi dan kuota yang dikonfigurasi.']);
                            }

                            foreach ($packageItems as $packageItem) {
                                $grantedSessions = $packageItem->session_quantity * $item['quantity'];
                                $entitlement = PackageEntitlement::create([
                                    'patient_package_id' => $patientPackage->id,
                                    'therapy_type_id' => $packageItem->therapy_type_id,
                                    'granted_sessions' => $grantedSessions,
                                    'remaining_sessions' => $grantedSessions,
                                ]);
                                DB::table('package_ledger')->insert([
                                    'package_entitlement_id' => $entitlement->id,
                                    'quantity_delta' => $grantedSessions,
                                    'reason' => 'package_purchase',
                                    'created_by' => $request->user()->id,
                                    'created_at' => now(),
                                ]);
                            }
                        }
                    }

                    if ($item['line_type'] === 'product') {
                        DB::table('products')->where('id', $catalog->id)->decrement('stock_quantity', $item['quantity']);
                    }
                }

                $sale->payment()->create([
                    'method' => $data['payment_method'],
                    'amount' => $grandTotal,
                    'reference_number' => $data['reference_number'] ?? null,
                    'bank_name' => $data['payment_method'] === 'bank_transfer' ? $data['bank_name'] : null,
                    'proof_path' => $proofPath,
                    'received_by' => $request->user()->id,
                    'received_at' => now(),
                ]);

                $assessmentForCheckout?->update(['status' => 'paid']);

                return $sale;
            });
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }

        if ($cartItems->contains(fn (array $item): bool => $item['line_type'] === 'package')) {
            DB::transaction(function () use ($sale): void {
                Sale::query()
                    ->where('patient_id', $sale->patient_id)
                    ->where('status', 'pending')
                    ->whereNotNull('appointment_id')
                    ->with('appointment')
                    ->lockForUpdate()
                    ->get()
                    ->each(function (Sale $pendingSale): void {
                        if ($pendingSale->appointment?->hasAvailablePackageCoverage()) {
                            $pendingSale->update(['status' => 'cancelled']);
                        }
                    });
            });
        }

        if (filled(config('services.whatsapp.url')) && filled(config('services.whatsapp.token'))) {
            $phone = Patient::whereKey($sale->patient_id)->value('primary_whatsapp');
            $phone ??= DB::table('patient_parents')
                ->join('parents', 'parents.id', '=', 'patient_parents.parent_id')
                ->where('patient_parents.patient_id', $sale->patient_id)
                ->orderByDesc('patient_parents.is_primary_contact')
                ->value('parents.whatsapp');

            if ($phone) {
                SendWhatsAppMessage::dispatch($phone, "Pembayaran {$sale->invoice_number} telah lunas sebesar Rp".number_format($sale->grand_total, 0, ',', '.').'.')
                    ->afterCommit();
            }
        }

        return redirect()->route('sales.receipt', $sale)->with('success', 'Pembayaran lunas berhasil dicatat.');
    }

    public function receipt(Sale $sale): Response
    {
        return Inertia::render('Sales/Receipt', ['sale' => $sale->load(['patient:id,patient_code,name', 'appointment:id,starts_at,ends_at', 'lines', 'payment'])]);
    }

    public function paymentProof(Payment $payment): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($payment->proof_path !== null && $disk->exists($payment->proof_path), 404);

        $response = response()->file($disk->path($payment->proof_path), [
            'Content-Type' => $this->proofMimeType($disk, $payment->proof_path),
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    private function proofMimeType($disk, string $proofPath): string
    {
        return match (strtolower(pathinfo($proofPath, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => $disk->mimeType($proofPath) ?: 'application/octet-stream',
        };
    }

    public function pay(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,bank_transfer,qris'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100', 'required_if:payment_method,bank_transfer'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $proofPath = $request->file('payment_proof')?->store('payment-proofs', 'local');

        if ($proofPath === false) {
            throw ValidationException::withMessages(['payment_proof' => 'Bukti pembayaran gagal disimpan.']);
        }

        try {
            $isAssessmentPayment = DB::transaction(function () use ($data, $request, $sale, $proofPath): bool {
                $lockedSale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

                if ($lockedSale->status !== 'pending') {
                    throw ValidationException::withMessages(['payment_method' => 'Invoice ini sudah dibayar atau tidak dapat dibayar.']);
                }

                $assessment = Assessment::query()
                    ->where('sale_id', $lockedSale->id)
                    ->lockForUpdate()
                    ->first();
                $appointment = null;

                if ($assessment !== null) {
                    if ($assessment->status !== 'awaiting_payment') {
                        throw ValidationException::withMessages(['payment_method' => 'Invoice assessment ini tidak lagi menunggu pembayaran.']);
                    }
                } else {
                    $appointment = Appointment::query()
                        ->whereKey($lockedSale->appointment_id)
                        ->lockForUpdate()
                        ->first();

                    if ($appointment === null
                        || ! in_array($appointment->status, ['scheduled', 'confirmed', 'in_progress'], true)
                        || ! $lockedSale->lines()->where('line_type', 'session')->exists()) {
                        throw ValidationException::withMessages(['payment_method' => 'Invoice tidak terhubung dengan sesi terapi yang menunggu pembayaran.']);
                    }
                }

                $lockedSale->payment()->create([
                    'method' => $data['payment_method'],
                    'amount' => $lockedSale->grand_total,
                    'reference_number' => $data['reference_number'] ?? null,
                    'bank_name' => $data['payment_method'] === 'bank_transfer' ? $data['bank_name'] : null,
                    'proof_path' => $proofPath,
                    'received_by' => $request->user()->id,
                    'received_at' => now(),
                ]);
                $lockedSale->update([
                    'cashier_id' => $request->user()->id,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                if ($assessment !== null) {
                    $assessment->update(['status' => 'paid']);
                }

                return $assessment !== null;
            });
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }

        return redirect()->route('sales.receipt', $sale)->with(
            'success',
            $isAssessmentPayment
                ? 'Pembayaran assessment berhasil dicatat. Hasil assessment kini dapat diisi.'
                : 'Pembayaran sesi terapi berhasil dicatat.',
        );
    }

    public function pdf(Sale $sale): \Illuminate\Http\Response
    {
        abort_unless($sale->status === 'paid' && $sale->payment()->exists(), 404);

        $sale->load(['patient.parents', 'lines', 'payment', 'cashier:id,name']);

        return Pdf::loadView('pdf.receipt', compact('sale'))->setPaper([0, 0, 226.77, 500])->stream("{$sale->invoice_number}.pdf");
    }

    private function hasUsablePackageSessions(PatientPackage $patientPackage): bool
    {
        if ($patientPackage->expires_at->lt(now())) {
            $patientPackage->update(['status' => 'expired']);

            return false;
        }

        $durationSessions = (int) DB::table('duration_package_entitlements')
            ->where('patient_package_id', $patientPackage->id)
            ->sum('remaining_sessions');
        $legacySessions = (int) DB::table('package_entitlements')
            ->where('patient_package_id', $patientPackage->id)
            ->sum('remaining_sessions');

        if ($durationSessions + $legacySessions < 1) {
            $patientPackage->update(['status' => 'completed']);

            return false;
        }

        return true;
    }
}

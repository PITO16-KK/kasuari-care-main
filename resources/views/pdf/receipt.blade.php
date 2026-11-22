<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14px; }
        body { width: 198px; margin: 0 auto; color: #111111; font-family: Arial, sans-serif; font-size: 10px; }
        .center { text-align: center; }
        .brand { color: #111111; font-size: 16px; font-weight: bold; }
        .muted { color: #444444; }
        hr { border: 0; border-top: 1px dashed #94a3b8; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        .right { text-align: right; }
        .total { border-top: 1px solid #111111; font-weight: bold; padding-top: 6px; }
        .watermark { position: fixed; top: 32%; left: 10%; width: 80%; text-align: center; opacity: 0.06; z-index: -1000; }
        .watermark img { width: 130px; height: auto; }
    </style>
</head>
<body>
    <div class="watermark">
        <img src="{{ public_path('logo-klinik.png') }}" alt="Watermark">
    </div>
    <div class="center">
        <img src="{{ public_path('logo-klinik.png') }}" alt="Logo Klinik" style="width:48px;height:auto;margin:0 auto 3px;">
        <div class="brand" style="font-size: 11px; line-height: 1.25;">RUMAH TERAPI ANAK KASUARI CARE</div>
        <div style="font-size: 7.5px; color: #444444; margin-top: 3px; line-height: 1.25;">
            {{ config('clinic.address') }}<br>
            WA: {{ config('clinic.phone') }}
        </div>
        <div class="muted" style="margin-top: 4px; font-weight: bold; letter-spacing: 0.5px;">BUKTI PEMBAYARAN</div>
    </div>
    <hr>
    <div>{{ $sale->invoice_number }}</div>
    <div>{{ $sale->paid_at->format('d/m/Y H:i') }}</div>
    <div>{{ $sale->patient->patient_code }} · {{ $sale->patient->name }}</div>
    <hr>
    <table>
        @foreach ($sale->lines as $line)
            <tr><td colspan="2">{{ $line->description }}</td></tr>
            <tr><td>{{ $line->quantity }} × Rp{{ number_format($line->unit_price, 0, ',', '.') }}</td><td class="right">Rp{{ number_format($line->line_total, 0, ',', '.') }}</td></tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Subtotal</td><td class="right">Rp{{ number_format($sale->subtotal, 0, ',', '.') }}</td></tr>
        <tr><td>Diskon</td><td class="right">-Rp{{ number_format($sale->discount_total, 0, ',', '.') }}</td></tr>
        <tr><td class="total">TOTAL LUNAS</td><td class="right total">Rp{{ number_format($sale->grand_total, 0, ',', '.') }}</td></tr>
        <tr><td>Metode</td><td class="right">{{ strtoupper(str_replace('_', ' ', $sale->payment->method)) }}</td></tr>
        @if ($sale->payment->bank_name)
            <tr><td>Bank pengirim</td><td class="right">{{ $sale->payment->bank_name }}</td></tr>
        @endif
        @if ($sale->payment->reference_number)
            <tr><td>Nomor referensi</td><td class="right">{{ $sale->payment->reference_number }}</td></tr>
        @endif
    </table>
    <hr>
    <div class="center">Terima kasih telah mempercayakan tumbuh kembang anak kepada kami.</div>
</body>
</html>

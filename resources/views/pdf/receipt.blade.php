<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14px; }
        body { width: 198px; margin: 0 auto; color: #183247; font-family: Arial, sans-serif; font-size: 10px; }
        .center { text-align: center; }
        .brand { color: #07578a; font-size: 16px; font-weight: bold; }
        .muted { color: #64748b; }
        hr { border: 0; border-top: 1px dashed #94a3b8; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        .right { text-align: right; }
        .total { border-top: 1px solid #183247; font-weight: bold; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="center"><div class="brand">Kasuari Care</div><div>Rumah Terapi Anak</div><div class="muted">Bukti pembayaran</div></div>
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

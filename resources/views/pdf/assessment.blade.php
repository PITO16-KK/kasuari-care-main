<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #183247; font-size: 11px; line-height: 1.5; }
        h1 { margin: 0; color: #07578a; font-size: 20px; }
        h2 { margin: 20px 0 6px; color: #07578a; font-size: 13px; }
        .brand { border-bottom: 2px solid #1686c7; padding-bottom: 12px; }
        .meta { margin-top: 16px; width: 100%; border-collapse: collapse; }
        .meta td { padding: 5px 8px; border: 1px solid #dbe4ea; }
        .label { width: 135px; color: #64748b; }
        .block { border: 1px solid #dbe4ea; padding: 9px; min-height: 30px; white-space: pre-wrap; }
        table.recommendations { width: 100%; border-collapse: collapse; }
        .recommendations th, .recommendations td { border: 1px solid #dbe4ea; padding: 7px; text-align: left; }
        .recommendations th { background: #eff7ff; }
        .footer { margin-top: 36px; text-align: right; }
    </style>
</head>
<body>
    <div class="brand"><h1>Kasuari Care</h1><div>Laporan hasil assessment anak</div></div>
    <table class="meta">
        <tr><td class="label">ID pasien</td><td>{{ $assessment->patient->patient_code }}</td><td class="label">Tanggal</td><td>{{ optional($assessment->completed_at ?? $assessment->scheduled_at ?? $assessment->created_at)->format('d M Y') }}</td></tr>
        <tr><td class="label">Nama anak</td><td colspan="3">{{ $assessment->patient->name }}</td></tr>
        <tr><td class="label">Tanggal lahir</td><td>{{ optional($assessment->patient->birth_date)->format('d M Y') ?? '-' }}</td><td class="label">Orang tua</td><td>{{ $assessment->patient->parents->pluck('name')->join(', ') ?: '-' }}</td></tr>
    </table>
    <h2>Keluhan utama</h2><div class="block">{{ $assessment->chief_complaint }}</div>
    <h2>Riwayat tumbuh kembang</h2>
    @foreach (['motorik' => 'Motorik', 'sensorik' => 'Sensorik', 'kemandirian' => 'Kemandirian', 'komunikasi' => 'Komunikasi'] as $key => $label)
        <p><b>{{ $label }}:</b> {{ data_get($assessment->development_history, $key, '-') ?: '-' }}</p>
    @endforeach
    <h2>Belajar, fokus, dan perilaku</h2>
    @foreach (['belajar' => 'Belajar', 'fokus' => 'Fokus', 'perilaku' => 'Perilaku'] as $key => $label)
        <p><b>{{ $label }}:</b> {{ data_get($assessment->learning_behavior, $key, '-') ?: '-' }}</p>
    @endforeach
    <h2>Rekomendasi terapis</h2>
    <table class="recommendations"><thead><tr><th>Jenis terapi</th><th>Nama terapis</th></tr></thead><tbody>
        @forelse ($assessment->patient->recommended_therapists as $recommendation)
            <tr><td>{{ $recommendation['therapy_name'] }}</td><td>{{ $recommendation['therapist_name'] ?: '-' }}</td></tr>
        @empty
            <tr><td colspan="2">Belum ada terapis yang ditetapkan pada data pasien.</td></tr>
        @endforelse
    </tbody></table>
    <h2>Kesimpulan</h2><div class="block">{{ $assessment->summary ?: '-' }}</div>
    <h2>Rujukan / catatan lanjutan</h2><div class="block">{{ $assessment->referral_notes ?: '-' }}</div>
    <div class="footer">Dokumen dibuat {{ now()->format('d M Y H:i') }}<br><br><br>____________________________<br>Terapis / pemeriksa</div>
</body>
</html>

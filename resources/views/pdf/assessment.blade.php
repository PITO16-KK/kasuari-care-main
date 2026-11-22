<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 18mm 22mm; }
        body { margin: 0; color: #111111; font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; line-height: 1.48; text-align: left; }
        .kop-surat { width: 100%; border-bottom: 3px double #111111; padding-bottom: 10px; margin-bottom: 12px; }
        .kop-surat td { vertical-align: middle; }
        .kop-logo { width: 75px; text-align: left; }
        .kop-logo img { width: 68px; height: auto; }
        .kop-text { text-align: center; padding-right: 68px; }
        .kop-title { font-size: 14pt; font-weight: bold; color: #111111; letter-spacing: 0.5px; }
        .kop-meta { font-size: 8pt; color: #333333; margin-top: 4px; line-height: 1.35; }
        .watermark { position: fixed; top: 32%; left: 10%; width: 80%; text-align: center; opacity: 0.08; z-index: -1000; }
        .watermark img { width: 380px; height: auto; }
        h1 { margin: 15px 0 3px; color: #111111; font-size: 20pt; line-height: 1.2; }
        .intro { margin: 0 0 13px; color: #444444; font-size: 9.5pt; }
        .patient-card { margin: 0 0 14px; padding: 11px 13px; border: 1px solid #d1d5db; border-left: 4px solid #111111; background: #f9fafb; }
        .eyebrow { margin-bottom: 3px; color: #444444; font-size: 8pt; font-weight: bold; letter-spacing: .6px; }
        .patient-name { color: #111111; font-size: 16pt; font-weight: bold; }
        .patient-code { margin-top: 2px; color: #444444; font-size: 9pt; }
        .details { width: 100%; margin-top: 8px; border-collapse: collapse; table-layout: fixed; }
        .details td { width: 50%; padding: 6px 8px 4px 0; border-top: 1px solid #e5e7eb; vertical-align: top; }
        .detail-label { display: block; margin-bottom: 2px; color: #555555; font-size: 8pt; }
        .detail-value { color: #111111; font-size: 9.5pt; font-weight: bold; }
        h2 { margin: 15px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; color: #111111; font-size: 12pt; line-height: 1.25; }
        .learning-section { page-break-before: always; }
        .entry { margin: 0 0 7px; padding: 7px 9px; border: 1px solid #e5e7eb; background: #ffffff; }
        .entry-label { margin: 0 0 2px; color: #111111; font-size: 9.5pt; font-weight: bold; }
        .entry-copy { margin: 0; color: #111111; font-size: 10.5pt; line-height: 1.5; }
        .empty { color: #666666; font-style: italic; }
        .summary { border-left: 3px solid #111111; background: #f9fafb; }
        .recommendations { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .recommendations th, .recommendations td { padding: 7px 9px; border: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        .recommendations th { background: #f3f4f6; color: #111111; font-size: 9pt; }
        .recommendations td { font-size: 9.5pt; }
        .signature { margin-top: 20px; text-align: right; }
        .signature-label { color: #444444; font-size: 8.5pt; }
        .signature-space { height: 42px; }
        .signature-line { display: inline-block; width: 220px; padding-top: 4px; border-top: 1px solid #444444; color: #333333; font-size: 8.5pt; text-align: center; }
    </style>
</head>
<body>
    <div class="watermark">
        <img src="{{ public_path('logo-klinik.png') }}" alt="Watermark">
    </div>

    <table class="kop-surat">
        <tr>
            <td class="kop-logo">
                <img src="{{ public_path('logo-klinik.png') }}" alt="Logo Klinik">
            </td>
            <td class="kop-text">
                <div class="kop-title">RUMAH TERAPI ANAK KASUARI CARE</div>
                <div class="kop-meta">
                    {{ config('clinic.address') }}<br>
                    Telp/WhatsApp: {{ config('clinic.phone') }} &bull; Email: {{ config('clinic.email') }}
                </div>
            </td>
        </tr>
    </table>

    <h1>Laporan Hasil Assessment</h1>
    <p class="intro">Ringkasan hasil pengamatan dan rekomendasi tim untuk mendukung langkah berikutnya.</p>

    <div class="patient-card">
        <div class="eyebrow">IDENTITAS PASIEN</div>
        <div class="patient-name">{{ $assessment->patient->name }}</div>
        <div class="patient-code">Kode pasien: {{ $assessment->patient->patient_code }}</div>
        <table class="details">
            <tr>
                <td><span class="detail-label">Tanggal lahir</span><span class="detail-value">{{ optional($assessment->patient->birth_date)->format('d/m/Y') ?? 'Belum dicatat' }}</span></td>
                <td><span class="detail-label">Tanggal assessment</span><span class="detail-value">{{ optional($assessment->completed_at ?? $assessment->scheduled_at ?? $assessment->created_at)->format('d/m/Y') }}</span></td>
            </tr>
            <tr><td colspan="2"><span class="detail-label">Orang tua / wali</span><span class="detail-value">{{ $assessment->patient->parents->pluck('name')->filter()->join(', ') ?: 'Belum dicatat' }}</span></td></tr>
        </table>
    </div>

    <h2>Keluhan utama</h2>
    <div class="entry"><p class="entry-copy">{!! nl2br(e($assessment->chief_complaint ?: 'Belum dicatat pada assessment ini.')) !!}</p></div>

    <h2>Riwayat tumbuh kembang</h2>
    @foreach (['motorik' => 'Motorik', 'sensorik' => 'Sensorik', 'kemandirian' => 'Kemandirian', 'komunikasi' => 'Komunikasi'] as $key => $label)
        <div class="entry">
            <p class="entry-label">{{ $label }}</p>
            <p class="entry-copy">{!! nl2br(e(data_get($assessment->development_history, $key) ?: 'Belum dicatat pada assessment ini.')) !!}</p>
        </div>
    @endforeach

    <h2 class="learning-section">Belajar, fokus, dan perilaku</h2>
    @foreach (['belajar' => 'Belajar', 'fokus' => 'Fokus', 'perilaku' => 'Perilaku'] as $key => $label)
        <div class="entry">
            <p class="entry-label">{{ $label }}</p>
            <p class="entry-copy">{!! nl2br(e(data_get($assessment->learning_behavior, $key) ?: 'Belum dicatat pada assessment ini.')) !!}</p>
        </div>
    @endforeach

    <h2>Rekomendasi terapi</h2>
    <table class="recommendations">
        <thead><tr><th style="width: 48%;">Jenis terapi</th><th>Terapis yang direkomendasikan</th></tr></thead>
        <tbody>
            @forelse ($assessment->patient->recommended_therapists ?? [] as $recommendation)
                <tr><td>{{ $recommendation['therapy_name'] ?: 'Belum ditentukan' }}</td><td>{{ $recommendation['therapist_name'] ?: 'Belum ditentukan' }}</td></tr>
            @empty
                <tr><td colspan="2">Belum ada rekomendasi terapis yang ditetapkan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Kesimpulan tim</h2>
    <div class="entry summary"><p class="entry-copy">{!! nl2br(e($assessment->summary ?: 'Belum dicatat pada assessment ini.')) !!}</p></div>

    <h2>Rujukan dan catatan lanjutan</h2>
    <div class="entry"><p class="entry-copy">{!! nl2br(e($assessment->referral_notes ?: 'Tidak ada catatan lanjutan.')) !!}</p></div>

    <div class="signature">
        <div class="signature-label">Dibuat {{ now()->format('d/m/Y H:i') }}</div>
        <div class="signature-label">Terapis / pemeriksa</div>
        <div class="signature-space"></div>
        <div class="signature-line">Nama dan tanda tangan</div>
    </div>
</body>
</html>

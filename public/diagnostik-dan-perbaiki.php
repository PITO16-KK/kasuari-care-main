<?php
/**
 * Kasuari Care - Skrip Diagnostik & Pemulihan Otomatis
 * Akses: https://testingklinik.my.id/diagnostik-dan-perbaiki.php
 */
define('LARAVEL_START', microtime(true));

// 1. Bootstrap Laravel
$autoloadPaths = [
    __DIR__.'/../vendor/autoload.php',
    __DIR__.'/vendor/autoload.php',
];

$app = null;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        $bootstrapPath = dirname($path).'/../bootstrap/app.php';
        if (file_exists($bootstrapPath)) {
            $app = require_once $bootstrapPath;
            break;
        }
        $bootstrapAlt = dirname($path).'/bootstrap/app.php';
        if (file_exists($bootstrapAlt)) {
            $app = require_once $bootstrapAlt;
            break;
        }
    }
}

if (! $app) {
    exit('<h1>Gagal memuat bootstrap Laravel. Pastikan file berada di folder public/ aplikasi Laravel.</h1>');
}

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$action = $_GET['action'] ?? null;
$message = '';
$messageType = 'info';

// 2. Eksekusi Perbaikan jika tombol ditekan
if ($action === 'fix_all') {
    try {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        // Bersihkan data dummy lama / yatim
        DB::unprepared('
            DELETE FROM duration_package_ledger WHERE duration_package_entitlement_id IN (101, 102, 103, 104, 105, 108);
            DELETE FROM duration_package_entitlements WHERE id IN (101, 102, 103, 104, 105, 108);
            DELETE FROM patient_packages WHERE id IN (101, 102, 103, 104, 105, 108) OR patient_id IN (101, 102, 103, 104, 105, 106, 107, 108);
            DELETE FROM session_reports WHERE appointment_id IN (SELECT id FROM (SELECT id FROM appointments WHERE patient_id IN (101, 102, 103, 104, 105, 106, 107, 108)) AS tmp_app);
            DELETE FROM appointments WHERE patient_id IN (101, 102, 103, 104, 105, 106, 107, 108);
            DELETE FROM assessment_recommendations WHERE assessment_id IN (101, 102, 103, 104, 105, 107, 108);
            DELETE FROM assessments WHERE patient_id IN (101, 102, 103, 104, 105, 106, 107, 108);
            DELETE FROM payments WHERE sale_id IN (201, 202, 203, 204, 205, 207, 208, 301, 302, 303, 304, 305, 308);
            DELETE FROM sale_lines WHERE sale_id IN (201, 202, 203, 204, 205, 207, 208, 301, 302, 303, 304, 305, 308);
            DELETE FROM sales WHERE id IN (201, 202, 203, 204, 205, 207, 208, 301, 302, 303, 304, 305, 308) OR patient_id IN (101, 102, 103, 104, 105, 106, 107, 108);
            DELETE FROM patient_intake_submissions WHERE patient_id IN (101, 102, 103, 104, 105, 106, 107, 108);
            DELETE FROM patient_therapists WHERE patient_id IN (101, 102, 103, 104, 105, 106, 107, 108);
            DELETE FROM patient_parents WHERE patient_id IN (101, 102, 103, 104, 105, 106, 107, 108);
            DELETE FROM parents WHERE id IN (101, 102, 103, 104, 105, 106, 107, 108);
            DELETE FROM patients WHERE id IN (101, 102, 103, 104, 105, 106, 107, 108);
        ');

        // 1. Jalankan migrasi jika ada struktur tabel / kolom baru
        Artisan::call('migrate', ['--force' => true]);

        // 2. Eksekusi dummy_patients_mysql.sql jika file ada
        $sqlPath = __DIR__.'/../database/dummy_patients_mysql.sql';
        if (file_exists($sqlPath)) {
            $sqlContent = file_get_contents($sqlPath);
            DB::unprepared($sqlContent);
            $message = '✓ Berhasil membersihkan data lama dan memasukkan 8 data dummy pasien secara valid!';
        } else {
            $message = '✓ Data yatim berhasil dibersihkan! (File dummy_patients_mysql.sql tidak ditemukan di database/)';
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        // Clear cache
        Artisan::call('optimize:clear');
        $message .= '<br>✓ Cache Laravel (config, route, view) berhasil dibersihkan!';
        $messageType = 'success';
    } catch (Throwable $e) {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $message = 'Gagal melakukan perbaikan: '.htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
} elseif ($action === 'clear_cache') {
    try {
        Artisan::call('optimize:clear');
        $message = '✓ Cache Laravel berhasil dibersihkan:<br><pre>'.htmlspecialchars(Artisan::output()).'</pre>';
        $messageType = 'success';
    } catch (Throwable $e) {
        $message = 'Gagal membersihkan cache: '.htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}

// 3. Ambil data diagnostik
$orphanAssessments = 0;
$orphanAppointments = 0;
$orphanSales = 0;
$totalPatients = 0;
$totalAssessments = 0;
$totalAppointments = 0;
$totalSales = 0;
$dummyPatientsCount = 0;

try {
    $totalPatients = DB::table('patients')->count();
    $totalAssessments = DB::table('assessments')->count();
    $totalAppointments = DB::table('appointments')->count();
    $totalSales = DB::table('sales')->count();

    // Cek record yatim (patient_id tidak ada di tabel patients)
    $orphanAssessments = DB::table('assessments')
        ->whereNotIn('patient_id', DB::table('patients')->select('id'))
        ->count();

    $orphanAppointments = DB::table('appointments')
        ->whereNotIn('patient_id', DB::table('patients')->select('id'))
        ->count();

    $orphanSales = DB::table('sales')
        ->whereNotNull('patient_id')
        ->whereNotIn('patient_id', DB::table('patients')->select('id'))
        ->count();

    $dummyPatientsCount = DB::table('patients')
        ->whereIn('id', [101, 102, 103, 104, 105, 106, 107, 108])
        ->count();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// 4. Baca 40 baris terakhir log Laravel
$logLines = [];
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = array_slice($lines, -40);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasuari Care - Diagnostik & Pemulihan Sistem</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; padding: 24px; line-height: 1.5; }
        .container { max-width: 960px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #0f172a; color: white; padding: 20px 24px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .header p { margin: 6px 0 0; font-size: 13px; color: #94a3b8; }
        .content { padding: 24px; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
        .card .title { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
        .card .val { font-size: 24px; font-weight: 700; color: #0f172a; }
        .card.danger { border-color: #fca5a5; background: #fff1f2; }
        .card.danger .val { color: #b91c1c; }
        .card.success { border-color: #86efac; background: #f0fdf4; }
        .card.success .val { color: #15803d; }
        .btn { display: inline-block; padding: 10px 18px; font-size: 14px; font-weight: 600; border-radius: 6px; text-decoration: none; cursor: pointer; border: none; }
        .btn-primary { background: #0284c7; color: white; }
        .btn-primary:hover { background: #0369a1; }
        .btn-danger { background: #e11d48; color: white; }
        .btn-danger:hover { background: #be123c; }
        .btn-outline { background: white; border: 1px solid #cbd5e1; color: #334155; }
        .btn-outline:hover { background: #f1f5f9; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
        .log-box { background: #0f172a; color: #f1f5f9; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; padding: 16px; border-radius: 8px; overflow-x: auto; max-height: 380px; white-space: pre-wrap; word-break: break-all; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-green { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🏥 Kasuari Care - Diagnostik Database & Perbaikan Sistem</h1>
        <p>Gunakan halaman ini untuk mendeteksi data yatim penyebab white screen dan memperbaikinya dalam 1 klik.</p>
    </div>

    <div class="content">
        <?php if ($message) { ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= $message ?>
            </div>
        <?php } ?>

        <h2>1. Hasil Audit Database Saat Ini</h2>
        <div class="grid">
            <div class="card <?= $orphanAssessments > 0 ? 'danger' : 'success' ?>">
                <div class="title">Asesmen Tanpa Pasien (Yatim)</div>
                <div class="val"><?= $orphanAssessments ?></div>
                <div style="font-size: 12px; margin-top: 4px; color: #64748b;">Penyebab White Screen /assessments</div>
            </div>
            <div class="card <?= $orphanAppointments > 0 ? 'danger' : 'success' ?>">
                <div class="title">Sesi Tanpa Pasien (Yatim)</div>
                <div class="val"><?= $orphanAppointments ?></div>
                <div style="font-size: 12px; margin-top: 4px; color: #64748b;">Penyebab White Screen /sessions</div>
            </div>
            <div class="card <?= $orphanSales > 0 ? 'danger' : 'success' ?>">
                <div class="title">Tagihan Tanpa Pasien (Yatim)</div>
                <div class="val"><?= $orphanSales ?></div>
                <div style="font-size: 12px; margin-top: 4px; color: #64748b;">Penyebab White Screen /bills</div>
            </div>
            <div class="card">
                <div class="title">Pasien Dummy Terpasang</div>
                <div class="val"><?= $dummyPatientsCount ?> / 8</div>
                <div style="font-size: 12px; margin-top: 4px; color: #64748b;">ID 101 s/d 108</div>
            </div>
        </div>

        <h2>2. Tindakan Pemulihan</h2>
        <div class="actions">
            <a href="?action=fix_all" class="btn btn-danger" onclick="return confirm('Yakin ingin membersihkan data yatim dan memasukkan 8 pasien dummy secara bersih?')">
                🚀 1-Klik: Perbaiki Database &amp; Pasang 8 Pasien Dummy
            </a>
            <a href="?action=clear_cache" class="btn btn-outline">
                🧹 Bersihkan Cache Laravel
            </a>
            <a href="/dashboard" class="btn btn-primary" target="_blank">
                🌐 Buka Aplikasi Kasuari Care
            </a>
        </div>

        <h2>3. 40 Baris Terakhir Error Log Server (storage/logs/laravel.log)</h2>
        <div class="log-box">
<?php
if (! empty($logLines)) {
    foreach ($logLines as $line) {
        echo htmlspecialchars($line)."\n";
    }
} else {
    echo 'Log kosong atau file storage/logs/laravel.log belum ada error.';
}
?>
        </div>
        <p style="font-size: 12px; color: #64748b; margin-top: 10px;">Catatan: Demi keamanan klinik, hapus file <code>public/diagnostik-dan-perbaiki.php</code> ini setelah sistem kembali normal.</p>
    </div>
</div>

</body>
</html>

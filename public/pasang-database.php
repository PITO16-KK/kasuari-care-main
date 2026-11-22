<?php
/**
 * Kasuari Care - Skrip Otomatisasi Instalasi Database (Fresh Install)
 * Jalankan via browser: https://testingklinik.my.id/pasang-database.php
 */
define('LARAVEL_START', microtime(true));

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
    exit('<h1>Gagal memuat bootstrap Laravel. Pastikan file ini berada di folder public/ aplikasi Kasuari Care.</h1>');
}

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$action = $_POST['action'] ?? null;
$logs = [];
$status = 'ready';

if ($action === 'install') {
    try {
        // 1. Cek koneksi DB
        DB::connection()->getPdo();
        $logs[] = '✓ Berhasil terhubung ke database MySQL.';

        // 2. Jalankan Migrasi
        Artisan::call('migrate', ['--force' => true]);
        $logs[] = "✓ Struktur tabel berhasil dibuat (Migrate):\n".trim(Artisan::output());

        // 3. Jalankan Seeder
        Artisan::call('db:seed', ['--force' => true]);
        $logs[] = "✓ Data master & 8 Pasien Dummy berhasil diisi (Seed):\n".trim(Artisan::output());

        // 4. Bersihkan Cache
        Artisan::call('optimize:clear');
        $logs[] = "✓ Cache aplikasi berhasil dibersihkan:\n".trim(Artisan::output());

        $status = 'success';
    } catch (Throwable $e) {
        $status = 'error';
        $logs[] = '✗ Terjadi kesalahan: '.$e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi Database Kasuari Care</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; color: #1e293b; padding: 30px 16px; margin: 0; }
        .card { max-width: 680px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #0f172a; color: white; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 8px 0 0; font-size: 13px; color: #94a3b8; }
        .body { padding: 24px; }
        .btn { display: inline-block; width: 100%; box-sizing: border-box; text-align: center; padding: 12px 20px; font-size: 15px; font-weight: 600; border-radius: 8px; cursor: pointer; text-decoration: none; border: none; }
        .btn-primary { background: #0284c7; color: white; }
        .btn-primary:hover { background: #0369a1; }
        .btn-success { background: #16a34a; color: white; margin-top: 12px; }
        .btn-success:hover { background: #15803d; }
        .log-box { background: #0f172a; color: #f1f5f9; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 8px; margin-top: 20px; white-space: pre-wrap; word-break: break-all; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 14px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h1>🏥 Instalasi Database Otomatis - Kasuari Care</h1>
        <p>Gunakan halaman ini untuk memasang tabel dan data dummy secara otomatis di hosting baru.</p>
    </div>

    <div class="body">
        <?php if ($status === 'success') { ?>
            <div class="alert-success">
                <strong>✓ Berhasil!</strong> Seluruh tabel database, data master, akun pengguna, dan 8 data dummy pasien telah terpasang dengan sempurna.
            </div>
            <a href="/dashboard" class="btn btn-success" target="_blank">🚀 Buka Dashboard Kasuari Care</a>
        <?php } elseif ($status === 'error') { ?>
            <div class="alert-error">
                <strong>Gagal!</strong> Periksa konfigurasi database di file <code>.env</code> Anda (nama DB, username, dan password).
            </div>
        <?php } ?>

        <?php if ($status !== 'success') { ?>
            <p style="font-size: 14px; line-height: 1.6; color: #475569;">
                Pastikan Anda sudah membuat database MySQL di cPanel dan mencocokkan namanya di file <code>.env</code>.
                Tekan tombol di bawah untuk membuat seluruh tabel dan mengisi data awal dalam 1 kali klik:
            </p>

            <form method="POST">
                <input type="hidden" name="action" value="install">
                <button type="submit" class="btn btn-primary" onclick="this.innerHTML='Memproses instalasi (mohon tunggu)...'; this.style.opacity='0.7';">
                    ⚡ Mulai Pemasangan Database Otomatis
                </button>
            </form>
        <?php } ?>

        <?php if (! empty($logs)) { ?>
            <div class="log-box">
                <?php foreach ($logs as $log) { ?>
                    <?= htmlspecialchars($log)."\n\n" ?>
                <?php } ?>
            </div>
            <p style="font-size: 12px; color: #64748b; margin-top: 12px;">Catatan keamanan: Silakan hapus file <code>public/pasang-database.php</code> setelah selesai.</p>
        <?php } ?>
    </div>
</div>

</body>
</html>

# Pembaruan Aplikasi cPanel Kasuari Care

Paket terbaru: `KASUARI_CARE_CPANEL_UPDATE_2026-11-21_v6.zip`.

Paket ini mencakup 6 pembaruan utama sesuai kebutuhan operasional klinik:

1. **Pencarian Pasien pada Form Jadwal Terapi:**
   - Input nama/kode pasien mendukung pengetikan langsung dengan filter autocomplete responsif.
   - Dilengkapi debounce, penanganan blur yang aman (tidak tertutup saat diklik), tombol hapus cepat (X), dan dukungan tombol keyboard (Enter / Panah).
2. **Transaksi Kasir Multi-Item Sekaligus:**
   - Kasir dapat memasukkan beberapa item sekaligus dalam 1 transaksi (kombinasi Paket Terapi, Sesi Lepas/Assessment, dan Produk/Buku).
   - Subtotal dan diskon otomatis terkalkulasi dalam 1 struk & pembayaran.
   - Tampilan dropdown menyertakan stok barang dan konfirmasi kuota paket.
3. **Kop Surat Resmi (Logo & Alamat) pada Struk & PDF Laporan:**
   - PDF Assessment / Evaluasi Hasil memiliki format Kop Surat formal lengkap dengan logo klinik, nama klinik, tagline, alamat, kontak WhatsApp, dan garis batas ganda (double-rule).
   - Struk cetak & web kasir (Receipt) memuat identitas klinik, logo, dan alamat lengkap.
   - Konfigurasi klinik tersentralisasi di `config/clinic.php`.
4. **Validasi Jadwal Terapis Fleksibel namun Aman:**
   - Seorang terapis dapat menangani lebih dari 1 pasien / direkomendasikan ke banyak pasien.
   - Sistem secara otomatis memblokir bentrok jam terapi (sesi terapi dan assessment) untuk terapis yang sama jika rentang waktunya bertumpang tindih. Peringatan bentrok tampil baik di antarmuka (frontend) maupun divalidasi ketat di sisi backend.
5. **Perbaikan Alur Pengisian Data Pasien (Cegah Duplikasi):**
   - Validasi deteksi pasien ganda berdasarkan kesamaan nama dan tanggal lahir / nomor WhatsApp.
   - Peringatan banner interaktif muncul saat pengisian nama mirip, dengan opsi konfirmasi jika memang anak berbeda.
   - Konversi pendaftaran online (Registrasi Publik) dilengkapi fitur *Tautkan ke Pasien Terdaftar* secara otomatis agar tidak menduplikasi rekam medis pasien lama.
   - Generator nomor rekam medis (`KL-YYYY-XXX`) terlindung dari duplikasi kode.
6. **Manajemen Produk Fisik (Buku, Alat Terapi, Sensory Toys, dll.):**
   - Penambahan menu baru **Produk & Buku** di navigasi utama dan tab Paket.
   - Fitur lengkap kelola produk: SKU, Nama Produk, Harga, Jumlah Stok, dan Status Aktif.
   - Terintegrasi langsung dengan modul kasir (stok otomatis berkurang saat transaksi selesai).

---

## File yang Disertakan dalam Paket v6

- `config/clinic.php`
- `database/migrations/` (migrasi attendance_status pada asesmen & penghapusan unique terapis)
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/ProductController.php`
- `app/Http/Controllers/PatientController.php`
- `app/Http/Controllers/RegistrationController.php`
- `app/Http/Controllers/AppointmentController.php`
- `app/Http/Controllers/AssessmentController.php`
- `app/Http/Controllers/SaleController.php`
- `app/Models/Product.php`
- `app/Models/Patient.php`
- `routes/web.php`
- `resources/js/Layouts/MainLayout.vue`
- `resources/js/Pages/Dashboard.vue`
- `resources/js/Pages/Scheduling/Index.vue`
- `resources/js/Pages/Sales/Create.vue`
- `resources/js/Pages/Sales/Receipt.vue`
- `resources/js/Pages/Patients/Index.vue`
- `resources/js/Pages/Registrations/Index.vue`
- `resources/js/Pages/Products/Index.vue`
- `resources/js/Pages/Packages/Index.vue`
- `resources/js/Pages/Assessments/Create.vue`
- `resources/views/pdf/assessment.blade.php`
- `resources/views/pdf/receipt.blade.php`
- `public/build/manifest.json`
- `public/build/assets/`
- `README_PERUBAHAN_CPANEL.md`

---

## Cara Menerapkan di cPanel

1. Buat salinan cadangan (*backup*) file aplikasi dan database production di cPanel.
2. Unggah file update ke direktori root aplikasi Laravel Anda di File Manager cPanel.
3. Ekstrak ZIP tersebut di folder aplikasi (pilih *Extract* dan izinkan menimpa / *overwrite* file lama).
4. Pastikan logo klinik sudah tersedia di direktori `public/logo-klinik.png`.
5. Buka Terminal cPanel / SSH di root direktori aplikasi dan jalankan perintah:
   ```bash
   php artisan migrate --force
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
6. Bersihkan cache browser (`Ctrl + F5`) atau buka mode Incognito. Jika server cPanel menggunakan OPcache, lakukan *Restart PHP-FPM* atau *Reset OPcache* dari menu cPanel agar PHP langsung memuat kode terbaru.

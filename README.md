# Kasuari Care | Rumah Terapi Anak

Aplikasi operasional klinik berbasis Laravel 13, Inertia.js, Vue 3, Tailwind CSS, dan PostgreSQL. Spesifikasi bisnis awal tetap tersimpan di `../README.md`.

## Menjalankan di Laragon

Prasyarat: PHP 8.3+ dengan ekstensi `pdo_pgsql`, Composer, Node.js/npm, dan PostgreSQL. Buat database kosong bernama `rumah_terapi`; pastikan ekstensi PostgreSQL aktif pada PHP Laragon.

```powershell
cd C:\laragon\www\Klinik\clinic-app
Copy-Item .env.example .env
composer install
npm install
php artisan key:generate
```

Atur koneksi PostgreSQL di `.env`, kemudian siapkan schema dan akun demo:

```powershell
php artisan migrate --seed
npm run build
```

Jalankan aplikasi dan worker pada terminal terpisah:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work
```

Buka <http://127.0.0.1:8000>. Untuk hot reload, jalankan `npm run dev` di terminal lain.

### Akun demo lokal

Kata sandi seluruh akun demo adalah `password`.

| Role | Email |
| --- | --- |
| Owner | `owner@rumahterapi.test` |
| Front office | `admin@rumahterapi.test` |
| Terapis | `terapis@rumahterapi.test` |
| Keuangan | `kasir@rumahterapi.test` |

Ganti atau hapus akun demo sebelum digunakan di luar mesin lokal.

## Alur yang sudah berjalan

- Login staf dan route authorization memakai role Spatie.
- Formulir pendaftaran publik, antrean follow-up, konversi ke pasien dengan kode `KL-YYYY-NNN`, dan relasi multi-parent.
- Daftar/cari pasien dan formulir assessment; laporan assessment dapat dicetak sebagai PDF.
- Booking terapi dengan pemeriksaan overlap, ditambah PostgreSQL exclusion constraints untuk terapis dan ruangan.
- POS untuk assessment, sesi eceran, paket, dan produk; diskon nominal/persentase; pembayaran lunas; invoice PDF; aktivasi kuota paket; stok produk.
- Laporan sesi mengurangi kuota dalam transaksi dan menulis ledger satu kali.
- Dashboard operasional, UI responsif, dan job WhatsApp asynchronous untuk bukti pembayaran bila gateway dikonfigurasi.

## PostgreSQL

Migration memasang `btree_gist` dan exclusion constraints ketika driver PostgreSQL digunakan. Bila role database aplikasi tidak berwenang memasang extension, minta administrator menjalankan:

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;
```

Untuk mencoba UI tanpa PostgreSQL, `.env` lokal dapat menggunakan `DB_CONNECTION=sqlite`; constraint exclusion native hanya aktif pada PostgreSQL.

## WhatsApp Gateway

Isi `WHATSAPP_GATEWAY_URL` dan `WHATSAPP_GATEWAY_TOKEN`. Adapter mengirim JSON `{"to":"nomor","message":"teks"}` dengan Bearer token melalui Laravel Queue. Sesuaikan payload pada `app/Jobs/SendWhatsAppMessage.php` bila kontrak penyedia berbeda. Jangan commit kredensial.

## Validasi

```powershell
php artisan test
npm run build
```

Sebelum produksi masih diperlukan portal orang tua, pengingat WhatsApp terjadwal/outbox, unggah dan berbagi media dengan consent/policy, konfigurasi shift, export XLSX, komisi, serta audit browser yang lebih lengkap. Jangan masukkan data kesehatan riil sebelum otorisasi, backup, enkripsi, dan kebijakan privasi klinik ditinjau.

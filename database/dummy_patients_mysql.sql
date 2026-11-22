-- =========================================================================
-- Kasuari Care - Data Dummy Pasien, Paket Terapi, Assessment, & Sesi
-- Target Database: MySQL / MariaDB (phpMyAdmin)
-- Kompatibel: Import langsung / Salin ke tab SQL di phpMyAdmin
-- =========================================================================

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- --------------------------------------------------------
-- 0. Bersihkan Data Dummy Lama (Agar tidak ada data yatim/konflik)
-- --------------------------------------------------------
DELETE FROM `duration_package_ledger` WHERE `duration_package_entitlement_id` IN (101, 102, 103, 104, 105, 108);
DELETE FROM `duration_package_entitlements` WHERE `id` IN (101, 102, 103, 104, 105, 108);
DELETE FROM `patient_packages` WHERE `id` IN (101, 102, 103, 104, 105, 108) OR `patient_id` IN (101, 102, 103, 104, 105, 106, 107, 108);
DELETE FROM `session_reports` WHERE `appointment_id` IN (SELECT `id` FROM (SELECT `id` FROM `appointments` WHERE `patient_id` IN (101, 102, 103, 104, 105, 106, 107, 108)) AS tmp_app);
DELETE FROM `appointments` WHERE `patient_id` IN (101, 102, 103, 104, 105, 106, 107, 108);
DELETE FROM `assessment_recommendations` WHERE `assessment_id` IN (101, 102, 103, 104, 105, 107, 108);
DELETE FROM `assessments` WHERE `patient_id` IN (101, 102, 103, 104, 105, 106, 107, 108);
DELETE FROM `payments` WHERE `sale_id` IN (201, 202, 203, 204, 205, 207, 208, 301, 302, 303, 304, 305, 308);
DELETE FROM `sale_lines` WHERE `sale_id` IN (201, 202, 203, 204, 205, 207, 208, 301, 302, 303, 304, 305, 308);
DELETE FROM `sales` WHERE `id` IN (201, 202, 203, 204, 205, 207, 208, 301, 302, 303, 304, 305, 308) OR `patient_id` IN (101, 102, 103, 104, 105, 106, 107, 108);
DELETE FROM `patient_intake_submissions` WHERE `patient_id` IN (101, 102, 103, 104, 105, 106, 107, 108);
DELETE FROM `patient_therapists` WHERE `patient_id` IN (101, 102, 103, 104, 105, 106, 107, 108);
DELETE FROM `patient_parents` WHERE `patient_id` IN (101, 102, 103, 104, 105, 106, 107, 108);
DELETE FROM `parents` WHERE `id` IN (101, 102, 103, 104, 105, 106, 107, 108);
DELETE FROM `patients` WHERE `id` IN (101, 102, 103, 104, 105, 106, 107, 108);

-- --------------------------------------------------------
-- 1. Master Data Dasar (Roles, Users, Terapis, Ruangan, Paket)
-- --------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'owner', 'web', NOW(), NOW()),
(2, 'front_office', 'web', NOW(), NOW()),
(3, 'therapist', 'web', NOW(), NOW()),
(4, 'finance', 'web', NOW(), NOW()),
(5, 'parent', 'web', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `users` (`id`, `name`, `email`, `password`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Pimpinan Klinik', 'owner@rumahterapi.test', '$2y$12$nUubLHv8jB5Y1I1Vkq1zb.F0u/JlE1nIIqnyP2SGxuSiwa.G5nsIC', 1, NOW(), NOW()),
(2, 'Admin Front Office', 'admin@rumahterapi.test', '$2y$12$6sOgJUMOYIa.fQU37LDOU.HJyQID/vzFzPm6dcfgWlB093nKSajmO', 1, NOW(), NOW()),
(3, 'Kasir Klinik', 'kasir@rumahterapi.test', '$2y$12$HROeAjOxnut3iFPxj9Rwa.y7gxh1jG5K1/n8QkjSMuHqNgjOnblrO', 1, NOW(), NOW()),
(4, 'Terapis Demo', 'terapis@rumahterapi.test', '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', 1, NOW(), NOW()),
(10, 'Siti Rahmawati, S.Tr.Kes', 'terapis.tw1@rumahterapi.test', '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', 1, NOW(), NOW()),
(11, 'Budi Santoso, S.ST', 'terapis.to1@rumahterapi.test', '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', 1, NOW(), NOW()),
(12, 'Dewi Lestari, S.Ft', 'terapis.si@rumahterapi.test', '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', 1, NOW(), NOW()),
(13, 'Nurul Fadilah, S.Tr.Kes', 'terapis.tw2@rumahterapi.test', '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', 1, NOW(), NOW()),
(14, 'Ahmad Fauzi, S.Psi', 'terapis.aba@rumahterapi.test', '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', 1, NOW(), NOW()),
(15, 'Rahmat Hidayat, S.Tr.Kes', 'terapis.tw3@rumahterapi.test', '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', 1, NOW(), NOW()),
(16, 'Putri Amelia, S.ST', 'terapis.to2@rumahterapi.test', '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 1),
(2, 'App\\Models\\User', 2),
(4, 'App\\Models\\User', 3),
(3, 'App\\Models\\User', 4),
(3, 'App\\Models\\User', 10),
(3, 'App\\Models\\User', 11),
(3, 'App\\Models\\User', 12),
(3, 'App\\Models\\User', 13),
(3, 'App\\Models\\User', 14),
(3, 'App\\Models\\User', 15),
(3, 'App\\Models\\User', 16)
ON DUPLICATE KEY UPDATE `role_id`=VALUES(`role_id`);

INSERT INTO `therapist_profiles` (`id`, `user_id`, `license_number`, `specialty`, `created_at`, `updated_at`) VALUES
(1, 4, NULL, 'Terapi Anak', NOW(), NOW()),
(10, 10, 'STR-TW-10001', 'Terapi Wicara', NOW(), NOW()),
(11, 11, 'STR-OT-10002', 'Terapi Okupasi', NOW(), NOW()),
(12, 12, 'STR-FT-10003', 'Fisioterapi', NOW(), NOW()),
(13, 13, 'STR-TW-10004', 'Terapi Wicara', NOW(), NOW()),
(14, 14, 'STR-OT-10005', 'Terapi Okupasi', NOW(), NOW()),
(15, 15, 'STR-TW-10006', 'Terapi Wicara', NOW(), NOW()),
(16, 16, 'STR-OT-10007', 'Terapi Okupasi', NOW(), NOW())
ON DUPLICATE KEY UPDATE `license_number`=VALUES(`license_number`), `specialty`=VALUES(`specialty`);

INSERT INTO `therapy_types` (`id`, `code`, `name`, `default_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'TW', 'Terapi Wicara', 60, 1, NOW(), NOW()),
(2, 'OT', 'Terapi Okupasi', 60, 1, NOW(), NOW()),
(3, 'SI', 'Sensori Integrasi', 60, 0, NOW(), NOW()),
(4, 'FT', 'Fisioterapi', 60, 1, NOW(), NOW()),
(5, 'ABA', 'ABA / Perilaku', 60, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE `code`=VALUES(`code`), `name`=VALUES(`name`), `is_active`=VALUES(`is_active`);

INSERT INTO `rooms` (`id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'TW', 'Ruang Wicara', 1, NOW(), NOW()),
(2, 'OT', 'Ruang Okupasi', 1, NOW(), NOW()),
(3, 'SI', 'Ruang Sensori', 1, NOW(), NOW()),
(4, 'FT', 'Ruang Fisioterapi', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `code`=VALUES(`code`), `name`=VALUES(`name`);

INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES
(1, 'ASSESSMENT-AWAL', 'Assessment & Konsultasi', 'assessment', NULL, 300000, 1, NOW(), NOW(), 'Wajib dilakukan pada pertemuan awal sebelum sesi terapi.'),
(2, 'EVALUASI-24-SESI', 'Evaluasi', 'assessment', NULL, 250000, 1, NOW(), NOW(), 'Dilakukan setelah 24 sesi terapi.'),
(3, 'SESI-TW', 'Sesi 1 Jam - Terapi Wicara', 'session', 1, 160000, 1, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(4, 'SESI-OT', 'Sesi 1 Jam - Terapi Okupasi', 'session', 2, 160000, 1, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(5, 'SESI-SI', 'Sesi 1 Jam - Sensori Integrasi', 'session', 3, 160000, 0, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(6, 'SESI-FT', 'Sesi 1 Jam - Fisioterapi', 'session', 4, 160000, 1, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(7, 'SESI-ABA', 'Sesi 1 Jam - ABA / Perilaku', 'session', 5, 160000, 0, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(8, 'SESI-30-MENIT', 'Sesi Terapi 30 Menit', 'session', NULL, 110000, 1, NOW(), NOW(), 'Satu sesi terapi dengan durasi 30 menit.')
ON DUPLICATE KEY UPDATE `code`=VALUES(`code`), `name`=VALUES(`name`), `is_active`=VALUES(`is_active`);

INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES
(1, 'Paket 1 Jam - Paket 1 (1x Terapi)', 90, 160000, 1, NOW(), NOW(), 60, 1),
(2, 'Paket 1 Jam - Paket 2 (4x Terapi)', 90, 630000, 1, NOW(), NOW(), 60, 4),
(3, 'Paket 1 Jam - Paket 3 (8x Terapi)', 90, 1240000, 1, NOW(), NOW(), 60, 8),
(4, 'Paket 1 Jam - Paket 4 (12x Terapi)', 90, 1800000, 1, NOW(), NOW(), 60, 12),
(5, 'Paket 30 Menit - Paket 1 (1x Terapi)', 90, 110000, 1, NOW(), NOW(), 30, 1),
(6, 'Paket 30 Menit - Paket 2 (6x Terapi)', 90, 570000, 1, NOW(), NOW(), 30, 6),
(7, 'Paket 30 Menit - Paket 3 (12x Terapi)', 90, 1080000, 1, NOW(), NOW(), 30, 12),
(8, 'Paket 30 Menit - Paket 4 (16x Terapi)', 90, 1360000, 1, NOW(), NOW(), 30, 16)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `patient_code_counters` (`year`, `last_number`) VALUES (2026, 8)
ON DUPLICATE KEY UPDATE `last_number`=GREATEST(`last_number`, 8);

-- --------------------------------------------------------
-- 2. Data Pasien (8 Anak)
-- --------------------------------------------------------
INSERT INTO `patients` (`id`, `patient_code`, `name`, `birth_date`, `gender`, `address`, `primary_whatsapp`, `main_complaint`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
-- 5 Anak Pasien dengan Paket Terapi Berbeda-beda
(101, 'KL-2026-001', 'Kenzo Alvaro', '2022-05-14', 'male', 'Jl. Melati No. 12, Kebayoran Baru, Jakarta Selatan', '081234567001', 'Speech delay, artikulasi kata belum jelas dan kosakata terbatas.', 1, NULL, NOW() - INTERVAL 28 DAY, NOW()),
(102, 'KL-2026-002', 'Aisha Humaira', '2023-08-20', 'female', 'Jl. Cempaka Putih Timur No. 45, Jakarta Pusat', '081234567002', 'Kesulitan fokus, mudah tantrum dan koordinasi motorik halus belum optimal.', 1, NULL, NOW() - INTERVAL 37 DAY, NOW()),
(103, 'KL-2026-003', 'Rayyan Arkanza', '2021-03-10', 'male', 'Komp. Bintaro Permai Blok C3 No. 8, Tangerang Selatan', '081234567003', 'Hipersensitif suara dan sentuhan, gangguan regulasi sensori integrasi.', 1, NULL, NOW() - INTERVAL 52 DAY, NOW()),
(104, 'KL-2026-004', 'Mikayla Zahra', '2024-02-15', 'female', 'Jl. Tebet Barat Dalam No. 19, Jakarta Selatan', '081234567004', 'Kontak mata minim, belum konsisten menoleh ketika namanya dipanggil.', 1, NULL, NOW() - INTERVAL 32 DAY, NOW()),
(105, 'KL-2026-005', 'Rafka Daniswara', '2020-09-05', 'male', 'Jl. Radio Dalam Raya No. 88, Gandaria, Jakarta Selatan', '081234567005', 'Perilaku repetitif, interaksi sosial terbatas dan hambatan transisi aktivitas.', 1, NULL, NOW() - INTERVAL 67 DAY, NOW()),
-- 2 Anak Pasien yang Belum Assessment
(106, 'KL-2026-006', 'Arka Bimasena', '2023-04-12', 'male', 'Jl. Kemang Timur No. 27, Jakarta Selatan', '081234567006', 'Speech delay, baru menguasai beberapa kata dasar, pemahaman perintah sederhana lambat.', 1, NULL, NOW() - INTERVAL 3 DAY, NOW()),
(107, 'KL-2026-007', 'Naura Salsabila', '2022-11-28', 'female', 'Jl. Margonda Raya No. 102, Depok', '081234567007', 'Sering tantrum dan frustrasi ketika tidak dimengerti keinginannya, kesulitan sosialisasi di PAUD.', 1, NULL, NOW() - INTERVAL 3 DAY, NOW()),
-- 1 Anak Pasien yang Sudah 12x Terapi & Paket Habis 0
(108, 'KL-2026-008', 'Gibran Al-Fatih', '2021-07-18', 'male', 'Jl. Fatmawati Raya No. 55, Cilandak, Jakarta Selatan', '081234567008', 'Keterlambatan bicara ekspresif dan reseptif, kesulitan artikulasi vokal/konsonan.', 1, NULL, NOW() - INTERVAL 104 DAY, NOW())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- --------------------------------------------------------
-- 3. Data Orang Tua (Parents)
-- --------------------------------------------------------
INSERT INTO `parents` (`id`, `name`, `phone`, `whatsapp`, `email`, `address`, `created_at`, `updated_at`) VALUES
(101, 'Hendra Alvaro', '081234567001', '081234567001', 'hendra-alvaro@gmail.com', 'Jl. Melati No. 12, Kebayoran Baru, Jakarta Selatan', NOW(), NOW()),
(102, 'Fajar Pratama', '081234567002', '081234567002', 'fajar-pratama@gmail.com', 'Jl. Cempaka Putih Timur No. 45, Jakarta Pusat', NOW(), NOW()),
(103, 'Dwi Kurniawan', '081234567003', '081234567003', 'dwi-kurniawan@gmail.com', 'Komp. Bintaro Permai Blok C3 No. 8, Tangerang Selatan', NOW(), NOW()),
(104, 'Rizki Ramadhani', '081234567004', '081234567004', 'rizki-ramadhani@gmail.com', 'Jl. Tebet Barat Dalam No. 19, Jakarta Selatan', NOW(), NOW()),
(105, 'Agus Setiawan', '081234567005', '081234567005', 'agus-setiawan@gmail.com', 'Jl. Radio Dalam Raya No. 88, Gandaria, Jakarta Selatan', NOW(), NOW()),
(106, 'Bima Sakti', '081234567006', '081234567006', 'bima-sakti@gmail.com', 'Jl. Kemang Timur No. 27, Jakarta Selatan', NOW(), NOW()),
(107, 'Aditya Pratama', '081234567007', '081234567007', 'aditya-pratama@gmail.com', 'Jl. Margonda Raya No. 102, Depok', NOW(), NOW()),
(108, 'Wahyu Hidayat', '081234567008', '081234567008', 'wahyu-hidayat@gmail.com', 'Jl. Fatmawati Raya No. 55, Cilandak, Jakarta Selatan', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `patient_parents` (`patient_id`, `parent_id`, `relation`, `is_primary_contact`, `can_access_portal`) VALUES
(101, 101, 'father', 1, 1),
(102, 102, 'father', 1, 1),
(103, 103, 'father', 1, 1),
(104, 104, 'mother', 1, 1),
(105, 105, 'father', 1, 1),
(106, 106, 'father', 1, 1),
(107, 107, 'father', 1, 1),
(108, 108, 'father', 1, 1)
ON DUPLICATE KEY UPDATE `is_primary_contact`=VALUES(`is_primary_contact`);

-- --------------------------------------------------------
-- 4. Penugasan Terapis Pasien (patient_therapists)
-- --------------------------------------------------------
INSERT INTO `patient_therapists` (`patient_id`, `therapy_type_id`, `therapist_profile_id`, `created_at`, `updated_at`) VALUES
(101, 1, 10, NOW(), NOW()),
(102, 2, 11, NOW(), NOW()),
(103, 3, 12, NOW(), NOW()),
(104, 1, 13, NOW(), NOW()),
(105, 5, 14, NOW(), NOW()),
(108, 1, 15, NOW(), NOW())
ON DUPLICATE KEY UPDATE `therapy_type_id`=VALUES(`therapy_type_id`);

-- --------------------------------------------------------
-- 5. Data Intake Registrasi Online
-- --------------------------------------------------------
INSERT INTO `patient_intake_submissions` (`id`, `source`, `response_id`, `submitted_at`, `child_data`, `father_data`, `mother_data`, `family_context`, `development_history`, `health_history`, `verification_status`, `patient_id`, `created_at`, `updated_at`) VALUES
(106, 'web_registration', 'RESP-ARKA-001', NOW() - INTERVAL 3 DAY, '{"name":"Arka Bimasena","birth_date":"2023-04-12","gender":"male","complaint":"Speech delay, kosakata terbatas"}', '{"name":"Bima Sakti","phone":"081234567006"}', '{"name":"Ibu Bima Sakti","phone":"081234567006"}', '{"address":"Jl. Kemang Timur No. 27, Jakarta Selatan"}', '{"milestones":"Motorik baik, bahasa perlu stimulasi"}', '{"allergies":"Tidak ada"}', 'pending', 106, NOW(), NOW()),
(107, 'web_registration', 'RESP-NAURA-002', NOW() - INTERVAL 3 DAY, '{"name":"Naura Salsabila","birth_date":"2022-11-28","gender":"female","complaint":"Tantrum berat di PAUD"}', '{"name":"Aditya Pratama","phone":"081234567007"}', '{"name":"Ibu Aditya Pratama","phone":"081234567007"}', '{"address":"Jl. Margonda Raya No. 102, Depok"}', '{"milestones":"Motorik kasar aktif, regulasi emosi butuh bantuan"}', '{"allergies":"Tidak ada"}', 'verified', 107, NOW(), NOW())
ON DUPLICATE KEY UPDATE `verification_status`=VALUES(`verification_status`);

-- --------------------------------------------------------
-- 6. Transaksi Penjualan & Pembayaran Assessment
-- --------------------------------------------------------
INSERT INTO `sales` (`id`, `invoice_number`, `patient_id`, `cashier_id`, `status`, `subtotal`, `discount_total`, `grand_total`, `paid_at`, `created_at`, `updated_at`) VALUES
(201, 'INV-20261016-KENZO', 101, 3, 'paid', 300000, 0, 300000, NOW() - INTERVAL 21 DAY, NOW() - INTERVAL 21 DAY, NOW()),
(202, 'INV-20261007-AISHA', 102, 3, 'paid', 300000, 0, 300000, NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 30 DAY, NOW()),
(203, 'INV-20260922-RAYYAN', 103, 3, 'paid', 300000, 0, 300000, NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 45 DAY, NOW()),
(204, 'INV-20261012-MIKAYL', 104, 3, 'paid', 300000, 0, 300000, NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 25 DAY, NOW()),
(205, 'INV-20260907-RAFKA', 105, 3, 'paid', 300000, 0, 300000, NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 60 DAY, NOW()),
(207, 'INV-20261106-NAURA', 107, 3, 'pending', 300000, 0, 300000, NULL, NOW() - INTERVAL 1 DAY, NOW()),
(208, 'INV-20260808-GIBRAN', 108, 3, 'paid', 300000, 0, 300000, NOW() - INTERVAL 90 DAY, NOW() - INTERVAL 90 DAY, NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `sale_lines` (`sale_id`, `line_type`, `service_catalog_id`, `description`, `quantity`, `unit_price`, `line_total`, `created_at`, `updated_at`) VALUES
(201, 'assessment', 1, 'Assessment & Konsultasi', 1, 300000, 300000, NOW(), NOW()),
(202, 'assessment', 1, 'Assessment & Konsultasi', 1, 300000, 300000, NOW(), NOW()),
(203, 'assessment', 1, 'Assessment & Konsultasi', 1, 300000, 300000, NOW(), NOW()),
(204, 'assessment', 1, 'Assessment & Konsultasi', 1, 300000, 300000, NOW(), NOW()),
(205, 'assessment', 1, 'Assessment & Konsultasi', 1, 300000, 300000, NOW(), NOW()),
(207, 'assessment', 1, 'Assessment & Konsultasi', 1, 300000, 300000, NOW(), NOW()),
(208, 'assessment', 1, 'Assessment & Konsultasi', 1, 300000, 300000, NOW(), NOW())
ON DUPLICATE KEY UPDATE `line_total`=VALUES(`line_total`);

INSERT INTO `payments` (`sale_id`, `method`, `amount`, `reference_number`, `bank_name`, `received_by`, `received_at`, `created_at`, `updated_at`) VALUES
(201, 'bank_transfer', 300000, 'TRF-BCA-201', 'BCA', 3, NOW() - INTERVAL 21 DAY, NOW(), NOW()),
(202, 'bank_transfer', 300000, 'TRF-BCA-202', 'BCA', 3, NOW() - INTERVAL 30 DAY, NOW(), NOW()),
(203, 'bank_transfer', 300000, 'TRF-BCA-203', 'BCA', 3, NOW() - INTERVAL 45 DAY, NOW(), NOW()),
(204, 'bank_transfer', 300000, 'TRF-BCA-204', 'BCA', 3, NOW() - INTERVAL 25 DAY, NOW(), NOW()),
(205, 'bank_transfer', 300000, 'TRF-BCA-205', 'BCA', 3, NOW() - INTERVAL 60 DAY, NOW(), NOW()),
(208, 'bank_transfer', 300000, 'TRF-MANDIRI-208', 'Mandiri', 3, NOW() - INTERVAL 90 DAY, NOW(), NOW())
ON DUPLICATE KEY UPDATE `amount`=VALUES(`amount`);

-- --------------------------------------------------------
-- 7. Assessments & Rekomendasi Terapi
-- --------------------------------------------------------
INSERT INTO `assessments` (`id`, `patient_id`, `sale_id`, `therapist_profile_id`, `status`, `scheduled_at`, `completed_at`, `chief_complaint`, `summary`, `created_by`, `created_at`, `updated_at`) VALUES
(101, 101, 201, 10, 'completed', NOW() - INTERVAL 21 DAY, NOW() - INTERVAL 21 DAY + INTERVAL 1 HOUR, 'Speech delay', 'Pasien Kenzo menunjukkan potensi artikulasi baik, disarankan Terapi Wicara intensif.', NULL, NOW(), NOW()),
(102, 102, 202, 11, 'completed', NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 30 DAY + INTERVAL 1 HOUR, 'Sensori & tantrum', 'Pasien Aisha memerlukan stimulasi okupasi untuk fokus dan regulasi sensorik.', NULL, NOW(), NOW()),
(103, 103, 203, 12, 'completed', NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 45 DAY + INTERVAL 1 HOUR, 'Hipersensitif suara', 'Pasien Rayyan menunjukkan respon positif terhadap modulasi Sensori Integrasi.', NULL, NOW(), NOW()),
(104, 104, 204, 13, 'completed', NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 25 DAY + INTERVAL 1 HOUR, 'Kontak mata minim', 'Pasien Mikayla dianjurkan terapi wicara durasi 30 menit dua kali seminggu.', NULL, NOW(), NOW()),
(105, 105, 205, 14, 'completed', NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 60 DAY + INTERVAL 1 HOUR, 'Perilaku repetitif', 'Pasien Rafka disarankan terapi perilaku (ABA) dengan penguatan komunikasi fungsional.', NULL, NOW(), NOW()),
-- Naura Salsabila: Terjadwal tapi BELUM DILAKSANAKAN
(107, 107, 207, 16, 'scheduled', NOW() + INTERVAL 4 DAY, NULL, 'Tantrum dan sulit sosialisasi di PAUD', NULL, NULL, NOW(), NOW()),
(108, 108, 208, 15, 'completed', NOW() - INTERVAL 90 DAY, NOW() - INTERVAL 90 DAY + INTERVAL 1 HOUR, 'Speech delay ekspresif', 'Pasien Gibran memerlukan 12 sesi terapi wicara terstruktur.', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `assessment_recommendations` (`assessment_id`, `therapy_type_id`, `sessions_per_week`, `duration_minutes`, `notes`, `created_at`, `updated_at`) VALUES
(101, 1, 2, 60, 'Fokus pada stimulasi artikulasi konsonan', NOW(), NOW()),
(102, 2, 2, 60, 'Fokus pada motorik halus dan integrasi taktil', NOW(), NOW()),
(103, 3, 2, 60, 'Fokus pada desensitisasi suara dan proprioseptif', NOW(), NOW()),
(104, 1, 2, 30, 'Fokus pada respon nama dan kontak mata', NOW(), NOW()),
(105, 5, 3, 30, 'Fokus pada kepatuhan instruksi dan transisi', NOW(), NOW()),
(108, 1, 2, 60, 'Program wicara intensif 12 sesi', NOW(), NOW())
ON DUPLICATE KEY UPDATE `notes`=VALUES(`notes`);

-- --------------------------------------------------------
-- 8. Penjualan Paket Terapi
-- --------------------------------------------------------
INSERT INTO `sales` (`id`, `invoice_number`, `patient_id`, `cashier_id`, `therapist_profile_id`, `status`, `subtotal`, `discount_total`, `grand_total`, `paid_at`, `created_at`, `updated_at`) VALUES
(301, 'INV-PKT-KENZO', 101, 3, 10, 'paid', 630000, 0, 630000, NOW() - INTERVAL 19 DAY, NOW() - INTERVAL 19 DAY, NOW()),
(302, 'INV-PKT-AISHA', 102, 3, 11, 'paid', 1240000, 0, 1240000, NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 28 DAY, NOW()),
(303, 'INV-PKT-RAYYAN', 103, 3, 12, 'paid', 1800000, 0, 1800000, NOW() - INTERVAL 43 DAY, NOW() - INTERVAL 43 DAY, NOW()),
(304, 'INV-PKT-MIKAYLA', 104, 3, 13, 'paid', 570000, 0, 570000, NOW() - INTERVAL 23 DAY, NOW() - INTERVAL 23 DAY, NOW()),
(305, 'INV-PKT-RAFKA', 105, 3, 14, 'paid', 1360000, 0, 1360000, NOW() - INTERVAL 58 DAY, NOW() - INTERVAL 58 DAY, NOW()),
(308, 'INV-PKT-GIBRAN', 108, 3, 15, 'paid', 1800000, 0, 1800000, NOW() - INTERVAL 88 DAY, NOW() - INTERVAL 88 DAY, NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `sale_lines` (`sale_id`, `line_type`, `package_catalog_id`, `description`, `quantity`, `unit_price`, `line_total`, `created_at`, `updated_at`) VALUES
(301, 'package', 2, 'Paket 1 Jam - Paket 2 (4x Terapi)', 1, 630000, 630000, NOW(), NOW()),
(302, 'package', 3, 'Paket 1 Jam - Paket 3 (8x Terapi)', 1, 1240000, 1240000, NOW(), NOW()),
(303, 'package', 4, 'Paket 1 Jam - Paket 4 (12x Terapi)', 1, 1800000, 1800000, NOW(), NOW()),
(304, 'package', 6, 'Paket 30 Menit - Paket 2 (6x Terapi)', 1, 570000, 570000, NOW(), NOW()),
(305, 'package', 8, 'Paket 30 Menit - Paket 4 (16x Terapi)', 1, 1360000, 1360000, NOW(), NOW()),
(308, 'package', 4, 'Paket 1 Jam - Paket 4 (12x Terapi)', 1, 1800000, 1800000, NOW(), NOW())
ON DUPLICATE KEY UPDATE `line_total`=VALUES(`line_total`);

INSERT INTO `payments` (`sale_id`, `method`, `amount`, `reference_number`, `received_by`, `received_at`, `created_at`, `updated_at`) VALUES
(301, 'qris', 630000, 'QRIS-301', 3, NOW() - INTERVAL 19 DAY, NOW(), NOW()),
(302, 'qris', 1240000, 'QRIS-302', 3, NOW() - INTERVAL 28 DAY, NOW(), NOW()),
(303, 'qris', 1800000, 'QRIS-303', 3, NOW() - INTERVAL 43 DAY, NOW(), NOW()),
(304, 'qris', 570000, 'QRIS-304', 3, NOW() - INTERVAL 23 DAY, NOW(), NOW()),
(305, 'qris', 1360000, 'QRIS-305', 3, NOW() - INTERVAL 58 DAY, NOW(), NOW()),
(308, 'bank_transfer', 1800000, 'TRF-308', 3, NOW() - INTERVAL 88 DAY, NOW(), NOW())
ON DUPLICATE KEY UPDATE `amount`=VALUES(`amount`);

-- --------------------------------------------------------
-- 9. Paket Pasien (patient_packages) & Kuota (duration_package_entitlements)
-- --------------------------------------------------------
INSERT INTO `patient_packages` (`id`, `patient_id`, `package_catalog_id`, `sale_id`, `status`, `starts_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(101, 101, 2, 301, 'active', NOW() - INTERVAL 19 DAY, NOW() + INTERVAL 71 DAY, NOW(), NOW()),
(102, 102, 3, 302, 'active', NOW() - INTERVAL 28 DAY, NOW() + INTERVAL 62 DAY, NOW(), NOW()),
(103, 103, 4, 303, 'active', NOW() - INTERVAL 43 DAY, NOW() + INTERVAL 47 DAY, NOW(), NOW()),
(104, 104, 6, 304, 'active', NOW() - INTERVAL 23 DAY, NOW() + INTERVAL 67 DAY, NOW(), NOW()),
(105, 105, 8, 305, 'active', NOW() - INTERVAL 58 DAY, NOW() + INTERVAL 32 DAY, NOW(), NOW()),
-- Gibran: Status Completed karena kuota 12 sesi sudah habis 0
(108, 108, 4, 308, 'completed', NOW() - INTERVAL 88 DAY, NOW() + INTERVAL 2 DAY, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `duration_package_entitlements` (`id`, `patient_package_id`, `duration_minutes`, `granted_sessions`, `remaining_sessions`) VALUES
(101, 101, 60, 4, 3),   -- Kenzo: 4 sesi, sisa 3
(102, 102, 60, 8, 6),   -- Aisha: 8 sesi, sisa 6
(103, 103, 60, 12, 8),  -- Rayyan: 12 sesi, sisa 8
(104, 104, 30, 6, 4),   -- Mikayla: 6 sesi, sisa 4
(105, 105, 30, 16, 11), -- Rafka: 16 sesi, sisa 11
(108, 108, 60, 12, 0)   -- Gibran: 12 sesi, HABIS 0
ON DUPLICATE KEY UPDATE `remaining_sessions`=VALUES(`remaining_sessions`);

INSERT INTO `duration_package_ledger` (`duration_package_entitlement_id`, `appointment_id`, `quantity_delta`, `reason`, `created_by`, `created_at`) VALUES
(101, NULL, 4, 'package_purchase', NULL, NOW() - INTERVAL 19 DAY),
(102, NULL, 8, 'package_purchase', NULL, NOW() - INTERVAL 28 DAY),
(103, NULL, 12, 'package_purchase', NULL, NOW() - INTERVAL 43 DAY),
(104, NULL, 6, 'package_purchase', NULL, NOW() - INTERVAL 23 DAY),
(105, NULL, 16, 'package_purchase', NULL, NOW() - INTERVAL 58 DAY),
(108, NULL, 12, 'package_purchase', NULL, NOW() - INTERVAL 88 DAY);

-- --------------------------------------------------------
-- 10. Sesi Terapi (Appointments & Session Reports status='submitted')
-- --------------------------------------------------------
-- Sesi Kenzo (1 completed, 1 scheduled)
INSERT INTO `appointments` (`id`, `patient_id`, `therapy_type_id`, `therapist_profile_id`, `room_id`, `starts_at`, `ends_at`, `status`, `booking_source`, `attendance_status`, `created_by`, `created_at`, `updated_at`) VALUES
(401, 101, 1, 10, 1, NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 14 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(402, 101, 1, 10, 1, NOW() + INTERVAL 2 DAY + INTERVAL 9 HOUR, NOW() + INTERVAL 2 DAY + INTERVAL 10 HOUR, 'scheduled', 'admin', NULL, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `session_reports` (`appointment_id`, `therapist_user_id`, `status`, `activities`, `parent_notes`, `homework`, `submitted_at`, `created_at`, `updated_at`) VALUES
(401, 10, 'submitted', 'Latihan sesi ke-1: Stimulasi fokus dan artikulasi huruf vokal.', 'Anak kooperatif dan responsif.', 'Latihan bicara 15 menit setiap hari.', NOW() - INTERVAL 14 DAY + INTERVAL 1 HOUR, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `duration_package_ledger` (`duration_package_entitlement_id`, `appointment_id`, `quantity_delta`, `reason`, `created_by`, `created_at`) VALUES
(101, 401, -1, 'session_completed', NULL, NOW() - INTERVAL 14 DAY);

-- Sesi Aisha (2 completed, 1 scheduled)
INSERT INTO `appointments` (`id`, `patient_id`, `therapy_type_id`, `therapist_profile_id`, `room_id`, `starts_at`, `ends_at`, `status`, `booking_source`, `attendance_status`, `created_by`, `created_at`, `updated_at`) VALUES
(403, 102, 2, 11, 2, NOW() - INTERVAL 21 DAY, NOW() - INTERVAL 21 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(404, 102, 2, 11, 2, NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 14 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(405, 102, 2, 11, 2, NOW() + INTERVAL 2 DAY + INTERVAL 10 HOUR, NOW() + INTERVAL 2 DAY + INTERVAL 11 HOUR, 'scheduled', 'admin', NULL, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `session_reports` (`appointment_id`, `therapist_user_id`, `status`, `activities`, `parent_notes`, `homework`, `submitted_at`, `created_at`, `updated_at`) VALUES
(403, 11, 'submitted', 'Latihan okupasi sesi 1: Meronce manik-manik besar dan koordinasi jari.', 'Fokus bertahan 15 menit.', 'Bermain puzzle di rumah.', NOW() - INTERVAL 21 DAY + INTERVAL 1 HOUR, NOW(), NOW()),
(404, 11, 'submitted', 'Latihan okupasi sesi 2: Menjepit spons dan meremas playdough.', 'Kontrol kekuatan tangan meningkat.', 'Latihan memasang kancing baju.', NOW() - INTERVAL 14 DAY + INTERVAL 1 HOUR, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `duration_package_ledger` (`duration_package_entitlement_id`, `appointment_id`, `quantity_delta`, `reason`, `created_by`, `created_at`) VALUES
(102, 403, -1, 'session_completed', NULL, NOW() - INTERVAL 21 DAY),
(102, 404, -1, 'session_completed', NULL, NOW() - INTERVAL 14 DAY);

-- Sesi Rayyan (4 completed, 1 scheduled)
INSERT INTO `appointments` (`id`, `patient_id`, `therapy_type_id`, `therapist_profile_id`, `room_id`, `starts_at`, `ends_at`, `status`, `booking_source`, `attendance_status`, `created_by`, `created_at`, `updated_at`) VALUES
(406, 103, 3, 12, 3, NOW() - INTERVAL 35 DAY, NOW() - INTERVAL 35 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(407, 103, 3, 12, 3, NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 28 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(408, 103, 3, 12, 3, NOW() - INTERVAL 21 DAY, NOW() - INTERVAL 21 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(409, 103, 3, 12, 3, NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 14 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(410, 103, 3, 12, 3, NOW() + INTERVAL 3 DAY + INTERVAL 11 HOUR, NOW() + INTERVAL 3 DAY + INTERVAL 12 HOUR, 'scheduled', 'admin', NULL, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `session_reports` (`appointment_id`, `therapist_user_id`, `status`, `activities`, `parent_notes`, `homework`, `submitted_at`, `created_at`, `updated_at`) VALUES
(406, 12, 'submitted', 'Sensori integrasi sesi 1: Ayunan linear dan bola terapi.', 'Toleransi vestibular baik.', 'Aktivitas jalan tanpa alas kaki di rumput.', NOW() - INTERVAL 35 DAY, NOW(), NOW()),
(407, 12, 'submitted', 'Sensori integrasi sesi 2: Berenang di kolam bola dan jembatan titian.', 'Keseimbangan meningkat.', 'Lompat trampolin mini di rumah.', NOW() - INTERVAL 28 DAY, NOW(), NOW()),
(408, 12, 'submitted', 'Sensori integrasi sesi 3: Tekstur shaving cream dan slime.', 'Toleransi taktil meningkat, tidak menolak.', 'Bermain pasir kinetik.', NOW() - INTERVAL 21 DAY, NOW(), NOW()),
(409, 12, 'submitted', 'Sensori integrasi sesi 4: Manuver rintangan terowongan dan panjat tangga tali.', 'Regulasi motorik stabil.', 'Latihan merangkak dengan beban ringan.', NOW() - INTERVAL 14 DAY, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `duration_package_ledger` (`duration_package_entitlement_id`, `appointment_id`, `quantity_delta`, `reason`, `created_by`, `created_at`) VALUES
(103, 406, -1, 'session_completed', NULL, NOW() - INTERVAL 35 DAY),
(103, 407, -1, 'session_completed', NULL, NOW() - INTERVAL 28 DAY),
(103, 408, -1, 'session_completed', NULL, NOW() - INTERVAL 21 DAY),
(103, 409, -1, 'session_completed', NULL, NOW() - INTERVAL 14 DAY);

-- Sesi Mikayla (2 completed, 1 scheduled)
INSERT INTO `appointments` (`id`, `patient_id`, `therapy_type_id`, `therapist_profile_id`, `room_id`, `starts_at`, `ends_at`, `status`, `booking_source`, `attendance_status`, `created_by`, `created_at`, `updated_at`) VALUES
(411, 104, 1, 13, 1, NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 18 DAY + INTERVAL 30 MINUTE, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(412, 104, 1, 13, 1, NOW() - INTERVAL 11 DAY, NOW() - INTERVAL 11 DAY + INTERVAL 30 MINUTE, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(413, 104, 1, 13, 1, NOW() + INTERVAL 3 DAY + INTERVAL 9 HOUR, NOW() + INTERVAL 3 DAY + INTERVAL 9 HOUR + INTERVAL 30 MINUTE, 'scheduled', 'admin', NULL, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `session_reports` (`appointment_id`, `therapist_user_id`, `status`, `activities`, `parent_notes`, `homework`, `submitted_at`, `created_at`, `updated_at`) VALUES
(411, 13, 'submitted', 'Wicara 30m sesi 1: Stimulasi kontak mata dengan gelembung sabun (bubble).', 'Kontak mata muncul 3-5 detik.', 'Main cilukba di rumah.', NOW() - INTERVAL 18 DAY, NOW(), NOW()),
(412, 13, 'submitted', 'Wicara 30m sesi 2: Meniru suara hewan (kucing, sapi, anjing).', 'Mulai mencoba vokalisasi ma.. pa..', 'Membacakan buku bergambar bersuara.', NOW() - INTERVAL 11 DAY, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `duration_package_ledger` (`duration_package_entitlement_id`, `appointment_id`, `quantity_delta`, `reason`, `created_by`, `created_at`) VALUES
(104, 411, -1, 'session_completed', NULL, NOW() - INTERVAL 18 DAY),
(104, 412, -1, 'session_completed', NULL, NOW() - INTERVAL 11 DAY);

-- Sesi Rafka (5 completed, 1 scheduled)
INSERT INTO `appointments` (`id`, `patient_id`, `therapy_type_id`, `therapist_profile_id`, `room_id`, `starts_at`, `ends_at`, `status`, `booking_source`, `attendance_status`, `created_by`, `created_at`, `updated_at`) VALUES
(414, 105, 5, 14, 4, NOW() - INTERVAL 42 DAY, NOW() - INTERVAL 42 DAY + INTERVAL 30 MINUTE, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(415, 105, 5, 14, 4, NOW() - INTERVAL 35 DAY, NOW() - INTERVAL 35 DAY + INTERVAL 30 MINUTE, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(416, 105, 5, 14, 4, NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 28 DAY + INTERVAL 30 MINUTE, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(417, 105, 5, 14, 4, NOW() - INTERVAL 21 DAY, NOW() - INTERVAL 21 DAY + INTERVAL 30 MINUTE, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(418, 105, 5, 14, 4, NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 14 DAY + INTERVAL 30 MINUTE, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(419, 105, 5, 14, 4, NOW() + INTERVAL 4 DAY + INTERVAL 13 HOUR, NOW() + INTERVAL 4 DAY + INTERVAL 13 HOUR + INTERVAL 30 MINUTE, 'scheduled', 'admin', NULL, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `session_reports` (`appointment_id`, `therapist_user_id`, `status`, `activities`, `parent_notes`, `homework`, `submitted_at`, `created_at`, `updated_at`) VALUES
(414, 14, 'submitted', 'ABA sesi 1: Latihan duduk tenang (sitting tolerance) dan eye-contact.', 'Toleransi duduk 10 menit.', 'Berikan reward saat anak patuh.', NOW() - INTERVAL 42 DAY, NOW(), NOW()),
(415, 14, 'submitted', 'ABA sesi 2: Kepatuhan instruksi 1 langkah (ambil, taruh).', 'Kepatuhan 60%.', 'Latih instruksi sederhana di meja makan.', NOW() - INTERVAL 35 DAY, NOW(), NOW()),
(416, 14, 'submitted', 'ABA sesi 3: Meniru gerakan tubuh (tepuk tangan, angkat tangan).', 'Respon imitasi meningkat.', 'Main tebak gerakan bersama saudara.', NOW() - INTERVAL 28 DAY, NOW(), NOW()),
(417, 14, 'submitted', 'ABA sesi 4: Menunjuk benda yang diinginkan (pointing gesture).', 'Pengurangan tantrum saat meminta sesuatu.', 'Jangan berikan sebelum anak menunjuk.', NOW() - INTERVAL 21 DAY, NOW(), NOW()),
(418, 14, 'submitted', 'ABA sesi 5: Transisi aktivitas menggunakan visual schedule board.', 'Transisi mainan berjalan lancar tanpa histeris.', 'Gunakan kartu bergambar saat transisi di rumah.', NOW() - INTERVAL 14 DAY, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `duration_package_ledger` (`duration_package_entitlement_id`, `appointment_id`, `quantity_delta`, `reason`, `created_by`, `created_at`) VALUES
(105, 414, -1, 'session_completed', NULL, NOW() - INTERVAL 42 DAY),
(105, 415, -1, 'session_completed', NULL, NOW() - INTERVAL 35 DAY),
(105, 416, -1, 'session_completed', NULL, NOW() - INTERVAL 28 DAY),
(105, 417, -1, 'session_completed', NULL, NOW() - INTERVAL 21 DAY),
(105, 418, -1, 'session_completed', NULL, NOW() - INTERVAL 14 DAY);

-- Sesi Gibran Al-Fatih (TEPAT 12 SESI LENGKAP & SISA 0)
INSERT INTO `appointments` (`id`, `patient_id`, `therapy_type_id`, `therapist_profile_id`, `room_id`, `starts_at`, `ends_at`, `status`, `booking_source`, `attendance_status`, `created_by`, `created_at`, `updated_at`) VALUES
(421, 108, 1, 15, 1, NOW() - INTERVAL 80 DAY, NOW() - INTERVAL 80 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(422, 108, 1, 15, 1, NOW() - INTERVAL 73 DAY, NOW() - INTERVAL 73 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(423, 108, 1, 15, 1, NOW() - INTERVAL 66 DAY, NOW() - INTERVAL 66 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(424, 108, 1, 15, 1, NOW() - INTERVAL 59 DAY, NOW() - INTERVAL 59 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(425, 108, 1, 15, 1, NOW() - INTERVAL 52 DAY, NOW() - INTERVAL 52 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(426, 108, 1, 15, 1, NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 45 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(427, 108, 1, 15, 1, NOW() - INTERVAL 38 DAY, NOW() - INTERVAL 38 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(428, 108, 1, 15, 1, NOW() - INTERVAL 31 DAY, NOW() - INTERVAL 31 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(429, 108, 1, 15, 1, NOW() - INTERVAL 24 DAY, NOW() - INTERVAL 24 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(430, 108, 1, 15, 1, NOW() - INTERVAL 17 DAY, NOW() - INTERVAL 17 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(431, 108, 1, 15, 1, NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW()),
(432, 108, 1, 15, 1, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY + INTERVAL 1 HOUR, 'completed', 'admin', 'present', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `session_reports` (`appointment_id`, `therapist_user_id`, `status`, `activities`, `parent_notes`, `homework`, `submitted_at`, `created_at`, `updated_at`) VALUES
(421, 15, 'submitted', 'Sesi 1 (Program 12x): Pengenalan media terapi dan evaluasi baseline oromotor.', 'Anak sedikit cemas di awal, adaptasi membaik.', 'Latihan tiup peluit di rumah.', NOW() - INTERVAL 80 DAY, NOW(), NOW()),
(422, 15, 'submitted', 'Sesi 2 (Program 12x): Latihan kekuatan bibir dan lidah (lip closure).', 'Mampu menahan sedotan 5 detik.', 'Minum menggunakan sedotan ulir.', NOW() - INTERVAL 73 DAY, NOW(), NOW()),
(423, 15, 'submitted', 'Sesi 3 (Program 12x): Produksi suara konsonan bilabial /p/, /b/, /m/.', 'Pelafalan kata papa, mama sudah jelas.', 'Ulangi kata papa & mama setiap bertemu.', NOW() - INTERVAL 66 DAY, NOW(), NOW()),
(424, 15, 'submitted', 'Sesi 4 (Program 12x): Fonasi vokal panjang /a/, /i/, /u/.', 'Kontrol nafas saat bersuara membaik.', 'Latihan tiup lilin dari jarak bertahap.', NOW() - INTERVAL 59 DAY, NOW(), NOW()),
(425, 15, 'submitted', 'Sesi 5 (Program 12x): Diskriminasi auditori kata bersuara mirip.', 'Respon mendengar meningkat.', 'Tebak bunyi benda di rumah.', NOW() - INTERVAL 52 DAY, NOW(), NOW()),
(426, 15, 'submitted', 'Sesi 6 (Program 12x): Evaluasi tengah paket (6 sesi). Kosakata bertambah 15 kata baru.', 'Kemajuan sangat signifikan.', 'Lanjutkan membaca kartu bergambar.', NOW() - INTERVAL 45 DAY, NOW(), NOW()),
(427, 15, 'submitted', 'Sesi 7 (Program 12x): Artikulasi konsonan alveolar /t/, /d/, /n/.', 'Lidah mampu menyentuh langit-langit mulut.', 'Latihan gerak lidah atas-bawah.', NOW() - INTERVAL 38 DAY, NOW(), NOW()),
(428, 15, 'submitted', 'Sesi 8 (Program 12x): Penggabungan 2 kata bermakna (mau minum, minta kue).', 'Penggunaan kata ekspresif meningkat.', 'Biasakan anak minta verbal sebelum diberi.', NOW() - INTERVAL 31 DAY, NOW(), NOW()),
(429, 15, 'submitted', 'Sesi 9 (Program 12x): Latihan intonasi dan jeda kalimat sederhana.', 'Intonasi bicara lebih natural.', 'Ajak bercakap-cakap santai.', NOW() - INTERVAL 24 DAY, NOW(), NOW()),
(430, 15, 'submitted', 'Sesi 10 (Program 12x): Stimulasi pemahaman konsep (besar-kecil, atas-bawah).', 'Pemahaman instruksi 2 tahap tercapai.', 'Bermain sortir mainan sesuai warna/ukuran.', NOW() - INTERVAL 17 DAY, NOW(), NOW()),
(431, 15, 'submitted', 'Sesi 11 (Program 12x): Merangkai kalimat 3 kata (aku mau makan).', 'Kepercayaan diri anak meningkat pesat.', 'Dukung anak bercerita pengalaman hari ini.', NOW() - INTERVAL 10 DAY, NOW(), NOW()),
(432, 15, 'submitted', 'Sesi 12 (Program 12x): Sesi penutup paket 12x terapi. Evaluasi pencapaian target terapi.', 'Target paket 12 sesi tercapai 90%. Kuota paket habis, direkomendasikan sesi evaluasi berkala.', 'Pertahankan stimulasi rutin di lingkungan keluarga.', NOW() - INTERVAL 3 DAY, NOW(), NOW())
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

INSERT INTO `duration_package_ledger` (`duration_package_entitlement_id`, `appointment_id`, `quantity_delta`, `reason`, `created_by`, `created_at`) VALUES
(108, 421, -1, 'session_completed', NULL, NOW() - INTERVAL 80 DAY),
(108, 422, -1, 'session_completed', NULL, NOW() - INTERVAL 73 DAY),
(108, 423, -1, 'session_completed', NULL, NOW() - INTERVAL 66 DAY),
(108, 424, -1, 'session_completed', NULL, NOW() - INTERVAL 59 DAY),
(108, 425, -1, 'session_completed', NULL, NOW() - INTERVAL 52 DAY),
(108, 426, -1, 'session_completed', NULL, NOW() - INTERVAL 45 DAY),
(108, 427, -1, 'session_completed', NULL, NOW() - INTERVAL 38 DAY),
(108, 428, -1, 'session_completed', NULL, NOW() - INTERVAL 31 DAY),
(108, 429, -1, 'session_completed', NULL, NOW() - INTERVAL 24 DAY),
(108, 430, -1, 'session_completed', NULL, NOW() - INTERVAL 17 DAY),
(108, 431, -1, 'session_completed', NULL, NOW() - INTERVAL 10 DAY),
(108, 432, -1, 'session_completed', NULL, NOW() - INTERVAL 3 DAY);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- =========================================================================
-- Kasuari Care / Ruang Tumbuh - Database MySQL / MariaDB (cPanel phpMyAdmin)
-- Basis Data Target: adstingk_kasuari
-- Compatible with: MariaDB 10.4+ / 10.11+ / MySQL 8.0+
-- Dibuat khusus untuk import langsung di phpMyAdmin cPanel
-- =========================================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET SESSION explicit_defaults_for_timestamp = 1;
START TRANSACTION;
SET time_zone = '+00:00';

-- Baris USE di bawah opsional jika database adstingk_kasuari sudah Anda klik di phpMyAdmin
-- USE `adstingk_kasuari`;

-- --------------------------------------------------------
-- 1. Bersihkan tabel lama jika ada
-- --------------------------------------------------------
DROP TABLE IF EXISTS `patient_intake_submissions`;
DROP TABLE IF EXISTS `patient_therapists`;
DROP TABLE IF EXISTS `therapist_payrolls`;
DROP TABLE IF EXISTS `clinic_expenses`;
DROP TABLE IF EXISTS `duration_package_ledger`;
DROP TABLE IF EXISTS `duration_package_entitlements`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `sale_lines`;
DROP TABLE IF EXISTS `package_ledger`;
DROP TABLE IF EXISTS `package_entitlements`;
DROP TABLE IF EXISTS `patient_packages`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `service_catalog`;
DROP TABLE IF EXISTS `package_catalog_items`;
DROP TABLE IF EXISTS `package_catalog`;
DROP TABLE IF EXISTS `session_goal_scores`;
DROP TABLE IF EXISTS `session_reports`;
DROP TABLE IF EXISTS `iep_goals`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `assessment_recommendations`;
DROP TABLE IF EXISTS `assessments`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `therapist_profiles`;
DROP TABLE IF EXISTS `therapy_types`;
DROP TABLE IF EXISTS `public_registrations`;
DROP TABLE IF EXISTS `patient_parents`;
DROP TABLE IF EXISTS `patient_code_counters`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `parents`;
DROP TABLE IF EXISTS `role_has_permissions`;
DROP TABLE IF EXISTS `model_has_roles`;
DROP TABLE IF EXISTS `model_has_permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `failed_jobs`;
DROP TABLE IF EXISTS `job_batches`;
DROP TABLE IF EXISTS `jobs`;
DROP TABLE IF EXISTS `cache_locks`;
DROP TABLE IF EXISTS `cache`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `migrations`;

-- --------------------------------------------------------
-- 2. Buat Tabel Migrations & Daftarkan Seluruh Batch Migrasi
-- --------------------------------------------------------
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('2026_09_28_122515_create_permission_tables', 1),
('2026_09_28_130000_create_clinic_tables', 1),
('2026_10_19_120656_add_duration_based_packages_and_service_notes', 1),
('2026_10_19_131952_add_therapist_profile_id_to_sales_table', 1),
('2026_10_20_124417_add_reversal_links_to_package_ledgers', 1),
('2026_10_22_124838_add_edit_tracking_to_session_reports_table', 1),
('2026_10_22_131813_add_assessment_payment_workflow', 1),
('2026_10_24_155510_add_photo_path_to_session_reports_table', 1),
('2026_10_24_172328_add_appointment_id_to_sales_table', 1),
('2026_10_24_180017_add_bank_and_proof_fields_to_payments_table', 1),
('2026_10_25_044824_create_clinic_expenses_table', 1),
('2026_10_25_044828_create_therapist_payrolls_table', 1),
('2026_10_25_123733_add_therapist_profile_id_to_assessments_table', 1),
('2026_10_28_141153_create_patient_therapists_table', 1),
('2026_11_03_084807_create_patient_intake_submissions_table', 1),
('2026_11_06_031818_add_other_answers_to_patient_intake_submissions_table', 1),
('2026_11_18_134738_update_active_clinic_therapy_types', 1),
('2026_11_19_120302_add_attendance_status_to_assessments_table', 1),
('2026_11_22_105000_remove_unique_therapist_constraint_from_patient_therapists_table', 1);

-- --------------------------------------------------------
-- 3. Tabel Standar Laravel (Users, Cache, Jobs, Sessions)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `payload` longtext NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Tabel Roles & Permissions (Spatie)
-- --------------------------------------------------------
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Tabel Pasien & Orang Tua
-- --------------------------------------------------------
CREATE TABLE `parents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parents_user_id_unique` (`user_id`),
  CONSTRAINT `parents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `address` text,
  `primary_whatsapp` varchar(30) DEFAULT NULL,
  `main_complaint` text,
  `school_name` varchar(255) DEFAULT NULL,
  `medical_history` text,
  `milestones` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patients_patient_code_unique` (`patient_code`),
  KEY `patients_is_active_created_at_index` (`is_active`,`created_at`),
  CONSTRAINT `patients_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patient_code_counters` (
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `patient_parents` (
  `patient_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned NOT NULL,
  `relation` varchar(20) NOT NULL,
  `is_primary_contact` tinyint(1) NOT NULL DEFAULT '0',
  `can_access_portal` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`patient_id`,`parent_id`),
  CONSTRAINT `patient_parents_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_parents_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `public_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `child_name` varchar(255) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `parent_name` varchar(255) NOT NULL,
  `parent_relation` varchar(20) NOT NULL DEFAULT 'guardian',
  `whatsapp` varchar(30) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `complaint` text,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `assigned_to` bigint unsigned DEFAULT NULL,
  `patient_id` bigint unsigned DEFAULT NULL,
  `follow_up_notes` text,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `public_registrations_status_index` (`status`),
  CONSTRAINT `public_registrations_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `public_registrations_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Tabel Master Terapi, Terapis & Ruangan
-- --------------------------------------------------------
CREATE TABLE `therapy_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(8) NOT NULL,
  `name` varchar(255) NOT NULL,
  `default_duration_minutes` smallint unsigned NOT NULL DEFAULT '60',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `therapy_types_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `therapist_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `license_number` varchar(255) DEFAULT NULL,
  `specialty` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `therapist_profiles_user_id_unique` (`user_id`),
  CONSTRAINT `therapist_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rooms_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Tabel Assessment & Rekomendasi
-- --------------------------------------------------------
CREATE TABLE `assessments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `therapist_profile_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `attendance_status` varchar(20) DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `chief_complaint` text,
  `development_history` json DEFAULT NULL,
  `learning_behavior` json DEFAULT NULL,
  `summary` text,
  `referral_notes` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assessments_sale_id_unique` (`sale_id`),
  KEY `assessments_status_index` (`status`),
  CONSTRAINT `assessments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assessments_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assessments_therapist_profile_id_foreign` FOREIGN KEY (`therapist_profile_id`) REFERENCES `therapist_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessment_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assessment_id` bigint unsigned NOT NULL,
  `therapy_type_id` bigint unsigned NOT NULL,
  `sessions_per_week` smallint unsigned NOT NULL DEFAULT '1',
  `duration_minutes` smallint unsigned NOT NULL DEFAULT '60',
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `assessment_recommendations_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assessment_recommendations_therapy_type_id_foreign` FOREIGN KEY (`therapy_type_id`) REFERENCES `therapy_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Tabel Jadwal (Appointments) & Laporan Sesi Terapi
-- --------------------------------------------------------
CREATE TABLE `appointments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `therapy_type_id` bigint unsigned NOT NULL,
  `therapist_profile_id` bigint unsigned DEFAULT NULL,
  `room_id` bigint unsigned DEFAULT NULL,
  `assessment_id` bigint unsigned DEFAULT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `booking_source` varchar(20) NOT NULL DEFAULT 'admin',
  `attendance_status` varchar(20) DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_note` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointments_starts_at_status_index` (`starts_at`,`status`),
  KEY `appointments_status_index` (`status`),
  CONSTRAINT `appointments_assessment_id_foreign` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_therapist_profile_id_foreign` FOREIGN KEY (`therapist_profile_id`) REFERENCES `therapist_profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_therapy_type_id_foreign` FOREIGN KEY (`therapy_type_id`) REFERENCES `therapy_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `iep_goals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `therapy_type_id` bigint unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `measurement_unit` varchar(40) DEFAULT NULL,
  `baseline` decimal(8,2) DEFAULT NULL,
  `target_value` decimal(8,2) DEFAULT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `iep_goals_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `iep_goals_therapy_type_id_foreign` FOREIGN KEY (`therapy_type_id`) REFERENCES `therapy_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `session_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint unsigned NOT NULL,
  `therapist_user_id` bigint unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `activities` text,
  `parent_notes` text,
  `homework` text,
  `photo_path` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `edited_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_reports_appointment_id_unique` (`appointment_id`),
  CONSTRAINT `session_reports_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `session_reports_edited_by_user_id_foreign` FOREIGN KEY (`edited_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `session_reports_therapist_user_id_foreign` FOREIGN KEY (`therapist_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `session_goal_scores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_report_id` bigint unsigned NOT NULL,
  `iep_goal_id` bigint unsigned NOT NULL,
  `score` decimal(8,2) NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_goal_scores_session_report_id_iep_goal_id_unique` (`session_report_id`,`iep_goal_id`),
  CONSTRAINT `session_goal_scores_iep_goal_id_foreign` FOREIGN KEY (`iep_goal_id`) REFERENCES `iep_goals` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `session_goal_scores_session_report_id_foreign` FOREIGN KEY (`session_report_id`) REFERENCES `session_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Tabel Katalog Produk & Layanan
-- --------------------------------------------------------
CREATE TABLE `package_catalog` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `validity_days` smallint unsigned NOT NULL DEFAULT '90',
  `price` int unsigned NOT NULL,
  `session_duration_minutes` smallint unsigned DEFAULT NULL,
  `session_quantity` smallint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_catalog_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_catalog_id` bigint unsigned NOT NULL,
  `therapy_type_id` bigint unsigned NOT NULL,
  `session_quantity` smallint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_catalog_items_package_catalog_id_therapy_type_id_unique` (`package_catalog_id`,`therapy_type_id`),
  CONSTRAINT `package_catalog_items_package_catalog_id_foreign` FOREIGN KEY (`package_catalog_id`) REFERENCES `package_catalog` (`id`) ON DELETE CASCADE,
  CONSTRAINT `package_catalog_items_therapy_type_id_foreign` FOREIGN KEY (`therapy_type_id`) REFERENCES `therapy_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_catalog` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `service_type` varchar(20) NOT NULL,
  `therapy_type_id` bigint unsigned DEFAULT NULL,
  `price` int unsigned NOT NULL,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_catalog_code_unique` (`code`),
  CONSTRAINT `service_catalog_therapy_type_id_foreign` FOREIGN KEY (`therapy_type_id`) REFERENCES `therapy_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sku` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` int unsigned NOT NULL,
  `stock_quantity` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_sku_unique` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. Tabel Penjualan (Sales, Invoicing, Payments)
-- --------------------------------------------------------
CREATE TABLE `sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(32) NOT NULL,
  `patient_id` bigint unsigned NOT NULL,
  `cashier_id` bigint unsigned DEFAULT NULL,
  `therapist_profile_id` bigint unsigned DEFAULT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'paid',
  `subtotal` int unsigned NOT NULL,
  `discount_total` int unsigned NOT NULL DEFAULT '0',
  `grand_total` int unsigned NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_invoice_number_unique` (`invoice_number`),
  UNIQUE KEY `sales_appointment_id_unique` (`appointment_id`),
  CONSTRAINT `sales_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_cashier_id_foreign` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_therapist_profile_id_foreign` FOREIGN KEY (`therapist_profile_id`) REFERENCES `therapist_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `assessments` ADD CONSTRAINT `assessments_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL;

CREATE TABLE `sale_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned NOT NULL,
  `line_type` varchar(20) NOT NULL,
  `package_catalog_id` bigint unsigned DEFAULT NULL,
  `service_catalog_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` smallint unsigned NOT NULL DEFAULT '1',
  `unit_price` int unsigned NOT NULL,
  `line_total` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `sale_lines_package_catalog_id_foreign` FOREIGN KEY (`package_catalog_id`) REFERENCES `package_catalog` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_lines_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_lines_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_lines_service_catalog_id_foreign` FOREIGN KEY (`service_catalog_id`) REFERENCES `service_catalog` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned NOT NULL,
  `method` varchar(20) NOT NULL,
  `amount` int unsigned NOT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `received_by` bigint unsigned NOT NULL,
  `received_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_sale_id_unique` (`sale_id`),
  CONSTRAINT `payments_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payments_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. Tabel Paket Pasien & Kuota Durasi (Ledger)
-- --------------------------------------------------------
CREATE TABLE `patient_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `package_catalog_id` bigint unsigned NOT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `starts_at` timestamp NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_packages_sale_id_unique` (`sale_id`),
  KEY `patient_packages_patient_id_status_index` (`patient_id`,`status`),
  CONSTRAINT `patient_packages_package_catalog_id_foreign` FOREIGN KEY (`package_catalog_id`) REFERENCES `package_catalog` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `patient_packages_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_packages_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_entitlements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_package_id` bigint unsigned NOT NULL,
  `therapy_type_id` bigint unsigned NOT NULL,
  `granted_sessions` smallint unsigned NOT NULL,
  `remaining_sessions` smallint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_entitlements_patient_package_id_therapy_type_id_unique` (`patient_package_id`,`therapy_type_id`),
  CONSTRAINT `package_entitlements_patient_package_id_foreign` FOREIGN KEY (`patient_package_id`) REFERENCES `patient_packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `package_entitlements_therapy_type_id_foreign` FOREIGN KEY (`therapy_type_id`) REFERENCES `therapy_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_ledger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_entitlement_id` bigint unsigned NOT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `reversal_of_id` bigint unsigned DEFAULT NULL,
  `quantity_delta` int NOT NULL,
  `reason` varchar(40) NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_ledger_appointment_id_package_entitlement_id_unique` (`appointment_id`,`package_entitlement_id`),
  UNIQUE KEY `package_ledger_reversal_of_id_unique` (`reversal_of_id`),
  CONSTRAINT `package_ledger_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `package_ledger_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `package_ledger_package_entitlement_id_foreign` FOREIGN KEY (`package_entitlement_id`) REFERENCES `package_entitlements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `package_ledger_reversal_of_id_foreign` FOREIGN KEY (`reversal_of_id`) REFERENCES `package_ledger` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `duration_package_entitlements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_package_id` bigint unsigned NOT NULL,
  `duration_minutes` smallint unsigned NOT NULL,
  `granted_sessions` int unsigned NOT NULL,
  `remaining_sessions` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dpe_patient_package_duration_unique` (`patient_package_id`,`duration_minutes`),
  CONSTRAINT `duration_package_entitlements_patient_package_id_foreign` FOREIGN KEY (`patient_package_id`) REFERENCES `patient_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `duration_package_ledger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `duration_package_entitlement_id` bigint unsigned NOT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `reversal_of_id` bigint unsigned DEFAULT NULL,
  `quantity_delta` int NOT NULL,
  `reason` varchar(40) NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `duration_ledger_appointment_entitlement_unique` (`appointment_id`,`duration_package_entitlement_id`),
  UNIQUE KEY `duration_package_ledger_reversal_of_id_unique` (`reversal_of_id`),
  CONSTRAINT `duration_package_ledger_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `duration_package_ledger_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `duration_package_ledger_duration_package_entitlement_id_foreign` FOREIGN KEY (`duration_package_entitlement_id`) REFERENCES `duration_package_entitlements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `duration_package_ledger_reversal_of_id_foreign` FOREIGN KEY (`reversal_of_id`) REFERENCES `duration_package_ledger` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 12. Tabel Keuangan & Payroll
-- --------------------------------------------------------
CREATE TABLE `clinic_expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `expense_date` date NOT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinic_expenses_expense_date_id_index` (`expense_date`,`id`),
  CONSTRAINT `clinic_expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `therapist_payrolls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `therapist_profile_id` bigint unsigned NOT NULL,
  `payroll_period` date NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `therapist_payrolls_payment_date_id_index` (`payment_date`,`id`),
  CONSTRAINT `therapist_payrolls_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `therapist_payrolls_therapist_profile_id_foreign` FOREIGN KEY (`therapist_profile_id`) REFERENCES `therapist_profiles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 13. Tabel Relasi Pasien-Terapis (Multi-Anak Didukung)
-- --------------------------------------------------------
CREATE TABLE `patient_therapists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `therapy_type_id` bigint unsigned NOT NULL,
  `therapist_profile_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_therapists_patient_id_therapy_type_id_unique` (`patient_id`,`therapy_type_id`),
  CONSTRAINT `patient_therapists_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_therapists_therapist_profile_id_foreign` FOREIGN KEY (`therapist_profile_id`) REFERENCES `therapist_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_therapists_therapy_type_id_foreign` FOREIGN KEY (`therapy_type_id`) REFERENCES `therapy_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 14. Tabel Intake Form & Audit Logs
-- --------------------------------------------------------
CREATE TABLE `patient_intake_submissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(100) NOT NULL,
  `response_id` varchar(255) NOT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `child_data` json DEFAULT NULL,
  `father_data` json DEFAULT NULL,
  `mother_data` json DEFAULT NULL,
  `family_context` json DEFAULT NULL,
  `development_history` json DEFAULT NULL,
  `health_history` json DEFAULT NULL,
  `other_answers` json DEFAULT NULL,
  `verification_status` varchar(30) NOT NULL DEFAULT 'pending',
  `patient_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_intake_submissions_response_id_unique` (`response_id`),
  KEY `pis_verification_status_submitted_at_idx` (`verification_status`,`submitted_at`),
  CONSTRAINT `patient_intake_submissions_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) NOT NULL,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `before` json DEFAULT NULL,
  `after` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_entity_type_entity_id_index` (`entity_type`,`entity_id`),
  CONSTRAINT `audit_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- DATA AWAL LENGKAP & VALID (Seed Data)
-- Semua password akun bawaan adalah: password
-- =========================================================================

-- 1. Roles
INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'owner', 'web', NOW(), NOW()),
(2, 'front_office', 'web', NOW(), NOW()),
(3, 'therapist', 'web', NOW(), NOW()),
(4, 'finance', 'web', NOW(), NOW()),
(5, 'parent', 'web', NOW(), NOW());

-- 2. Users (Akun Utama & Tim Terapis Spesialis)
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `is_active`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Pimpinan Klinik', 'owner@rumahterapi.test', '081234567890', 1, '$2y$12$nUubLHv8jB5Y1I1Vkq1zb.F0u/JlE1nIIqnyP2SGxuSiwa.G5nsIC', NOW(), NOW()),
(2, 'Admin Front Office', 'admin@rumahterapi.test', '081234567891', 1, '$2y$12$6sOgJUMOYIa.fQU37LDOU.HJyQID/vzFzPm6dcfgWlB093nKSajmO', NOW(), NOW()),
(3, 'Kasir Klinik', 'kasir@rumahterapi.test', '081234567892', 1, '$2y$12$HROeAjOxnut3iFPxj9Rwa.y7gxh1jG5K1/n8QkjSMuHqNgjOnblrO', NOW(), NOW()),
(4, 'Terapis Demo', 'terapis@rumahterapi.test', '081234567893', 1, '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', NOW(), NOW()),
(10, 'Siti Rahmawati, S.Tr.Kes', 'terapis.tw1@rumahterapi.test', '081234567810', 1, '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', NOW(), NOW()),
(11, 'Budi Santoso, S.ST', 'terapis.to1@rumahterapi.test', '081234567811', 1, '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', NOW(), NOW()),
(12, 'Dewi Lestari, S.Ft', 'terapis.si@rumahterapi.test', '081234567812', 1, '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', NOW(), NOW()),
(13, 'Nurul Fadilah, S.Tr.Kes', 'terapis.tw2@rumahterapi.test', '081234567813', 1, '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', NOW(), NOW()),
(14, 'Ahmad Fauzi, S.Psi', 'terapis.aba@rumahterapi.test', '081234567814', 1, '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', NOW(), NOW()),
(15, 'Rahmat Hidayat, S.Tr.Kes', 'terapis.tw3@rumahterapi.test', '081234567815', 1, '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', NOW(), NOW()),
(16, 'Putri Amelia, S.ST', 'terapis.to2@rumahterapi.test', '081234567816', 1, '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', NOW(), NOW());

-- 3. Hubungkan Role dengan Akun User
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
(3, 'App\\Models\\User', 16);

-- 4. Profil Terapis (Setiap profil terikat 100% dengan akun User di atas)
INSERT INTO `therapist_profiles` (`id`, `user_id`, `license_number`, `specialty`, `created_at`, `updated_at`) VALUES
(1, 4, NULL, 'Terapi Anak', NOW(), NOW()),
(10, 10, 'STR-TW-10001', 'Terapi Wicara', NOW(), NOW()),
(11, 11, 'STR-OT-10002', 'Terapi Okupasi', NOW(), NOW()),
(12, 12, 'STR-FT-10003', 'Fisioterapi', NOW(), NOW()),
(13, 13, 'STR-TW-10004', 'Terapi Wicara', NOW(), NOW()),
(14, 14, 'STR-OT-10005', 'Terapi Okupasi', NOW(), NOW()),
(15, 15, 'STR-TW-10006', 'Terapi Wicara', NOW(), NOW()),
(16, 16, 'STR-OT-10007', 'Terapi Okupasi', NOW(), NOW());

-- 5. Jenis Terapi (Therapy Types)
INSERT INTO `therapy_types` (`id`, `code`, `name`, `default_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'TW', 'Terapi Wicara', 60, 1, NOW(), NOW()),
(2, 'OT', 'Terapi Okupasi', 60, 1, NOW(), NOW()),
(3, 'SI', 'Sensori Integrasi', 60, 0, NOW(), NOW()),
(4, 'FT', 'Fisioterapi', 60, 1, NOW(), NOW()),
(5, 'ABA', 'ABA / Perilaku', 60, 0, NOW(), NOW());

-- 6. Ruangan Terapi (Rooms)
INSERT INTO `rooms` (`id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'TW', 'Ruang Wicara', 1, NOW(), NOW()),
(2, 'OT', 'Ruang Okupasi', 1, NOW(), NOW()),
(3, 'SI', 'Ruang Sensori', 1, NOW(), NOW()),
(4, 'FT', 'Ruang Fisioterapi', 1, NOW(), NOW());

-- 7. Katalog Layanan & Biaya Sesi (Service Catalog)
INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES
(1, 'ASSESSMENT-AWAL', 'Assessment & Konsultasi', 'assessment', NULL, 300000, 1, NOW(), NOW(), 'Wajib dilakukan pada pertemuan awal sebelum sesi terapi.'),
(2, 'EVALUASI-24-SESI', 'Evaluasi', 'assessment', NULL, 250000, 1, NOW(), NOW(), 'Dilakukan setelah 24 sesi terapi.'),
(3, 'SESI-TW', 'Sesi 1 Jam - Terapi Wicara', 'session', 1, 160000, 1, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(4, 'SESI-OT', 'Sesi 1 Jam - Terapi Okupasi', 'session', 2, 160000, 1, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(5, 'SESI-SI', 'Sesi 1 Jam - Sensori Integrasi', 'session', 3, 160000, 0, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(6, 'SESI-FT', 'Sesi 1 Jam - Fisioterapi', 'session', 4, 160000, 1, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(7, 'SESI-ABA', 'Sesi 1 Jam - ABA / Perilaku', 'session', 5, 160000, 0, NOW(), NOW(), 'Satu sesi terapi dengan durasi 1 jam.'),
(8, 'SESI-30-MENIT', 'Sesi Terapi 30 Menit', 'session', NULL, 110000, 1, NOW(), NOW(), 'Satu sesi terapi dengan durasi 30 menit.');

-- 8. Katalog Paket Terapi (Package Catalog)
INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES
(1, 'Paket 1 Jam - Paket 1 (1x Terapi)', 90, 160000, 1, NOW(), NOW(), 60, 1),
(2, 'Paket 1 Jam - Paket 2 (4x Terapi)', 90, 630000, 1, NOW(), NOW(), 60, 4),
(3, 'Paket 1 Jam - Paket 3 (8x Terapi)', 90, 1240000, 1, NOW(), NOW(), 60, 8),
(4, 'Paket 1 Jam - Paket 4 (12x Terapi)', 90, 1800000, 1, NOW(), NOW(), 60, 12),
(5, 'Paket 30 Menit - Paket 1 (1x Terapi)', 90, 110000, 1, NOW(), NOW(), 30, 1),
(6, 'Paket 30 Menit - Paket 2 (6x Terapi)', 90, 570000, 1, NOW(), NOW(), 30, 6),
(7, 'Paket 30 Menit - Paket 3 (12x Terapi)', 90, 1080000, 1, NOW(), NOW(), 30, 12),
(8, 'Paket 30 Menit - Paket 4 (16x Terapi)', 90, 1360000, 1, NOW(), NOW(), 30, 16);

-- 9. Produk Fisik / Merchandise
INSERT INTO `products` (`id`, `sku`, `name`, `price`, `stock_quantity`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'MEDIA-KARTU-01', 'Kartu aktivitas komunikasi', 85000, 20, 1, NOW(), NOW());

-- 10. Inisialisasi Counter Nomor Rekam Medis
INSERT INTO `patient_code_counters` (`year`, `last_number`) VALUES
(2026, 0);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

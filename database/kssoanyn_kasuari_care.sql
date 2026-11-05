-- =========================================================================
-- Kasuari Care / Ruang Tumbuh - Database MySQL / MariaDB (cPanel phpMyAdmin)
-- Target Database: kssoanyn_kasuari_care
-- Compatible with: MariaDB 10.11+ / MySQL 8.0+
-- =========================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET SESSION explicit_defaults_for_timestamp = 1;
START TRANSACTION;
SET time_zone = '+00:00';

USE `kssoanyn_kasuari_care`;

-- --------------------------------------------------------
-- Bersihkan tabel jika sebelumnya pernah di-import sebagian
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
-- Tabel migrations
-- --------------------------------------------------------
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: 0001_01_01_000000_create_users_table
create table `users` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `email` varchar(255) not null, `email_verified_at` timestamp null, `password` varchar(255) not null, `remember_token` varchar(100) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `users` add unique `users_email_unique`(`email`);
create table `password_reset_tokens` (`email` varchar(255) not null, `token` varchar(255) not null, `created_at` timestamp null, primary key (`email`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `sessions` (`id` varchar(255) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `sessions` add index `sessions_user_id_index`(`user_id`);
alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0001_01_01_000000_create_users_table', 1);

-- Migration: 0001_01_01_000001_create_cache_table
create table `cache` (`key` varchar(255) not null, `value` mediumtext not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `cache` add index `cache_expiration_index`(`expiration`);
create table `cache_locks` (`key` varchar(255) not null, `owner` varchar(255) not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `cache_locks` add index `cache_locks_expiration_index`(`expiration`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0001_01_01_000001_create_cache_table', 1);

-- Migration: 0001_01_01_000002_create_jobs_table
create table `jobs` (`id` bigint unsigned not null auto_increment primary key, `queue` varchar(255) not null, `payload` longtext not null, `attempts` smallint unsigned not null, `reserved_at` int unsigned null, `available_at` int unsigned not null, `created_at` int unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `jobs` add index `jobs_queue_index`(`queue`);
create table `job_batches` (`id` varchar(255) not null, `name` varchar(255) not null, `total_jobs` int not null, `pending_jobs` int not null, `failed_jobs` int not null, `failed_job_ids` longtext not null, `options` mediumtext null, `cancelled_at` int null, `created_at` int not null, `finished_at` int null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `failed_jobs` (`id` bigint unsigned not null auto_increment primary key, `uuid` varchar(255) not null, `connection` varchar(255) not null, `queue` varchar(255) not null, `payload` longtext not null, `exception` longtext not null, `failed_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `failed_jobs` add index `failed_jobs_connection_queue_failed_at_index`(`connection`, `queue`, `failed_at`);
alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0001_01_01_000002_create_jobs_table', 1);

-- Migration: 2026_09_28_122515_create_permission_tables
create table `permissions` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `guard_name` varchar(255) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `permissions` add unique `permissions_name_guard_name_unique`(`name`, `guard_name`);
create table `roles` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `guard_name` varchar(255) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `roles` add unique `roles_name_guard_name_unique`(`name`, `guard_name`);
create table `model_has_permissions` (`permission_id` bigint unsigned not null, `model_type` varchar(255) not null, `model_id` bigint unsigned not null, primary key (`permission_id`, `model_id`, `model_type`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `model_has_permissions` add index `model_has_permissions_model_id_model_type_index`(`model_id`, `model_type`);
alter table `model_has_permissions` add constraint `model_has_permissions_permission_id_foreign` foreign key (`permission_id`) references `permissions` (`id`) on delete cascade;
create table `model_has_roles` (`role_id` bigint unsigned not null, `model_type` varchar(255) not null, `model_id` bigint unsigned not null, primary key (`role_id`, `model_id`, `model_type`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `model_has_roles` add index `model_has_roles_model_id_model_type_index`(`model_id`, `model_type`);
alter table `model_has_roles` add constraint `model_has_roles_role_id_foreign` foreign key (`role_id`) references `roles` (`id`) on delete cascade;
create table `role_has_permissions` (`permission_id` bigint unsigned not null, `role_id` bigint unsigned not null, primary key (`permission_id`, `role_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `role_has_permissions` add constraint `role_has_permissions_permission_id_foreign` foreign key (`permission_id`) references `permissions` (`id`) on delete cascade;
alter table `role_has_permissions` add constraint `role_has_permissions_role_id_foreign` foreign key (`role_id`) references `roles` (`id`) on delete cascade;
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_09_28_122515_create_permission_tables', 1);

-- Migration: 2026_09_28_130000_create_clinic_tables
alter table `users` add `phone` varchar(255) null;
alter table `users` add `is_active` tinyint(1) not null default '1';
create table `parents` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned null, `name` varchar(255) not null, `phone` varchar(30) null, `whatsapp` varchar(30) null, `email` varchar(255) null, `address` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `parents` add constraint `parents_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete set null;
alter table `parents` add unique `parents_user_id_unique`(`user_id`);
create table `patients` (`id` bigint unsigned not null auto_increment primary key, `patient_code` varchar(20) not null, `name` varchar(255) not null, `birth_date` date null, `gender` varchar(20) null, `address` text null, `primary_whatsapp` varchar(30) null, `main_complaint` text null, `school_name` varchar(255) null, `medical_history` text null, `milestones` json null, `is_active` tinyint(1) not null default '1', `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `patients` add constraint `patients_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `patients` add index `patients_is_active_created_at_index`(`is_active`, `created_at`);
alter table `patients` add unique `patients_patient_code_unique`(`patient_code`);
create table `patient_code_counters` (`year` smallint unsigned not null, `last_number` int unsigned not null default '0', primary key (`year`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `patient_parents` (`patient_id` bigint unsigned not null, `parent_id` bigint unsigned not null, `relation` varchar(20) not null, `is_primary_contact` tinyint(1) not null default '0', `can_access_portal` tinyint(1) not null default '0', primary key (`patient_id`, `parent_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `patient_parents` add constraint `patient_parents_patient_id_foreign` foreign key (`patient_id`) references `patients` (`id`) on delete cascade;
alter table `patient_parents` add constraint `patient_parents_parent_id_foreign` foreign key (`parent_id`) references `parents` (`id`) on delete cascade;
create table `public_registrations` (`id` bigint unsigned not null auto_increment primary key, `child_name` varchar(255) not null, `birth_date` date null, `gender` varchar(20) null, `parent_name` varchar(255) not null, `parent_relation` varchar(20) not null default 'guardian', `whatsapp` varchar(30) not null, `email` varchar(255) null, `address` text null, `complaint` text null, `status` varchar(20) not null default 'new', `assigned_to` bigint unsigned null, `patient_id` bigint unsigned null, `follow_up_notes` text null, `submitted_at` timestamp not null default CURRENT_TIMESTAMP, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `public_registrations` add constraint `public_registrations_assigned_to_foreign` foreign key (`assigned_to`) references `users` (`id`) on delete set null;
alter table `public_registrations` add constraint `public_registrations_patient_id_foreign` foreign key (`patient_id`) references `patients` (`id`) on delete set null;
alter table `public_registrations` add index `public_registrations_status_index`(`status`);
create table `therapy_types` (`id` bigint unsigned not null auto_increment primary key, `code` varchar(8) not null, `name` varchar(255) not null, `default_duration_minutes` smallint unsigned not null default '60', `is_active` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `therapy_types` add unique `therapy_types_code_unique`(`code`);
create table `therapist_profiles` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `license_number` varchar(255) null, `specialty` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `therapist_profiles` add constraint `therapist_profiles_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `therapist_profiles` add unique `therapist_profiles_user_id_unique`(`user_id`);
create table `rooms` (`id` bigint unsigned not null auto_increment primary key, `code` varchar(20) not null, `name` varchar(255) not null, `is_active` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `rooms` add unique `rooms_code_unique`(`code`);
create table `assessments` (`id` bigint unsigned not null auto_increment primary key, `patient_id` bigint unsigned not null, `status` varchar(20) not null default 'scheduled', `scheduled_at` timestamp null, `completed_at` timestamp null, `chief_complaint` text null, `development_history` json null, `learning_behavior` json null, `summary` text null, `referral_notes` text null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `assessments` add constraint `assessments_patient_id_foreign` foreign key (`patient_id`) references `patients` (`id`) on delete cascade;
alter table `assessments` add constraint `assessments_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `assessments` add index `assessments_status_index`(`status`);
create table `assessment_recommendations` (`id` bigint unsigned not null auto_increment primary key, `assessment_id` bigint unsigned not null, `therapy_type_id` bigint unsigned not null, `sessions_per_week` smallint unsigned not null default '1', `duration_minutes` smallint unsigned not null default '60', `notes` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `assessment_recommendations` add constraint `assessment_recommendations_assessment_id_foreign` foreign key (`assessment_id`) references `assessments` (`id`) on delete cascade;
alter table `assessment_recommendations` add constraint `assessment_recommendations_therapy_type_id_foreign` foreign key (`therapy_type_id`) references `therapy_types` (`id`) on delete restrict;
create table `appointments` (`id` bigint unsigned not null auto_increment primary key, `patient_id` bigint unsigned not null, `therapy_type_id` bigint unsigned not null, `therapist_profile_id` bigint unsigned null, `room_id` bigint unsigned null, `assessment_id` bigint unsigned null, `starts_at` timestamp not null, `ends_at` timestamp not null, `status` varchar(20) not null default 'scheduled', `booking_source` varchar(20) not null default 'admin', `attendance_status` varchar(20) null, `created_by` bigint unsigned null, `cancelled_at` timestamp null, `cancellation_note` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `appointments` add constraint `appointments_patient_id_foreign` foreign key (`patient_id`) references `patients` (`id`) on delete cascade;
alter table `appointments` add constraint `appointments_therapy_type_id_foreign` foreign key (`therapy_type_id`) references `therapy_types` (`id`) on delete restrict;
alter table `appointments` add constraint `appointments_therapist_profile_id_foreign` foreign key (`therapist_profile_id`) references `therapist_profiles` (`id`) on delete set null;
alter table `appointments` add constraint `appointments_room_id_foreign` foreign key (`room_id`) references `rooms` (`id`) on delete set null;
alter table `appointments` add constraint `appointments_assessment_id_foreign` foreign key (`assessment_id`) references `assessments` (`id`) on delete set null;
alter table `appointments` add constraint `appointments_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `appointments` add index `appointments_starts_at_status_index`(`starts_at`, `status`);
alter table `appointments` add index `appointments_status_index`(`status`);
create table `iep_goals` (`id` bigint unsigned not null auto_increment primary key, `patient_id` bigint unsigned not null, `therapy_type_id` bigint unsigned not null, `title` varchar(255) not null, `description` text null, `measurement_unit` varchar(40) null, `baseline` decimal(8, 2) null, `target_value` decimal(8, 2) null, `starts_on` date null, `ends_on` date null, `status` varchar(20) not null default 'active', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `iep_goals` add constraint `iep_goals_patient_id_foreign` foreign key (`patient_id`) references `patients` (`id`) on delete cascade;
alter table `iep_goals` add constraint `iep_goals_therapy_type_id_foreign` foreign key (`therapy_type_id`) references `therapy_types` (`id`) on delete restrict;
create table `session_reports` (`id` bigint unsigned not null auto_increment primary key, `appointment_id` bigint unsigned not null, `therapist_user_id` bigint unsigned not null, `status` varchar(20) not null default 'draft', `activities` text null, `parent_notes` text null, `homework` text null, `submitted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `session_reports` add constraint `session_reports_appointment_id_foreign` foreign key (`appointment_id`) references `appointments` (`id`) on delete cascade;
alter table `session_reports` add constraint `session_reports_therapist_user_id_foreign` foreign key (`therapist_user_id`) references `users` (`id`) on delete restrict;
alter table `session_reports` add unique `session_reports_appointment_id_unique`(`appointment_id`);
create table `session_goal_scores` (`id` bigint unsigned not null auto_increment primary key, `session_report_id` bigint unsigned not null, `iep_goal_id` bigint unsigned not null, `score` decimal(8, 2) not null, `notes` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `session_goal_scores` add constraint `session_goal_scores_session_report_id_foreign` foreign key (`session_report_id`) references `session_reports` (`id`) on delete cascade;
alter table `session_goal_scores` add constraint `session_goal_scores_iep_goal_id_foreign` foreign key (`iep_goal_id`) references `iep_goals` (`id`) on delete restrict;
alter table `session_goal_scores` add unique `session_goal_scores_session_report_id_iep_goal_id_unique`(`session_report_id`, `iep_goal_id`);
create table `package_catalog` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `validity_days` smallint unsigned not null default '90', `price` int unsigned not null, `is_active` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `package_catalog_items` (`id` bigint unsigned not null auto_increment primary key, `package_catalog_id` bigint unsigned not null, `therapy_type_id` bigint unsigned not null, `session_quantity` smallint unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `package_catalog_items` add constraint `package_catalog_items_package_catalog_id_foreign` foreign key (`package_catalog_id`) references `package_catalog` (`id`) on delete cascade;
alter table `package_catalog_items` add constraint `package_catalog_items_therapy_type_id_foreign` foreign key (`therapy_type_id`) references `therapy_types` (`id`) on delete restrict;
alter table `package_catalog_items` add unique `package_catalog_items_package_catalog_id_therapy_type_id_unique`(`package_catalog_id`, `therapy_type_id`);
create table `service_catalog` (`id` bigint unsigned not null auto_increment primary key, `code` varchar(30) not null, `name` varchar(255) not null, `service_type` varchar(20) not null, `therapy_type_id` bigint unsigned null, `price` int unsigned not null, `is_active` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `service_catalog` add constraint `service_catalog_therapy_type_id_foreign` foreign key (`therapy_type_id`) references `therapy_types` (`id`) on delete set null;
alter table `service_catalog` add unique `service_catalog_code_unique`(`code`);
create table `products` (`id` bigint unsigned not null auto_increment primary key, `sku` varchar(255) not null, `name` varchar(255) not null, `price` int unsigned not null, `stock_quantity` int unsigned not null default '0', `is_active` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `products` add unique `products_sku_unique`(`sku`);
create table `sales` (`id` bigint unsigned not null auto_increment primary key, `invoice_number` varchar(32) not null, `patient_id` bigint unsigned not null, `cashier_id` bigint unsigned not null, `status` varchar(20) not null default 'paid', `subtotal` int unsigned not null, `discount_total` int unsigned not null default '0', `grand_total` int unsigned not null, `paid_at` timestamp not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `sales` add constraint `sales_patient_id_foreign` foreign key (`patient_id`) references `patients` (`id`) on delete restrict;
alter table `sales` add constraint `sales_cashier_id_foreign` foreign key (`cashier_id`) references `users` (`id`) on delete restrict;
alter table `sales` add unique `sales_invoice_number_unique`(`invoice_number`);
create table `patient_packages` (`id` bigint unsigned not null auto_increment primary key, `patient_id` bigint unsigned not null, `package_catalog_id` bigint unsigned not null, `sale_id` bigint unsigned null, `status` varchar(20) not null default 'active', `starts_at` timestamp not null, `expires_at` timestamp not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `patient_packages` add constraint `patient_packages_patient_id_foreign` foreign key (`patient_id`) references `patients` (`id`) on delete cascade;
alter table `patient_packages` add constraint `patient_packages_package_catalog_id_foreign` foreign key (`package_catalog_id`) references `package_catalog` (`id`) on delete restrict;
alter table `patient_packages` add constraint `patient_packages_sale_id_foreign` foreign key (`sale_id`) references `sales` (`id`) on delete set null;
alter table `patient_packages` add index `patient_packages_patient_id_status_index`(`patient_id`, `status`);
alter table `patient_packages` add unique `patient_packages_sale_id_unique`(`sale_id`);
create table `package_entitlements` (`id` bigint unsigned not null auto_increment primary key, `patient_package_id` bigint unsigned not null, `therapy_type_id` bigint unsigned not null, `granted_sessions` smallint unsigned not null, `remaining_sessions` smallint unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `package_entitlements` add constraint `package_entitlements_patient_package_id_foreign` foreign key (`patient_package_id`) references `patient_packages` (`id`) on delete cascade;
alter table `package_entitlements` add constraint `package_entitlements_therapy_type_id_foreign` foreign key (`therapy_type_id`) references `therapy_types` (`id`) on delete restrict;
alter table `package_entitlements` add unique `package_entitlements_patient_package_id_therapy_type_id_unique`(`patient_package_id`, `therapy_type_id`);
create table `package_ledger` (`id` bigint unsigned not null auto_increment primary key, `package_entitlement_id` bigint unsigned not null, `appointment_id` bigint unsigned null, `quantity_delta` int not null, `reason` varchar(40) not null, `created_by` bigint unsigned null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `package_ledger` add constraint `package_ledger_package_entitlement_id_foreign` foreign key (`package_entitlement_id`) references `package_entitlements` (`id`) on delete restrict;
alter table `package_ledger` add constraint `package_ledger_appointment_id_foreign` foreign key (`appointment_id`) references `appointments` (`id`) on delete set null;
alter table `package_ledger` add constraint `package_ledger_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `package_ledger` add unique `package_ledger_appointment_id_package_entitlement_id_unique`(`appointment_id`, `package_entitlement_id`);
create table `sale_lines` (`id` bigint unsigned not null auto_increment primary key, `sale_id` bigint unsigned not null, `line_type` varchar(20) not null, `package_catalog_id` bigint unsigned null, `service_catalog_id` bigint unsigned null, `product_id` bigint unsigned null, `description` varchar(255) not null, `quantity` smallint unsigned not null default '1', `unit_price` int unsigned not null, `line_total` int unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `sale_lines` add constraint `sale_lines_sale_id_foreign` foreign key (`sale_id`) references `sales` (`id`) on delete cascade;
alter table `sale_lines` add constraint `sale_lines_package_catalog_id_foreign` foreign key (`package_catalog_id`) references `package_catalog` (`id`) on delete set null;
alter table `sale_lines` add constraint `sale_lines_service_catalog_id_foreign` foreign key (`service_catalog_id`) references `service_catalog` (`id`) on delete set null;
alter table `sale_lines` add constraint `sale_lines_product_id_foreign` foreign key (`product_id`) references `products` (`id`) on delete set null;
create table `payments` (`id` bigint unsigned not null auto_increment primary key, `sale_id` bigint unsigned not null, `method` varchar(20) not null, `amount` int unsigned not null, `reference_number` varchar(255) null, `received_by` bigint unsigned not null, `received_at` timestamp not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `payments` add constraint `payments_sale_id_foreign` foreign key (`sale_id`) references `sales` (`id`) on delete cascade;
alter table `payments` add constraint `payments_received_by_foreign` foreign key (`received_by`) references `users` (`id`) on delete restrict;
alter table `payments` add unique `payments_sale_id_unique`(`sale_id`);
create table `audit_logs` (`id` bigint unsigned not null auto_increment primary key, `actor_id` bigint unsigned null, `event` varchar(255) not null, `entity_type` varchar(255) not null, `entity_id` bigint unsigned null, `before` json null, `after` json null, `ip_address` varchar(45) null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `audit_logs` add constraint `audit_logs_actor_id_foreign` foreign key (`actor_id`) references `users` (`id`) on delete set null;
alter table `audit_logs` add index `audit_logs_entity_type_entity_id_index`(`entity_type`, `entity_id`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_09_28_130000_create_clinic_tables', 1);

-- Migration: 2026_10_19_120656_add_duration_based_packages_and_service_notes
alter table `package_catalog` add `session_duration_minutes` smallint unsigned null after `price`;
alter table `package_catalog` add `session_quantity` smallint unsigned null after `session_duration_minutes`;
alter table `service_catalog` add `description` text null after `price`;
create table `duration_package_entitlements` (`id` bigint unsigned not null auto_increment primary key, `patient_package_id` bigint unsigned not null, `duration_minutes` smallint unsigned not null, `granted_sessions` int unsigned not null, `remaining_sessions` int unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `duration_package_entitlements` add constraint `duration_package_entitlements_patient_package_id_foreign` foreign key (`patient_package_id`) references `patient_packages` (`id`) on delete cascade;
alter table `duration_package_entitlements` add unique `dpe_patient_package_duration_unique`(`patient_package_id`, `duration_minutes`);
create table `duration_package_ledger` (`id` bigint unsigned not null auto_increment primary key, `duration_package_entitlement_id` bigint unsigned not null, `appointment_id` bigint unsigned null, `quantity_delta` int not null, `reason` varchar(40) not null, `created_by` bigint unsigned null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `duration_package_ledger` add constraint `duration_package_ledger_duration_package_entitlement_id_foreign` foreign key (`duration_package_entitlement_id`) references `duration_package_entitlements` (`id`) on delete restrict;
alter table `duration_package_ledger` add constraint `duration_package_ledger_appointment_id_foreign` foreign key (`appointment_id`) references `appointments` (`id`) on delete set null;
alter table `duration_package_ledger` add constraint `duration_package_ledger_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `duration_package_ledger` add unique `duration_ledger_appointment_entitlement_unique`(`appointment_id`, `duration_package_entitlement_id`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_19_120656_add_duration_based_packages_and_service_notes', 1);

-- Migration: 2026_10_19_131952_add_therapist_profile_id_to_sales_table
alter table `sales` add `therapist_profile_id` bigint unsigned null after `cashier_id`;
alter table `sales` add constraint `sales_therapist_profile_id_foreign` foreign key (`therapist_profile_id`) references `therapist_profiles` (`id`) on delete set null;
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_19_131952_add_therapist_profile_id_to_sales_table', 1);

-- Migration: 2026_10_20_124417_add_reversal_links_to_package_ledgers
alter table `package_ledger` add `reversal_of_id` bigint unsigned null;
alter table `package_ledger` add constraint `package_ledger_reversal_of_id_foreign` foreign key (`reversal_of_id`) references `package_ledger` (`id`) on delete restrict;
alter table `package_ledger` add unique `package_ledger_reversal_of_id_unique`(`reversal_of_id`);
alter table `duration_package_ledger` add `reversal_of_id` bigint unsigned null;
alter table `duration_package_ledger` add constraint `duration_package_ledger_reversal_of_id_foreign` foreign key (`reversal_of_id`) references `duration_package_ledger` (`id`) on delete restrict;
alter table `duration_package_ledger` add unique `duration_package_ledger_reversal_of_id_unique`(`reversal_of_id`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_20_124417_add_reversal_links_to_package_ledgers', 1);

-- Migration: 2026_10_22_124838_add_edit_tracking_to_session_reports_table
alter table `session_reports` add `edited_at` timestamp null after `submitted_at`;
alter table `session_reports` add `edited_by_user_id` bigint unsigned null after `edited_at`;
alter table `session_reports` add constraint `session_reports_edited_by_user_id_foreign` foreign key (`edited_by_user_id`) references `users` (`id`) on delete set null;
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_22_124838_add_edit_tracking_to_session_reports_table', 1);

-- Migration: 2026_10_22_131813_add_assessment_payment_workflow
alter table `assessments` add `sale_id` bigint unsigned null;
alter table `assessments` add constraint `assessments_sale_id_foreign` foreign key (`sale_id`) references `sales` (`id`) on delete set null;
alter table `assessments` add unique `assessments_sale_id_unique`(`sale_id`);
alter table `sales` modify `cashier_id` bigint unsigned null;
alter table `sales` modify `paid_at` timestamp null;
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_22_131813_add_assessment_payment_workflow', 1);

-- Migration: 2026_10_24_155510_add_photo_path_to_session_reports_table
alter table `session_reports` add `photo_path` varchar(255) null after `homework`;
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_24_155510_add_photo_path_to_session_reports_table', 1);

-- Migration: 2026_10_24_172328_add_appointment_id_to_sales_table
alter table `sales` add `appointment_id` bigint unsigned null;
alter table `sales` add constraint `sales_appointment_id_foreign` foreign key (`appointment_id`) references `appointments` (`id`) on delete set null;
alter table `sales` add unique `sales_appointment_id_unique`(`appointment_id`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_24_172328_add_appointment_id_to_sales_table', 1);

-- Migration: 2026_10_24_180017_add_bank_and_proof_fields_to_payments_table
alter table `payments` add `bank_name` varchar(100) null after `reference_number`;
alter table `payments` add `proof_path` varchar(255) null after `bank_name`;
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_24_180017_add_bank_and_proof_fields_to_payments_table', 1);

-- Migration: 2026_10_25_044824_create_clinic_expenses_table
create table `clinic_expenses` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(150) not null, `amount` bigint unsigned not null, `expense_date` date not null, `proof_path` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `clinic_expenses` add constraint `clinic_expenses_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `clinic_expenses` add index `clinic_expenses_expense_date_id_index`(`expense_date`, `id`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_25_044824_create_clinic_expenses_table', 1);

-- Migration: 2026_10_25_044828_create_therapist_payrolls_table
create table `therapist_payrolls` (`id` bigint unsigned not null auto_increment primary key, `therapist_profile_id` bigint unsigned not null, `payroll_period` date not null, `amount` bigint unsigned not null, `payment_date` date not null, `proof_path` varchar(255) null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `therapist_payrolls` add constraint `therapist_payrolls_therapist_profile_id_foreign` foreign key (`therapist_profile_id`) references `therapist_profiles` (`id`) on delete restrict;
alter table `therapist_payrolls` add constraint `therapist_payrolls_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `therapist_payrolls` add index `therapist_payrolls_payment_date_id_index`(`payment_date`, `id`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_25_044828_create_therapist_payrolls_table', 1);

-- Migration: 2026_10_25_123733_add_therapist_profile_id_to_assessments_table
alter table `assessments` add `therapist_profile_id` bigint unsigned null;
alter table `assessments` add constraint `assessments_therapist_profile_id_foreign` foreign key (`therapist_profile_id`) references `therapist_profiles` (`id`) on delete set null;
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_25_123733_add_therapist_profile_id_to_assessments_table', 1);

-- Migration: 2026_10_28_141153_create_patient_therapists_table
create table `patient_therapists` (`id` bigint unsigned not null auto_increment primary key, `patient_id` bigint unsigned not null, `therapy_type_id` bigint unsigned not null, `therapist_profile_id` bigint unsigned not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `patient_therapists` add constraint `patient_therapists_patient_id_foreign` foreign key (`patient_id`) references `patients` (`id`) on delete cascade;
alter table `patient_therapists` add constraint `patient_therapists_therapy_type_id_foreign` foreign key (`therapy_type_id`) references `therapy_types` (`id`) on delete restrict;
alter table `patient_therapists` add constraint `patient_therapists_therapist_profile_id_foreign` foreign key (`therapist_profile_id`) references `therapist_profiles` (`id`) on delete cascade;
alter table `patient_therapists` add unique `patient_therapists_patient_id_therapy_type_id_unique`(`patient_id`, `therapy_type_id`);
alter table `patient_therapists` add unique `patient_therapists_therapist_profile_id_unique`(`therapist_profile_id`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_28_141153_create_patient_therapists_table', 1);

-- Migration: 2026_11_03_084807_create_patient_intake_submissions_table
create table `patient_intake_submissions` (`id` bigint unsigned not null auto_increment primary key, `source` varchar(100) not null, `response_id` varchar(255) not null, `submitted_at` timestamp null, `child_data` json null, `father_data` json null, `mother_data` json null, `family_context` json null, `development_history` json null, `health_history` json null, `verification_status` varchar(30) not null default 'pending', `patient_id` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `patient_intake_submissions` add constraint `patient_intake_submissions_patient_id_foreign` foreign key (`patient_id`) references `patients` (`id`) on delete set null;
alter table `patient_intake_submissions` add index `pis_verification_status_submitted_at_idx`(`verification_status`, `submitted_at`);
alter table `patient_intake_submissions` add unique `patient_intake_submissions_response_id_unique`(`response_id`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_11_03_084807_create_patient_intake_submissions_table', 1);

-- --------------------------------------------------------
-- Data Awal (DatabaseSeeder)
-- --------------------------------------------------------
-- Data untuk tabel `roles`
INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (1, 'owner', 'web', '2026-11-03 10:52:02', '2026-11-03 10:52:02');
INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (2, 'front_office', 'web', '2026-11-03 10:52:02', '2026-11-03 10:52:02');
INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (3, 'therapist', 'web', '2026-11-03 10:52:02', '2026-11-03 10:52:02');
INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (4, 'finance', 'web', '2026-11-03 10:52:02', '2026-11-03 10:52:02');
INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (5, 'parent', 'web', '2026-11-03 10:52:02', '2026-11-03 10:52:02');

-- Data untuk tabel `users` (Password default: password)
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `phone`, `is_active`) VALUES (1, 'Pimpinan Klinik', 'owner@rumahterapi.test', NULL, '$2y$12$nUubLHv8jB5Y1I1Vkq1zb.F0u/JlE1nIIqnyP2SGxuSiwa.G5nsIC', NULL, '2026-11-03 10:52:03', '2026-11-03 10:52:03', NULL, 1);
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `phone`, `is_active`) VALUES (2, 'Admin Front Office', 'admin@rumahterapi.test', NULL, '$2y$12$6sOgJUMOYIa.fQU37LDOU.HJyQID/vzFzPm6dcfgWlB093nKSajmO', NULL, '2026-11-03 10:52:03', '2026-11-03 10:52:03', NULL, 1);
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `phone`, `is_active`) VALUES (3, 'Kasir Klinik', 'kasir@rumahterapi.test', NULL, '$2y$12$HROeAjOxnut3iFPxj9Rwa.y7gxh1jG5K1/n8QkjSMuHqNgjOnblrO', NULL, '2026-11-03 10:52:03', '2026-11-03 10:52:03', NULL, 1);
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `phone`, `is_active`) VALUES (4, 'Terapis Demo', 'terapis@rumahterapi.test', NULL, '$2y$12$FBXUukHGcbjdHWKKIgHZv.YTg.kYdf7nifIL.vF5vP44mfgxBg/bq', NULL, '2026-11-03 10:52:04', '2026-11-03 10:52:04', NULL, 1);

-- Data untuk tabel `model_has_roles`
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES (1, 'App\\Models\\User', 1);
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES (2, 'App\\Models\\User', 2);
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES (4, 'App\\Models\\User', 3);
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES (3, 'App\\Models\\User', 4);

-- Data untuk tabel `therapist_profiles`
INSERT INTO `therapist_profiles` (`id`, `user_id`, `license_number`, `specialty`, `created_at`, `updated_at`) VALUES (1, 4, NULL, 'Terapi Anak', '2026-11-03 10:52:04', '2026-11-03 10:52:04');

-- Data untuk tabel `therapy_types`
INSERT INTO `therapy_types` (`id`, `code`, `name`, `default_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES (1, 'TW', 'Terapi Wicara', 60, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');
INSERT INTO `therapy_types` (`id`, `code`, `name`, `default_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES (2, 'TO', 'Terapi Okupasi', 60, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');
INSERT INTO `therapy_types` (`id`, `code`, `name`, `default_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES (3, 'SI', 'Sensori Integrasi', 60, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');
INSERT INTO `therapy_types` (`id`, `code`, `name`, `default_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES (4, 'FT', 'Fisioterapi', 60, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');
INSERT INTO `therapy_types` (`id`, `code`, `name`, `default_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES (5, 'ABA', 'ABA / Perilaku', 60, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');

-- Data untuk tabel `rooms`
INSERT INTO `rooms` (`id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES (1, 'TW', 'Ruang Wicara', 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');
INSERT INTO `rooms` (`id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES (2, 'TO', 'Ruang Okupasi', 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');
INSERT INTO `rooms` (`id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES (3, 'SI', 'Ruang Sensori', 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');
INSERT INTO `rooms` (`id`, `code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES (4, 'FT', 'Ruang Fisioterapi', 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');

-- Data untuk tabel `service_catalog`
INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES (1, 'ASSESSMENT-AWAL', 'Assessment & Konsultasi', 'assessment', NULL, 300000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 'Wajib dilakukan pada pertemuan awal sebelum sesi terapi.');
INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES (2, 'EVALUASI-24-SESI', 'Evaluasi', 'assessment', NULL, 250000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 'Dilakukan setelah 24 sesi terapi.');
INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES (3, 'SESI-TW', 'Sesi 1 Jam - Terapi Wicara', 'session', 1, 160000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 'Satu sesi terapi dengan durasi 1 jam.');
INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES (4, 'SESI-TO', 'Sesi 1 Jam - Terapi Okupasi', 'session', 2, 160000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 'Satu sesi terapi dengan durasi 1 jam.');
INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES (5, 'SESI-SI', 'Sesi 1 Jam - Sensori Integrasi', 'session', 3, 160000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 'Satu sesi terapi dengan durasi 1 jam.');
INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES (6, 'SESI-FT', 'Sesi 1 Jam - Fisioterapi', 'session', 4, 160000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 'Satu sesi terapi dengan durasi 1 jam.');
INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES (7, 'SESI-ABA', 'Sesi 1 Jam - ABA / Perilaku', 'session', 5, 160000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 'Satu sesi terapi dengan durasi 1 jam.');
INSERT INTO `service_catalog` (`id`, `code`, `name`, `service_type`, `therapy_type_id`, `price`, `is_active`, `created_at`, `updated_at`, `description`) VALUES (8, 'SESI-30-MENIT', 'Sesi Terapi 30 Menit', 'session', NULL, 110000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 'Satu sesi terapi dengan durasi 30 menit.');

-- Data untuk tabel `package_catalog`
INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES (1, 'Paket 1 Jam - Paket 1 (1x Terapi)', 90, 160000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 60, 1);
INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES (2, 'Paket 1 Jam - Paket 2 (4x Terapi)', 90, 630000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 60, 4);
INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES (3, 'Paket 1 Jam - Paket 3 (8x Terapi)', 90, 1240000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 60, 8);
INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES (4, 'Paket 1 Jam - Paket 4 (12x Terapi)', 90, 1800000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 60, 12);
INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES (5, 'Paket 30 Menit - Paket 1 (1x Terapi)', 90, 110000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 30, 1);
INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES (6, 'Paket 30 Menit - Paket 2 (6x Terapi)', 90, 570000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 30, 6);
INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES (7, 'Paket 30 Menit - Paket 3 (12x Terapi)', 90, 1080000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 30, 12);
INSERT INTO `package_catalog` (`id`, `name`, `validity_days`, `price`, `is_active`, `created_at`, `updated_at`, `session_duration_minutes`, `session_quantity`) VALUES (8, 'Paket 30 Menit - Paket 4 (16x Terapi)', 90, 1360000, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04', 30, 16);

-- Data untuk tabel `products`
INSERT INTO `products` (`id`, `sku`, `name`, `price`, `stock_quantity`, `is_active`, `created_at`, `updated_at`) VALUES (1, 'MEDIA-KARTU-01', 'Kartu aktivitas komunikasi', 85000, 20, 1, '2026-11-03 10:52:04', '2026-11-03 10:52:04');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;


-- ============================================================
-- HR MANAGEMENT SYSTEM - DATABASE SCHEMA
-- ============================================================
-- Database : new_hr   (matches DB_NAME in config.php)
-- Engine   : InnoDB / utf8mb4
--
-- Base: the team's exported new_hr schema.
--
-- Applied on top of that export, so the schema matches what the
-- application code actually queries:
--
--   1. staff.gender                     - LeaveManager and the request-leave
--                                         pages select it; without it
--                                         maternity/paternity leave is
--                                         silently unavailable.
--   2. staff.user_id                    - hr/pages/staff-management.php inserts
--                                         it and staff/profile.php reads it to
--                                         change a staff password.
--   3. job_applications.cv_path,
--      linkedin_profile, facebook_profile
--                                       - HRManager::applyForJob inserts these;
--                                         the export had linkedin_url/github_url
--                                         instead, so applying for a job failed.
--   4. temporary_exit_requests.hr_remarks
--                                       - hr/pages/staff-requests.php writes it
--                                         when approving or rejecting.
--   5. staff_leave_balance_view rebuilt gender-aware. The exported view
--      hardcoded 'none' AS gender and filtered gender_specific = 'none',
--      so maternity and paternity leave never showed in staff balances.
--   6. update_leave_allocation_after_approval rebuilt. The exported trigger
--      derived is_exhausted from `days_remaining - NEW.total_days` in the same
--      SET clause that had already decremented days_remaining, subtracting the
--      days twice: using 7 of 14 annual days marked the allowance exhausted
--      with 7 days still available.
--   7. staff_handbook table added - new feature, HR uploads a staff handbook
--      (PDF only) and every staff member can view/download the current one.
--   8. public_holidays table added - new feature, HR schedules public
--      holidays (all campuses or one specific campus), excluded from leave
--      day counts and attendance working-day totals alongside weekends.
--
-- This file contains structure and safe seed data only. No staff records,
-- contract history, or session tokens from the source database.
--
-- Import:  mysql -u root < database/new_hr_schema.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `new_hr`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `new_hr`;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `appraisals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appraisals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `appraisal_year` int(11) NOT NULL,
  `form_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`form_data`)),
  `status` enum('pending','submitted_to_hod','hod_reviewed','submitted_to_hr','hr_reviewed','completed') DEFAULT 'pending',
  `staff_submitted_at` timestamp NULL DEFAULT NULL,
  `hod_reviewed_at` timestamp NULL DEFAULT NULL,
  `hod_remarks` text DEFAULT NULL,
  `hr_reviewed_at` timestamp NULL DEFAULT NULL,
  `hr_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_year` (`staff_id`,`appraisal_year`),
  CONSTRAINT `appraisals_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('present','absent','late','leave') DEFAULT 'present',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_date` (`staff_id`,`attendance_date`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `benefits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `benefits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` longtext DEFAULT NULL,
  `benefit_type` enum('health','insurance','retirement','wellness','other') DEFAULT 'other',
  `provider` varchar(150) DEFAULT NULL,
  `cost_per_employee` decimal(10,2) DEFAULT NULL,
  `coverage_details` longtext DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `certificate_name` varchar(150) NOT NULL,
  `issuing_organization` varchar(150) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `certificate_file` varchar(255) DEFAULT NULL,
  `credential_id` varchar(100) DEFAULT NULL,
  `credential_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_expiry_date` (`expiry_date`),
  CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contract_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contract_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_name` varchar(255) NOT NULL,
  `staff_email` varchar(255) DEFAULT NULL,
  `position` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `campus` varchar(100) DEFAULT NULL,
  `salary` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_issued` date NOT NULL,
  `contract_start` date NOT NULL,
  `contract_end` date NOT NULL,
  `template_type` varchar(50) DEFAULT NULL,
  `contract_file` varchar(255) DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_name` (`staff_name`),
  KEY `idx_date_issued` (`date_issued`),
  KEY `idx_campus` (`campus`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `contract_title` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `duration_label` varchar(50) NOT NULL,
  `template_body` longtext NOT NULL,
  `docx_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `application_id` (`application_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` longtext DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `budget` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_name` (`name`),
  KEY `manager_id` (`manager_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `disciplinary_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disciplinary_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `from_staff_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `severity` enum('low','medium','high') DEFAULT 'low',
  `status` enum('pending','viewed','replied','resolved') DEFAULT 'pending',
  `reply_message` text DEFAULT NULL,
  `reply_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `from_staff_id` (`from_staff_id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `disciplinary_actions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disciplinary_actions_ibfk_2` FOREIGN KEY (`from_staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `verification_token` varchar(255) NOT NULL,
  `token_expiry` datetime NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `verification_token` (`verification_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_verification_token` (`verification_token`),
  KEY `idx_is_verified` (`is_verified`),
  CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_benefits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_benefits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `benefit_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `coverage_type` varchar(100) DEFAULT NULL,
  `premium_contribution` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`staff_id`,`benefit_id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `benefit_id` (`benefit_id`),
  CONSTRAINT `employee_benefits_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_benefits_ibfk_2` FOREIGN KEY (`benefit_id`) REFERENCES `benefits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hr_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hr_id` (`hr_id`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `hr_activity_logs_ibfk_1` FOREIGN KEY (`hr_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hr_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_hr_id` (`hr_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `hr_sessions_ibfk_1` FOREIGN KEY (`hr_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `interview_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time NOT NULL,
  `interview_type` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `scheduled_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `interview_schedules_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `interview_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `interview_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT 'Interviewer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_interview_id` (`interview_id`),
  KEY `idx_staff_id` (`staff_id`),
  CONSTRAINT `interview_staff_ibfk_1` FOREIGN KEY (`interview_id`) REFERENCES `interview_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `interview_staff_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_vacancy_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','reviewed','shortlisted','rejected','accepted') DEFAULT 'pending',
  `applied_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `resume_url` varchar(255) DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `linkedin_profile` varchar(255) DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `facebook_profile` varchar(255) DEFAULT NULL,
  `cover_letter` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nysc_certificate` varchar(255) DEFAULT NULL,
  `degree_certificate` varchar(255) DEFAULT NULL,
  `masters_certificate` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_application` (`job_vacancy_id`,`user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_job_vacancy_id` (`job_vacancy_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`job_vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_applications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_vacancies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_vacancies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `salary_range` varchar(100) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT NULL,
  `posted_by` int(11) NOT NULL,
  `posted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `deadline` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `applicants_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_posted_date` (`posted_date`),
  KEY `idx_posted_by` (`posted_by`),
  CONSTRAINT `job_vacancies_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `late_arrival_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `late_arrival_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `late_arrival_time` time NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `hr_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `late_arrival_requests_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leave_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `days_allocated` int(11) NOT NULL,
  `days_used` int(11) DEFAULT 0,
  `days_remaining` int(11) NOT NULL,
  `is_exhausted` tinyint(1) DEFAULT 0,
  `has_used_one_time` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_allocation` (`staff_id`,`leave_type_id`,`year`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_year` (`year`),
  KEY `idx_is_exhausted` (`is_exhausted`),
  CONSTRAINT `leave_allocations_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_allocations_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL DEFAULT 1,
  `request_date` date NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `substitute_staff_id` int(11) DEFAULT NULL,
  `supporting_documents` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `substitute_status` enum('pending','accepted','rejected') DEFAULT NULL,
  `days_used` int(11) DEFAULT 0,
  `hod_remarks` text DEFAULT NULL,
  `hr_remarks` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `substitute_staff_id` (`substitute_staff_id`),
  KEY `leave_requests_ibfk_approved_by` (`approved_by`),
  KEY `idx_leave_type_id` (`leave_type_id`),
  CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`substitute_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_ibfk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_ibfk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/  /*!50003 TRIGGER `update_leave_allocation_after_approval`
AFTER UPDATE ON `leave_requests`
FOR EACH ROW
BEGIN
    DECLARE v_year INT;
    DECLARE v_allocated_days INT DEFAULT 0;
    DECLARE v_is_one_time TINYINT(1) DEFAULT 0;
    DECLARE v_new_remaining INT DEFAULT 0;

    SET v_year = YEAR(NEW.start_date);

    IF NEW.status = 'approved' AND OLD.status <> 'approved' THEN
        SELECT days_allocated, is_one_time
          INTO v_allocated_days, v_is_one_time
          FROM leave_types
         WHERE id = NEW.leave_type_id;

        INSERT INTO leave_allocations
            (staff_id, leave_type_id, year, days_allocated, days_used, days_remaining, has_used_one_time)
        VALUES
            (NEW.staff_id, NEW.leave_type_id, v_year, v_allocated_days, 0, v_allocated_days, 0)
        ON DUPLICATE KEY UPDATE id = id;

        SELECT days_remaining - NEW.total_days
          INTO v_new_remaining
          FROM leave_allocations
         WHERE staff_id      = NEW.staff_id
           AND leave_type_id = NEW.leave_type_id
           AND year          = v_year;

        UPDATE leave_allocations
           SET days_used         = days_used + NEW.total_days,
               days_remaining    = v_new_remaining,
               is_exhausted      = IF(v_new_remaining <= 0, 1, 0),
               has_used_one_time = IF(v_is_one_time = 1, 1, has_used_one_time),
               updated_at        = CURRENT_TIMESTAMP
         WHERE staff_id      = NEW.staff_id
           AND leave_type_id = NEW.leave_type_id
           AND year          = v_year;
    END IF;

    IF OLD.status = 'approved' AND NEW.status IN ('rejected', 'cancelled') THEN
        SELECT days_remaining + NEW.total_days
          INTO v_new_remaining
          FROM leave_allocations
         WHERE staff_id      = NEW.staff_id
           AND leave_type_id = NEW.leave_type_id
           AND year          = v_year;

        UPDATE leave_allocations
           SET days_used      = GREATEST(0, days_used - NEW.total_days),
               days_remaining = v_new_remaining,
               is_exhausted   = IF(v_new_remaining > 0, 0, 1),
               updated_at     = CURRENT_TIMESTAMP
         WHERE staff_id      = NEW.staff_id
           AND leave_type_id = NEW.leave_type_id
           AND year          = v_year;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `days_allocated` int(11) DEFAULT 0,
  `allocation_unit` enum('days','weeks') DEFAULT 'days',
  `min_days` int(11) DEFAULT NULL,
  `max_days` int(11) DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT 1,
  `requires_approval` tinyint(1) DEFAULT 1,
  `is_unlimited` tinyint(1) DEFAULT 0,
  `is_one_time` tinyint(1) DEFAULT 0,
  `gender_specific` enum('none','female','male') DEFAULT 'none',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_staff_id` int(11) NOT NULL,
  `to_staff_id` int(11) DEFAULT NULL,
  `to_hod` int(11) DEFAULT NULL,
  `to_hr` int(11) DEFAULT NULL,
  `to_management` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('complaint','enquiry','other') DEFAULT 'other',
  `status` enum('sent','read','replied') DEFAULT 'sent',
  `reply_message` text DEFAULT NULL,
  `reply_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `from_staff_id` (`from_staff_id`),
  KEY `to_staff_id` (`to_staff_id`),
  KEY `to_hod` (`to_hod`),
  KEY `to_hr` (`to_hr`),
  KEY `to_management` (`to_management`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`from_staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`to_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reset_token` varchar(255) NOT NULL,
  `token_expiry` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reset_token` (`reset_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_reset_token` (`reset_token`),
  KEY `idx_token_expiry` (`token_expiry`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `month_year` varchar(20) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `medical_allowance` decimal(10,2) DEFAULT 0.00,
  `rent_allowance` decimal(10,2) DEFAULT 0.00,
  `other_allowance` decimal(10,2) DEFAULT 0.00,
  `gross_salary` decimal(10,2) NOT NULL,
  `tax_deduction` decimal(10,2) DEFAULT 0.00,
  `attendance_deduction` decimal(10,2) DEFAULT 0.00,
  `disciplinary_deduction` decimal(10,2) DEFAULT 0.00,
  `cooperative_deduction` decimal(10,2) DEFAULT 0.00,
  `other_deduction` decimal(10,2) DEFAULT 0.00,
  `total_deduction` decimal(10,2) GENERATED ALWAYS AS (coalesce(`tax_deduction`,0) + coalesce(`attendance_deduction`,0) + coalesce(`disciplinary_deduction`,0) + coalesce(`cooperative_deduction`,0) + coalesce(`other_deduction`,0)) STORED,
  `amount_payable` decimal(10,2) GENERATED ALWAYS AS (`gross_salary` - coalesce(`total_deduction`,0)) STORED,
  `date_paid` date DEFAULT NULL,
  `payment_slip_generated` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_month` (`staff_id`,`month_year`),
  CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `performance_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `performance_goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `goal_title` varchar(150) NOT NULL,
  `description` longtext DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `target_value` varchar(100) DEFAULT NULL,
  `progress_percentage` int(11) DEFAULT 0,
  `status` enum('not-started','in-progress','completed','failed') DEFAULT 'not-started',
  `created_by` int(11) DEFAULT NULL,
  `review_comments` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `performance_goals_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `performance_goals_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` longtext DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `salary_range_min` decimal(12,2) DEFAULT NULL,
  `salary_range_max` decimal(12,2) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`),
  KEY `idx_title` (`title`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promotion_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promotion_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `request_type` enum('promotion','transfer') NOT NULL,
  `from_position` varchar(100) DEFAULT NULL,
  `to_position` varchar(100) NOT NULL,
  `from_department` varchar(100) DEFAULT NULL,
  `to_department` varchar(100) NOT NULL,
  `reason` longtext DEFAULT NULL,
  `requested_date` date NOT NULL,
  `effective_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `approver_id` int(11) DEFAULT NULL,
  `approval_notes` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_status` (`status`),
  KEY `approver_id` (`approver_id`),
  CONSTRAINT `promotion_transfers_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_transfers_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Added: public holidays HR can schedule per campus (or all campuses),
-- excluded from leave day counts and attendance working-day totals.
DROP TABLE IF EXISTS `public_holidays`;
CREATE TABLE `public_holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `holiday_date` date NOT NULL,
  `campus_location` varchar(100) DEFAULT NULL COMMENT 'NULL = applies to all campuses',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_holiday_date` (`holiday_date`),
  KEY `idx_campus_location` (`campus_location`),
  CONSTRAINT `public_holidays_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `lincoln_email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `campus_location` varchar(100) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `user_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `lincoln_email` (`lincoln_email`),
  KEY `idx_staff_user_id` (`user_id`),
  CONSTRAINT `staff_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Added: staff handbook uploaded by HR, downloadable by all staff as PDF.
DROP TABLE IF EXISTS `staff_handbook`;
CREATE TABLE `staff_handbook` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_uploaded_at` (`uploaded_at`),
  CONSTRAINT `staff_handbook_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `staff_leave_balance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_leave_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `total_annual_leave` int(11) DEFAULT 14,
  `used_leave` int(11) DEFAULT 0,
  `available_leave` int(11) DEFAULT 14,
  `year` int(11) DEFAULT year(curdate()),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  CONSTRAINT `staff_leave_balance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_leave_balance_view`;
/*!50001 DROP VIEW IF EXISTS `staff_leave_balance_view`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `staff_leave_balance_view` AS SELECT
 1 AS `staff_id`,
  1 AS `first_name`,
  1 AS `last_name`,
  1 AS `email`,
  1 AS `gender`,
  1 AS `leave_type_id`,
  1 AS `leave_type`,
  1 AS `description`,
  1 AS `is_unlimited`,
  1 AS `is_one_time`,
  1 AS `year`,
  1 AS `days_allocated`,
  1 AS `days_used`,
  1 AS `days_remaining`,
  1 AS `is_exhausted`,
  1 AS `has_used_one_time`,
  1 AS `can_request` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `staff_training_attendees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_training_attendees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `attendance_status` enum('scheduled','attended','absent','excused') DEFAULT 'scheduled',
  `decline_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_training` (`training_id`,`staff_id`),
  KEY `idx_training_id` (`training_id`),
  KEY `idx_staff_id` (`staff_id`),
  CONSTRAINT `staff_training_attendees_ibfk_1` FOREIGN KEY (`training_id`) REFERENCES `staff_trainings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_training_attendees_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_trainings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_trainings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `training_date` date NOT NULL,
  `training_time` time NOT NULL,
  `venue` varchar(255) NOT NULL,
  `training_type` varchar(100) DEFAULT 'workshop',
  `trainer_name` varchar(255) DEFAULT NULL,
  `duration_hours` decimal(5,2) DEFAULT 0.00,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `scheduled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_training_date` (`training_date`),
  KEY `idx_scheduled_by` (`scheduled_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temporary_exit_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temporary_exit_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `reason` text NOT NULL,
  `substitute_staff_id` int(11) DEFAULT NULL,
  `supporting_document` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `substitute_status` enum('pending','accepted','rejected') DEFAULT NULL,
  `hod_remarks` text DEFAULT NULL,
  `hr_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `substitute_staff_id` (`substitute_staff_id`),
  CONSTRAINT `temporary_exit_requests_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `temporary_exit_requests_ibfk_2` FOREIGN KEY (`substitute_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `status` enum('pending','accepted','declined') DEFAULT 'pending',
  `decline_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_training` (`staff_id`,`training_id`),
  KEY `training_id` (`training_id`),
  CONSTRAINT `training_attendance_ibfk_1` FOREIGN KEY (`training_id`) REFERENCES `training_programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_attendance_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `training_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `training_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `training_date` date NOT NULL,
  `training_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `from_staff_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `from_staff_id` (`from_staff_id`),
  CONSTRAINT `training_programs_ibfk_1` FOREIGN KEY (`from_staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bio` text DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `resume_url` varchar(255) DEFAULT NULL,
  `profile_picture_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role` enum('student','hr','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_role` (`role`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id_unique` (`google_id`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Users table with support for email/password and Google OAuth authentication';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP FUNCTION IF EXISTS `can_request_leave` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE FUNCTION `can_request_leave`(`p_staff_id` INT, `p_leave_type_id` INT, `p_days_requested` INT, `p_year` INT) RETURNS tinyint(1)
    READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE v_is_unlimited BOOLEAN;
    DECLARE v_is_one_time BOOLEAN;
    DECLARE v_days_remaining INT;
    DECLARE v_is_exhausted BOOLEAN;
    DECLARE v_has_used_one_time BOOLEAN;
    DECLARE v_can_request BOOLEAN DEFAULT 0;
    
    
    SELECT is_unlimited, is_one_time INTO v_is_unlimited, v_is_one_time
    FROM leave_types
    WHERE id = p_leave_type_id;
    
    
    IF v_is_unlimited = 1 THEN
        RETURN 1;
    END IF;
    
    
    IF EXISTS (
        SELECT 1 FROM leave_allocations 
        WHERE staff_id = p_staff_id 
        AND leave_type_id = p_leave_type_id 
        AND year = p_year
    ) THEN
        SELECT days_remaining, is_exhausted, has_used_one_time 
        INTO v_days_remaining, v_is_exhausted, v_has_used_one_time
        FROM leave_allocations
        WHERE staff_id = p_staff_id 
        AND leave_type_id = p_leave_type_id 
        AND year = p_year;
        
        
        IF v_is_one_time = 1 AND v_has_used_one_time = 1 THEN
            RETURN 0;
        END IF;
        
        
        IF v_is_exhausted = 1 OR v_days_remaining < p_days_requested THEN
            RETURN 0;
        END IF;
        
        RETURN 1;
    ELSE
        
        RETURN 0;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `initialize_staff_leave_allocations` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE PROCEDURE `initialize_staff_leave_allocations`(IN `p_year` INT)
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_staff_id INT;
    DECLARE v_staff_gender VARCHAR(10);
    DECLARE gender_column_exists INT DEFAULT 0;
    
    
    SELECT COUNT(*) INTO gender_column_exists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'staff'
    AND COLUMN_NAME = 'gender';
    
    
    IF gender_column_exists > 0 THEN
        BEGIN
            DECLARE staff_cursor CURSOR FOR 
                SELECT id, COALESCE(gender, 'none') FROM staff WHERE status = 'active';
            
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
            
            OPEN staff_cursor;
            
            staff_loop: LOOP
                FETCH staff_cursor INTO v_staff_id, v_staff_gender;
                
                IF done THEN
                    LEAVE staff_loop;
                END IF;
                
                
                INSERT INTO leave_allocations (staff_id, leave_type_id, year, days_allocated, days_used, days_remaining, is_exhausted, has_used_one_time)
                SELECT 
                    v_staff_id,
                    lt.id,
                    p_year,
                    lt.days_allocated,
                    0,
                    lt.days_allocated,
                    0,
                    0
                FROM leave_types lt
                WHERE lt.is_active = 1
                AND (
                    lt.gender_specific = 'none' 
                    OR (lt.gender_specific = 'female' AND v_staff_gender = 'female')
                    OR (lt.gender_specific = 'male' AND v_staff_gender = 'male')
                )
                ON DUPLICATE KEY UPDATE
                    days_allocated = VALUES(days_allocated),
                    updated_at = CURRENT_TIMESTAMP;
                    
            END LOOP;
            
            CLOSE staff_cursor;
        END;
    ELSE
        
        BEGIN
            DECLARE staff_cursor_no_gender CURSOR FOR 
                SELECT id FROM staff WHERE status = 'active';
            
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
            
            OPEN staff_cursor_no_gender;
            
            staff_loop_no_gender:LOOP
                FETCH staff_cursor_no_gender INTO v_staff_id;
                
                IF done THEN
                    LEAVE staff_loop_no_gender;
                END IF;
                
                
                INSERT INTO leave_allocations (staff_id, leave_type_id, year, days_allocated, days_used, days_remaining, is_exhausted, has_used_one_time)
                SELECT 
                    v_staff_id,
                    lt.id,
                    p_year,
                    lt.days_allocated,
                    0,
                    lt.days_allocated,
                    0,
                    0
                FROM leave_types lt
                WHERE lt.is_active = 1
                AND lt.gender_specific = 'none'
                ON DUPLICATE KEY UPDATE
                    days_allocated = VALUES(days_allocated),
                    updated_at = CURRENT_TIMESTAMP;
                    
            END LOOP;
            
            CLOSE staff_cursor_no_gender;
        END;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `staff_leave_balance_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */

/*!50001 VIEW `staff_leave_balance_view` AS select `s`.`id` AS `staff_id`,`s`.`first_name` AS `first_name`,`s`.`last_name` AS `last_name`,`s`.`email` AS `email`,coalesce(`s`.`gender`,'none') AS `gender`,`lt`.`id` AS `leave_type_id`,`lt`.`name` AS `leave_type`,`lt`.`description` AS `description`,`lt`.`is_unlimited` AS `is_unlimited`,`lt`.`is_one_time` AS `is_one_time`,coalesce(`la`.`year`,year(curdate())) AS `year`,coalesce(`la`.`days_allocated`,`lt`.`days_allocated`) AS `days_allocated`,coalesce(`la`.`days_used`,0) AS `days_used`,coalesce(`la`.`days_remaining`,`lt`.`days_allocated`) AS `days_remaining`,coalesce(`la`.`is_exhausted`,0) AS `is_exhausted`,coalesce(`la`.`has_used_one_time`,0) AS `has_used_one_time`,case when `lt`.`is_unlimited` = 1 then 1 when `lt`.`is_one_time` = 1 and coalesce(`la`.`has_used_one_time`,0) = 1 then 0 when coalesce(`la`.`is_exhausted`,0) = 1 then 0 when coalesce(`la`.`days_remaining`,`lt`.`days_allocated`) <= 0 then 0 else 1 end AS `can_request` from ((`staff` `s` join `leave_types` `lt`) left join `leave_allocations` `la` on(`la`.`staff_id` = `s`.`id` and `la`.`leave_type_id` = `lt`.`id` and `la`.`year` = year(curdate()))) where `s`.`status` = 'active' and `lt`.`is_active` = 1 and (`lt`.`gender_specific` = 'none' or `lt`.`gender_specific` = 'female' and `s`.`gender` = 'female' or `lt`.`gender_specific` = 'male' and `s`.`gender` = 'male') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ============================================================
-- SEED DATA - LEAVE TYPES
-- ============================================================

INSERT INTO `leave_types`
  (name, description, days_allocated, allocation_unit, min_days, max_days,
   is_paid, requires_approval, is_unlimited, is_one_time, gender_specific, is_active)
VALUES
('Annual Leave', 'Annual vacation leave - 14 days at a stretch, minimum 7 days', 14, 'days', 7, 14, 1, 1, 0, 0, 'none', 1),
('Maternity Leave', 'For female staff during pregnancy and childbirth - 2 to 4 weeks', 28, 'days', 14, 28, 1, 1, 0, 0, 'female', 1),
('Paternity Leave', 'For male staff - 2 days (birth day and dedication day)', 2, 'days', 1, 2, 1, 1, 0, 0, 'male', 1),
('Compassionate Leave', 'For disasters (death, fire, flood, etc.) - 2 days', 2, 'days', 1, 2, 1, 1, 0, 0, 'none', 1),
('Serious Illness Leave', 'For serious illness - 2 days (alternative: use annual leave)', 2, 'days', 1, 2, 1, 1, 0, 0, 'none', 1),
('Marriage Leave', 'First time marriage only - 3 days', 3, 'days', 1, 3, 1, 1, 0, 1, 'none', 1),
('Unpaid Leave', 'Unpaid leave - available to anybody at any time', 0, 'days', NULL, NULL, 0, 1, 1, 0, 'none', 1),
('Special Leave (Conference)', 'For conferences, seminars, and professional development', 5, 'days', 1, 5, 1, 1, 0, 0, 'none', 1),
('Religious Leave', 'Religious observance leave - 30 days', 30, 'days', 1, 30, 1, 1, 0, 0, 'none', 1)
ON DUPLICATE KEY UPDATE
  description       = VALUES(description),
  days_allocated    = VALUES(days_allocated),
  allocation_unit   = VALUES(allocation_unit),
  min_days          = VALUES(min_days),
  max_days          = VALUES(max_days),
  is_paid           = VALUES(is_paid),
  is_unlimited      = VALUES(is_unlimited),
  is_one_time       = VALUES(is_one_time),
  gender_specific   = VALUES(gender_specific),
  updated_at        = CURRENT_TIMESTAMP;

-- ============================================================
-- SEED DATA - DEFAULT HR ADMIN
-- ============================================================
-- Login at /hr/  with:  admin@lincoln.edu.ng  /  Admin@123
-- CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN.

INSERT INTO `users` (first_name, last_name, email, phone, password, is_active)
VALUES ('System', 'Administrator', 'admin@lincoln.edu.ng', '08000000000',
        '$2y$10$B5QYet6PR5GDhNYs2n6ZaORdiX.qt.IAlqv4XKONcJ9L1kirfEixy', 1)
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO `user_roles` (user_id, role)
SELECT id, 'hr' FROM `users` WHERE email = 'admin@lincoln.edu.ng'
ON DUPLICATE KEY UPDATE role = role;

INSERT INTO `user_profiles` (user_id)
SELECT id FROM `users` WHERE email = 'admin@lincoln.edu.ng'
ON DUPLICATE KEY UPDATE user_id = user_id;

-- ============================================================
-- END OF SCHEMA
-- ============================================================

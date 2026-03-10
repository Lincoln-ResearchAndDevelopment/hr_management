-- ============================================
-- HR SYSTEM - COMPLETE DATABASE SCHEMA
-- ============================================
-- Created: January 27, 2026
-- Description: Consolidated database schema for HR Management System
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `hr_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hr_system`;

-- ============================================
-- TABLE: users (Job Applicants)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `google_id` VARCHAR(255) NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id_unique` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Users table with support for email/password and Google OAuth authentication';

-- ============================================
-- TABLE: hr_users (HR Personnel)
-- ============================================
CREATE TABLE IF NOT EXISTS `hr_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','hr_manager','hr_staff') DEFAULT 'hr_staff',
  `department` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: staff (Hired Employees)
-- ============================================
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `lincoln_email` varchar(255) DEFAULT NULL,
  `password` VARCHAR(255) NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `campus_location` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `salary` decimal(15,2) DEFAULT NULL,
  `status` enum('active','inactive','terminated') DEFAULT 'active',
  `user_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `lincoln_email` (`lincoln_email`),
  KEY `user_id` (`user_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: job_vacancies
-- ============================================
CREATE TABLE IF NOT EXISTS `job_vacancies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `job_type` enum('full-time','part-time','contract','internship') DEFAULT 'full-time',
  `description` longtext NOT NULL,
  `requirements` longtext DEFAULT NULL,
  `responsibilities` longtext DEFAULT NULL,
  `salary_range` varchar(100) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('open','closed','filled') DEFAULT 'open',
  `posted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `posted_by` (`posted_by`),
  KEY `idx_status` (`status`),
  KEY `idx_deadline` (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: job_applications
-- ============================================
CREATE TABLE IF NOT EXISTS `job_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_vacancy_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cover_letter` longtext DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `additional_documents` varchar(255) DEFAULT NULL,
  `nysc_certificate` varchar(255) DEFAULT NULL,
  `degree_certificate` varchar(255) DEFAULT NULL,
  `masters_certificate` varchar(255) DEFAULT NULL,
  `status` enum('pending','reviewed','interview','accepted','rejected','withdrawn') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `facebook_profile` VARCHAR(255) NULL DEFAULT NULL,
  `twitter_profile` VARCHAR(255) NULL DEFAULT NULL,
  `linkedin_profile` VARCHAR(255) NULL DEFAULT NULL,
  `instagram_profile` VARCHAR(255) NULL DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `job_vacancy_id` (`job_vacancy_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`job_vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_applications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: interview_schedules
-- ============================================
CREATE TABLE IF NOT EXISTS `interview_schedules` (
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
  KEY `scheduled_by` (`scheduled_by`),
  CONSTRAINT `interview_schedules_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: interview_staff
-- ============================================
CREATE TABLE IF NOT EXISTS `interview_staff` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `interview_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `role` VARCHAR(100) DEFAULT 'Interviewer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_interview_staff` (`interview_id`, `staff_id`),
    FOREIGN KEY (`interview_id`) REFERENCES `interview_schedules`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: attendance_records
-- ============================================
CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('present','absent','late','leave') DEFAULT 'present',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `idx_attendance_date` (`attendance_date`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: payroll
-- ============================================
CREATE TABLE IF NOT EXISTS `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `month_year` varchar(20) NOT NULL,
  `duration_days` int(11) DEFAULT 0,
  `basic_salary` decimal(15,2) NOT NULL,
  `medical_allowance` decimal(15,2) DEFAULT 0.00,
  `rent_allowance` decimal(15,2) DEFAULT 0.00,
  `other_allowance` decimal(15,2) DEFAULT 0.00,
  `gross_salary` decimal(15,2) NOT NULL,
  `tax_deduction` decimal(15,2) DEFAULT 0.00,
  `attendance_deduction` decimal(15,2) DEFAULT 0.00,
  `disciplinary_deduction` decimal(15,2) DEFAULT 0.00,
  `cooperative_deduction` decimal(15,2) DEFAULT 0.00,
  `other_deduction` decimal(15,2) DEFAULT 0.00,
  `amount_payable` decimal(15,2) NOT NULL,
  `date_paid` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `idx_month_year` (`month_year`),
  CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: appraisals
-- ============================================
CREATE TABLE IF NOT EXISTS `appraisals` (
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
  KEY `staff_id` (`staff_id`),
  KEY `idx_appraisal_year` (`appraisal_year`),
  CONSTRAINT `appraisals_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: messages
-- ============================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_staff_id` int(11) DEFAULT NULL,
  `to_staff_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `status` enum('sent','read','replied') DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `from_staff_id` (`from_staff_id`),
  KEY `to_staff_id` (`to_staff_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`from_staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`to_staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: disciplinary_actions
-- ============================================
CREATE TABLE IF NOT EXISTS `disciplinary_actions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `staff_id` INT NOT NULL,
  `from_staff_id` INT NOT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `severity` ENUM('low', 'medium', 'high') DEFAULT 'low',
  `status` ENUM('pending', 'viewed', 'replied', 'resolved') DEFAULT 'pending',
  `reply_message` TEXT,
  `reply_sent_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`from_staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE,
  INDEX `idx_staff_id` (`staff_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: training_programs
-- ============================================
CREATE TABLE IF NOT EXISTS `training_programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `training_date` date NOT NULL,
  `training_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `training_type` varchar(100) DEFAULT 'workshop',
  `duration_hours` int(11) DEFAULT 0,
  `capacity` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_training_date` (`training_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: training_attendance
-- ============================================
CREATE TABLE IF NOT EXISTS `training_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `status` enum('pending','accepted','declined') DEFAULT 'pending',
  `decline_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_training_staff` (`training_id`,`staff_id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `training_attendance_ibfk_1` FOREIGN KEY (`training_id`) REFERENCES `training_programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_attendance_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: staff_trainings (for HR-scheduled trainings)
-- ============================================
CREATE TABLE IF NOT EXISTS `staff_trainings` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `training_date` DATE NOT NULL,
    `training_time` TIME NOT NULL,
    `venue` VARCHAR(255) NOT NULL,
    `training_type` VARCHAR(100) DEFAULT 'workshop',
    `trainer_name` VARCHAR(255),
    `duration_hours` DECIMAL(5,2) DEFAULT 0,
    `is_mandatory` TINYINT(1) DEFAULT 0,
    `scheduled_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_training_date` (`training_date`),
    INDEX `idx_scheduled_by` (`scheduled_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: staff_training_attendees
-- ============================================
CREATE TABLE IF NOT EXISTS `staff_training_attendees` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `training_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `attendance_status` ENUM('scheduled', 'attended', 'absent', 'excused') DEFAULT 'scheduled',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_staff_training` (`training_id`, `staff_id`),
    FOREIGN KEY (`training_id`) REFERENCES `staff_trainings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE,
    INDEX `idx_training_id` (`training_id`),
    INDEX `idx_staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: permissions (Staff Requests)
-- ============================================
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `request_type` enum('leave','late_arrival','temporary_exit','permission') NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `idx_status` (`status`),
  CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: audit_logs
-- ============================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: email_verifications
-- ============================================
CREATE TABLE IF NOT EXISTS `email_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `verification_token` varchar(255) NOT NULL,
  `token_expiry` datetime NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `verification_token` (`verification_token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: hr_activity_logs
-- ============================================
CREATE TABLE IF NOT EXISTS `hr_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hr_id` (`hr_id`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `hr_activity_logs_ibfk_1` FOREIGN KEY (`hr_id`) REFERENCES `hr_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- TABLE: hr_sessions
-- ============================================
CREATE TABLE IF NOT EXISTS `hr_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `hr_id` (`hr_id`),
  CONSTRAINT `hr_sessions_ibfk_1` FOREIGN KEY (`hr_id`) REFERENCES `hr_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- COMMIT TRANSACTION
-- ============================================
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

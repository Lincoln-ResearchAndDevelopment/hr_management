-- ============================================================
-- Migration: staff ID number used to match attendance uploads
-- ============================================================
-- Attendance files are matched to staff by this ID (the biometric
-- "Enroll ID", e.g. 000000190) instead of by name, because two people
-- can share a name. Stored as text so leading zeros are kept.
-- Safe for live data: only adds a nullable column and a unique index.
--
-- Import:  mysql -u root new_hr < database/migration_staff_attendance_id.sql
-- ============================================================

ALTER TABLE `staff`
  ADD COLUMN `attendance_id` varchar(20) DEFAULT NULL AFTER `lincoln_email`,
  ADD UNIQUE KEY `uniq_staff_attendance_id` (`attendance_id`);

-- ============================================================
-- Migration: HR assigns the Head of Department role + staff type
-- ============================================================
-- * staff.staff_type  - 'academic' or 'non_academic'. Only academic staff can be
--                       a Head of Department, and non-academic staff need HR
--                       approval only for leave (no HOD step).
-- * staff.is_hod      - set by HR on the Staff Management page.
-- * leave_requests.hod_skipped - 1 when the HOD step was not needed (non-academic
--                       staff, no HOD assigned, or the requester is the HOD), so
--                       the request goes straight to HR.
--
-- Existing staff become 'academic'. Anyone whose position already says
-- "Head of Department" is carried over as an assigned HOD, so nothing changes
-- until HR reviews the roles.
--
-- Safe for live data: only adds columns.
-- Import:  mysql -u root new_hr < database/migration_hod_assignment.sql
-- ============================================================

ALTER TABLE `staff`
  ADD COLUMN `staff_type` enum('academic','non_academic') NOT NULL DEFAULT 'academic' AFTER `department`,
  ADD COLUMN `is_hod` tinyint(1) NOT NULL DEFAULT 0 AFTER `staff_type`;

ALTER TABLE `leave_requests`
  ADD COLUMN `hod_skipped` tinyint(1) NOT NULL DEFAULT 0 AFTER `hod_remarks`;

UPDATE `staff` SET `is_hod` = 1 WHERE LOWER(`position`) LIKE '%head of department%';

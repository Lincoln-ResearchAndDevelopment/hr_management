-- ============================================================
-- Migration: HOD approval stage for leave requests
-- ============================================================
-- Run this against an existing new_hr database to add the
-- Head of Department review step ahead of HR's final decision,
-- without dropping/recreating any tables (safe for live data).
--
-- Import:  mysql -u root new_hr < database/migration_hod_leave_approval.sql
-- ============================================================

ALTER TABLE `leave_requests`
  MODIFY `status` enum('pending','hod_approved','hod_rejected','approved','rejected') DEFAULT 'pending';

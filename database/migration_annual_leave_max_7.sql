-- ============================================================
-- Migration: Annual Leave - up to 7 days per request, fewer is allowed
-- ============================================================
-- Before: a single annual leave request had to be at least 7 days (and at most 14).
-- Now:    a staff member can ask for anything from 1 to 7 days at a time, but not more
--         than 7 days in one request. The yearly allowance (14 days) is unchanged, so
--         a second request can be made for the rest.
--
-- HR can also change these two numbers on the Leave Types page.
-- Import:  mysql -u root new_hr < database/migration_annual_leave_max_7.sql
-- ============================================================

UPDATE `leave_types`
SET `min_days` = 1, `max_days` = 7
WHERE `name` = 'Annual Leave';

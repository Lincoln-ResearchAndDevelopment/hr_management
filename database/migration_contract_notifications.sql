-- ============================================================
-- Migration: tell staff when a contract has been issued to them
-- ============================================================
-- * contract_history.staff_id            - the staff member the contract was issued to
--                                          (matched by their email / Lincoln email).
-- * contract_history.staff_downloaded_at - set when the staff member downloads it from
--                                          their dashboard. Until then the dashboard shows
--                                          a "contract issued" notice.
--
-- Safe for live data: only adds columns. (The app also adds these automatically the
-- first time a contract is issued, so running this is optional.)
-- Import:  mysql -u root new_hr < database/migration_contract_notifications.sql
-- ============================================================

ALTER TABLE `contract_history`
  ADD COLUMN `staff_id` int(11) DEFAULT NULL AFTER `staff_email`,
  ADD COLUMN `staff_downloaded_at` datetime DEFAULT NULL AFTER `contract_file`,
  ADD KEY `idx_contract_staff_id` (`staff_id`);

-- Link contracts that were issued before this feature to the matching active staff, and
-- mark them as already downloaded so nobody gets a notice for an old contract.
UPDATE `contract_history` ch
JOIN `staff` s ON (s.email = ch.staff_email OR s.lincoln_email = ch.staff_email) AND s.status = 'active'
SET ch.staff_id = s.id, ch.staff_downloaded_at = NOW()
WHERE ch.staff_id IS NULL;

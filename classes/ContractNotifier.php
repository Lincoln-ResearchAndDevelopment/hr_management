<?php

/**
 * Contract notifications for staff.
 *
 * When HR issues a contract it is linked to the staff member (contract_history.staff_id).
 * The staff member is emailed the letter and sees a notice on their dashboard until they
 * download it (contract_history.staff_downloaded_at is set by staff/download-contract.php).
 */
class ContractNotifier
{
    /**
     * Adds the columns this feature needs if the migration (database/migration_contract_notifications.sql)
     * has not been run yet, so issuing a contract never fails on an older database.
     */
    public static function ensureSchema($conn)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $has_staff_id = $conn->query("SHOW COLUMNS FROM contract_history LIKE 'staff_id'");
        if ($has_staff_id && $has_staff_id->num_rows === 0) {
            $conn->query("ALTER TABLE contract_history ADD COLUMN staff_id int(11) DEFAULT NULL AFTER staff_email, ADD KEY idx_contract_staff_id (staff_id)");
        }
        $has_downloaded = $conn->query("SHOW COLUMNS FROM contract_history LIKE 'staff_downloaded_at'");
        if ($has_downloaded && $has_downloaded->num_rows === 0) {
            $conn->query("ALTER TABLE contract_history ADD COLUMN staff_downloaded_at datetime DEFAULT NULL AFTER contract_file");
        }
    }

    /**
     * The active staff member a contract email address belongs to (personal or Lincoln email), or null.
     */
    public static function findStaffId($conn, $email)
    {
        $stmt = $conn->prepare("SELECT id FROM staff WHERE (email = ? OR lincoln_email = ?) AND status = 'active' LIMIT 1");
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Contracts issued to this staff member that they have not downloaded yet, newest first.
     */
    public static function pendingForStaff($conn, $staff_id)
    {
        self::ensureSchema($conn);
        $stmt = $conn->prepare(
            "SELECT id, position, contract_start, contract_end, date_issued
             FROM contract_history
             WHERE staff_id = ? AND staff_downloaded_at IS NULL AND contract_file IS NOT NULL AND contract_file <> ''
             ORDER BY id DESC"
        );
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

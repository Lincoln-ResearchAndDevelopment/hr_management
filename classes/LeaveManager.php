<?php

/**
 * Leave Manager Class
 * Handles leave type allocations, balance tracking, and leave requests
 */

class LeaveManager
{
    private $conn;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Get all active leave types
     */
    public function getActiveLeaveTypes()
    {
        $query = "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name ASC";
        $result = $this->conn->query($query);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get leave types available for a specific staff member (considering gender)
     */
    public function getAvailableLeaveTypesForStaff($staff_id)
    {
        // Check if gender column exists in staff table
        $gender_exists = false;
        $check_query = "SHOW COLUMNS FROM staff LIKE 'gender'";
        $check_result = $this->conn->query($check_query);
        if ($check_result && $check_result->num_rows > 0) {
            $gender_exists = true;
        }

        if ($gender_exists) {
            // Query with gender support
            $query = "SELECT lt.*, 
                        COALESCE(la.days_allocated, lt.days_allocated) as staff_days_allocated,
                        COALESCE(la.days_used, 0) as days_used,
                        COALESCE(la.days_remaining, lt.days_allocated) as days_remaining,
                        COALESCE(la.is_exhausted, 0) as is_exhausted,
                        COALESCE(la.has_used_one_time, 0) as has_used_one_time,
                        CASE 
                            WHEN lt.is_unlimited = 1 THEN 1
                            WHEN lt.is_one_time = 1 AND COALESCE(la.has_used_one_time, 0) = 1 THEN 0
                            WHEN COALESCE(la.is_exhausted, 0) = 1 THEN 0
                            WHEN COALESCE(la.days_remaining, lt.days_allocated) <= 0 THEN 0
                            ELSE 1
                        END as can_request
                    FROM leave_types lt
                    LEFT JOIN staff s ON s.id = ?
                    LEFT JOIN leave_allocations la ON la.staff_id = s.id 
                        AND la.leave_type_id = lt.id 
                        AND la.year = YEAR(CURDATE())
                    WHERE lt.is_active = 1
                        AND (
                            lt.gender_specific = 'none'
                            OR (lt.gender_specific = 'female' AND s.gender = 'female')
                            OR (lt.gender_specific = 'male' AND s.gender = 'male')
                        )
                    ORDER BY lt.name ASC";
        } else {
            // Query without gender support - only show non-gender-specific leave types
            $query = "SELECT lt.*, 
                        COALESCE(la.days_allocated, lt.days_allocated) as staff_days_allocated,
                        COALESCE(la.days_used, 0) as days_used,
                        COALESCE(la.days_remaining, lt.days_allocated) as days_remaining,
                        COALESCE(la.is_exhausted, 0) as is_exhausted,
                        COALESCE(la.has_used_one_time, 0) as has_used_one_time,
                        CASE 
                            WHEN lt.is_unlimited = 1 THEN 1
                            WHEN lt.is_one_time = 1 AND COALESCE(la.has_used_one_time, 0) = 1 THEN 0
                            WHEN COALESCE(la.is_exhausted, 0) = 1 THEN 0
                            WHEN COALESCE(la.days_remaining, lt.days_allocated) <= 0 THEN 0
                            ELSE 1
                        END as can_request
                    FROM leave_types lt
                    LEFT JOIN staff s ON s.id = ?
                    LEFT JOIN leave_allocations la ON la.staff_id = s.id 
                        AND la.leave_type_id = lt.id 
                        AND la.year = YEAR(CURDATE())
                    WHERE lt.is_active = 1
                        AND lt.gender_specific = 'none'
                    ORDER BY lt.name ASC";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get leave balance for a specific staff and leave type
     */
    public function getLeaveBalance($staff_id, $leave_type_id, $year = null)
    {
        if ($year === null) {
            $year = date('Y');
        }

        $query = "SELECT la.*, lt.name as leave_type_name, lt.is_unlimited, lt.is_one_time
                FROM leave_allocations la
                JOIN leave_types lt ON lt.id = la.leave_type_id
                WHERE la.staff_id = ? AND la.leave_type_id = ? AND la.year = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $staff_id, $leave_type_id, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }

    /**
     * Get all leave balances for a staff member
     */
    public function getAllLeaveBalances($staff_id, $year = null)
    {
        if ($year === null) {
            $year = date('Y');
        }

        $query = "SELECT * FROM staff_leave_balance_view 
                WHERE staff_id = ? AND year = ? 
                ORDER BY leave_type ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $staff_id, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Check if staff can request a specific leave type
     */
    public function canRequestLeave($staff_id, $leave_type_id, $days_requested, $year = null)
    {
        if ($year === null) {
            $year = date('Y');
        }

        // First check if leave type exists and is active
        $leave_type = $this->getLeaveTypeById($leave_type_id);
        if (!$leave_type || !$leave_type['is_active']) {
            return ['can_request' => false, 'reason' => 'Invalid or inactive leave type'];
        }

        // Check gender restriction only if gender column exists
        if ($leave_type['gender_specific'] != 'none') {
            // Check if gender column exists in staff table
            $column_check = $this->conn->query("SHOW COLUMNS FROM staff LIKE 'gender'");
            if ($column_check && $column_check->num_rows > 0) {
                $staff = $this->getStaffGender($staff_id);
                if (!$staff || $staff['gender'] != $leave_type['gender_specific']) {
                    return ['can_request' => false, 'reason' => 'This leave type is not available for your gender'];
                }
            } else {
                // No gender column exists - cannot grant gender-specific leaves
                return ['can_request' => false, 'reason' => 'Gender-specific leaves require database migration. Please contact HR.'];
            }
        }

        // Check if unlimited (like unpaid leave)
        if ($leave_type['is_unlimited']) {
            return ['can_request' => true, 'reason' => ''];
        }

        // Check min/max days
        if ($leave_type['min_days'] !== null && $days_requested < $leave_type['min_days']) {
            return ['can_request' => false, 'reason' => "Minimum {$leave_type['min_days']} days required for this leave type"];
        }
        if ($leave_type['max_days'] !== null && $days_requested > $leave_type['max_days']) {
            return ['can_request' => false, 'reason' => "Maximum {$leave_type['max_days']} days allowed for this leave type"];
        }

        // Get or create allocation
        $allocation = $this->getLeaveBalance($staff_id, $leave_type_id, $year);

        if (!$allocation) {
            // Initialize allocation for this staff
            $this->initializeStaffAllocation($staff_id, $leave_type_id, $year);
            $allocation = $this->getLeaveBalance($staff_id, $leave_type_id, $year);
        }

        // Check one-time leave (like marriage leave)
        if ($leave_type['is_one_time'] && $allocation['has_used_one_time']) {
            return ['can_request' => false, 'reason' => 'You have already used this one-time leave'];
        }

        // Check if exhausted
        if ($allocation['is_exhausted'] || $allocation['days_remaining'] < $days_requested) {
            return [
                'can_request' => false,
                'reason' => "Insufficient leave balance. You have {$allocation['days_remaining']} days remaining"
            ];
        }

        return ['can_request' => true, 'reason' => ''];
    }

    /**
     * Initialize leave allocation for a staff member
     */
    public function initializeStaffAllocation($staff_id, $leave_type_id, $year)
    {
        $leave_type = $this->getLeaveTypeById($leave_type_id);
        if (!$leave_type) {
            return false;
        }

        $query = "INSERT INTO leave_allocations 
                (staff_id, leave_type_id, year, days_allocated, days_used, days_remaining, is_exhausted, has_used_one_time)
                VALUES (?, ?, ?, ?, 0, ?, 0, 0)
                ON DUPLICATE KEY UPDATE
                days_allocated = VALUES(days_allocated),
                days_remaining = VALUES(days_remaining)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iiiii",
            $staff_id,
            $leave_type_id,
            $year,
            $leave_type['days_allocated'],
            $leave_type['days_allocated']
        );

        return $stmt->execute();
    }

    /**
     * Initialize all leave allocations for a staff member
     */
    public function initializeAllStaffAllocations($staff_id, $year = null)
    {
        if ($year === null) {
            $year = date('Y');
        }

        $leave_types = $this->getAvailableLeaveTypesForStaff($staff_id);
        foreach ($leave_types as $leave_type) {
            $this->initializeStaffAllocation($staff_id, $leave_type['id'], $year);
        }

        return true;
    }

    /**
     * Get leave type by ID
     */
    private function getLeaveTypeById($leave_type_id)
    {
        $query = "SELECT * FROM leave_types WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $leave_type_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }

    /**
     * Get staff gender (backwards compatible)
     */
    private function getStaffGender($staff_id)
    {
        // Check if gender column exists
        $column_check = $this->conn->query("SHOW COLUMNS FROM staff LIKE 'gender'");
        if (!$column_check || $column_check->num_rows == 0) {
            return null; // Gender column doesn't exist
        }

        $query = "SELECT gender FROM staff WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }

    /**
     * Create a leave request
     */
    public function createLeaveRequest($data)
    {
        // Validate the request
        $can_request = $this->canRequestLeave(
            $data['staff_id'],
            $data['leave_type_id'],
            $data['total_days'],
            date('Y', strtotime($data['start_date']))
        );

        if (!$can_request['can_request']) {
            return [
                'success' => false,
                'message' => $can_request['reason']
            ];
        }

        // Insert the leave request
        $query = "INSERT INTO leave_requests 
                (staff_id, leave_type_id, request_date, start_date, end_date, total_days, reason, 
                substitute_staff_id, supporting_documents, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iisssisss",
            $data['staff_id'],
            $data['leave_type_id'],
            $data['request_date'],
            $data['start_date'],
            $data['end_date'],
            $data['total_days'],
            $data['reason'],
            $data['substitute_staff_id'],
            $data['supporting_documents']
        );

        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Leave request submitted successfully',
                'request_id' => $this->conn->insert_id
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error submitting leave request: ' . $stmt->error
            ];
        }
    }

    /**
     * Get leave requests for a staff member
     */
    public function getStaffLeaveRequests($staff_id, $status = null, $limit = null)
    {
        $query = "SELECT lr.*, lt.name as leave_type_name, 
                    s.first_name as substitute_first_name, 
                    s.last_name as substitute_last_name
                FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                LEFT JOIN staff s ON s.id = lr.substitute_staff_id
                WHERE lr.staff_id = ?";

        if ($status !== null) {
            $query .= " AND lr.status = ?";
        }

        $query .= " ORDER BY lr.created_at DESC";

        if ($limit !== null) {
            $query .= " LIMIT ?";
        }

        $stmt = $this->conn->prepare($query);

        if ($status !== null && $limit !== null) {
            $stmt->bind_param("isi", $staff_id, $status, $limit);
        } elseif ($status !== null) {
            $stmt->bind_param("is", $staff_id, $status);
        } elseif ($limit !== null) {
            $stmt->bind_param("ii", $staff_id, $limit);
        } else {
            $stmt->bind_param("i", $staff_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get all pending leave requests (for HR)
     */
    public function getAllLeaveRequests($status = null, $leave_type_id = null)
    {
        $query = "SELECT lr.*, lt.name as leave_type_name,
                    st.first_name, st.last_name, st.email, st.position, st.department,
                    sub.first_name as substitute_first_name, 
                    sub.last_name as substitute_last_name
                FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                JOIN staff st ON st.id = lr.staff_id
                LEFT JOIN staff sub ON sub.id = lr.substitute_staff_id
                WHERE 1=1";

        if ($status !== null) {
            $query .= " AND lr.status = ?";
        }

        if ($leave_type_id !== null) {
            $query .= " AND lr.leave_type_id = ?";
        }

        $query .= " ORDER BY lr.created_at DESC";

        $stmt = $this->conn->prepare($query);

        if ($status !== null && $leave_type_id !== null) {
            $stmt->bind_param("si", $status, $leave_type_id);
        } elseif ($status !== null) {
            $stmt->bind_param("s", $status);
        } elseif ($leave_type_id !== null) {
            $stmt->bind_param("i", $leave_type_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}

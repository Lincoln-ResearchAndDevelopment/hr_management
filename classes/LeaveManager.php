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

        // This only needs to create the allocation row the first time a staff
        // member is seen for a given leave type/year. On every later page
        // load the row already exists and must be left untouched - an
        // approved leave request's days_used/days_remaining must never be
        // reset back to the full allocation just because this ran again.
        $query = "INSERT INTO leave_allocations
                (staff_id, leave_type_id, year, days_allocated, days_used, days_remaining, is_exhausted, has_used_one_time)
                VALUES (?, ?, ?, ?, 0, ?, 0, 0)
                ON DUPLICATE KEY UPDATE
                id = id";

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
     * Whether this staff member has been assigned the Head of Department role
     * by HR. Only active academic staff can hold it.
     */
    public function isHeadOfDepartment($staff_id)
    {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM staff WHERE id = ? AND is_hod = 1 AND staff_type = 'academic' AND status = 'active'"
        );
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    /**
     * Active academic staff HR has assigned as Head of Department of a
     * department, optionally excluding one staff_id (e.g. the requester, so a
     * HOD's own leave request doesn't route to themselves).
     */
    public function getDepartmentHeads($department, $excludeStaffId = null)
    {
        if (empty($department)) {
            return [];
        }

        $query = "SELECT id, first_name, last_name, email, lincoln_email, position
                    FROM staff
                    WHERE department = ? AND status = 'active'
                        AND is_hod = 1 AND staff_type = 'academic'";
        $types = 's';
        $params = [$department];

        if ($excludeStaffId !== null) {
            $query .= " AND id != ?";
            $types .= 'i';
            $params[] = $excludeStaffId;
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Where a staff member's leave request goes first.
     *  - non-academic staff: HR approval only, no HOD step
     *  - academic staff: their department's assigned HOD(s); if there is none
     *    (or they are the HOD themselves) it goes straight to HR so it is never
     *    left waiting on nobody.
     * Returns ['skip' => bool, 'reason' => string, 'heads' => array].
     */
    public function getLeaveApprovalRoute($staff_id)
    {
        $stmt = $this->conn->prepare("SELECT department, staff_type, is_hod FROM staff WHERE id = ?");
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();

        if (!$staff) {
            return ['skip' => true, 'reason' => 'Sent to HR for approval.', 'heads' => []];
        }
        if ($staff['staff_type'] === 'non_academic') {
            return ['skip' => true, 'reason' => 'Non-academic staff: HR approval only (no Head of Department review).', 'heads' => []];
        }

        $heads = $this->getDepartmentHeads($staff['department'], $staff_id);
        if (!empty($heads)) {
            return ['skip' => false, 'reason' => '', 'heads' => $heads];
        }
        if ((int) $staff['is_hod'] === 1) {
            return ['skip' => true, 'reason' => 'Requester is the Head of Department: sent straight to HR.', 'heads' => []];
        }
        return ['skip' => true, 'reason' => 'No Head of Department is assigned for this department: sent straight to HR.', 'heads' => []];
    }

    /**
     * Send a staff member's leave requests that are still waiting on a HOD
     * straight to HR (used when HR makes them non-academic). Returns how many.
     */
    public function sendPendingLeaveToHr($staff_id, $reason)
    {
        $stmt = $this->conn->prepare(
            "UPDATE leave_requests SET status = 'hod_approved', hod_skipped = 1, hod_remarks = ?
             WHERE staff_id = ? AND status = 'pending'"
        );
        $stmt->bind_param('si', $reason, $staff_id);
        $stmt->execute();
        return $stmt->affected_rows;
    }
    /**
     * Leave requests from a department awaiting this HOD's first-pass
     * decision (status 'pending'), excluding the HOD's own requests.
     */
    public function getDepartmentPendingLeaveRequests($department, $excludeStaffId = null)
    {
        $query = "SELECT lr.*, lt.name as leave_type_name,
                    st.first_name, st.last_name, st.position, st.department
                FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                JOIN staff st ON st.id = lr.staff_id
                WHERE lr.status = 'pending' AND st.department = ?";
        $types = 's';
        $params = [$department];

        if ($excludeStaffId !== null) {
            $query .= " AND lr.staff_id != ?";
            $types .= 'i';
            $params[] = $excludeStaffId;
        }

        $query .= " ORDER BY lr.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Leave requests from a department this HOD has already acted on (or
     * that HR has since finalized), most recent first - read-only history.
     */
    public function getDepartmentReviewedLeaveRequests($department, $excludeStaffId = null, $limit = 20)
    {
        $query = "SELECT lr.*, lt.name as leave_type_name,
                    st.first_name, st.last_name, st.position, st.department
                FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                JOIN staff st ON st.id = lr.staff_id
                WHERE lr.status IN ('hod_approved', 'hod_rejected', 'approved', 'rejected')
                    AND lr.hod_skipped = 0
                    AND st.department = ?";
        $types = 's';
        $params = [$department];

        if ($excludeStaffId !== null) {
            $query .= " AND lr.staff_id != ?";
            $types .= 'i';
            $params[] = $excludeStaffId;
        }

        $query .= " ORDER BY lr.updated_at DESC LIMIT ?";
        $types .= 'i';
        $params[] = $limit;

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
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

<?php

/**
 * Substitute staff: the colleague a staff member names to cover for them on a leave or
 * temporary exit request.
 *
 *  - notifyAppointed()  emails the colleague as soon as the request is made
 *  - assignmentsFor()   what shows on that colleague's own dashboard
 *  - forRequest()       what HR sees next to a request
 */
class SubstituteNotifier
{
    /**
     * Email the appointed substitute. Never throws: a mail problem must not block the request.
     * $kind is 'leave' or 'temporary_exit'. For leave, $when* are dates; for an exit, $whenFrom
     * is the date and $whenTo is "start - end" times.
     */
    public static function notifyAppointed($conn, $substitute_id, $requester_id, $kind, $date_from, $date_to, $time_text = '')
    {
        $substitute_id = (int) $substitute_id;
        if ($substitute_id <= 0 || $substitute_id === (int) $requester_id) {
            return false;
        }

        try {
            $stmt = $conn->prepare("SELECT id, first_name, last_name, email, lincoln_email FROM staff WHERE id IN (?, ?)");
            $stmt->bind_param('ii', $substitute_id, $requester_id);
            $stmt->execute();
            $people = [];
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $people[(int) $row['id']] = $row;
            }
            if (!isset($people[$substitute_id], $people[(int) $requester_id])) {
                return false;
            }
            $sub = $people[$substitute_id];
            $req = $people[(int) $requester_id];

            $to = $sub['lincoln_email'] ?: $sub['email'];
            if (empty($to)) {
                return false;
            }

            require_once __DIR__ . '/Mailer.php';
            return (new Mailer())->sendSubstituteAppointment(
                $to,
                trim($sub['first_name'] . ' ' . $sub['last_name']),
                trim($req['first_name'] . ' ' . $req['last_name']),
                $kind,
                $date_from,
                $date_to,
                $time_text
            );
        } catch (Throwable $e) {
            error_log('Substitute appointment email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Requests that name this staff member as substitute and are still current: leave that has
     * not finished, and exits for today or later. Rejected requests are left out.
     */
    public static function assignmentsFor($conn, $staff_id)
    {
        $staff_id = (int) $staff_id;
        $rows = [];

        $leave = $conn->prepare(
            "SELECT lr.id, lr.start_date, lr.end_date, lr.status, lt.name AS leave_type, s.first_name, s.last_name, s.position
             FROM leave_requests lr
             JOIN staff s ON s.id = lr.staff_id
             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.substitute_staff_id = ? AND lr.end_date >= CURDATE() AND lr.status NOT IN ('rejected', 'hod_rejected')
             ORDER BY lr.start_date ASC"
        );
        $leave->bind_param('i', $staff_id);
        $leave->execute();
        foreach ($leave->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $rows[] = [
                'kind' => 'leave',
                'requester' => trim($r['first_name'] . ' ' . $r['last_name']),
                'position' => $r['position'],
                'what' => ($r['leave_type'] ?: 'Leave'),
                'from' => $r['start_date'],
                'to' => $r['end_date'],
                'status' => $r['status'],
            ];
        }

        $exit = $conn->prepare(
            "SELECT t.id, t.request_date, t.start_time, t.end_time, t.status, s.first_name, s.last_name, s.position
             FROM temporary_exit_requests t
             JOIN staff s ON s.id = t.staff_id
             WHERE t.substitute_staff_id = ? AND t.request_date >= CURDATE() AND t.status <> 'rejected'
             ORDER BY t.request_date ASC, t.start_time ASC"
        );
        $exit->bind_param('i', $staff_id);
        $exit->execute();
        foreach ($exit->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $rows[] = [
                'kind' => 'temporary_exit',
                'requester' => trim($r['first_name'] . ' ' . $r['last_name']),
                'position' => $r['position'],
                'what' => 'Temporary exit',
                'from' => $r['request_date'],
                'to' => $r['request_date'],
                'status' => $r['status'],
                'time_text' => date('g:i A', strtotime($r['start_time'])) . ' - ' . date('g:i A', strtotime($r['end_time'])),
            ];
        }

        usort($rows, fn($a, $b) => strcmp($a['from'], $b['from']));
        return $rows;
    }

    /**
     * Every current or upcoming substitute appointment across all staff, for the HR dashboard:
     * who is away, who is covering, when, and whether the request is approved yet.
     */
    public static function allCurrent($conn)
    {
        $rows = [];

        $leave = $conn->query(
            "SELECT r.first_name AS req_first, r.last_name AS req_last, r.department,
                    sub.first_name AS sub_first, sub.last_name AS sub_last,
                    lt.name AS what, lr.start_date AS date_from, lr.end_date AS date_to, lr.status, NULL AS time_text
             FROM leave_requests lr
             JOIN staff r ON r.id = lr.staff_id
             JOIN staff sub ON sub.id = lr.substitute_staff_id
             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.end_date >= CURDATE() AND lr.status NOT IN ('rejected', 'hod_rejected')"
        );
        if ($leave) {
            foreach ($leave->fetch_all(MYSQLI_ASSOC) as $row) {
                $row['what'] = $row['what'] ?: 'Leave';
                $rows[] = $row;
            }
        }

        $exit = $conn->query(
            "SELECT r.first_name AS req_first, r.last_name AS req_last, r.department,
                    sub.first_name AS sub_first, sub.last_name AS sub_last,
                    'Temporary exit' AS what, t.request_date AS date_from, t.request_date AS date_to, t.status,
                    CONCAT(TIME_FORMAT(t.start_time, '%l:%i %p'), ' - ', TIME_FORMAT(t.end_time, '%l:%i %p')) AS time_text
             FROM temporary_exit_requests t
             JOIN staff r ON r.id = t.staff_id
             JOIN staff sub ON sub.id = t.substitute_staff_id
             WHERE t.request_date >= CURDATE() AND t.status <> 'rejected'"
        );
        if ($exit) {
            foreach ($exit->fetch_all(MYSQLI_ASSOC) as $row) {
                $rows[] = $row;
            }
        }

        usort($rows, fn($a, $b) => strcmp($a['date_from'], $b['date_from']));
        return $rows;
    }

    /**
     * The substitute named on one request, for HR: ['name' => ..., 'position' => ...] or null.
     */
    public static function forRequest($conn, $type, $request_id)
    {
        $table = $type === 'temporary_exit' ? 'temporary_exit_requests' : ($type === 'leave' ? 'leave_requests' : null);
        if ($table === null) {
            return null;
        }
        $stmt = $conn->prepare(
            "SELECT s.first_name, s.last_name, s.position
             FROM $table r JOIN staff s ON s.id = r.substitute_staff_id
             WHERE r.id = ?"
        );
        $request_id = (int) $request_id;
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? ['name' => trim($row['first_name'] . ' ' . $row['last_name']), 'position' => $row['position']] : null;
    }
}

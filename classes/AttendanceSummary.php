<?php

require_once __DIR__ . '/AttendanceImporter.php';

/**
 * A staff member's attendance breakdown for one month, and emailing it to them after HR
 * uploads attendance: how many days on time, how many late (and what that cost), how many
 * absent (and what that cost), the total deducted, and the date of every deduction.
 */
class AttendanceSummary
{
    /**
     * The breakdown for one staff member and month ('YYYY-MM'), built from the attendance
     * records, with the same amounts the importer charges.
     */
    public static function forMonth($conn, $staff_id, $month)
    {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        $stmt = $conn->prepare(
            "SELECT attendance_date, time_in, time_out, status
             FROM attendance_records
             WHERE staff_id = ? AND attendance_date BETWEEN ? AND ?
             ORDER BY attendance_date"
        );
        $stmt->bind_param('iss', $staff_id, $start, $end);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $summary = [
            'month' => $month,
            'month_label' => date('F Y', strtotime($start)),
            'days_recorded' => count($rows),
            'on_time' => 0,
            'late_count' => 0,
            'late_amount' => 0,
            'absent_count' => 0,
            'absent_amount' => 0,
            'total' => 0,
            'deductions' => [], // each deducted day: date, reason, amount
        ];

        foreach ($rows as $r) {
            $time_in = $r['time_in'] ? date('g:i A', strtotime($r['time_in'])) : null;
            if ($r['status'] === 'late') {
                $summary['late_count']++;
                $summary['deductions'][] = [
                    'date' => $r['attendance_date'],
                    'reason' => 'Late: signed in at ' . $time_in . ' (after 9:00 AM)',
                    'amount' => AttendanceImporter::LATE_DEDUCTION,
                ];
            } elseif ($r['status'] === 'absent') {
                $summary['absent_count']++;
                $summary['deductions'][] = [
                    'date' => $r['attendance_date'],
                    'reason' => $time_in ? 'Absent: signed in at ' . $time_in . ' but did not sign out' : 'Absent',
                    'amount' => AttendanceImporter::ABSENT_DEDUCTION,
                ];
            } elseif ($r['status'] === 'present') {
                $summary['on_time']++;
            }
        }

        $summary['late_amount'] = $summary['late_count'] * AttendanceImporter::LATE_DEDUCTION;
        $summary['absent_amount'] = $summary['absent_count'] * AttendanceImporter::ABSENT_DEDUCTION;
        $summary['total'] = $summary['late_amount'] + $summary['absent_amount'];

        return $summary;
    }

    /**
     * Emails every staff member the upload recorded attendance for, one email per month covered.
     * $affected is the importer's 'affected' list: staff_id => ['2026-10' => true, ...].
     * Never throws; returns counts so HR can be told what happened.
     */
    public static function emailAffectedStaff($conn, array $affected)
    {
        $result = ['sent' => 0, 'failed' => 0, 'no_email' => 0, 'failed_names' => []];
        if (empty($affected)) {
            return $result;
        }

        // A big file means many emails; do not let the page time out halfway through.
        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            require_once __DIR__ . '/Mailer.php';
            $mailer = new Mailer();
            $mailer->keepConnectionOpen();

            foreach ($affected as $staff_id => $months) {
                $stmt = $conn->prepare("SELECT first_name, last_name, email, lincoln_email FROM staff WHERE id = ?");
                $stmt->bind_param('i', $staff_id);
                $stmt->execute();
                $staff = $stmt->get_result()->fetch_assoc();
                if (!$staff) {
                    continue;
                }

                // Staff are emailed at their Lincoln address once they have one
                $to = $staff['lincoln_email'] ?: $staff['email'];
                $name = trim($staff['first_name'] . ' ' . $staff['last_name']);
                if (empty($to)) {
                    $result['no_email']++;
                    continue;
                }

                $month_keys = array_keys($months);
                sort($month_keys);
                foreach ($month_keys as $month) {
                    $ok = $mailer->sendAttendanceSummary($to, $name, self::forMonth($conn, (int) $staff_id, $month));
                    if ($ok) {
                        $result['sent']++;
                    } else {
                        $result['failed']++;
                        $result['failed_names'][] = $name;
                    }
                }
            }

            $mailer->closeConnection();
        } catch (Throwable $e) {
            error_log('Attendance summary emails error: ' . $e->getMessage());
        }

        return $result;
    }
}

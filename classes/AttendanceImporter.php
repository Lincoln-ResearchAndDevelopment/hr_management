<?php

/**
 * Attendance Import Handler Class
 * Handles CSV/Excel file uploads and attendance processing
 */

class AttendanceImporter
{
    private $conn;

    public function __construct($database_connection)
    {
        $this->conn = $database_connection;
    }

    /**
     * Parse CSV file and extract attendance data
     */
    public function parseCSVFile($file_path)
    {
        if (!file_exists($file_path)) {
            return ['success' => false, 'message' => 'File not found'];
        }

        $records = [];
        $row_number = 0;
        $errors = [];

        if (($handle = fopen($file_path, 'r')) !== FALSE) {
            // Read header row
            $header = fgetcsv($handle);

            // Expected columns: last_name, first_name, date, check_in_time, status (optional)
            $last_name_col = array_search('last_name', array_map('strtolower', $header));
            $first_name_col = array_search('first_name', array_map('strtolower', $header));
            $date_col = array_search('date', array_map('strtolower', $header));
            $check_in_col = array_search('check_in_time', array_map('strtolower', $header));
            $check_out_col = array_search('check_out_time', array_map('strtolower', $header)) !== false
                ? array_search('check_out_time', array_map('strtolower', $header))
                : null;

            if ($last_name_col === false || $date_col === false || $check_in_col === false) {
                return ['success' => false, 'message' => 'CSV must contain: last_name, date, check_in_time columns'];
            }

            while (($row = fgetcsv($handle)) !== FALSE) {
                $row_number++;

                // Skip empty rows
                if (empty($row[0])) continue;

                $last_name = trim($row[$last_name_col]);
                $first_name = isset($row[$first_name_col]) ? trim($row[$first_name_col]) : '';
                $date = trim($row[$date_col]);
                $check_in = trim($row[$check_in_col]);
                $check_out = ($check_out_col !== null && isset($row[$check_out_col])) ? trim($row[$check_out_col]) : null;

                // Validate date format
                if (!$this->isValidDate($date)) {
                    $errors[] = "Row $row_number: Invalid date format '$date'";
                    continue;
                }

                // Validate time format
                if (!$this->isValidTime($check_in)) {
                    $errors[] = "Row $row_number: Invalid check-in time '$check_in'";
                    continue;
                }

                $records[] = [
                    'last_name' => $last_name,
                    'first_name' => $first_name,
                    'date' => $date,
                    'check_in_time' => $check_in,
                    'check_out_time' => $check_out,
                    'row_number' => $row_number
                ];
            }
            fclose($handle);
        } else {
            return ['success' => false, 'message' => 'Unable to open file'];
        }

        return [
            'success' => true,
            'records' => $records,
            'errors' => $errors,
            'total_rows' => $row_number
        ];
    }

    /**
     * Validate date format (YYYY-MM-DD or DD-MM-YYYY or M/D/Y)
     */
    private function isValidDate($date)
    {
        $formats = ['Y-m-d', 'd-m-Y', 'm/d/Y', 'd/m/Y'];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed && $parsed->format($format) === $date) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate time format (HH:MM or HH:MM:SS)
     */
    private function isValidTime($time)
    {
        return preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time);
    }

    /**
     * Normalize date to YYYY-MM-DD
     */
    private function normalizeDate($date)
    {
        $formats = ['Y-m-d', 'd-m-Y', 'm/d/Y', 'd/m/Y'];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed) {
                return $parsed->format('Y-m-d');
            }
        }
        return null;
    }

    /**
     * Process attendance records and update database
     */
    public function processAttendanceRecords($records)
    {
        $processed = 0;
        $skipped = 0;
        $errors = [];
        $late_arrivals = [];

        foreach ($records as $record) {
            // Find staff by last name
            $staff = $this->findStaffByLastName($record['last_name']);

            if (!$staff) {
                $errors[] = "Row {$record['row_number']}: Staff with last name '{$record['last_name']}' not found";
                $skipped++;
                continue;
            }

            // Normalize date
            $attendance_date = $this->normalizeDate($record['date']);
            if (!$attendance_date) {
                $errors[] = "Row {$record['row_number']}: Could not parse date '{$record['date']}'";
                $skipped++;
                continue;
            }

            // Determine status and check for late arrival
            $status = 'present';
            $is_late = false;
            $check_in_time = $record['check_in_time'];

            // Check if arrival is after 9:00 AM
            if ($this->isTimeAfter($check_in_time, '09:00')) {
                $status = 'late';
                $is_late = true;
                $late_arrivals[] = [
                    'staff_id' => $staff['id'],
                    'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                    'date' => $attendance_date,
                    'check_in_time' => $check_in_time
                ];
            }

            // Insert or update attendance record
            $result = $this->insertOrUpdateAttendance(
                $staff['id'],
                $attendance_date,
                $check_in_time,
                $record['check_out_time'],
                $status
            );

            if ($result['success']) {
                $processed++;

                // If late arrival, add payroll deduction
                if ($is_late) {
                    $this->addLateArrivalDeduction($staff['id'], $attendance_date);
                }
            } else {
                $errors[] = "Row {$record['row_number']}: " . $result['message'];
                $skipped++;
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'late_arrivals' => $late_arrivals
        ];
    }

    /**
     * Find staff by last name (returns first match)
     */
    private function findStaffByLastName($last_name)
    {
        $find_staff = $this->conn->prepare(
            "SELECT id, first_name, last_name, email FROM staff WHERE LOWER(last_name) = LOWER(?)"
        );
        $find_staff->bind_param("s", $last_name);
        $find_staff->execute();
        $result = $find_staff->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Check if time is after threshold (e.g., 09:00)
     */
    private function isTimeAfter($time, $threshold)
    {
        $timeObj = \DateTime::createFromFormat('H:i', substr($time, 0, 5));
        $thresholdObj = \DateTime::createFromFormat('H:i', $threshold);

        if (!$timeObj || !$thresholdObj) {
            return false;
        }

        return $timeObj > $thresholdObj;
    }

    /**
     * Insert or update attendance record
     */
    private function insertOrUpdateAttendance($staff_id, $attendance_date, $check_in_time, $check_out_time, $status)
    {
        // Check if record exists
        $check_record = $this->conn->prepare(
            "SELECT id FROM attendance_records WHERE staff_id = ? AND attendance_date = ?"
        );
        $check_record->bind_param("is", $staff_id, $attendance_date);
        $check_record->execute();
        $exists = $check_record->get_result()->fetch_assoc();

        if ($exists) {
            // Update existing record
            $update = $this->conn->prepare(
                "UPDATE attendance_records 
                 SET time_in = ?, time_out = ?, status = ? 
                 WHERE staff_id = ? AND attendance_date = ?"
            );
            $update->bind_param("sssiss", $check_in_time, $check_out_time, $status, $staff_id, $attendance_date);

            if ($update->execute()) {
                return ['success' => true, 'message' => 'Attendance updated'];
            } else {
                return ['success' => false, 'message' => 'Failed to update attendance: ' . $update->error];
            }
        } else {
            // Insert new record
            $insert = $this->conn->prepare(
                "INSERT INTO attendance_records (staff_id, attendance_date, time_in, time_out, status) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insert->bind_param("issss", $staff_id, $attendance_date, $check_in_time, $check_out_time, $status);

            if ($insert->execute()) {
                return ['success' => true, 'message' => 'Attendance recorded'];
            } else {
                return ['success' => false, 'message' => 'Failed to record attendance: ' . $insert->error];
            }
        }
    }

    /**
     * Add late arrival deduction to payroll
     */
    private function addLateArrivalDeduction($staff_id, $attendance_date)
    {
        // Extract month-year from attendance date
        $month_year = date('Y-m', strtotime($attendance_date));

        // Check if payroll record exists for this month
        $check_payroll = $this->conn->prepare(
            "SELECT id FROM payroll WHERE staff_id = ? AND month_year = ?"
        );
        $check_payroll->bind_param("is", $staff_id, $month_year);
        $check_payroll->execute();
        $payroll = $check_payroll->get_result()->fetch_assoc();

        if (!$payroll) {
            // Create payroll record if it doesn't exist
            $salary = $this->getStaffBasicSalary($staff_id);
            if (!$salary) {
                return false;
            }

            $insert_payroll = $this->conn->prepare(
                "INSERT INTO payroll (staff_id, month_year, duration_days, basic_salary, gross_salary) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            $duration_days = date('t', strtotime($attendance_date));
            $insert_payroll->bind_param("isiii", $staff_id, $month_year, $duration_days, $salary, $salary);
            $insert_payroll->execute();
        }

        // Add/Update late arrival deduction
        $update_deduction = $this->conn->prepare(
            "UPDATE payroll 
             SET attendance_deduction = attendance_deduction + 1000
             WHERE staff_id = ? AND month_year = ?"
        );
        $update_deduction->bind_param("is", $staff_id, $month_year);
        return $update_deduction->execute();
    }

    /**
     * Get staff basic salary
     */
    private function getStaffBasicSalary($staff_id)
    {
        $get_salary = $this->conn->prepare("SELECT salary FROM staff WHERE id = ?");
        $get_salary->bind_param("i", $staff_id);
        $get_salary->execute();
        $result = $get_salary->get_result()->fetch_assoc();

        return $result ? (int)$result['salary'] : 0;
    }

    /**
     * Revert attendance and remove deduction (HR only)
     */
    public function revertAttendanceRecord($attendance_id, $hr_id)
    {
        // Get attendance record details
        $get_record = $this->conn->prepare(
            "SELECT staff_id, attendance_date FROM attendance_records WHERE id = ?"
        );
        $get_record->bind_param("i", $attendance_id);
        $get_record->execute();
        $record = $get_record->get_result()->fetch_assoc();

        if (!$record) {
            return ['success' => false, 'message' => 'Attendance record not found'];
        }

        // Delete attendance record
        $delete = $this->conn->prepare("DELETE FROM attendance_records WHERE id = ?");
        $delete->bind_param("i", $attendance_id);

        if ($delete->execute()) {
            // Revert payroll deduction if it was a late arrival
            $month_year = date('Y-m', strtotime($record['attendance_date']));
            $update_payroll = $this->conn->prepare(
                "UPDATE payroll 
                 SET attendance_deduction = GREATEST(0, attendance_deduction - 1000)
                 WHERE staff_id = ? AND month_year = ?"
            );
            $update_payroll->bind_param("is", $record['staff_id'], $month_year);
            $update_payroll->execute();

            // Log HR action
            $this->logHRAction($hr_id, 'ATTENDANCE_REVERT', 'Reverted attendance record for staff ID: ' . $record['staff_id']);

            return ['success' => true, 'message' => 'Attendance record reverted and deductions removed'];
        } else {
            return ['success' => false, 'message' => 'Failed to revert attendance record'];
        }
    }

    /**
     * Log HR action
     */
    private function logHRAction($hr_id, $action, $description)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $log = $this->conn->prepare(
            "INSERT INTO hr_activity_logs (hr_id, action, description, ip_address) VALUES (?, ?, ?, ?)"
        );
        $log->bind_param("isss", $hr_id, $action, $description, $ip);
        return $log->execute();
    }
}

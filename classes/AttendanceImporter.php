<?php

/**
 * Attendance Import Handler Class
 * Handles CSV/Excel file uploads and attendance processing
 */

class AttendanceImporter
{
    // Flat payroll deduction per late arrival / per absent day (naira).
    public const LATE_DEDUCTION = 1000;
    public const ABSENT_DEDUCTION = 1000;

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
     * Parse a real .xlsx file and extract attendance data. Supports two
     * layouts:
     *  - "Flat" layout: a header row with last_name/first_name/date/
     *    check_in_time/check_out_time columns, same as parseCSVFile().
     *  - "Biometric log" layout: the calendar-grid export format used by
     *    Lincoln's fingerprint clock devices - a "Calculate Date:X ~ Y"
     *    period line, one row of date-of-month column headers, then
     *    repeating (Enroll ID/Name/Dept header row, time-punch data row)
     *    blocks, one pair per staff member. Each date cell holds either
     *    nothing (absent), a single "HH:MM" (check-in only), or two times
     *    joined by a newline (check-in and check-out).
     */
    public function parseXlsxFile($file_path)
    {
        if (!file_exists($file_path)) {
            return ['success' => false, 'message' => 'File not found'];
        }

        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'message' => 'PHP Zip extension is not enabled. Enable ext-zip in php.ini and restart Apache.'];
        }

        $rows = $this->readXlsxRows($file_path);
        if ($rows === null) {
            return ['success' => false, 'message' => 'Unable to read this Excel file - it may be corrupted or not a valid .xlsx file.'];
        }

        if ($this->looksLikeFlatXlsx($rows)) {
            return $this->parseFlatXlsxRows($rows);
        }

        if ($this->looksLikeBiometricXlsx($rows)) {
            return $this->parseBiometricXlsxRows($rows);
        }

        return ['success' => false, 'message' => 'Could not recognize this file\'s layout. Expected either a last_name/date/check_in_time table, or the Enroll ID attendance log format.'];
    }

    /**
     * Read an .xlsx file's first worksheet into [rowNumber => [colLetter => text]].
     * Returns null if the file can't be read as a valid xlsx.
     */
    private function readXlsxRows($file_path)
    {
        $zip = new \ZipArchive();
        if ($zip->open($file_path) !== true) {
            return null;
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sst = @simplexml_load_string($sharedXml);
            if ($sst !== false) {
                foreach ($sst->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } else {
                        $text = '';
                        foreach ($si->r as $run) {
                            $text .= (string) $run->t;
                        }
                        $sharedStrings[] = $text;
                    }
                }
            }
        }

        // Find the first worksheet - sheet1.xml is the near-universal
        // default, fall back to scanning the worksheets folder if absent.
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheetXml = $zip->getFromName($name);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false) {
            return null;
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false || !isset($sheet->sheetData)) {
            return null;
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowNum = (int) $row['r'];
            $rowData = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                    continue;
                }
                $col = $m[1];
                $type = (string) $cell['t'];

                $value = null;
                if (isset($cell->v)) {
                    $raw = (string) $cell->v;
                    $value = $type === 's' ? ($sharedStrings[(int) $raw] ?? '') : $raw;
                } elseif (isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                }

                if ($value !== null && $value !== '') {
                    $rowData[$col] = $value;
                }
            }
            if (!empty($rowData)) {
                $rows[$rowNum] = $rowData;
            }
        }

        return $rows;
    }

    /**
     * Convert a spreadsheet column letter (A, B, ..., Z, AA, AB, ...) to a
     * zero-based index.
     */
    private function xlsxColToIndex($col)
    {
        $index = 0;
        foreach (str_split($col) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }
        return $index - 1;
    }

    /**
     * True if the first non-empty row looks like a flat header row
     * (last_name/date/check_in_time), same convention as parseCSVFile().
     */
    private function looksLikeFlatXlsx($rows)
    {
        foreach ($rows as $rowData) {
            $values = array_map('strtolower', array_map('trim', array_values($rowData)));
            if (in_array('last_name', $values, true) && in_array('date', $values, true) && in_array('check_in_time', $values, true)) {
                return true;
            }
            break; // only check the very first non-empty row
        }
        return false;
    }

    /**
     * True if any cell in the file matches the "Enroll ID: ... Name: ...
     * Dept.: ..." header pattern used by the biometric clock export.
     */
    private function looksLikeBiometricXlsx($rows)
    {
        foreach ($rows as $rowData) {
            foreach ($rowData as $value) {
                if (preg_match('/Enroll\s*ID\s*:\s*\S+.*Name\s*:\s*.+/i', $value)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Parse a flat xlsx table (last_name/first_name/date/check_in_time/
     * check_out_time header row) - same semantics as parseCSVFile().
     */
    private function parseFlatXlsxRows($rows)
    {
        $records = [];
        $errors = [];
        $row_number = 0;
        $header = null;
        $col_map = [];

        foreach ($rows as $rowNum => $rowData) {
            if ($header === null) {
                foreach ($rowData as $col => $value) {
                    $col_map[strtolower(trim($value))] = $col;
                }
                if (!isset($col_map['last_name'], $col_map['date'], $col_map['check_in_time'])) {
                    return ['success' => false, 'message' => 'Excel file must contain: last_name, date, check_in_time columns'];
                }
                $header = true;
                continue;
            }

            $row_number++;
            $last_name = trim($rowData[$col_map['last_name']] ?? '');
            if ($last_name === '') {
                continue;
            }
            $first_name = trim($rowData[$col_map['first_name'] ?? ''] ?? '');
            $date = trim($rowData[$col_map['date']] ?? '');
            $check_in = trim($rowData[$col_map['check_in_time']] ?? '');
            $check_out = isset($col_map['check_out_time']) ? trim($rowData[$col_map['check_out_time']] ?? '') : null;

            if (!$this->isValidDate($date)) {
                $errors[] = "Row {$row_number}: Invalid date format '{$date}'";
                continue;
            }
            if (!$this->isValidTime($check_in)) {
                $errors[] = "Row {$row_number}: Invalid check-in time '{$check_in}'";
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

        return ['success' => true, 'records' => $records, 'errors' => $errors, 'total_rows' => $row_number];
    }

    /**
     * Parse the biometric clock "Attendance Log" grid layout.
     */
    private function parseBiometricXlsxRows($rows)
    {
        $records = [];
        $errors = [];
        $row_number = 0;

        // Find the "Calculate Date:X ~ Y" period start date, and the date
        // header row (whichever row's cells are the day-of-month columns
        // right after it) - the row immediately below the period line.
        $period_start = null;
        $date_header_row_num = null;
        foreach ($rows as $rowNum => $rowData) {
            foreach ($rowData as $value) {
                if (preg_match('/Calculate\s*Date\s*:\s*(\d{4}-\d{2}-\d{2})\s*~/i', $value, $m)) {
                    $period_start = $m[1];
                    break 2;
                }
            }
        }

        if ($period_start === null) {
            return ['success' => false, 'message' => 'Could not find a "Calculate Date" period line in this file.'];
        }

        // The date header row is the first row after the period line whose
        // cells match the "DD\n(DOW)" day-of-month pattern.
        foreach ($rows as $rowNum => $rowData) {
            $first_value = reset($rowData);
            if ($first_value !== false && preg_match('/^\d{1,2}\s*[\r\n]+\s*\(\w+\)$/u', trim($first_value))) {
                $date_header_row_num = $rowNum;
                break;
            }
        }

        if ($date_header_row_num === null) {
            return ['success' => false, 'message' => 'Could not find the date column header row in this file.'];
        }

        // Build column -> actual calendar date, walking columns left to
        // right and incrementing one day per column from the period start.
        $header_cols = $rows[$date_header_row_num];
        uksort($header_cols, fn($a, $b) => $this->xlsxColToIndex($a) <=> $this->xlsxColToIndex($b));

        $col_dates = [];
        $current_date = new \DateTime($period_start);
        foreach ($header_cols as $col => $value) {
            $col_dates[$col] = $current_date->format('Y-m-d');
            $current_date->modify('+1 day');
        }

        // Walk every row after the date header looking for "Enroll ID: ...
        // Name: ... Dept.: ..." blocks; the next row holds that staff
        // member's punch times, aligned to the same date columns.
        $row_numbers = array_keys($rows);
        foreach ($row_numbers as $i => $rowNum) {
            if ($rowNum <= $date_header_row_num) {
                continue;
            }
            $first_value = reset($rows[$rowNum]);
            if ($first_value === false || !preg_match('/Enroll\s*ID\s*:\s*(\S+)\s+Name\s*:\s*(.+?)\s+Dept\.?\s*:\s*(.+)/iu', $first_value, $m)) {
                continue;
            }

            $name_parts = preg_split('/\s+/', trim($m[2]), 2);
            $last_name = $name_parts[0] ?? '';
            $first_name = $name_parts[1] ?? '';

            if ($last_name === '') {
                continue;
            }

            $data_row_num = $row_numbers[$i + 1] ?? null;
            if ($data_row_num === null || !isset($rows[$data_row_num])) {
                continue;
            }
            $data_row = $rows[$data_row_num];

            foreach ($data_row as $col => $cell_value) {
                if (!isset($col_dates[$col])) {
                    continue;
                }
                $row_number++;
                $times = preg_split('/[\r\n]+/', trim($cell_value));
                $times = array_values(array_filter($times, fn($t) => trim($t) !== ''));
                if (empty($times)) {
                    continue;
                }

                $check_in = trim($times[0]);
                $check_out = isset($times[1]) ? trim($times[1]) : null;
                $date = $col_dates[$col];

                if (!$this->isValidTime($check_in)) {
                    $errors[] = "Row {$data_row_num}, {$date}: Invalid check-in time '{$check_in}' for {$last_name} {$first_name}";
                    continue;
                }
                if ($check_out !== null && !$this->isValidTime($check_out)) {
                    $errors[] = "Row {$data_row_num}, {$date}: Invalid check-out time '{$check_out}' for {$last_name} {$first_name}";
                    $check_out = null;
                }

                $records[] = [
                    'last_name' => $last_name,
                    'first_name' => $first_name,
                    'date' => $date,
                    'check_in_time' => $check_in,
                    'check_out_time' => $check_out,
                    'row_number' => $data_row_num
                ];
            }
        }

        return ['success' => true, 'records' => $records, 'errors' => $errors, 'total_rows' => $row_number];
    }

    /**
     * Validate date format (YYYY-MM-DD, DD-MM-YYYY, or M/D/Y - with or
     * without zero-padding, e.g. both "07/01/2026" and "7/1/2026" are
     * valid, since spreadsheet software commonly exports dates unpadded).
     * Uses regex + checkdate() rather than DateTime round-tripping, since
     * PHP 8.2's DateTime::getLastErrors() is unreliable for this check.
     */
    private function isValidDate($date)
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) { // Y-m-d
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
        }
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $date, $m)) { // d-m-Y
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]);
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $date, $m)) {
            // Try m/d/Y first (US-style); fall back to d/m/Y for values that
            // are only valid the other way round (e.g. "25/12/2026").
            return checkdate((int) $m[1], (int) $m[2], (int) $m[3])
                || checkdate((int) $m[2], (int) $m[1], (int) $m[3]);
        }
        return false;
    }

    /**
     * Validate time format: 24-hour (H:MM, HH:MM, with optional :SS) or
     * 12-hour with AM/PM (H:MM AM, HH:MM:SS PM, etc, case-insensitive,
     * with or without a space before AM/PM) - spreadsheet software commonly
     * exports either depending on locale/cell formatting.
     */
    private function isValidTime($time)
    {
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time)) {
            return true;
        }
        if (preg_match('/^(0?[1-9]|1[0-2]):[0-5][0-9](:[0-5][0-9])?\s*[APap][Mm]$/', $time)) {
            return true;
        }
        return false;
    }

    /**
     * Normalize a validated time string to strict 24-hour HH:MM:SS, since
     * that's what the `time_in`/`time_out` TIME columns expect - a raw
     * 12-hour "8:00 AM" string passed straight through would be misread.
     */
    private function normalizeTime($time)
    {
        if ($time === null || trim($time) === '') {
            return null;
        }
        $time = trim($time);

        if (preg_match('/^(0?[1-9]|1[0-2]):([0-5][0-9])(:([0-5][0-9]))?\s*([APap][Mm])$/', $time, $m)) {
            $hour = (int) $m[1];
            $minute = $m[2];
            $second = $m[4] !== '' ? $m[4] : '00';
            $meridiem = strtoupper($m[5]);
            if ($meridiem === 'AM') {
                $hour = $hour === 12 ? 0 : $hour;
            } else {
                $hour = $hour === 12 ? 12 : $hour + 12;
            }
            return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        }

        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/', $time, $m)) {
            $second = !empty($m[4]) ? $m[4] : '00';
            return sprintf('%02d:%02d:%02d', (int) $m[1], $m[2], $second);
        }

        return null;
    }

    /**
     * Normalize date to YYYY-MM-DD. Mirrors isValidDate()'s regex +
     * checkdate() logic rather than DateTime::createFromFormat(), which
     * silently overflows invalid month/day values (e.g. treating "25" as
     * month 25) instead of rejecting them, producing a wrong date instead
     * of falling back to the other m/d vs d/m interpretation.
     */
    private function normalizeDate($date)
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) { // Y-m-d
            if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
            }
        }
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $date, $m)) { // d-m-Y
            if (checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $date, $m)) {
            if (checkdate((int) $m[1], (int) $m[2], (int) $m[3])) { // m/d/Y
                return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
            }
            if (checkdate((int) $m[2], (int) $m[1], (int) $m[3])) { // d/m/Y fallback
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
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
        $absences = [];

        // Sign-out is only judged when the file actually carries sign-out
        // data. A file with no check-out column at all (it's optional) says
        // nothing about who did or didn't sign out.
        $file_tracks_checkout = false;
        foreach ($records as $r) {
            if (!empty(trim((string) ($r['check_out_time'] ?? '')))) {
                $file_tracks_checkout = true;
                break;
            }
        }

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

            // Saturdays and Sundays are not working days - attendance is not
            // tracked for weekends, so any weekend row is skipped rather than
            // recorded.
            if ($this->isWeekend($attendance_date)) {
                $errors[] = "Row {$record['row_number']}: {$attendance_date} is a weekend (Saturday/Sunday) - attendance is not tracked for weekends, row skipped";
                $skipped++;
                continue;
            }

            // Public holidays (all-campus or specific to this staff member's
            // campus) are also not working days - attendance is not tracked
            // for them either.
            $holiday_name = $this->getPublicHolidayName($attendance_date, $staff['campus_location'] ?? null);
            if ($holiday_name !== null) {
                $errors[] = "Row {$record['row_number']}: {$attendance_date} is a public holiday ({$holiday_name}) - attendance is not tracked for public holidays, row skipped";
                $skipped++;
                continue;
            }

            // Determine status and check for late arrival
            $status = 'present';
            $is_late = false;
            $check_in_time = $this->normalizeTime($record['check_in_time']);
            if (!$check_in_time) {
                $errors[] = "Row {$record['row_number']}: Could not parse check-in time '{$record['check_in_time']}'";
                $skipped++;
                continue;
            }
            $check_out_time = $this->normalizeTime($record['check_out_time']);

            // Signed in but never signed out on a day that is already over:
            // the day is marked absent and deducted. This replaces the late
            // flag for that day so it is never deducted twice.
            $is_absent = $file_tracks_checkout
                && !$check_out_time
                && $attendance_date < date('Y-m-d');

            if ($is_absent) {
                $status = 'absent';
                $absences[] = [
                    'staff_id' => $staff['id'],
                    'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                    'date' => $attendance_date,
                    'check_in_time' => $check_in_time
                ];
            } elseif ($this->isTimeAfter($check_in_time, '09:00')) {
                // Check if arrival is after 9:00 AM
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
                $check_out_time,
                $status
            );

            if ($result['success']) {
                $processed++;

                // If late arrival, add payroll deduction - once per day, so
                // re-importing the same file doesn't deduct it again.
                if ($is_late && !in_array($result['previous_status'] ?? null, ['late', 'absent'], true)) {
                    $this->addLateArrivalDeduction($staff['id'], $attendance_date, $staff['campus_location'] ?? null);
                }

                // Absent: deduct once per day. Skip when the day was already
                // absent (re-import) or already deducted as late.
                if ($is_absent) {
                    $deduct = !in_array($result['previous_status'] ?? null, ['absent', 'late'], true);
                    if ($deduct) {
                        $this->addAttendanceDeduction($staff['id'], $attendance_date, $staff['campus_location'] ?? null, self::ABSENT_DEDUCTION);
                    }
                    $absences[count($absences) - 1]['deducted'] = $deduct;
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
            'late_arrivals' => $late_arrivals,
            'absences' => $absences
        ];
    }

    /**
     * Find staff by last name (returns first match)
     */
    private function findStaffByLastName($last_name)
    {
        $find_staff = $this->conn->prepare(
            "SELECT id, first_name, last_name, email, campus_location FROM staff WHERE LOWER(last_name) = LOWER(?)"
        );
        $find_staff->bind_param("s", $last_name);
        $find_staff->execute();
        $result = $find_staff->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Check if a Y-m-d date falls on a Saturday or Sunday.
     */
    private function isWeekend($date)
    {
        $dow = (int) (new \DateTime($date))->format('N'); // 1=Mon ... 6=Sat, 7=Sun
        return $dow >= 6;
    }

    /**
     * Returns the name of the public holiday on the given date if one applies
     * to the given campus (or all campuses), or null if it's not a holiday.
     */
    private function getPublicHolidayName($date, $campus_location = null)
    {
        $sql = "SELECT name FROM public_holidays WHERE holiday_date = ? AND (campus_location IS NULL";
        $types = 's';
        $params = [$date];
        if (!empty($campus_location)) {
            $sql .= " OR campus_location = ?";
            $types .= 's';
            $params[] = $campus_location;
        }
        $sql .= ") LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? $row['name'] : null;
    }

    /**
     * Count weekdays (Mon-Fri) in a given month/year, excluding public
     * holidays for the given campus (or all campuses) - used as the working
     * days total for a payroll period, since weekends and holidays are not
     * working days.
     */
    private function countWeekdaysInMonth($month, $year, $campus_location = null)
    {
        $start = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $end = (clone $start)->modify('last day of this month')->modify('+1 day');
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);

        $range_start = $start->format('Y-m-d');
        $range_end = (clone $end)->modify('-1 day')->format('Y-m-d');
        $sql = "SELECT holiday_date FROM public_holidays WHERE holiday_date BETWEEN ? AND ? AND (campus_location IS NULL";
        $types = 'ss';
        $params = [$range_start, $range_end];
        if (!empty($campus_location)) {
            $sql .= " OR campus_location = ?";
            $types .= 's';
            $params[] = $campus_location;
        }
        $sql .= ")";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $holidays = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $holidays[] = $row['holiday_date'];
        }

        $count = 0;
        foreach ($period as $date) {
            $d = $date->format('Y-m-d');
            if ((int) $date->format('N') < 6 && !in_array($d, $holidays, true)) {
                $count++;
            }
        }
        return $count;
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
            "SELECT id, status FROM attendance_records WHERE staff_id = ? AND attendance_date = ?"
        );
        $check_record->bind_param("is", $staff_id, $attendance_date);
        $check_record->execute();
        $exists = $check_record->get_result()->fetch_assoc();
        $previous_status = $exists['status'] ?? null;

        if ($exists) {
            // Update existing record
            $update = $this->conn->prepare(
                "UPDATE attendance_records 
                 SET time_in = ?, time_out = ?, status = ? 
                 WHERE staff_id = ? AND attendance_date = ?"
            );
            $update->bind_param("sssis", $check_in_time, $check_out_time, $status, $staff_id, $attendance_date);

            if ($update->execute()) {
                return ['success' => true, 'message' => 'Attendance updated', 'previous_status' => $previous_status];
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
                return ['success' => true, 'message' => 'Attendance recorded', 'previous_status' => null];
            } else {
                return ['success' => false, 'message' => 'Failed to record attendance: ' . $insert->error];
            }
        }
    }

    /**
     * Add late arrival deduction to payroll
     */
    private function addLateArrivalDeduction($staff_id, $attendance_date, $campus_location = null)
    {
        return $this->addAttendanceDeduction($staff_id, $attendance_date, $campus_location, self::LATE_DEDUCTION);
    }

    /**
     * Add a flat attendance deduction (late or absent) to the staff
     * member's payroll for the month of the given date.
     */
    private function addAttendanceDeduction($staff_id, $attendance_date, $campus_location, $amount)
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
            // Working days in the month, not raw calendar days - weekends
            // are excluded since they are not working days.
            $month_num = (int) date('m', strtotime($attendance_date));
            $year_num = (int) date('Y', strtotime($attendance_date));
            $duration_days = $this->countWeekdaysInMonth($month_num, $year_num, $campus_location);
            $insert_payroll->bind_param("isiii", $staff_id, $month_year, $duration_days, $salary, $salary);
            $insert_payroll->execute();
        }

        // Add/Update late arrival deduction
        $update_deduction = $this->conn->prepare(
            "UPDATE payroll 
             SET attendance_deduction = attendance_deduction + ?
             WHERE staff_id = ? AND month_year = ?"
        );
        $update_deduction->bind_param("dis", $amount, $staff_id, $month_year);
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

<?php

/**
 * Interview Reminders Component
 * Displays scheduled interviews as calendar reminders on applicant dashboard
 * Shows interview date, time, and assigned staff members
 */

function getUpcomingInterviewReminders($conn, $user_id)
{
    $query = "
        SELECT 
            i.id,
            i.interview_date,
            i.interview_time,
            i.interview_type,
            i.notes,
            i.status,
            ja.id as application_id,
            jv.title as job_title,
            jv.company,
            u.first_name as posted_by_first,
            u.last_name as posted_by_last,
            CONCAT(s.first_name, ' ', s.last_name) as interview_staff
        FROM interview_schedules i
        JOIN job_applications ja ON i.application_id = ja.id
        JOIN job_vacancies jv ON ja.job_vacancy_id = jv.id
        JOIN users u ON jv.posted_by = u.id
        LEFT JOIN staff s ON i.scheduled_by = s.id
        WHERE ja.user_id = ? AND i.interview_date >= CURDATE()
        ORDER BY i.interview_date ASC, i.interview_time ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_all(MYSQLI_ASSOC);
}

function formatInterviewDate($date)
{
    return date('D, M d, Y', strtotime($date));
}

function formatInterviewTime($time)
{
    return date('h:i A', strtotime($time));
}

function getDaysUntilInterview($date)
{
    $today = new DateTime();
    $interview = new DateTime($date);
    $interval = $today->diff($interview);

    if ($interval->days == 0) {
        return 'Today';
    } elseif ($interval->days == 1) {
        return 'Tomorrow';
    } else {
        return $interval->days . ' days away';
    }
}

function getInterviewStatusColor($status)
{
    switch ($status) {
        case 'scheduled':
            return '#007BFF';
        case 'completed':
            return '#28A745';
        case 'cancelled':
            return '#DC3545';
        default:
            return '#6C757D';
    }
}

function displayInterviewReminders($conn, $user_id)
{
    $interviews = getUpcomingInterviewReminders($conn, $user_id);

    if (empty($interviews)) {
        return '';
    }

    $html = '
    <div class="interview-reminders">
        <div class="interview-header">
            <h3><i class="fas fa-calendar-check"></i> Interview Reminders</h3>
        </div>
        
        <div class="interview-timeline">';

    foreach ($interviews as $interview) {
        $daysUntil = getDaysUntilInterview($interview['interview_date']);
        $dateFormatted = formatInterviewDate($interview['interview_date']);
        $timeFormatted = formatInterviewTime($interview['interview_time']);
        $statusColor = getInterviewStatusColor($interview['status']);

        $html .= '
            <div class="interview-card" style="border-left: 4px solid ' . $statusColor . '">
                <div class="interview-card-header">
                    <div class="interview-date-time">
                        <div class="interview-date">
                            <strong>' . $dateFormatted . '</strong>
                            <span class="badge badge-light">' . $daysUntil . '</span>
                        </div>
                        <div class="interview-time">
                            <i class="fas fa-clock"></i> ' . $timeFormatted . '
                        </div>
                    </div>
                    <span class="badge badge-' . ($interview['status'] == 'scheduled' ? 'info' : ($interview['status'] == 'completed' ? 'success' : 'danger')) . '">
                        ' . ucfirst($interview['status']) . '
                    </span>
                </div>
                
                <div class="interview-card-body">
                    <div class="interview-job">
                        <h5>' . htmlspecialchars($interview['job_title']) . '</h5>
                        <p class="company-name">' . htmlspecialchars($interview['company']) . '</p>
                    </div>
                    
                    <div class="interview-details">
                        <div class="detail-row">
                            <label>Interview Type:</label>
                            <span>' . htmlspecialchars($interview['interview_type'] ?? 'Not specified') . '</span>
                        </div>';

        if (!empty($interview['interview_staff'])) {
            $html .= '
                        <div class="detail-row">
                            <label><i class="fas fa-users"></i> Interview Panel:</label>
                            <span>' . htmlspecialchars($interview['interview_staff']) . '</span>
                        </div>';
        }

        if (!empty($interview['notes'])) {
            $html .= '
                        <div class="detail-row">
                            <label>Notes:</label>
                            <span>' . htmlspecialchars($interview['notes']) . '</span>
                        </div>';
        }

        $html .= '
                    </div>
                </div>
            </div>';
    }

    $html .= '
        </div>
    </div>';

    return $html;
}

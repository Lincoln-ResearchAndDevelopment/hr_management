<?php

/**
 * HR Manager Class
 * Handles job postings and applications
 */

class HRManager
{
    private $conn;

    public function __construct($database_connection)
    {
        $this->conn = $database_connection;
    }

    /**
     * Post a new job vacancy
     */
    public function postJobVacancy($title, $description, $company, $location, $salary_range, $employment_type, $deadline, $posted_by)
    {
        // Validate inputs
        if (empty($title) || empty($description) || empty($company) || empty($location)) {
            return ['success' => false, 'message' => 'All required fields must be filled'];
        }

        $allowed_locations = [
            'Lincoln College, Abuja Campus',
            'Lincoln University, NSUK Campus',
            'Lincoln University, Kumo Campus'
        ];

        if (!in_array($location, $allowed_locations, true)) {
            return ['success' => false, 'message' => 'Invalid campus location selected'];
        }

        $insert_job = $this->conn->prepare(
            "INSERT INTO job_vacancies (title, description, company, location, salary_range, employment_type, deadline, posted_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $insert_job->bind_param("sssssssi", $title, $description, $company, $location, $salary_range, $employment_type, $deadline, $posted_by);

        if ($insert_job->execute()) {
            return ['success' => true, 'message' => 'Job vacancy posted successfully', 'job_id' => $this->conn->insert_id];
        } else {
            return ['success' => false, 'message' => 'Failed to post job vacancy: ' . $this->conn->error];
        }
    }

    /**
     * Get all active job vacancies
     */
    public function getActiveJobs($limit = null)
    {
        $query = "SELECT * FROM job_vacancies WHERE is_active = 1 ORDER BY posted_date DESC";

        if ($limit) {
            $query .= " LIMIT " . intval($limit);
        }

        $result = $this->conn->query($query);

        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    }

    /**
     * Get job vacancy by ID
     */
    public function getJobById($job_id)
    {
        $get_job = $this->conn->prepare("SELECT * FROM job_vacancies WHERE id = ? AND is_active = 1");
        $get_job->bind_param("i", $job_id);
        $get_job->execute();

        return $get_job->get_result()->fetch_assoc();
    }

    /**
     * Get all jobs posted by HR
     */
    public function getHRJobs($hr_id)
    {
        $get_jobs = $this->conn->prepare("SELECT * FROM job_vacancies WHERE posted_by = ? ORDER BY posted_date DESC");
        $get_jobs->bind_param("i", $hr_id);
        $get_jobs->execute();

        return $get_jobs->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Apply for a job
     */
    public function applyForJob($job_vacancy_id, $user_id, $resume_url = null, $cover_letter = null, $linkedin_url = null, $github_url = null, $nysc_cert = null, $degree_cert = null, $masters_cert = null)
    {
        // Check if already applied
        $check_application = $this->conn->prepare(
            "SELECT id FROM job_applications WHERE job_vacancy_id = ? AND user_id = ?"
        );
        $check_application->bind_param("ii", $job_vacancy_id, $user_id);
        $check_application->execute();

        if ($check_application->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'You have already applied for this job'];
        }

        // Insert application with new certificate columns
        $apply = $this->conn->prepare(
            "INSERT INTO job_applications (job_vacancy_id, user_id, cv_path, cover_letter, linkedin_profile, facebook_profile, nysc_certificate, degree_certificate, masters_certificate) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        // Using cv_path for resume, linkedin_profile instead of linkedin_url, facebook_profile for github (as temp solution)
        $apply->bind_param("iisssssss", $job_vacancy_id, $user_id, $resume_url, $cover_letter, $linkedin_url, $github_url, $nysc_cert, $degree_cert, $masters_cert);

        if ($apply->execute()) {
            $application_id = $this->conn->insert_id;

            // Update applicant count
            $update_count = $this->conn->prepare(
                "UPDATE job_vacancies SET applicants_count = applicants_count + 1 WHERE id = ?"
            );
            $update_count->bind_param("i", $job_vacancy_id);
            $update_count->execute();

            // Send notification to HR manager
            $this->notifyHRAboutNewApplication($job_vacancy_id, $user_id);

            return ['success' => true, 'message' => 'Application submitted successfully', 'application_id' => $application_id];
        } else {
            return ['success' => false, 'message' => 'Failed to submit application: ' . $this->conn->error];
        }
    }

    /**
     * Notify HR about new application
     */
    private function notifyHRAboutNewApplication($job_vacancy_id, $applicant_user_id)
    {
        try {
            require_once __DIR__ . '/../classes/Mailer.php';

            // Get job details and HR info
            $job_query = $this->conn->prepare(
                "SELECT jv.*, u.id as hr_id, u.first_name as hr_first, u.last_name as hr_last, u.email as hr_email
                 FROM job_vacancies jv
                 JOIN users u ON jv.posted_by = u.id
                 WHERE jv.id = ?"
            );
            $job_query->bind_param("i", $job_vacancy_id);
            $job_query->execute();
            $job_data = $job_query->get_result()->fetch_assoc();

            // Get applicant info
            $applicant_query = $this->conn->prepare(
                "SELECT first_name, last_name, email, phone FROM users WHERE id = ?"
            );
            $applicant_query->bind_param("i", $applicant_user_id);
            $applicant_query->execute();
            $applicant_data = $applicant_query->get_result()->fetch_assoc();

            if ($job_data && $applicant_data) {
                $mailer = new Mailer();
                $mailer->sendNewApplicantNotification(
                    $job_data['hr_email'],
                    $job_data['hr_first'] . ' ' . $job_data['hr_last'],
                    $applicant_data['first_name'] . ' ' . $applicant_data['last_name'],
                    $job_data['title'],
                    $job_data['company'],
                    $applicant_data['email'],
                    $applicant_data['phone'] ?? 'Not provided'
                );
            }
        } catch (Exception $e) {
            error_log("Failed to notify HR about new application: " . $e->getMessage());
        }
    }

    /**
     * Get user applications
     */
    public function getUserApplications($user_id)
    {
        $get_apps = $this->conn->prepare(
            "SELECT ja.*, jv.title, jv.company, jv.location, jv.salary_range 
             FROM job_applications ja 
             JOIN job_vacancies jv ON ja.job_vacancy_id = jv.id 
             WHERE ja.user_id = ? 
             ORDER BY ja.applied_date DESC"
        );

        $get_apps->bind_param("i", $user_id);
        $get_apps->execute();

        return $get_apps->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get job applicants
     */
    public function getJobApplicants($job_vacancy_id)
    {
        $get_applicants = $this->conn->prepare(
            "SELECT ja.*, u.first_name, u.last_name, u.email, u.phone 
             FROM job_applications ja 
             JOIN users u ON ja.user_id = u.id 
             WHERE ja.job_vacancy_id = ? 
             ORDER BY ja.applied_date DESC"
        );

        $get_applicants->bind_param("i", $job_vacancy_id);
        $get_applicants->execute();

        return $get_applicants->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get applicants across all jobs
     */
    public function getAllApplicants($hr_id = null)
    {
        $base_query = "SELECT ja.*, u.first_name, u.last_name, u.email, u.phone,
                              jv.title AS job_title, jv.location AS job_location, jv.company AS job_company
                       FROM job_applications ja
                       JOIN users u ON ja.user_id = u.id
                       JOIN job_vacancies jv ON ja.job_vacancy_id = jv.id";

        if ($hr_id !== null) {
            $base_query .= " WHERE jv.posted_by = ?";
        }

        $base_query .= " ORDER BY ja.applied_date DESC";

        if ($hr_id !== null) {
            $stmt = $this->conn->prepare($base_query);
            $stmt->bind_param("i", $hr_id);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $result = $this->conn->query($base_query);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }

        return [];
    }

    /**
     * Update job status
     */
    public function updateJobStatus($job_id, $is_active)
    {
        $update = $this->conn->prepare("UPDATE job_vacancies SET is_active = ? WHERE id = ?");
        $update->bind_param("ii", $is_active, $job_id);

        if ($update->execute()) {
            return ['success' => true, 'message' => 'Job status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update job status'];
        }
    }

    /**
     * Update application status
     */
    public function updateApplicationStatus($application_id, $status)
    {
        $valid_statuses = ['pending', 'reviewed', 'shortlisted', 'rejected', 'accepted'];

        if (!in_array($status, $valid_statuses)) {
            return ['success' => false, 'message' => 'Invalid application status'];
        }

        $update = $this->conn->prepare("UPDATE job_applications SET status = ? WHERE id = ?");
        $update->bind_param("si", $status, $application_id);

        if ($update->execute()) {
            return ['success' => true, 'message' => 'Application status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update application status'];
        }
    }
}

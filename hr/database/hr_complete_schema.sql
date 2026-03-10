-- HR System Complete Database Schema (26 Tables)
-- Database: hrsystem
-- This schema includes all necessary tables for a complete HR management system

-- 1. Users/Employees Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    phone VARCHAR(15),
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'hr', 'manager', 'employee') DEFAULT 'employee',
    department_id INT,
    position_id INT,
    hire_date DATE,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    postal_code VARCHAR(20),
    emergency_contact VARCHAR(100),
    emergency_contact_phone VARCHAR(15),
    profile_image VARCHAR(255),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (position_id) REFERENCES positions(id),
    INDEX idx_email (email),
    INDEX idx_employee_id (employee_id),
    INDEX idx_user_type (user_type),
    INDEX idx_department_id (department_id)
);

-- 2. Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description LONGTEXT,
    manager_id INT,
    budget DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name)
);

-- 3. Positions/Designations Table
CREATE TABLE IF NOT EXISTS positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL UNIQUE,
    description LONGTEXT,
    department_id INT,
    salary_range_min DECIMAL(12,2),
    salary_range_max DECIMAL(12,2),
    level VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    INDEX idx_title (title),
    INDEX idx_department_id (department_id)
);

-- 4. Job Postings Table
CREATE TABLE IF NOT EXISTS job_postings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    description LONGTEXT NOT NULL,
    department_id INT NOT NULL,
    position_id INT,
    required_qualifications LONGTEXT,
    preferred_qualifications LONGTEXT,
    salary_range VARCHAR(100),
    job_type ENUM('full-time', 'part-time', 'contract', 'temporary') DEFAULT 'full-time',
    experience_required INT,
    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closing_date DATE NOT NULL,
    status ENUM('open', 'closed', 'on-hold') DEFAULT 'open',
    posted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (position_id) REFERENCES positions(id),
    FOREIGN KEY (posted_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_closing_date (closing_date)
);

-- 5. Applications Table
CREATE TABLE IF NOT EXISTS applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_posting_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    resume_path VARCHAR(255),
    cover_letter LONGTEXT,
    qualifications LONGTEXT,
    experience_years INT,
    current_company VARCHAR(100),
    current_position VARCHAR(100),
    status ENUM('applied', 'shortlisted', 'interviewed', 'rejected', 'hired') DEFAULT 'applied',
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    applied_by INT,
    interview_date DATETIME,
    interviewer_id INT,
    notes LONGTEXT,
    rating INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_posting_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (applied_by) REFERENCES users(id),
    FOREIGN KEY (interviewer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_job_posting_id (job_posting_id),
    INDEX idx_application_date (application_date)
);

-- 6. Leave Requests Table
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    number_of_days DECIMAL(5,2),
    reason LONGTEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approver_id INT,
    approval_date DATETIME,
    approval_notes LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status),
    INDEX idx_start_date (start_date)
);

-- 7. Leave Types Table
CREATE TABLE IF NOT EXISTS leave_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description LONGTEXT,
    annual_allocation INT DEFAULT 0,
    is_paid BOOLEAN DEFAULT 1,
    requires_approval BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
);

-- 8. Attendance Table
CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    status ENUM('present', 'absent', 'late', 'half-day') DEFAULT 'absent',
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (employee_id, attendance_date),
    INDEX idx_attendance_date (attendance_date),
    INDEX idx_employee_id (employee_id)
);

-- 9. Payroll Table
CREATE TABLE IF NOT EXISTS payroll (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    payroll_month DATE NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL,
    allowances DECIMAL(12,2) DEFAULT 0,
    deductions DECIMAL(12,2) DEFAULT 0,
    gross_salary DECIMAL(12,2),
    net_salary DECIMAL(12,2),
    tax_amount DECIMAL(12,2) DEFAULT 0,
    payment_method ENUM('bank_transfer', 'check', 'cash') DEFAULT 'bank_transfer',
    payment_date DATE,
    status ENUM('pending', 'processed', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payroll (employee_id, payroll_month),
    INDEX idx_payroll_month (payroll_month),
    INDEX idx_status (status)
);

-- 10. Performance Appraisals Table
CREATE TABLE IF NOT EXISTS performance_appraisals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    appraiser_id INT NOT NULL,
    appraisal_period_start DATE NOT NULL,
    appraisal_period_end DATE NOT NULL,
    overall_rating INT,
    communication_rating INT,
    productivity_rating INT,
    teamwork_rating INT,
    reliability_rating INT,
    comments LONGTEXT,
    recommendations LONGTEXT,
    status ENUM('draft', 'submitted', 'reviewed', 'finalized') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appraiser_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_employee_id (employee_id),
    INDEX idx_appraisal_period_start (appraisal_period_start)
);

-- 11. Training Requests Table
CREATE TABLE IF NOT EXISTS training_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    training_name VARCHAR(150) NOT NULL,
    training_provider VARCHAR(150),
    training_type VARCHAR(100),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    cost DECIMAL(10,2),
    reason LONGTEXT,
    status ENUM('pending', 'approved', 'completed', 'rejected') DEFAULT 'pending',
    approver_id INT,
    approval_date DATETIME,
    certificate_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status)
);

-- 12. Complaints/Grievances Table
CREATE TABLE IF NOT EXISTS complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id VARCHAR(50) UNIQUE NOT NULL,
    complainant_id INT NOT NULL,
    respondent_id INT,
    complaint_type VARCHAR(100) NOT NULL,
    description LONGTEXT NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('open', 'in-progress', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT,
    resolution LONGTEXT,
    complaint_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolution_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (complainant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (respondent_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_complaint_date (complaint_date)
);

-- 13. Disciplinary Actions Table
CREATE TABLE IF NOT EXISTS disciplinary_actions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    action_type ENUM('warning', 'suspension', 'demotion', 'termination') NOT NULL,
    reason LONGTEXT NOT NULL,
    issued_date DATE NOT NULL,
    effective_date DATE,
    duration_days INT,
    issued_by INT,
    comments LONGTEXT,
    appeal_status ENUM('no-appeal', 'appealed', 'appeal-approved', 'appeal-rejected') DEFAULT 'no-appeal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_id (employee_id),
    INDEX idx_action_type (action_type)
);

-- 14. Employee Requests (Late Arrival, Permission, etc.) Table
CREATE TABLE IF NOT EXISTS employee_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    request_type ENUM('late-arrival', 'permission', 'temporary-exit') NOT NULL,
    request_date DATE NOT NULL,
    expected_time TIME,
    actual_time TIME,
    reason LONGTEXT,
    duration_minutes INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approver_id INT,
    approval_date DATETIME,
    approval_notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_id (employee_id),
    INDEX idx_request_type (request_type),
    INDEX idx_status (status)
);

-- 15. Benefits Table
CREATE TABLE IF NOT EXISTS benefits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description LONGTEXT,
    benefit_type ENUM('health', 'insurance', 'retirement', 'wellness', 'other') DEFAULT 'other',
    provider VARCHAR(150),
    cost_per_employee DECIMAL(10,2),
    coverage_details LONGTEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
);

-- 16. Employee Benefits Assignment Table
CREATE TABLE IF NOT EXISTS employee_benefits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    benefit_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    coverage_type VARCHAR(100),
    premium_contribution DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (benefit_id) REFERENCES benefits(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (employee_id, benefit_id),
    INDEX idx_employee_id (employee_id)
);

-- 17. Schedules/Shifts Table
CREATE TABLE IF NOT EXISTS schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    schedule_name VARCHAR(100) NOT NULL,
    shift_type ENUM('morning', 'afternoon', 'night', 'flexible') DEFAULT 'morning',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_duration INT DEFAULT 60,
    description LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_schedule_name (schedule_name)
);

-- 18. Employee Schedule Assignment Table
CREATE TABLE IF NOT EXISTS employee_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    schedule_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    INDEX idx_employee_id (employee_id)
);

-- 19. Communication/Announcements Table
CREATE TABLE IF NOT EXISTS announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content LONGTEXT NOT NULL,
    announcement_type ENUM('general', 'department', 'urgent', 'policy') DEFAULT 'general',
    posted_by INT NOT NULL,
    visibility ENUM('all', 'department', 'selected-users') DEFAULT 'all',
    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id),
    INDEX idx_posted_date (posted_date),
    INDEX idx_visibility (visibility)
);

-- 20. HR Sessions Table
CREATE TABLE IF NOT EXISTS hr_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hr_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hr_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_hr_id (hr_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
);

-- 21. HR Activity Logs Table
CREATE TABLE IF NOT EXISTS hr_activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hr_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    description LONGTEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hr_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_hr_id (hr_id),
    INDEX idx_timestamp (timestamp)
);

-- 22. Interview Staff Assignment Table
CREATE TABLE IF NOT EXISTS interview_staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    interview_id INT NOT NULL,
    staff_id INT NOT NULL,
    role VARCHAR(100) DEFAULT 'Interviewer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (interview_id) REFERENCES interview_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_interview_id (interview_id),
    INDEX idx_staff_id (staff_id)
);

-- 22. Interview Schedules Table
CREATE TABLE IF NOT EXISTS interview_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_id INT NOT NULL,
    interviewer_id INT NOT NULL,
    interview_date DATETIME NOT NULL,
    location VARCHAR(255),
    interview_type ENUM('phone', 'video', 'in-person') DEFAULT 'in-person',
    round INT DEFAULT 1,
    status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    feedback LONGTEXT,
    rating INT,
    notes LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (interviewer_id) REFERENCES users(id),
    INDEX idx_interview_date (interview_date),
    INDEX idx_status (status)
);

-- 23. Promotion/Transfer Requests Table
CREATE TABLE IF NOT EXISTS promotion_transfers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    request_type ENUM('promotion', 'transfer') NOT NULL,
    from_position_id INT,
    to_position_id INT NOT NULL,
    from_department_id INT,
    to_department_id INT NOT NULL,
    reason LONGTEXT,
    requested_date DATE NOT NULL,
    effective_date DATE,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    approver_id INT,
    approval_notes LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_position_id) REFERENCES positions(id),
    FOREIGN KEY (to_position_id) REFERENCES positions(id),
    FOREIGN KEY (from_department_id) REFERENCES departments(id),
    FOREIGN KEY (to_department_id) REFERENCES departments(id),
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status)
);

-- 24. Performance Goals Table
CREATE TABLE IF NOT EXISTS performance_goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    goal_title VARCHAR(150) NOT NULL,
    description LONGTEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    target_value VARCHAR(100),
    progress_percentage INT DEFAULT 0,
    status ENUM('not-started', 'in-progress', 'completed', 'failed') DEFAULT 'not-started',
    created_by INT,
    review_comments LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status)
);

-- 25. Certificates/Qualifications Table
CREATE TABLE IF NOT EXISTS certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    certificate_name VARCHAR(150) NOT NULL,
    issuing_organization VARCHAR(150),
    issue_date DATE NOT NULL,
    expiry_date DATE,
    certificate_file VARCHAR(255),
    credential_id VARCHAR(100),
    credential_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_employee_id (employee_id),
    INDEX idx_expiry_date (expiry_date)
);

-- 26. System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value LONGTEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Create indexes for frequently searched fields
CREATE INDEX idx_users_active ON users(is_active);
CREATE INDEX idx_applications_email ON applications(email);
CREATE INDEX idx_payroll_employee_status ON payroll(employee_id, status);
CREATE INDEX idx_attendance_employee_date ON attendance(employee_id, attendance_date);

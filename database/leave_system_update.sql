-- Leave Management System Update
-- This script adds comprehensive leave types and allocation tracking

-- Drop existing leave-related tables if they exist (be careful in production)
-- DROP TABLE IF EXISTS leave_allocations;
-- DROP TABLE IF EXISTS leave_types;
-- DROP TABLE IF EXISTS leave_requests;

-- Create Leave Types Table with specific allocations
CREATE TABLE IF NOT EXISTS leave_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    days_allocated INT DEFAULT 0,
    allocation_unit ENUM('days', 'weeks') DEFAULT 'days',
    min_days INT DEFAULT NULL,
    max_days INT DEFAULT NULL,
    is_paid BOOLEAN DEFAULT 1,
    requires_approval BOOLEAN DEFAULT 1,
    is_unlimited BOOLEAN DEFAULT 0,
    is_one_time BOOLEAN DEFAULT 0,
    gender_specific ENUM('none', 'female', 'male') DEFAULT 'none',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_is_active (is_active)
);

-- Update Leave Requests Table
-- First, create the table if it doesn't exist (new installation)
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    request_date DATE NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    substitute_staff_id INT DEFAULT NULL,
    supporting_documents VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    hr_remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (substitute_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_staff_id (staff_id),
    INDEX idx_status (status),
    INDEX idx_start_date (start_date)
);

-- Now alter the table to add new columns if they don't exist (migration for existing installations)
SET @dbname = DATABASE();
SET @tablename = 'leave_requests';
SET @columnname1 = 'leave_type_id';
SET @columnname2 = 'total_days';
SET @columnname3 = 'approved_by';
SET @columnname4 = 'approved_at';

-- Add leave_type_id column if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname1)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname1, ' INT NOT NULL DEFAULT 1 AFTER staff_id')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add total_days column if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname2)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname2, ' INT NOT NULL DEFAULT 1 AFTER end_date')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add approved_by column if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname3)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname3, ' INT DEFAULT NULL AFTER hr_remarks')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add approved_at column if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname4)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname4, ' DATETIME DEFAULT NULL AFTER approved_by')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add foreign keys and indexes if they don't exist
-- Check and add foreign key for leave_type_id
SET @fk_check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND CONSTRAINT_NAME = 'leave_requests_ibfk_leave_type');

SET @preparedStatement = IF(@fk_check > 0,
  'SELECT 1',
  'ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_ibfk_leave_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add foreign key for approved_by
SET @fk_check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = @dbname 
                 AND TABLE_NAME = @tablename 
                 AND CONSTRAINT_NAME = 'leave_requests_ibfk_approved_by');

SET @preparedStatement = IF(@fk_check > 0,
  'SELECT 1',
  'ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_ibfk_approved_by FOREIGN KEY (approved_by) REFERENCES staff(id) ON DELETE SET NULL'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add index for leave_type_id
SET @idx_check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                  WHERE TABLE_SCHEMA = @dbname 
                  AND TABLE_NAME = @tablename 
                  AND INDEX_NAME = 'idx_leave_type_id');

SET @preparedStatement = IF(@idx_check > 0,
  'SELECT 1',
  'ALTER TABLE leave_requests ADD INDEX idx_leave_type_id (leave_type_id)'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Create Leave Allocations Table (tracks used/available leave per staff per type)
CREATE TABLE IF NOT EXISTS leave_allocations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    year INT NOT NULL,
    days_allocated INT NOT NULL,
    days_used INT DEFAULT 0,
    days_remaining INT NOT NULL,
    is_exhausted BOOLEAN DEFAULT 0,
    has_used_one_time BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_allocation (staff_id, leave_type_id, year),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    INDEX idx_staff_id (staff_id),
    INDEX idx_year (year),
    INDEX idx_is_exhausted (is_exhausted)
);

-- Insert Leave Types with specified allocations
INSERT INTO leave_types (name, description, days_allocated, allocation_unit, min_days, max_days, is_paid, requires_approval, is_unlimited, is_one_time, gender_specific, is_active) VALUES
('Annual Leave', 'Annual vacation leave - 14 days at a stretch, minimum 7 days', 14, 'days', 7, 14, 1, 1, 0, 0, 'none', 1),
('Maternity Leave', 'For female staff during pregnancy and childbirth - 2 to 4 weeks', 28, 'days', 14, 28, 1, 1, 0, 0, 'female', 1),
('Paternity Leave', 'For male staff - 2 days (birth day and dedication day)', 2, 'days', 1, 2, 1, 1, 0, 0, 'male', 1),
('Compassionate Leave', 'For disasters (death, fire, flood, etc.) - 2 days', 2, 'days', 1, 2, 1, 1, 0, 0, 'none', 1),
('Serious Illness Leave', 'For serious illness - 2 days (alternative: use annual leave)', 2, 'days', 1, 2, 1, 1, 0, 0, 'none', 1),
('Marriage Leave', 'First time marriage only - 3 days', 3, 'days', 1, 3, 1, 1, 0, 1, 'none', 1),
('Unpaid Leave', 'Unpaid leave - available to anybody at any time', 0, 'days', NULL, NULL, 0, 1, 1, 0, 'none', 1),
('Special Leave (Conference)', 'For conferences, seminars, and professional development', 5, 'days', 1, 5, 1, 1, 0, 0, 'none', 1),
('Religious Leave', 'Religious observance leave - 30 days', 30, 'days', 1, 30, 1, 1, 0, 0, 'none', 1)
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    days_allocated = VALUES(days_allocated),
    allocation_unit = VALUES(allocation_unit),
    min_days = VALUES(min_days),
    max_days = VALUES(max_days),
    is_paid = VALUES(is_paid),
    is_unlimited = VALUES(is_unlimited),
    is_one_time = VALUES(is_one_time),
    gender_specific = VALUES(gender_specific),
    updated_at = CURRENT_TIMESTAMP;

-- Create trigger to update leave allocations when leave is approved
DELIMITER $$

DROP TRIGGER IF EXISTS update_leave_allocation_after_approval$$
CREATE TRIGGER update_leave_allocation_after_approval
AFTER UPDATE ON leave_requests
FOR EACH ROW
BEGIN
    DECLARE current_year INT;
    DECLARE allocated_days INT;
    DECLARE is_one_time_leave BOOLEAN;
    
    SET current_year = YEAR(NEW.start_date);
    
    -- Only process if status changed to approved
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        -- Get leave type details
        SELECT days_allocated, is_one_time INTO allocated_days, is_one_time_leave
        FROM leave_types 
        WHERE id = NEW.leave_type_id;
        
        -- Check if allocation record exists, if not create it
        IF NOT EXISTS (
            SELECT 1 FROM leave_allocations 
            WHERE staff_id = NEW.staff_id 
            AND leave_type_id = NEW.leave_type_id 
            AND year = current_year
        ) THEN
            INSERT INTO leave_allocations (staff_id, leave_type_id, year, days_allocated, days_used, days_remaining, has_used_one_time)
            VALUES (NEW.staff_id, NEW.leave_type_id, current_year, allocated_days, 0, allocated_days, 0);
        END IF;
        
        -- Update the allocation
        UPDATE leave_allocations
        SET 
            days_used = days_used + NEW.total_days,
            days_remaining = days_remaining - NEW.total_days,
            is_exhausted = IF(days_remaining - NEW.total_days <= 0, 1, 0),
            has_used_one_time = IF(is_one_time_leave = 1, 1, has_used_one_time),
            updated_at = CURRENT_TIMESTAMP
        WHERE staff_id = NEW.staff_id 
        AND leave_type_id = NEW.leave_type_id 
        AND year = current_year;
    END IF;
    
    -- If status changed from approved to rejected/cancelled, restore the allocation
    IF OLD.status = 'approved' AND NEW.status IN ('rejected', 'cancelled') THEN
        UPDATE leave_allocations
        SET 
            days_used = days_used - NEW.total_days,
            days_remaining = days_remaining + NEW.total_days,
            is_exhausted = IF(days_remaining + NEW.total_days > 0, 0, is_exhausted),
            updated_at = CURRENT_TIMESTAMP
        WHERE staff_id = NEW.staff_id 
        AND leave_type_id = NEW.leave_type_id 
        AND year = current_year;
    END IF;
END$$

DELIMITER ;

-- Stored procedure to initialize leave allocations for all active staff for the current year
DELIMITER $$

DROP PROCEDURE IF EXISTS initialize_staff_leave_allocations$$
CREATE PROCEDURE initialize_staff_leave_allocations(IN p_year INT)
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_staff_id INT;
    DECLARE v_staff_gender VARCHAR(10);
    DECLARE gender_column_exists INT DEFAULT 0;
    
    -- Check if gender column exists in staff table
    SELECT COUNT(*) INTO gender_column_exists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'staff'
    AND COLUMN_NAME = 'gender';
    
    -- Cursor for all active staff (with or without gender)
    IF gender_column_exists > 0 THEN
        BEGIN
            DECLARE staff_cursor CURSOR FOR 
                SELECT id, COALESCE(gender, 'none') FROM staff WHERE status = 'active';
            
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
            
            OPEN staff_cursor;
            
            staff_loop: LOOP
                FETCH staff_cursor INTO v_staff_id, v_staff_gender;
                
                IF done THEN
                    LEAVE staff_loop;
                END IF;
                
                -- Insert allocations for each leave type
                INSERT INTO leave_allocations (staff_id, leave_type_id, year, days_allocated, days_used, days_remaining, is_exhausted, has_used_one_time)
                SELECT 
                    v_staff_id,
                    lt.id,
                    p_year,
                    lt.days_allocated,
                    0,
                    lt.days_allocated,
                    0,
                    0
                FROM leave_types lt
                WHERE lt.is_active = 1
                AND (
                    lt.gender_specific = 'none' 
                    OR (lt.gender_specific = 'female' AND v_staff_gender = 'female')
                    OR (lt.gender_specific = 'male' AND v_staff_gender = 'male')
                )
                ON DUPLICATE KEY UPDATE
                    days_allocated = VALUES(days_allocated),
                    updated_at = CURRENT_TIMESTAMP;
                    
            END LOOP;
            
            CLOSE staff_cursor;
        END;
    ELSE
        -- If gender column doesn't exist, assign all non-gender-specific leave types
        BEGIN
            DECLARE staff_cursor_no_gender CURSOR FOR 
                SELECT id FROM staff WHERE status = 'active';
            
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
            
            OPEN staff_cursor_no_gender;
            
            staff_loop_no_gender: LOOP
                FETCH staff_cursor_no_gender INTO v_staff_id;
                
                IF done THEN
                    LEAVE staff_loop_no_gender;
                END IF;
                
                -- Insert allocations for non-gender-specific leave types only
                INSERT INTO leave_allocations (staff_id, leave_type_id, year, days_allocated, days_used, days_remaining, is_exhausted, has_used_one_time)
                SELECT 
                    v_staff_id,
                    lt.id,
                    p_year,
                    lt.days_allocated,
                    0,
                    lt.days_allocated,
                    0,
                    0
                FROM leave_types lt
                WHERE lt.is_active = 1
                AND lt.gender_specific = 'none'
                ON DUPLICATE KEY UPDATE
                    days_allocated = VALUES(days_allocated),
                    updated_at = CURRENT_TIMESTAMP;
                    
            END LOOP;
            
            CLOSE staff_cursor_no_gender;
        END;
    END IF;
END$$

DELIMITER ;

-- Function to check if staff can request a specific leave type
DELIMITER $$

DROP FUNCTION IF EXISTS can_request_leave$$
CREATE FUNCTION can_request_leave(
    p_staff_id INT,
    p_leave_type_id INT,
    p_days_requested INT,
    p_year INT
) RETURNS BOOLEAN
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_is_unlimited BOOLEAN;
    DECLARE v_is_one_time BOOLEAN;
    DECLARE v_days_remaining INT;
    DECLARE v_is_exhausted BOOLEAN;
    DECLARE v_has_used_one_time BOOLEAN;
    DECLARE v_can_request BOOLEAN DEFAULT 0;
    
    -- Get leave type details
    SELECT is_unlimited, is_one_time INTO v_is_unlimited, v_is_one_time
    FROM leave_types
    WHERE id = p_leave_type_id;
    
    -- Unlimited leave (like unpaid leave) is always available
    IF v_is_unlimited = 1 THEN
        RETURN 1;
    END IF;
    
    -- Check if allocation exists
    IF EXISTS (
        SELECT 1 FROM leave_allocations 
        WHERE staff_id = p_staff_id 
        AND leave_type_id = p_leave_type_id 
        AND year = p_year
    ) THEN
        SELECT days_remaining, is_exhausted, has_used_one_time 
        INTO v_days_remaining, v_is_exhausted, v_has_used_one_time
        FROM leave_allocations
        WHERE staff_id = p_staff_id 
        AND leave_type_id = p_leave_type_id 
        AND year = p_year;
        
        -- Check one-time leave (like marriage leave)
        IF v_is_one_time = 1 AND v_has_used_one_time = 1 THEN
            RETURN 0;
        END IF;
        
        -- Check if exhausted
        IF v_is_exhausted = 1 OR v_days_remaining < p_days_requested THEN
            RETURN 0;
        END IF;
        
        RETURN 1;
    ELSE
        -- No allocation exists, cannot request
        RETURN 0;
    END IF;
END$$

DELIMITER ;

-- Initialize allocations for all active staff for current year
CALL initialize_staff_leave_allocations(YEAR(CURDATE()));

-- View to easily see staff leave balances
-- Note: This view will work with or without a gender column in the staff table
SET @gender_col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'staff' 
                          AND COLUMN_NAME = 'gender');

SET @create_view_sql = IF(@gender_col_exists > 0,
    "CREATE OR REPLACE VIEW staff_leave_balance_view AS
    SELECT 
        s.id as staff_id,
        s.first_name,
        s.last_name,
        s.email,
        COALESCE(s.gender, 'none') as gender,
        lt.id as leave_type_id,
        lt.name as leave_type,
        lt.description,
        lt.is_unlimited,
        lt.is_one_time,
        la.year,
        COALESCE(la.days_allocated, lt.days_allocated) as days_allocated,
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
    FROM staff s
    CROSS JOIN leave_types lt
    LEFT JOIN leave_allocations la ON la.staff_id = s.id 
        AND la.leave_type_id = lt.id 
        AND la.year = YEAR(CURDATE())
    WHERE s.status = 'active'
        AND lt.is_active = 1
        AND (
            lt.gender_specific = 'none'
            OR (lt.gender_specific = 'female' AND s.gender = 'female')
            OR (lt.gender_specific = 'male' AND s.gender = 'male')
        )",
    "CREATE OR REPLACE VIEW staff_leave_balance_view AS
    SELECT 
        s.id as staff_id,
        s.first_name,
        s.last_name,
        s.email,
        'none' as gender,
        lt.id as leave_type_id,
        lt.name as leave_type,
        lt.description,
        lt.is_unlimited,
        lt.is_one_time,
        la.year,
        COALESCE(la.days_allocated, lt.days_allocated) as days_allocated,
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
    FROM staff s
    CROSS JOIN leave_types lt
    LEFT JOIN leave_allocations la ON la.staff_id = s.id 
        AND la.leave_type_id = lt.id 
        AND la.year = YEAR(CURDATE())
    WHERE s.status = 'active'
        AND lt.is_active = 1
        AND lt.gender_specific = 'none'"
);

PREPARE create_view_stmt FROM @create_view_sql;
EXECUTE create_view_stmt;
DEALLOCATE PREPARE create_view_stmt;

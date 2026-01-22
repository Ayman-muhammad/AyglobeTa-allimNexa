-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS if0_40733989_wch;
USE if0_40733989_wch;

-- Drop tables in reverse dependency order (to avoid foreign key constraints)
DROP TABLE IF EXISTS download_logs;
DROP TABLE IF EXISTS saved_documents;
DROP TABLE IF EXISTS admin_logs;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS document_views;
DROP TABLE IF EXISTS chat_logs;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS feedback_likes;
DROP TABLE IF EXISTS feedbacks;
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS timetable_comments;
DROP TABLE IF EXISTS timetable_views;
DROP TABLE IF EXISTS timetable_privacy;
DROP TABLE IF EXISTS timetable_reminders;
DROP TABLE IF EXISTS semester_dates;
DROP TABLE IF EXISTS university_rooms;
DROP TABLE IF EXISTS university_buildings;
DROP TABLE IF EXISTS timetable_course_units;
DROP TABLE IF EXISTS timetable_academic_info;
DROP TABLE IF EXISTS user_academic_info;
DROP TABLE IF EXISTS course_units;
DROP TABLE IF EXISTS template_entries;
DROP TABLE IF EXISTS timetable_entries;
DROP TABLE IF EXISTS shared_timetables;
DROP TABLE IF EXISTS timetable_templates;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS faculties;
DROP TABLE IF EXISTS user_timetables;
DROP TABLE IF EXISTS users;

-- Enhanced Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    verification_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_token_expiry DATETIME,
    is_verified BOOLEAN DEFAULT FALSE,
    is_admin BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    login_attempts INT DEFAULT 0,
    last_login_attempt DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Timetable table
CREATE TABLE user_timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    academic_year VARCHAR(20),
    semester VARCHAR(50),
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Additional tables for university features

-- Faculties/Schools table
CREATE TABLE faculties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    description TEXT,
    dean_name VARCHAR(100),
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments table
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    description TEXT,
    head_of_department VARCHAR(100),
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE CASCADE
);

-- Courses/Programs table
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    duration_years INT DEFAULT 4,
    total_credits INT DEFAULT 120,
    description TEXT,
    course_coordinator VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Enhanced Documents table
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    original_title VARCHAR(255),
    description TEXT,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    category VARCHAR(100),
    subcategory VARCHAR(100),
    education_level ENUM('JSS', 'CBC', 'University', 'College', 'General'),
    tags TEXT,
    version INT DEFAULT 1,
    parent_version_id INT DEFAULT NULL,
    is_latest BOOLEAN DEFAULT TRUE,
    download_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    total_ratings INT DEFAULT 0,
    report_count INT DEFAULT 0,
    is_approved BOOLEAN DEFAULT TRUE,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_version_id) REFERENCES documents(id) ON DELETE SET NULL
);

-- Timetable entries/events
CREATE TABLE timetable_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject_title VARCHAR(255) NOT NULL,
    subject_code VARCHAR(50),
    room_number VARCHAR(50),
    lecturer VARCHAR(100),
    color VARCHAR(20) DEFAULT '#007bff',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES user_timetables(id) ON DELETE CASCADE
);

-- Shared timetables
CREATE TABLE shared_timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT NOT NULL,
    owner_id INT NOT NULL,
    shared_with_id INT NOT NULL,
    access_token VARCHAR(100) UNIQUE,
    can_edit BOOLEAN DEFAULT FALSE,
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES user_timetables(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_share (timetable_id, shared_with_id)
);

-- Timetable templates
CREATE TABLE timetable_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    education_level VARCHAR(50),
    course_type VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Course Units/Modules table
CREATE TABLE course_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    credits INT DEFAULT 3,
    semester INT,
    is_core BOOLEAN DEFAULT TRUE,
    prerequisites TEXT,
    learning_outcomes TEXT,
    assessment_methods TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_unit (course_id, code)
);

-- User academic information
CREATE TABLE user_academic_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    faculty_id INT,
    department_id INT,
    course_id INT,
    level VARCHAR(50),
    student_id VARCHAR(50) UNIQUE,
    enrollment_year YEAR,
    expected_graduation YEAR,
    gpa DECIMAL(3,2),
    total_credits INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Timetable academic info (additional info for timetables)
CREATE TABLE timetable_academic_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT NOT NULL,
    faculty_id INT,
    department_id INT,
    course_id INT,
    level VARCHAR(50),
    campus VARCHAR(100),
    total_credits INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES user_timetables(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Timetable course units (units for a specific timetable)
CREATE TABLE timetable_course_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT NOT NULL,
    unit_code VARCHAR(20),
    unit_name VARCHAR(100),
    credits INT DEFAULT 3,
    lecturer VARCHAR(100),
    is_examined BOOLEAN DEFAULT TRUE,
    exam_date DATE,
    exam_time TIME,
    exam_venue VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES user_timetables(id) ON DELETE CASCADE
);

-- University buildings/rooms
CREATE TABLE university_buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campus VARCHAR(100),
    building_code VARCHAR(20) UNIQUE,
    building_name VARCHAR(100),
    description TEXT,
    location_coordinates VARCHAR(100), -- For maps
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE university_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    room_type ENUM('Lecture Hall', 'Lab', 'Tutorial Room', 'Seminar Room', 'Computer Lab', 'Specialized Lab'),
    capacity INT,
    facilities TEXT, -- JSON or comma-separated list
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES university_buildings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room (building_id, room_number)
);

-- Semester dates
CREATE TABLE semester_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year VARCHAR(20),
    semester VARCHAR(50),
    semester_start DATE,
    semester_end DATE,
    registration_start DATE,
    registration_end DATE,
    add_drop_deadline DATE,
    midterm_start DATE,
    midterm_end DATE,
    finals_start DATE,
    finals_end DATE,
    holiday_dates TEXT, -- JSON array of holiday dates
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_semester (academic_year, semester)
);

-- Class reminders/notifications
CREATE TABLE timetable_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    timetable_id INT NOT NULL,
    entry_id INT,
    reminder_type ENUM('class_start', 'exam', 'assignment', 'custom'),
    reminder_time TIME,
    reminder_minutes_before INT DEFAULT 15,
    is_active BOOLEAN DEFAULT TRUE,
    last_sent DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (timetable_id) REFERENCES user_timetables(id) ON DELETE CASCADE,
    FOREIGN KEY (entry_id) REFERENCES timetable_entries(id) ON DELETE CASCADE
);

-- Create timetable privacy settings table
CREATE TABLE timetable_privacy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT NOT NULL,
    require_login BOOLEAN DEFAULT TRUE,
    require_password BOOLEAN DEFAULT FALSE,
    password_hash VARCHAR(255),
    allow_viewing BOOLEAN DEFAULT TRUE,
    allow_comments BOOLEAN DEFAULT TRUE,
    allow_duplicate BOOLEAN DEFAULT TRUE,
    allowed_users TEXT, -- JSON array of user IDs
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES user_timetables(id) ON DELETE CASCADE
);

-- Create timetable views log
CREATE TABLE timetable_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES user_timetables(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create timetable comments
CREATE TABLE timetable_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    parent_id INT DEFAULT NULL,
    is_approved BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES user_timetables(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES timetable_comments(id) ON DELETE CASCADE
);

-- Template entries
CREATE TABLE template_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject_title VARCHAR(255) NOT NULL,
    subject_code VARCHAR(50),
    room_number VARCHAR(50),
    lecturer VARCHAR(100),
    color VARCHAR(20),
    notes TEXT,
    FOREIGN KEY (template_id) REFERENCES timetable_templates(id) ON DELETE CASCADE
);

-- Ratings table
CREATE TABLE ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rating (document_id, user_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Feedback/Comments table
CREATE TABLE feedbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    likes INT DEFAULT 0,
    is_helpful BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Likes table for feedback
CREATE TABLE feedback_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (feedback_id, user_id),
    FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Reports table
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    report_type ENUM('copyright', 'inappropriate', 'spam', 'incorrect', 'other'),
    description TEXT,
    status ENUM('pending', 'reviewed', 'resolved', 'dismissed') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Enhanced Chat logs table
CREATE TABLE chat_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(100),
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    intent VARCHAR(50),
    confidence_score DECIMAL(4,3),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Document views tracking
CREATE TABLE document_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Rate limiting table
CREATE TABLE rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempt_count INT DEFAULT 1,
    first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_blocked BOOLEAN DEFAULT FALSE,
    block_until DATETIME,
    UNIQUE KEY unique_ip_action (ip_address, action)
);

-- Admin actions log
CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Saved documents table
CREATE TABLE saved_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_document (user_id, document_id)
);

-- Download logs table
CREATE TABLE IF NOT EXISTS download_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_document (document_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Now add columns to existing tables

-- Enhanced timetable entries with university-specific fields
ALTER TABLE timetable_entries 
ADD COLUMN unit_code VARCHAR(20) AFTER subject_title,
ADD COLUMN is_lab BOOLEAN DEFAULT FALSE AFTER room_number,
ADD COLUMN is_tutorial BOOLEAN DEFAULT FALSE AFTER is_lab,
ADD COLUMN is_lecture BOOLEAN DEFAULT TRUE AFTER is_tutorial,
ADD COLUMN credits INT DEFAULT 3 AFTER is_lecture,
ADD COLUMN week_pattern VARCHAR(50) AFTER notes; -- e.g., "Every week", "Odd weeks", "Even weeks"

-- Enhanced template entries
ALTER TABLE template_entries 
ADD COLUMN unit_code VARCHAR(20) AFTER subject_title,
ADD COLUMN is_lab BOOLEAN DEFAULT FALSE AFTER room_number,
ADD COLUMN is_tutorial BOOLEAN DEFAULT FALSE AFTER is_lab,
ADD COLUMN is_lecture BOOLEAN DEFAULT TRUE AFTER is_tutorial,
ADD COLUMN credits INT DEFAULT 3 AFTER is_lecture,
ADD COLUMN week_pattern VARCHAR(50) AFTER notes;

-- Add privacy settings to timetable table
ALTER TABLE user_timetables 
ADD COLUMN allow_comments BOOLEAN DEFAULT TRUE AFTER is_public,
ADD COLUMN allow_duplicate BOOLEAN DEFAULT TRUE AFTER allow_comments,
ADD COLUMN require_password BOOLEAN DEFAULT FALSE AFTER allow_duplicate,
ADD COLUMN password_hash VARCHAR(255) AFTER require_password,
ADD COLUMN last_viewed DATETIME AFTER password_hash,
ADD COLUMN view_count INT DEFAULT 0 AFTER last_viewed;

-- Insert sample university data
INSERT INTO faculties (name, code, description) VALUES
('Faculty of Science and Technology', 'FST', 'Faculty of Science and Technology offering various STEM programs'),
('Faculty of Business and Economics', 'FBE', 'Faculty focused on business, economics, and management studies'),
('Faculty of Arts and Social Sciences', 'FASS', 'Faculty offering arts, humanities, and social science programs'),
('Faculty of Engineering', 'FOE', 'Faculty offering various engineering disciplines'),
('Faculty of Health Sciences', 'FHS', 'Faculty for medical and health-related programs');

INSERT INTO departments (faculty_id, name, code, description) VALUES
(1, 'Computer Science', 'CS', 'Department of Computer Science and Information Technology'),
(1, 'Mathematics', 'MATH', 'Department of Pure and Applied Mathematics'),
(1, 'Physics', 'PHY', 'Department of Physics and Astronomy'),
(1, 'Chemistry', 'CHEM', 'Department of Chemistry and Chemical Sciences'),
(4, 'Civil Engineering', 'CIV', 'Department of Civil and Environmental Engineering'),
(4, 'Electrical Engineering', 'EE', 'Department of Electrical and Electronic Engineering'),
(4, 'Mechanical Engineering', 'ME', 'Department of Mechanical and Manufacturing Engineering'),
(2, 'Business Administration', 'BA', 'Department of Business Administration and Management'),
(2, 'Accounting', 'ACC', 'Department of Accounting and Finance'),
(2, 'Economics', 'ECO', 'Department of Economics and Statistics');

INSERT INTO courses (department_id, name, code, duration_years) VALUES
(1, 'Bachelor of Science in Computer Science', 'BSCS', 4),
(1, 'Bachelor of Science in Information Technology', 'BSIT', 4),
(1, 'Master of Science in Computer Science', 'MSCS', 2),
(5, 'Bachelor of Science in Civil Engineering', 'BSCE', 5),
(6, 'Bachelor of Science in Electrical Engineering', 'BSEE', 5),
(8, 'Bachelor of Business Administration', 'BBA', 4),
(9, 'Bachelor of Commerce in Accounting', 'BCOM-ACC', 4),
(10, 'Bachelor of Arts in Economics', 'BA-ECO', 4);

INSERT INTO course_units (course_id, code, name, credits, semester, is_core) VALUES
(1, 'CS101', 'Introduction to Programming', 4, 1, TRUE),
(1, 'CS102', 'Data Structures and Algorithms', 4, 2, TRUE),
(1, 'CS201', 'Object-Oriented Programming', 4, 3, TRUE),
(1, 'CS202', 'Database Systems', 4, 4, TRUE),
(1, 'CS301', 'Operating Systems', 4, 5, TRUE),
(1, 'CS302', 'Computer Networks', 4, 6, TRUE),
(1, 'CS401', 'Software Engineering', 4, 7, TRUE),
(1, 'CS402', 'Project', 6, 8, TRUE),
(4, 'CIV101', 'Engineering Mechanics', 4, 1, TRUE),
(4, 'CIV102', 'Structural Analysis', 4, 2, TRUE),
(6, 'BBA101', 'Principles of Management', 3, 1, TRUE),
(6, 'BBA102', 'Business Mathematics', 3, 1, TRUE);
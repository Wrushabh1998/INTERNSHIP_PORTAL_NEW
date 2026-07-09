-- ============================================================
-- Student Internship Tracking & Attendance Management Portal
-- MySQL Database Schema  |  Engine: InnoDB  |  Charset: utf8mb4
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";

CREATE DATABASE IF NOT EXISTS `internship_portal`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `internship_portal`;

-- ────────────────────────────────────────────────────────────
-- 1. ADMIN
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL UNIQUE,
  `password`      VARCHAR(255) NOT NULL,
  `profile_photo` VARCHAR(255) DEFAULT NULL,
  `phone`         VARCHAR(20)  DEFAULT NULL,
  `is_active`     TINYINT(1)   DEFAULT 1,
  `last_login`    DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 2. DEPARTMENTS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT         DEFAULT NULL,
  `is_active`   TINYINT(1)   DEFAULT 1,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 3. INTERNSHIP BATCHES
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `internship_batches` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `batch_name`    VARCHAR(100) NOT NULL,
  `start_date`    DATE         NOT NULL,
  `end_date`      DATE         NOT NULL,
  `academic_year` VARCHAR(20)  DEFAULT NULL,
  `description`   TEXT         DEFAULT NULL,
  `is_active`     TINYINT(1)   DEFAULT 1,
  `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 4. STUDENTS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `students` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`         VARCHAR(20)  NOT NULL UNIQUE COMMENT 'e.g. INT2024001',
  `name`               VARCHAR(150) NOT NULL,
  `email`              VARCHAR(150) NOT NULL UNIQUE,
  `password`           VARCHAR(255) NOT NULL,
  `phone`              VARCHAR(20)  DEFAULT NULL,
  `gender`             ENUM('Male','Female','Other') DEFAULT NULL,
  `dob`                DATE         DEFAULT NULL,
  `college`            VARCHAR(200) DEFAULT NULL,
  `department_id`      INT UNSIGNED DEFAULT NULL,
  `course`             VARCHAR(100) DEFAULT NULL,
  `roll_number`        VARCHAR(50)  DEFAULT NULL,
  `batch_id`           INT UNSIGNED DEFAULT NULL,
  `mentor_name`        VARCHAR(150) DEFAULT NULL,
  `profile_photo`      VARCHAR(255) DEFAULT NULL,
  `resume_path`        VARCHAR(255) DEFAULT NULL,
  `aadhaar_path`       VARCHAR(255) DEFAULT NULL,
  `address`            TEXT         DEFAULT NULL,
  `city`               VARCHAR(100) DEFAULT NULL,
  `state`              VARCHAR(100) DEFAULT NULL,
  `emergency_contact`  VARCHAR(20)  DEFAULT NULL,
  `emergency_name`     VARCHAR(150) DEFAULT NULL,
  `skills`             TEXT         DEFAULT NULL,
  `internship_start`   DATE         DEFAULT NULL,
  `internship_end`     DATE         DEFAULT NULL,
  `is_active`          TINYINT(1)   DEFAULT 1,
  `remember_token`     VARCHAR(100) DEFAULT NULL,
  `reset_token`        VARCHAR(100) DEFAULT NULL,
  `reset_expires`      DATETIME     DEFAULT NULL,
  `last_login`         DATETIME     DEFAULT NULL,
  `login_attempts`     TINYINT      DEFAULT 0,
  `locked_until`       DATETIME     DEFAULT NULL,
  `created_at`         DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`batch_id`)      REFERENCES `internship_batches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 5. ATTENDANCE
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `attendance` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`        INT UNSIGNED NOT NULL,
  `date`              DATE         NOT NULL,
  `punch_in`          TIME         DEFAULT NULL,
  `lunch_start`       TIME         DEFAULT NULL,
  `lunch_end`         TIME         DEFAULT NULL,
  `punch_out`         TIME         DEFAULT NULL,
  `working_hours`     DECIMAL(5,2) DEFAULT 0.00,
  `overtime_hours`    DECIMAL(5,2) DEFAULT 0.00,
  `break_duration`    SMALLINT     DEFAULT 0 COMMENT 'minutes',
  `status`            ENUM('Present','Absent','Half Day','Late','Leave','Holiday') DEFAULT 'Absent',
  `punch_in_ip`       VARCHAR(50)  DEFAULT NULL,
  `punch_out_ip`      VARCHAR(50)  DEFAULT NULL,
  `punch_in_browser`  VARCHAR(255) DEFAULT NULL,
  `punch_in_lat`      VARCHAR(50)  DEFAULT NULL,
  `punch_in_lng`      VARCHAR(50)  DEFAULT NULL,
  `punch_out_lat`     VARCHAR(50)  DEFAULT NULL,
  `punch_out_lng`     VARCHAR(50)  DEFAULT NULL,
  `location`          VARCHAR(255) DEFAULT NULL,
  `remarks`           TEXT         DEFAULT NULL,
  `is_corrected`      TINYINT(1)   DEFAULT 0,
  `correction_reason` TEXT         DEFAULT NULL,
  `approved_by`       INT UNSIGNED DEFAULT NULL,
  `created_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_student_date` (`student_id`, `date`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `admin`(`id`) ON DELETE SET NULL,
  INDEX idx_date (`date`),
  INDEX idx_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 6. ATTENDANCE IMAGES
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `attendance_images` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `attendance_id` INT UNSIGNED NOT NULL,
  `type`          ENUM('punch_in','punch_out') NOT NULL,
  `image_path`    VARCHAR(255) NOT NULL,
  `captured_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`attendance_id`) REFERENCES `attendance`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 7. HOLIDAYS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `holidays` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150) NOT NULL,
  `date`        DATE         NOT NULL UNIQUE,
  `type`        ENUM('National','State','Optional','Office') DEFAULT 'National',
  `description` TEXT         DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 8. LEAVE REQUESTS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`   INT UNSIGNED NOT NULL,
  `leave_type`   ENUM('Casual','Sick','Emergency','Medical','Other') NOT NULL,
  `from_date`    DATE         NOT NULL,
  `to_date`      DATE         NOT NULL,
  `days`         TINYINT      NOT NULL DEFAULT 1,
  `reason`       TEXT         NOT NULL,
  `proof_path`   VARCHAR(255) DEFAULT NULL,
  `status`       ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  `admin_remark` TEXT         DEFAULT NULL,
  `reviewed_by`  INT UNSIGNED DEFAULT NULL,
  `reviewed_at`  DATETIME     DEFAULT NULL,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `admin`(`id`) ON DELETE SET NULL,
  INDEX idx_student (`student_id`),
  INDEX idx_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 9. PROJECTS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projects` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(200) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `tech_stack`  VARCHAR(255) DEFAULT NULL,
  `start_date`  DATE         DEFAULT NULL,
  `end_date`    DATE         DEFAULT NULL,
  `priority`    ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
  `status`      ENUM('Planning','Active','On Hold','Completed','Cancelled') DEFAULT 'Planning',
  `completion`  TINYINT      DEFAULT 0 COMMENT '0-100 percent',
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `admin`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 10. PROJECT ASSIGNMENTS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `project_assignments` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `role`       VARCHAR(100) DEFAULT 'Developer',
  `assigned_at` DATETIME    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_proj_student` (`project_id`,`student_id`),
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 11. MILESTONES
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `milestones` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `due_date`    DATE         DEFAULT NULL,
  `status`      ENUM('Pending','In Progress','Completed') DEFAULT 'Pending',
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 12. TASKS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tasks` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id`   INT UNSIGNED DEFAULT NULL,
  `student_id`   INT UNSIGNED NOT NULL,
  `title`        VARCHAR(255) NOT NULL,
  `description`  TEXT         DEFAULT NULL,
  `priority`     ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
  `deadline`     DATE         DEFAULT NULL,
  `status`       ENUM('Pending','In Progress','Completed','Verified') DEFAULT 'Pending',
  `progress`     TINYINT      DEFAULT 0 COMMENT '0-100',
  `attachment`   VARCHAR(255) DEFAULT NULL,
  `assigned_by`  INT UNSIGNED DEFAULT NULL,
  `verified_by`  INT UNSIGNED DEFAULT NULL,
  `verified_at`  DATETIME     DEFAULT NULL,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`)  REFERENCES `projects`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `admin`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`verified_by`) REFERENCES `admin`(`id`) ON DELETE SET NULL,
  INDEX idx_student_status (`student_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 13. TASK UPDATES
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `task_updates` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_id`      INT UNSIGNED NOT NULL,
  `updated_by`   INT UNSIGNED NOT NULL COMMENT 'student id',
  `progress`     TINYINT      DEFAULT 0,
  `note`         TEXT         DEFAULT NULL,
  `proof_path`   VARCHAR(255) DEFAULT NULL,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 14. DAILY REPORTS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `daily_reports` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`    INT UNSIGNED NOT NULL,
  `project_id`    INT UNSIGNED DEFAULT NULL,
  `date`          DATE         NOT NULL,
  `module`        VARCHAR(200) DEFAULT NULL,
  `work_done`     TEXT         NOT NULL,
  `description`   TEXT         DEFAULT NULL,
  `hours_worked`  DECIMAL(4,2) DEFAULT 0.00,
  `challenges`    TEXT         DEFAULT NULL,
  `solution`      TEXT         DEFAULT NULL,
  `github_link`   VARCHAR(255) DEFAULT NULL,
  `live_url`      VARCHAR(255) DEFAULT NULL,
  `screenshot`    VARCHAR(255) DEFAULT NULL,
  `remarks`       TEXT         DEFAULT NULL,
  `status`        ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  `admin_remark`  TEXT         DEFAULT NULL,
  `reviewed_by`   INT UNSIGNED DEFAULT NULL,
  `reviewed_at`   DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_student_date_report` (`student_id`,`date`),
  FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`project_id`)  REFERENCES `projects`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reviewed_by`) REFERENCES `admin`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 15. ANNOUNCEMENTS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `announcements` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(255) NOT NULL,
  `content`     TEXT         NOT NULL,
  `type`        ENUM('General','Urgent','Meeting','Holiday','Training','Event') DEFAULT 'General',
  `attachment`  VARCHAR(255) DEFAULT NULL,
  `target`      ENUM('All','Batch','Department') DEFAULT 'All',
  `target_id`   INT UNSIGNED DEFAULT NULL COMMENT 'batch_id or dept_id',
  `is_active`   TINYINT(1)   DEFAULT 1,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `expires_at`  DATE         DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `admin`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 16. NOTIFICATIONS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `type`       ENUM('Attendance','Leave','Task','Announcement','Deadline','General') DEFAULT 'General',
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT         NOT NULL,
  `link`       VARCHAR(255) DEFAULT NULL,
  `is_read`    TINYINT(1)   DEFAULT 0,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  INDEX idx_student_read (`student_id`,`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 17. FEEDBACK
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `feedback` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`       INT UNSIGNED NOT NULL,
  `internship_rating` TINYINT     DEFAULT NULL COMMENT '1-5',
  `mentor_rating`    TINYINT      DEFAULT NULL COMMENT '1-5',
  `internship_fb`    TEXT         DEFAULT NULL,
  `mentor_fb`        TEXT         DEFAULT NULL,
  `suggestions`      TEXT         DEFAULT NULL,
  `would_recommend`  TINYINT(1)   DEFAULT NULL,
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 18. DOCUMENTS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`  INT UNSIGNED NOT NULL,
  `doc_type`    ENUM('Resume','Offer Letter','Joining Letter','ID Card','Certificate','Project Report','PPT','Other') NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `file_path`   VARCHAR(255) NOT NULL,
  `file_size`   INT UNSIGNED DEFAULT 0,
  `mime_type`   VARCHAR(100) DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  INDEX idx_student_type (`student_id`,`doc_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 19. CERTIFICATES
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `certificates` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`   INT UNSIGNED NOT NULL,
  `cert_type`    ENUM('Completion','Experience','Appreciation') DEFAULT 'Completion',
  `issued_date`  DATE         DEFAULT NULL,
  `cert_number`  VARCHAR(50)  DEFAULT NULL UNIQUE,
  `issued_by`    INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`issued_by`)  REFERENCES `admin`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 20. ACTIVITY LOGS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_type`  ENUM('admin','student') NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `description` TEXT        DEFAULT NULL,
  `ip_address` VARCHAR(50)  DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (`user_type`,`user_id`),
  INDEX idx_action (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 21. LOGIN LOGS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_type`   ENUM('admin','student') NOT NULL,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `email`       VARCHAR(150) NOT NULL,
  `status`      ENUM('Success','Failed','Locked') NOT NULL,
  `ip_address`  VARCHAR(50)  DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (`email`),
  INDEX idx_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- 22. SETTINGS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key_name`    VARCHAR(100) NOT NULL UNIQUE,
  `value`       TEXT         DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────────────
-- SAMPLE DATA
-- ────────────────────────────────────────────────────────────

-- Admin (password: Admin@123)
INSERT INTO `admin` (`name`,`email`,`password`,`phone`) VALUES
('Super Admin','admin@portal.com','$2y$12$usYQQ78fs8qARqH83HF3rug1BulywO8ZRb/DSwZp3sZNneaVwn9K6','9876543210');

-- Departments
INSERT INTO `departments` (`name`) VALUES
('Computer Science'),('Information Technology'),('Electronics'),('Mechanical'),('Civil');

-- Batches
INSERT INTO `internship_batches` (`batch_name`,`start_date`,`end_date`,`academic_year`) VALUES
('Batch 2024-A','2024-06-01','2024-08-31','2024-25'),
('Batch 2025-A','2025-06-01','2025-08-31','2025-26'),
('Batch 2025-B','2025-07-01','2025-09-30','2025-26');

-- Students (password: Student@123)
INSERT INTO `students`
  (`student_id`,`name`,`email`,`password`,`phone`,`gender`,`college`,`department_id`,`course`,`roll_number`,`batch_id`,`mentor_name`,`internship_start`,`internship_end`,`skills`,`is_active`) VALUES
('INT2025001','Aarav Sharma','aarav@student.com','$2y$12$/3w9X8LoEvjk8ucPrSydVONDT4tYR2ZAFmIr6ukwGktzlPoAV5FMe','9876501001','Male','Government Engineering College',1,'B.Tech CSE','CS2021001',3,'Dr. Priya Mehta','2025-07-01','2025-09-30','PHP, MySQL, JavaScript, HTML, CSS',1),
('INT2025002','Priya Patel','priya@student.com','$2y$12$/3w9X8LoEvjk8ucPrSydVONDT4tYR2ZAFmIr6ukwGktzlPoAV5FMe','9876501002','Female','Amravati University',2,'B.Tech IT','IT2021002',3,'Mr. Rahul Gupta','2025-07-01','2025-09-30','React, Node.js, Python, Django',1),
('INT2025003','Ravi Kumar','ravi@student.com','$2y$12$/3w9X8LoEvjk8ucPrSydVONDT4tYR2ZAFmIr6ukwGktzlPoAV5FMe','9876501003','Male','NIT Nagpur',1,'B.Tech CSE','CS2021003',3,'Ms. Anjali Singh','2025-07-01','2025-09-30','Java, Spring Boot, MySQL',1),
('INT2025004','Sneha Joshi','sneha@student.com','$2y$12$/3w9X8LoEvjk8ucPrSydVONDT4tYR2ZAFmIr6ukwGktzlPoAV5FMe','9876501004','Female','VIT Pune',1,'B.Tech CSE','CS2021004',3,'Dr. Priya Mehta','2025-07-01','2025-09-30','Flutter, Dart, Firebase',1),
('INT2025005','Arjun Verma','arjun@student.com','$2y$12$/3w9X8LoEvjk8ucPrSydVONDT4tYR2ZAFmIr6ukwGktzlPoAV5FMe','9876501005','Male','COEP Pune',2,'B.Tech IT','IT2021005',3,'Mr. Rahul Gupta','2025-07-01','2025-09-30','Angular, TypeScript, MongoDB',1);

-- Projects
INSERT INTO `projects` (`title`,`description`,`tech_stack`,`start_date`,`end_date`,`priority`,`status`,`completion`,`created_by`) VALUES
('Internship Management Portal','Complete portal for tracking internship attendance and progress','PHP, MySQL, HTML, CSS, JS','2025-07-01','2025-09-30','High','Active',35,1),
('E-Commerce Platform','Full-stack e-commerce website with payment integration','React, Node.js, MongoDB, Stripe','2025-07-01','2025-08-31','High','Active',50,1),
('Mobile Attendance App','Android app for attendance tracking','Flutter, Firebase','2025-07-15','2025-09-15','Medium','Active',20,1);

-- Project Assignments
INSERT INTO `project_assignments` (`project_id`,`student_id`,`role`) VALUES
(1,1,'Full Stack Developer'),(1,3,'Backend Developer'),(2,2,'Frontend Developer'),(2,5,'Backend Developer'),(3,4,'Mobile Developer');

-- Tasks
INSERT INTO `tasks` (`project_id`,`student_id`,`title`,`description`,`priority`,`deadline`,`status`,`progress`,`assigned_by`) VALUES
(1,1,'Setup Database Schema','Create all MySQL tables with proper relations','High','2025-07-10','Completed',100,1),
(1,1,'Build Admin Dashboard','Create responsive admin dashboard with charts','High','2025-07-20','In Progress',60,1),
(1,3,'REST API Development','Build PHP AJAX endpoints for all modules','Medium','2025-07-25','In Progress',40,1),
(2,2,'React Component Library','Build reusable UI components','Medium','2025-07-18','Completed',100,1),
(2,5,'Node.js API Setup','Setup Express.js server with authentication','High','2025-07-22','In Progress',70,1),
(3,4,'Flutter UI Design','Design all app screens','Medium','2025-07-30','Pending',0,1);

-- Milestones
INSERT INTO `milestones` (`project_id`,`title`,`due_date`,`status`) VALUES
(1,'Database Design Complete','2025-07-10','Completed'),
(1,'Authentication Module','2025-07-15','Completed'),
(1,'Admin Panel Beta','2025-07-31','In Progress'),
(1,'Student Panel Beta','2025-08-15','Pending'),
(1,'Testing & QA','2025-09-01','Pending'),
(1,'Production Deploy','2025-09-30','Pending');

-- Attendance (last 5 days for student 1)
INSERT INTO `attendance` (`student_id`,`date`,`punch_in`,`lunch_start`,`lunch_end`,`punch_out`,`working_hours`,`status`) VALUES
(1,DATE_SUB(CURDATE(),INTERVAL 4 DAY),'09:05:00','13:00:00','14:00:00','18:10:00',8.08,'Present'),
(1,DATE_SUB(CURDATE(),INTERVAL 3 DAY),'09:35:00','13:05:00','14:00:00','18:00:00',7.33,'Late'),
(1,DATE_SUB(CURDATE(),INTERVAL 2 DAY),'09:00:00','13:00:00','13:45:00','17:50:00',8.08,'Present'),
(1,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'09:10:00','12:55:00','13:50:00','18:05:00',8.17,'Present'),
(2,DATE_SUB(CURDATE(),INTERVAL 4 DAY),'09:00:00','13:00:00','14:00:00','18:00:00',8.00,'Present'),
(2,DATE_SUB(CURDATE(),INTERVAL 3 DAY),'09:00:00','13:00:00','14:00:00','18:00:00',8.00,'Present'),
(3,DATE_SUB(CURDATE(),INTERVAL 4 DAY),'09:00:00','13:00:00','14:00:00','18:00:00',8.00,'Present'),
(4,DATE_SUB(CURDATE(),INTERVAL 4 DAY),'09:15:00',NULL,NULL,'13:30:00',4.25,'Half Day');

-- Announcements
INSERT INTO `announcements` (`title`,`content`,`type`,`created_by`) VALUES
('Welcome to Internship 2025!','Dear interns, welcome to the internship program. Please complete your profile and check your task assignments.','General',1),
('Weekly Meeting - Friday 4PM','All interns are required to attend the weekly progress meeting via Google Meet. Link will be shared on Friday morning.','Meeting',1),
('National Holiday - Independence Day','15th August is a National Holiday. Office will remain closed.','Holiday',1),
('Git Best Practices Training','A training session on Git and GitHub best practices will be held on 25th July 2025 at 11 AM.','Training',1);

-- Notifications for student 1
INSERT INTO `notifications` (`student_id`,`type`,`title`,`message`,`link`) VALUES
(1,'Task','New Task Assigned','You have been assigned a new task: Build Admin Dashboard','student/tasks.php'),
(1,'Announcement','Welcome Announcement','You have a new announcement from Admin','student/announcements.php'),
(1,'Leave','Leave Request Update','Your leave request has been processed','student/leave.php');

-- Leave Requests
INSERT INTO `leave_requests` (`student_id`,`leave_type`,`from_date`,`to_date`,`days`,`reason`,`status`) VALUES
(1,'Casual',DATE_ADD(CURDATE(),INTERVAL 5 DAY),DATE_ADD(CURDATE(),INTERVAL 5 DAY),1,'Personal work','Pending'),
(2,'Sick',DATE_SUB(CURDATE(),INTERVAL 3 DAY),DATE_SUB(CURDATE(),INTERVAL 2 DAY),2,'Fever and cold','Approved');

-- Settings
INSERT INTO `settings` (`key_name`,`value`,`description`) VALUES
('office_start','09:00','Office start time'),
('office_end','18:00','Office end time'),
('late_after','09:30','Marked late after this time'),
('lunch_duration','60','Lunch break duration in minutes'),
('half_day_hours','4','Minimum hours for half day'),
('full_day_hours','8','Standard full day hours'),
('site_name','InternTrack Pro','Portal name'),
('timezone','Asia/Kolkata','Default timezone'),
('max_leave_casual','12','Maximum casual leaves per year'),
('max_leave_sick','6','Maximum sick leaves per year');

-- Holidays
INSERT INTO `holidays` (`name`,`date`,`type`) VALUES
('Republic Day','2025-01-26','National'),
('Holi','2025-03-14','National'),
('Good Friday','2025-04-18','National'),
('Independence Day','2025-08-15','National'),
('Gandhi Jayanti','2025-10-02','National'),
('Diwali','2025-10-21','National'),
('Christmas','2025-12-25','National');

SET FOREIGN_KEY_CHECKS = 1;

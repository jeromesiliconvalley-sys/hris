-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 19, 2025 at 07:16 AM
-- Server version: 5.7.23-23
-- PHP Version: 8.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `qusevomy_hris`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('CREATE','UPDATE','DELETE','APPROVE','REJECT','LOGIN','LOGOUT','VIEW','EXPORT','OTHER') NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `location_latitude` decimal(10,8) DEFAULT NULL,
  `location_longitude` decimal(11,8) DEFAULT NULL,
  `device_info` json DEFAULT NULL COMMENT 'Device name, browser, OS',
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `posted_by` int(11) DEFAULT NULL,
  `posted_date` date NOT NULL,
  `target_type` enum('All','Organizational Unit','Position','Specific Employees') NOT NULL,
  `target_organizational_units` json DEFAULT NULL COMMENT 'Array of organizational unit IDs (branches/departments)',
  `target_positions` json DEFAULT NULL COMMENT 'Array of position IDs if target is Position',
  `target_employees` json DEFAULT NULL COMMENT 'Array of employee IDs if target is Specific Employees',
  `requires_acknowledgment` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_acknowledgments`
--

CREATE TABLE `announcement_acknowledgments` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `viewed_at` timestamp NULL DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(11) NOT NULL,
  `manpower_request_id` int(11) DEFAULT NULL COMMENT 'Link to request if applicable',
  `applicant_number` varchar(20) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `current_address` text,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `source` enum('Facebook','LinkedIn','JobStreet','Indeed','Company Website','Walk-In to Branch','Walk-In to Head Office','Referral','Other') NOT NULL,
  `application_date` date NOT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `expected_salary` decimal(12,2) DEFAULT NULL,
  `years_of_experience` int(11) DEFAULT NULL,
  `highest_education` varchar(100) DEFAULT NULL,
  `position_applied` varchar(100) DEFAULT NULL,
  `status` enum('Applied','For Online Exam','Exam Passed','Exam Failed','For Interview','Interviewed','Job Offer','Hired','Rejected','Withdrawn') DEFAULT 'Applied',
  `rating` decimal(3,2) DEFAULT NULL,
  `notes` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `applicant_exam_results`
--

CREATE TABLE `applicant_exam_results` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `exam_type` varchar(100) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `passing_score` decimal(5,2) DEFAULT NULL,
  `passed` tinyint(1) DEFAULT NULL,
  `exam_link` varchar(255) DEFAULT NULL,
  `remarks` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `applicant_interviews`
--

CREATE TABLE `applicant_interviews` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `interview_type` enum('Initial Screening','Phone Interview','Technical Interview','Panel Interview','HR Interview','Final Interview') NOT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time NOT NULL,
  `interview_mode` enum('In-Person','Video Call','Phone Call') DEFAULT 'In-Person',
  `interviewer_ids` json DEFAULT NULL COMMENT 'Array of employee IDs',
  `status` enum('Scheduled','Completed','Cancelled','No Show') DEFAULT 'Scheduled',
  `rating` decimal(3,2) DEFAULT NULL,
  `feedback` text,
  `recommendation` enum('Strongly Recommend','Recommend','Maybe','Not Recommend','Reject') DEFAULT 'Maybe',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `asset_assignments`
--

CREATE TABLE `asset_assignments` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `condition_on_assignment` enum('Excellent','Good','Fair','Poor') DEFAULT 'Good',
  `condition_on_return` enum('Excellent','Good','Fair','Poor') DEFAULT NULL,
  `status` enum('Active','Returned','Overdue') DEFAULT 'Active',
  `assignment_notes` text,
  `return_notes` text,
  `assigned_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `asset_maintenance`
--

CREATE TABLE `asset_maintenance` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `maintenance_type` enum('Preventive','Repair','Replacement','Cleaning','Inspection') NOT NULL,
  `maintenance_date` date NOT NULL,
  `completed_date` date DEFAULT NULL,
  `description` text NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `service_provider` varchar(200) DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Scheduled',
  `next_maintenance_date` date DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` bigint(20) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_in_latitude` decimal(10,8) DEFAULT NULL,
  `time_in_longitude` decimal(11,8) DEFAULT NULL,
  `time_in_selfie_path` varchar(255) DEFAULT NULL,
  `time_in_face_verified` tinyint(1) DEFAULT NULL,
  `time_in_device_info` json DEFAULT NULL,
  `time_in_ip_address` varchar(45) DEFAULT NULL,
  `lunch_out` time DEFAULT NULL,
  `lunch_out_latitude` decimal(10,8) DEFAULT NULL,
  `lunch_out_longitude` decimal(11,8) DEFAULT NULL,
  `lunch_out_selfie_path` varchar(255) DEFAULT NULL,
  `lunch_out_face_verified` tinyint(1) DEFAULT NULL,
  `lunch_out_device_info` json DEFAULT NULL,
  `lunch_out_ip_address` varchar(45) DEFAULT NULL,
  `lunch_in` time DEFAULT NULL,
  `lunch_in_latitude` decimal(10,8) DEFAULT NULL,
  `lunch_in_longitude` decimal(11,8) DEFAULT NULL,
  `lunch_in_selfie_path` varchar(255) DEFAULT NULL,
  `lunch_in_face_verified` tinyint(1) DEFAULT NULL,
  `lunch_in_device_info` json DEFAULT NULL,
  `lunch_in_ip_address` varchar(45) DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `time_out_latitude` decimal(10,8) DEFAULT NULL,
  `time_out_longitude` decimal(11,8) DEFAULT NULL,
  `time_out_selfie_path` varchar(255) DEFAULT NULL,
  `time_out_face_verified` tinyint(1) DEFAULT NULL,
  `time_out_device_info` json DEFAULT NULL,
  `time_out_ip_address` varchar(45) DEFAULT NULL,
  `remarks` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_field_work`
--

CREATE TABLE `attendance_field_work` (
  `id` bigint(20) NOT NULL,
  `attendance_id` bigint(20) NOT NULL COMMENT 'Link to main attendance record',
  `employee_id` int(11) NOT NULL,
  `field_date` date NOT NULL,
  `field_out` time NOT NULL,
  `field_out_latitude` decimal(10,8) DEFAULT NULL,
  `field_out_longitude` decimal(11,8) DEFAULT NULL,
  `field_out_selfie_path` varchar(255) DEFAULT NULL,
  `field_out_face_verified` tinyint(1) DEFAULT NULL,
  `field_out_device_info` json DEFAULT NULL,
  `field_out_ip_address` varchar(45) DEFAULT NULL,
  `field_in` time DEFAULT NULL,
  `field_in_latitude` decimal(10,8) DEFAULT NULL,
  `field_in_longitude` decimal(11,8) DEFAULT NULL,
  `field_in_selfie_path` varchar(255) DEFAULT NULL,
  `field_in_face_verified` tinyint(1) DEFAULT NULL,
  `field_in_device_info` json DEFAULT NULL,
  `field_in_ip_address` varchar(45) DEFAULT NULL,
  `client_name` varchar(200) DEFAULT NULL COMMENT 'Client or location visited',
  `purpose` text NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Approved',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Field work entries - employee can have multiple per day';

-- --------------------------------------------------------

--
-- Table structure for table `attendance_obt`
--

CREATE TABLE `attendance_obt` (
  `id` bigint(20) NOT NULL,
  `attendance_id` bigint(20) NOT NULL COMMENT 'Link to main attendance record',
  `employee_id` int(11) NOT NULL,
  `obt_date` date NOT NULL,
  `obt_out` time NOT NULL,
  `obt_out_latitude` decimal(10,8) DEFAULT NULL,
  `obt_out_longitude` decimal(11,8) DEFAULT NULL,
  `obt_out_selfie_path` varchar(255) DEFAULT NULL,
  `obt_out_face_verified` tinyint(1) DEFAULT NULL,
  `obt_out_device_info` json DEFAULT NULL,
  `obt_out_ip_address` varchar(45) DEFAULT NULL,
  `obt_in` time DEFAULT NULL,
  `obt_in_latitude` decimal(10,8) DEFAULT NULL,
  `obt_in_longitude` decimal(11,8) DEFAULT NULL,
  `obt_in_selfie_path` varchar(255) DEFAULT NULL,
  `obt_in_face_verified` tinyint(1) DEFAULT NULL,
  `obt_in_device_info` json DEFAULT NULL,
  `obt_in_ip_address` varchar(45) DEFAULT NULL,
  `obt_reason` text NOT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Approved',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_override_requests`
--

CREATE TABLE `attendance_override_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_id` bigint(20) DEFAULT NULL COMMENT 'Link to attendance if correcting existing record',
  `attendance_date` date NOT NULL,
  `override_type` enum('Missing Time In','Missing Time Out','Missing Lunch Out','Missing Lunch In','Incorrect Time In','Incorrect Time Out','Incorrect Lunch','Missing Entire Day','Wrong OBT','Wrong Field Work','Other') NOT NULL,
  `original_time_in` time DEFAULT NULL,
  `original_lunch_out` time DEFAULT NULL,
  `original_lunch_in` time DEFAULT NULL,
  `original_time_out` time DEFAULT NULL,
  `requested_time_in` time DEFAULT NULL,
  `requested_lunch_out` time DEFAULT NULL,
  `requested_lunch_in` time DEFAULT NULL,
  `requested_time_out` time DEFAULT NULL,
  `reason` text NOT NULL COMMENT 'e.g., Internet issue, Device error, Forgot to clock, etc.',
  `supporting_documents` text COMMENT 'File paths for proofs like screenshots, photos, etc.',
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `approver_remarks` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Requests to override/correct attendance due to technical issues, human error, etc.';

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `building_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Building name if applicable',
  `unit_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `house_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barangay` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tin` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sss_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `philhealth_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagibig_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_assets`
--

CREATE TABLE `company_assets` (
  `id` int(11) NOT NULL,
  `asset_code` varchar(50) NOT NULL,
  `asset_name` varchar(200) NOT NULL,
  `asset_category` enum('Computer','Laptop','Mobile Phone','Tablet','Printer','Furniture','Vehicle','Equipment','Tools','Other') NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(12,2) DEFAULT NULL,
  `depreciation_rate` decimal(5,2) DEFAULT NULL COMMENT 'Annual depreciation %',
  `current_value` decimal(12,2) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `supplier` varchar(200) DEFAULT NULL,
  `organizational_unit_id` int(11) DEFAULT NULL COMMENT 'Branch/Department where asset is located',
  `status` enum('Available','Assigned','Under Maintenance','Retired','Lost','Damaged') DEFAULT 'Available',
  `condition` enum('Excellent','Good','Fair','Poor') DEFAULT 'Good',
  `notes` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `company_deductions`
--

CREATE TABLE `company_deductions` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `deduction_type` enum('Employee Sale','Cash Advance','Inventory Loss','Store Penalty','Uniform','Overpaid','Damage','Other') NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `remaining_balance` decimal(12,2) NOT NULL,
  `monthly_deduction` decimal(12,2) DEFAULT NULL COMMENT 'Fixed amount to deduct monthly',
  `deduction_date` date NOT NULL,
  `status` enum('Active','Fully Paid','Cancelled','Paused') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `company_deduction_payments`
--

CREATE TABLE `company_deduction_payments` (
  `id` int(11) NOT NULL,
  `deduction_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `new_balance` decimal(12,2) NOT NULL,
  `payroll_period_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `data_access_logs`
--

CREATE TABLE `data_access_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `accessed_table` varchar(100) NOT NULL,
  `accessed_fields` json DEFAULT NULL,
  `action` enum('VIEW','EXPORT','PRINT','DOWNLOAD') NOT NULL,
  `purpose` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `location_latitude` decimal(10,8) DEFAULT NULL,
  `location_longitude` decimal(11,8) DEFAULT NULL,
  `device_info` json DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `data_privacy_consents`
--

CREATE TABLE `data_privacy_consents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `granted` tinyint(1) DEFAULT '0',
  `granted_at` timestamp NULL DEFAULT NULL,
  `consent_text` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` json DEFAULT NULL COMMENT 'Device name, browser, OS',
  `location_latitude` decimal(10,8) DEFAULT NULL,
  `location_longitude` decimal(11,8) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_number` varchar(20) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `civil_status` enum('Single','Married','Separated','Widowed','Annulled') NOT NULL,
  `birthdate` date NOT NULL,
  `birthplace` varchar(150) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Filipino',
  `religion` enum('Roman Catholic','Islam','Iglesia ni Cristo','Protestant','Buddhist','Born Again Christian','Jehovah''s Witness','Seventh-day Adventist','Other','None') DEFAULT 'Roman Catholic',
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `sss_number` varchar(20) NOT NULL,
  `philhealth_number` varchar(20) NOT NULL,
  `pagibig_number` varchar(20) NOT NULL,
  `tin_number` varchar(20) NOT NULL,
  `current_building_name` varchar(200) DEFAULT NULL,
  `current_unit_number` varchar(20) DEFAULT NULL,
  `current_house_number` varchar(20) DEFAULT NULL,
  `current_street_name` varchar(150) DEFAULT NULL,
  `current_barangay` varchar(100) DEFAULT NULL,
  `current_city` varchar(100) DEFAULT NULL,
  `current_province` varchar(100) DEFAULT NULL,
  `current_region` varchar(100) DEFAULT NULL,
  `current_postal_code` varchar(10) DEFAULT NULL,
  `current_latitude` decimal(10,8) DEFAULT NULL,
  `current_longitude` decimal(11,8) DEFAULT NULL,
  `permanent_building_name` varchar(200) DEFAULT NULL,
  `permanent_unit_number` varchar(20) DEFAULT NULL,
  `permanent_house_number` varchar(20) DEFAULT NULL,
  `permanent_street_name` varchar(150) DEFAULT NULL,
  `permanent_barangay` varchar(100) DEFAULT NULL,
  `permanent_city` varchar(100) DEFAULT NULL,
  `permanent_province` varchar(100) DEFAULT NULL,
  `permanent_region` varchar(100) DEFAULT NULL,
  `permanent_postal_code` varchar(10) DEFAULT NULL,
  `permanent_latitude` decimal(10,8) DEFAULT NULL,
  `permanent_longitude` decimal(11,8) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `organizational_unit_id` int(11) NOT NULL COMMENT 'Branch or Department',
  `position_id` int(11) NOT NULL,
  `immediate_head_id` int(11) DEFAULT NULL,
  `date_hired` date NOT NULL,
  `employment_end_date` date DEFAULT NULL,
  `regularization_date` date DEFAULT NULL,
  `employment_status_id` int(11) NOT NULL,
  `employment_type_id` int(11) NOT NULL,
  `basic_salary` decimal(12,2) DEFAULT NULL,
  `is_minimum_wage` tinyint(1) DEFAULT '0' COMMENT 'Is employee a minimum wage earner?',
  `shift_schedule_id` int(11) DEFAULT NULL COMMENT 'Default shift schedule',
  `schedule_type` enum('Fixed','Custom') DEFAULT 'Fixed',
  `payroll_group` enum('Semi-Monthly','Monthly') DEFAULT 'Semi-Monthly',
  `pay_type` enum('BDO Cash Card','BDO Debit Card','Cash','Other Bank') DEFAULT 'Cash',
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_allowances`
--

CREATE TABLE `employee_allowances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `allowance_type` enum('Rice Allowance','Clothing Allowance','Transportation Allowance','Meal Allowance','Communication Allowance','Housing Allowance','Other') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `frequency` enum('Daily','Weekly','Semi-Monthly','Monthly','Quarterly','Annually','One-Time') DEFAULT 'Monthly',
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL for ongoing allowances',
  `is_taxable` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_attachments`
--

CREATE TABLE `employee_attachments` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `file_type` enum('1x1 Photo','2x2 Photo','NBI Clearance','Medical Certificate','Police Clearance','Barangay Clearance','SSS ID','PhilHealth ID','Pag-IBIG ID','TIN ID','Birth Certificate','Marriage Contract','Diploma','TOR','Resume','Other') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `description` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_benefits`
--

CREATE TABLE `employee_benefits` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `benefit_type` enum('HMO','Life Insurance','Accident Insurance','Employee Discount','Gym Membership','Education Assistance','Other') NOT NULL,
  `benefit_name` varchar(100) NOT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `coverage_amount` decimal(12,2) DEFAULT NULL,
  `premium_amount` decimal(10,2) DEFAULT NULL,
  `employer_contribution` decimal(10,2) DEFAULT NULL,
  `employee_contribution` decimal(10,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `policy_number` varchar(100) DEFAULT NULL,
  `dependents_covered` text COMMENT 'Names of dependents covered',
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_dependents`
--

CREATE TABLE `employee_dependents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `relationship` enum('Spouse','Child','Parent','Sibling','Other') NOT NULL,
  `birthdate` date NOT NULL,
  `is_beneficiary` tinyint(1) DEFAULT '0',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_type` enum('Job Offer','Employment Contract','Probationary Contract','Regular Contract','Contract Renewal','Promotion Letter','Transfer Letter','Salary Adjustment Letter','NDA','Non-Compete Agreement','Acknowledgment Receipt','Memo','Warning Letter','Other') NOT NULL,
  `document_title` varchar(255) NOT NULL,
  `document_number` varchar(100) DEFAULT NULL COMMENT 'e.g., CONTRACT-2025-001',
  `document_date` date NOT NULL,
  `effective_date` date DEFAULT NULL COMMENT 'When document takes effect',
  `expiry_date` date DEFAULT NULL COMMENT 'For contracts with end dates',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `description` text,
  `requires_signature` tinyint(1) DEFAULT '1',
  `is_signed` tinyint(1) DEFAULT '0',
  `signed_date` date DEFAULT NULL,
  `signed_file_path` varchar(500) DEFAULT NULL COMMENT 'Path to signed copy',
  `uploaded_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Repository for employee contracts, job offers, official memos, and other important documents';

-- --------------------------------------------------------

--
-- Table structure for table `employee_education`
--

CREATE TABLE `employee_education` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `level` enum('Elementary','High School','Senior High School','Vocational','College','Post Graduate') NOT NULL,
  `school_name` varchar(200) NOT NULL,
  `course` varchar(150) DEFAULT NULL,
  `year_started` year(4) DEFAULT NULL,
  `year_ended` year(4) DEFAULT NULL,
  `is_graduated` tinyint(1) DEFAULT '0',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_exit_interview`
--

CREATE TABLE `employee_exit_interview` (
  `id` int(11) NOT NULL,
  `offboarding_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `interview_date` date NOT NULL,
  `interviewed_by` int(11) DEFAULT NULL,
  `reason_for_leaving` text,
  `new_employer` varchar(200) DEFAULT NULL,
  `job_satisfaction_rating` int(11) DEFAULT NULL COMMENT '1-5 scale',
  `work_environment_rating` int(11) DEFAULT NULL,
  `management_rating` int(11) DEFAULT NULL,
  `compensation_rating` int(11) DEFAULT NULL,
  `growth_opportunities_rating` int(11) DEFAULT NULL,
  `what_did_you_like` text,
  `what_needs_improvement` text,
  `would_recommend_company` tinyint(1) DEFAULT NULL,
  `would_consider_returning` tinyint(1) DEFAULT NULL,
  `additional_feedback` text,
  `interviewer_notes` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_final_pay`
--

CREATE TABLE `employee_final_pay` (
  `id` int(11) NOT NULL,
  `offboarding_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `unpaid_salary` decimal(12,2) DEFAULT '0.00',
  `prorated_13th_month` decimal(12,2) DEFAULT '0.00',
  `unused_leave_credits` decimal(12,2) DEFAULT '0.00',
  `tax_refund` decimal(12,2) DEFAULT '0.00',
  `other_earnings` decimal(12,2) DEFAULT '0.00',
  `total_earnings` decimal(12,2) DEFAULT '0.00',
  `outstanding_loans` decimal(12,2) DEFAULT '0.00',
  `company_deductions` decimal(12,2) DEFAULT '0.00',
  `withheld_taxes` decimal(12,2) DEFAULT '0.00',
  `other_deductions` decimal(12,2) DEFAULT '0.00',
  `total_deductions` decimal(12,2) DEFAULT '0.00',
  `net_final_pay` decimal(12,2) DEFAULT '0.00',
  `payment_date` date DEFAULT NULL,
  `payment_method` enum('Bank Transfer','Cash','Check') DEFAULT 'Bank Transfer',
  `status` enum('Computed','Approved','Released') DEFAULT 'Computed',
  `computed_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_leaves`
--

CREATE TABLE `employee_leaves` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('SIL - Vacation','SIL - Sick','SIL - Emergency','Maternity Leave','Paternity Leave','Solo Parent Leave','Leave Without Pay') NOT NULL,
  `reason` text NOT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL,
  `is_half_day` tinyint(1) DEFAULT '0' COMMENT 'Is this a half-day leave?',
  `half_day_type` enum('Morning','Afternoon') DEFAULT NULL COMMENT 'Which half of the day',
  `total_days` decimal(5,2) NOT NULL COMMENT 'e.g., 0.5 for half day, 1.0 for full day',
  `leave_status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `requirements_submitted` tinyint(1) DEFAULT '0' COMMENT 'For Paternity/Solo Parent',
  `supporting_documents` text COMMENT 'File paths for requirements',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_leave_credits`
--

CREATE TABLE `employee_leave_credits` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `credit_type` enum('SIL','Maternity Leave','Paternity Leave','Solo Parent Leave') NOT NULL,
  `year` year(4) NOT NULL,
  `total_earned` decimal(5,2) DEFAULT '0.00',
  `total_used` decimal(5,2) DEFAULT '0.00',
  `total_balance` decimal(5,2) DEFAULT '0.00',
  `notes` text COMMENT 'E.g., "Maternity not paid by company, SSS pays"',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_memos`
--

CREATE TABLE `employee_memos` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `memo_type` enum('Notice to Explain','Memo','Disciplinary Action','Suspension','Salary Increase','Promotion','Demotion','Commendation','Warning','Show Cause Memo','Termination Notice','Other') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `issued_date` date NOT NULL,
  `incident_date` date DEFAULT NULL COMMENT 'When the incident occurred (for disciplinary)',
  `response_deadline` date DEFAULT NULL COMMENT 'Deadline for employee to respond',
  `attachment_path` varchar(255) DEFAULT NULL,
  `requires_acknowledgment` tinyint(1) DEFAULT '1',
  `viewed_at` timestamp NULL DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `employee_response` text,
  `employee_response_date` date DEFAULT NULL,
  `employee_response_attachments` text COMMENT 'File paths for employee supporting docs',
  `status` enum('Issued','Acknowledged','Responded','Under Review','Closed') DEFAULT 'Issued',
  `final_decision` text COMMENT 'HR/Management decision after review',
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_movements`
--

CREATE TABLE `employee_movements` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `movement_type` enum('Transfer','Promotion','Demotion','Department Change','Branch Change','Position Change','Secondment','Reassignment') NOT NULL,
  `effective_date` date NOT NULL,
  `previous_organizational_unit_id` int(11) DEFAULT NULL COMMENT 'Previous branch/department',
  `previous_position_id` int(11) DEFAULT NULL,
  `previous_immediate_head_id` int(11) DEFAULT NULL,
  `new_organizational_unit_id` int(11) DEFAULT NULL COMMENT 'New branch/department',
  `new_position_id` int(11) DEFAULT NULL,
  `new_immediate_head_id` int(11) DEFAULT NULL,
  `reason` text,
  `approved_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_offboarding`
--

CREATE TABLE `employee_offboarding` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `separation_type` enum('Resignation','Retirement','Termination','End of Contract','Death','AWOL') NOT NULL,
  `resignation_date` date DEFAULT NULL COMMENT 'Date resignation was filed',
  `last_working_date` date NOT NULL,
  `effectivity_date` date NOT NULL,
  `reason` text,
  `exit_interview_scheduled` tinyint(1) DEFAULT '0',
  `exit_interview_completed` tinyint(1) DEFAULT '0',
  `clearance_completed` tinyint(1) DEFAULT '0',
  `final_pay_released` tinyint(1) DEFAULT '0',
  `certificate_of_employment_issued` tinyint(1) DEFAULT '0',
  `is_rehireable` tinyint(1) DEFAULT '1',
  `offboarding_notes` text,
  `approved_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_overtimes`
--

CREATE TABLE `employee_overtimes` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `ot_date` date NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `crosses_midnight` tinyint(1) DEFAULT '0' COMMENT 'OT extends to next day',
  `ot_hours` decimal(5,2) DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_requests`
--

CREATE TABLE `employee_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `request_type` enum('Certificate of Employment','Company ID','Uniform','Salary Concern','Payroll Concern','Leave Concern','Schedule Change','Transfer Request','Equipment Request','Other') NOT NULL,
  `request_category` enum('HR','Payroll','Admin','IT','Operations') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `quantity` int(11) DEFAULT NULL COMMENT 'For uniform, ID requests',
  `size` varchar(20) DEFAULT NULL COMMENT 'For uniform requests',
  `supporting_documents` text COMMENT 'File paths',
  `status` enum('Pending','Under Review','Approved','Processing','Completed','Rejected','Cancelled') DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'HR/Admin staff assigned',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_notes` text,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completion_notes` text,
  `has_deduction` tinyint(1) DEFAULT '0',
  `deduction_amount` decimal(10,2) DEFAULT NULL,
  `deduction_status` enum('Not Applicable','Pending','Deducted') DEFAULT 'Not Applicable',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_request_comments`
--

CREATE TABLE `employee_request_comments` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `commented_by` int(11) NOT NULL,
  `comment` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT '0' COMMENT 'Internal note, not visible to employee',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_salary_history`
--

CREATE TABLE `employee_salary_history` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `previous_salary` decimal(12,2) DEFAULT NULL,
  `new_salary` decimal(12,2) NOT NULL,
  `adjustment_type` enum('Initial Salary','Merit Increase','Promotion','Adjustment','Minimum Wage Compliance','Demotion') NOT NULL,
  `adjustment_percentage` decimal(5,2) DEFAULT NULL,
  `adjustment_amount` decimal(12,2) DEFAULT NULL,
  `effective_date` date NOT NULL,
  `reason` text,
  `approved_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_schedule`
--

CREATE TABLE `employee_schedule` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `shift_schedule_id` int(11) DEFAULT NULL COMMENT 'Reference to shift_schedules for fixed schedule',
  `effective_date` date NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `time_in` time DEFAULT NULL,
  `lunch_out` time DEFAULT NULL,
  `lunch_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `is_rest_day` tinyint(1) DEFAULT '0',
  `saturday_type` enum('odd','even','all','none') DEFAULT 'none' COMMENT 'For Saturday schedule: odd=1st,3rd,5th; even=2nd,4th',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employee_work_history`
--

CREATE TABLE `employee_work_history` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `position` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `responsibilities` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employment_statuses`
--

CREATE TABLE `employment_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `employment_types`
--

CREATE TABLE `employment_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_service_requests`
--

CREATE TABLE `equipment_service_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `organizational_unit_id` int(11) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL COMMENT 'Link to organizational_unit_equipment or company_assets',
  `equipment_type` varchar(100) DEFAULT NULL,
  `request_type` enum('Repair','Replacement','Maintenance','Installation') NOT NULL,
  `problem_description` text NOT NULL,
  `urgency` enum('Low','Normal','High','Critical') DEFAULT 'Normal',
  `requested_by` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `status` enum('Pending','Approved','In Progress','Completed','Rejected','Cancelled') DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'IT/Maintenance staff',
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `service_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `service_notes` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `face_data`
--

CREATE TABLE `face_data` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `face_descriptor` json NOT NULL COMMENT 'Face recognition data',
  `face_image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `enrollment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `government_loans`
--

CREATE TABLE `government_loans` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `loan_type` enum('SSS Salary Loan','SSS Calamity Loan','SSS Consolidated Loan','Pag-IBIG Multi-Purpose Loan','Pag-IBIG Calamity Loan') NOT NULL,
  `loan_amount` decimal(12,2) NOT NULL,
  `monthly_amortization` decimal(10,2) NOT NULL,
  `term_months` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `principal_balance` decimal(12,2) NOT NULL,
  `months_paid` int(11) DEFAULT '0',
  `deduction_type` enum('Salary Deduction','Voluntary') DEFAULT 'Salary Deduction',
  `status` enum('Pending','Active','Fully Paid','Cancelled','On Hold') DEFAULT 'Pending',
  `certificate_request_date` date DEFAULT NULL COMMENT 'When employee requested certificate',
  `certificate_issued_date` date DEFAULT NULL COMMENT 'When certificate was issued',
  `is_reloan` tinyint(1) DEFAULT '0' COMMENT 'Is this a reloan?',
  `previous_loan_id` int(11) DEFAULT NULL COMMENT 'Reference to previous loan if reloan',
  `notes` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `government_loan_payments`
--

CREATE TABLE `government_loan_payments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `new_balance` decimal(12,2) NOT NULL,
  `payroll_period_id` int(11) DEFAULT NULL COMMENT 'Link to payroll if salary deduction',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `holiday_name` varchar(100) NOT NULL,
  `holiday_type` enum('Regular Holiday','Special Non-Working Holiday') NOT NULL,
  `holiday_date` date NOT NULL,
  `scope` enum('Nationwide','Region-Wide','City-Wide') NOT NULL,
  `region` varchar(100) DEFAULT NULL COMMENT 'Specific region if scope is Region-Wide',
  `city` varchar(100) DEFAULT NULL COMMENT 'Specific city if scope is City-Wide',
  `effectivity_year` year(4) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `import_batches`
--

CREATE TABLE `import_batches` (
  `id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL COMMENT 'Module name (e.g., minimum_wage_rates)',
  `filename` varchar(255) NOT NULL COMMENT 'Original CSV filename',
  `total_rows` int(11) NOT NULL DEFAULT '0',
  `inserted_rows` int(11) NOT NULL DEFAULT '0',
  `skipped_rows` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks bulk import operations for rollback capability';

-- --------------------------------------------------------

--
-- Table structure for table `incident_reports`
--

CREATE TABLE `incident_reports` (
  `id` int(11) NOT NULL,
  `incident_number` varchar(50) NOT NULL,
  `reported_by` int(11) NOT NULL COMMENT 'Employee who reported',
  `incident_date` date NOT NULL,
  `incident_time` time DEFAULT NULL,
  `organizational_unit_id` int(11) DEFAULT NULL COMMENT 'Where incident occurred',
  `incident_type` enum('Accident','Injury','Property Damage','Theft','Safety Violation','Harassment','Workplace Violence','Equipment Failure','Near Miss','Other') NOT NULL,
  `severity` enum('Minor','Moderate','Serious','Critical') DEFAULT 'Minor',
  `persons_involved` text COMMENT 'Names of people involved',
  `witnesses` text COMMENT 'Names of witnesses',
  `incident_description` text NOT NULL,
  `immediate_action_taken` text,
  `injury_details` text COMMENT 'If there are injuries',
  `property_damage_details` text COMMENT 'If there is property damage',
  `estimated_cost` decimal(12,2) DEFAULT NULL COMMENT 'Estimated cost of damage',
  `attachments` text COMMENT 'File paths for photos, documents',
  `investigation_required` tinyint(1) DEFAULT '0',
  `investigation_notes` text,
  `corrective_actions` text,
  `status` enum('Reported','Under Investigation','Resolved','Closed') DEFAULT 'Reported',
  `reported_to_authorities` tinyint(1) DEFAULT '0' COMMENT 'Police, DOLE, etc.',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `manpower_requests`
--

CREATE TABLE `manpower_requests` (
  `id` int(11) NOT NULL,
  `request_type` enum('Extra Manpower','Replacement','New Branch') NOT NULL,
  `requested_by` int(11) NOT NULL COMMENT 'Branch head or requesting person',
  `position_id` int(11) NOT NULL,
  `organizational_unit_id` int(11) NOT NULL COMMENT 'For which branch/department',
  `branch_location` varchar(200) DEFAULT NULL,
  `vacancies` int(11) DEFAULT '1',
  `reason` text NOT NULL,
  `qualifications` text,
  `request_date` date NOT NULL,
  `needed_by_date` date DEFAULT NULL,
  `status` enum('Pending','Approved','In Progress','Filled','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `minimum_wage_rates`
--

CREATE TABLE `minimum_wage_rates` (
  `id` int(11) NOT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g., NCR, Region IV-A',
  `region_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Province if applicable',
  `province_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Specific city if rate differs',
  `city_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `import_batch_id` int(11) DEFAULT NULL,
  `daily_rate` decimal(10,2) NOT NULL COMMENT 'Non-Agricultural daily minimum wage',
  `effective_date` date NOT NULL,
  `wage_order_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g., NCR-24, RTWPB-04-23',
  `is_current` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Additional details about the wage order',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Regional minimum wage rates with city-specific variations';

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) NOT NULL,
  `recipient_user_id` int(11) NOT NULL COMMENT 'User who receives notification',
  `recipient_employee_id` int(11) DEFAULT NULL COMMENT 'Employee record if applicable',
  `notification_type` enum('Leave Request','Overtime Request','Schedule Change','Attendance Override','Employee Request','Incident Report','Payroll Released','Memo Issued','Document Uploaded','Performance Review','Equipment Request','Supply Request','Shift Change','Announcement','Other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(500) DEFAULT NULL COMMENT 'Link to relevant page/record',
  `reference_type` varchar(100) DEFAULT NULL COMMENT 'e.g., employee_leaves, employee_requests, etc.',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID of the related record',
  `priority` enum('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `is_sent_email` tinyint(1) DEFAULT '0',
  `sent_email_at` timestamp NULL DEFAULT NULL,
  `is_sent_sms` tinyint(1) DEFAULT '0',
  `sent_sms_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type` varchar(100) NOT NULL,
  `in_app_enabled` tinyint(1) DEFAULT '1',
  `email_enabled` tinyint(1) DEFAULT '1',
  `sms_enabled` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User notification preferences per type';

-- --------------------------------------------------------

--
-- Table structure for table `notification_templates`
--

CREATE TABLE `notification_templates` (
  `id` int(11) NOT NULL,
  `template_code` varchar(50) NOT NULL COMMENT 'e.g., LEAVE_APPROVED, PAYROLL_READY',
  `notification_type` varchar(100) NOT NULL,
  `title_template` varchar(255) NOT NULL COMMENT 'Use {placeholders} for dynamic values',
  `message_template` text NOT NULL,
  `email_subject` varchar(255) DEFAULT NULL,
  `email_body_template` text,
  `sms_template` varchar(160) DEFAULT NULL COMMENT 'SMS character limit',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Templates for automated notifications';

-- --------------------------------------------------------

--
-- Table structure for table `offboarding_clearance`
--

CREATE TABLE `offboarding_clearance` (
  `id` int(11) NOT NULL,
  `offboarding_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL COMMENT 'e.g., IT, Finance, HR, Warehouse',
  `cleared_by` int(11) DEFAULT NULL,
  `cleared_date` date DEFAULT NULL,
  `status` enum('Pending','Cleared','With Issues') DEFAULT 'Pending',
  `remarks` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `office_supplies`
--

CREATE TABLE `office_supplies` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `category` enum('Stationery','Paper Products','Writing Instruments','Filing Supplies','Office Equipment','Cleaning Supplies','Pantry Supplies','Other') NOT NULL,
  `description` text,
  `unit_of_measure` varchar(50) DEFAULT NULL COMMENT 'pcs, box, ream, pack',
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `reorder_level` int(11) DEFAULT NULL COMMENT 'Minimum quantity before reorder',
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `office_supplies_movements`
--

CREATE TABLE `office_supplies_movements` (
  `id` int(11) NOT NULL,
  `supply_id` int(11) NOT NULL,
  `organizational_unit_id` int(11) NOT NULL,
  `movement_type` enum('Stock In','Stock Out','Transfer','Adjustment','Replenishment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` enum('Purchase','Request','Transfer','Adjustment','Issuance') NOT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'Link to request or transfer',
  `movement_date` date NOT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `office_supplies_requests`
--

CREATE TABLE `office_supplies_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `organizational_unit_id` int(11) NOT NULL COMMENT 'Requesting branch/department',
  `requested_by` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `urgency` enum('Regular','Urgent') DEFAULT 'Regular',
  `justification` text,
  `status` enum('Pending','Approved','Partially Fulfilled','Fulfilled','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `office_supplies_request_items`
--

CREATE TABLE `office_supplies_request_items` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `supply_id` int(11) NOT NULL,
  `quantity_requested` int(11) NOT NULL,
  `quantity_approved` int(11) DEFAULT NULL,
  `quantity_fulfilled` int(11) DEFAULT '0',
  `remarks` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `office_supplies_stock`
--

CREATE TABLE `office_supplies_stock` (
  `id` int(11) NOT NULL,
  `supply_id` int(11) NOT NULL,
  `organizational_unit_id` int(11) NOT NULL COMMENT 'Which branch/department',
  `quantity_on_hand` int(11) DEFAULT '0',
  `last_replenished_date` date DEFAULT NULL,
  `last_replenished_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `organizational_units`
--

CREATE TABLE `organizational_units` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `unit_code` varchar(20) NOT NULL,
  `unit_name` varchar(100) NOT NULL,
  `unit_type` enum('Head Office','Branch','Warehouse','Regional Office','Department') NOT NULL,
  `description` text,
  `manager_employee_id` int(11) DEFAULT NULL COMMENT 'Manager or Head of this unit',
  `building_name` varchar(200) DEFAULT NULL COMMENT 'Building/Mall name: e.g., "SM Mall of Asia", "BDO Corporate Center"',
  `mall_type` enum('SM','Ayala Malls','Robinsons','Puregold','Starmalls','Waltermart','Vista Mall','Other','Not Applicable') DEFAULT 'Not Applicable' COMMENT 'Type of mall if branch is in a mall',
  `unit_number` varchar(50) DEFAULT NULL COMMENT 'Unit/Store number: e.g., "2F-123", "G/F Unit 45"',
  `house_number` varchar(50) DEFAULT NULL,
  `street_name` varchar(150) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `minimum_wage_rate_id` int(11) DEFAULT NULL COMMENT 'Link to applicable minimum wage for this location',
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Combined Branches and Departments - Organizational structure';

-- --------------------------------------------------------

--
-- Table structure for table `organizational_unit_equipment`
--

CREATE TABLE `organizational_unit_equipment` (
  `id` int(11) NOT NULL,
  `organizational_unit_id` int(11) NOT NULL,
  `equipment_name` varchar(200) NOT NULL,
  `equipment_type` enum('Computer','Printer','Scanner','Telephone','POS System','Cash Register','CCTV','Air Conditioner','Refrigerator','Other') NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(12,2) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `status` enum('Active','For Repair','For Replacement','Retired') DEFAULT 'Active',
  `condition` enum('Excellent','Good','Fair','Poor') DEFAULT 'Good',
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `notes` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Equipment assigned to branches/departments (not to individual employees)';

-- --------------------------------------------------------

--
-- Table structure for table `organizational_unit_groups`
--

CREATE TABLE `organizational_unit_groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL COMMENT 'e.g., "Luzon Cluster", "NCR Branches", "Sales Departments"',
  `group_code` varchar(20) NOT NULL,
  `description` text,
  `manager_employee_id` int(11) DEFAULT NULL COMMENT 'Cluster Head or Executive managing this group',
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Groups/Clusters of organizational units for Cluster Heads/Executives';

-- --------------------------------------------------------

--
-- Table structure for table `organizational_unit_group_members`
--

CREATE TABLE `organizational_unit_group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `organizational_unit_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `added_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Which branches/departments belong to which group/cluster';

-- --------------------------------------------------------

--
-- Table structure for table `pagibig_rates`
--

CREATE TABLE `pagibig_rates` (
  `id` int(11) NOT NULL,
  `effective_year` year(4) NOT NULL,
  `employee_contribution` decimal(12,2) DEFAULT '200.00',
  `employer_contribution` decimal(12,2) DEFAULT '200.00',
  `effective_date` date NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_adjustments`
--

CREATE TABLE `payroll_adjustments` (
  `id` int(11) NOT NULL,
  `payroll_detail_id` bigint(20) NOT NULL COMMENT 'Which payroll detail to adjust',
  `employee_id` int(11) NOT NULL,
  `payroll_period_id` int(11) NOT NULL,
  `adjustment_type` enum('Addition','Deduction') NOT NULL,
  `adjustment_category` enum('Earnings','Deductions','Government','Loans','Other') NOT NULL,
  `adjustment_reason` varchar(255) NOT NULL COMMENT 'e.g., Retroactive Pay, Missed OT, Correction, etc.',
  `amount` decimal(12,2) NOT NULL,
  `description` text,
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Applied') DEFAULT 'Pending',
  `applied_date` date DEFAULT NULL COMMENT 'When adjustment was applied to payroll',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Payroll adjustments for corrections, retroactive pay, missed items, etc.';

-- --------------------------------------------------------

--
-- Table structure for table `payroll_details`
--

CREATE TABLE `payroll_details` (
  `id` bigint(20) NOT NULL,
  `payroll_period_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `employee_number` varchar(20) DEFAULT NULL,
  `employee_name` varchar(200) DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `organizational_unit_name` varchar(100) DEFAULT NULL COMMENT 'Branch or Department name',
  `position_title` varchar(100) DEFAULT NULL,
  `pay_type` varchar(50) DEFAULT NULL,
  `bank_account_number` varchar(30) DEFAULT NULL,
  `basic_pay` decimal(12,2) DEFAULT '0.00',
  `allowances` decimal(12,2) DEFAULT '0.00',
  `regular_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Regular day OT pay',
  `night_diff_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Night differential pay (10pm-6am)',
  `night_diff_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'OT during night diff',
  `rest_day_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Rest day work pay',
  `rest_day_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Rest day OT pay',
  `rest_day_night_diff_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Rest day night diff pay',
  `rest_day_night_diff_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Rest day night diff OT pay',
  `regular_holiday_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Regular holiday work pay',
  `regular_holiday_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Regular holiday OT pay',
  `regular_holiday_night_diff_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Regular holiday night diff pay',
  `regular_holiday_night_diff_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Regular holiday night diff OT pay',
  `regular_holiday_rest_day_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Regular holiday on rest day pay',
  `regular_holiday_rest_day_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Regular holiday on rest day OT pay',
  `regular_holiday_rest_day_night_diff_pay` decimal(12,2) DEFAULT '0.00',
  `regular_holiday_rest_day_night_diff_overtime_pay` decimal(12,2) DEFAULT '0.00',
  `special_holiday_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Special holiday work pay',
  `special_holiday_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Special holiday OT pay',
  `special_holiday_night_diff_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Special holiday night diff pay',
  `special_holiday_night_diff_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Special holiday night diff OT pay',
  `special_holiday_rest_day_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Special holiday on rest day pay',
  `special_holiday_rest_day_overtime_pay` decimal(12,2) DEFAULT '0.00' COMMENT 'Special holiday on rest day OT pay',
  `special_holiday_rest_day_night_diff_pay` decimal(12,2) DEFAULT '0.00',
  `special_holiday_rest_day_night_diff_overtime_pay` decimal(12,2) DEFAULT '0.00',
  `gross_pay` decimal(12,2) DEFAULT '0.00',
  `sss_employee` decimal(12,2) DEFAULT '0.00',
  `sss_employer` decimal(12,2) DEFAULT '0.00',
  `philhealth_employee` decimal(12,2) DEFAULT '0.00' COMMENT '2.5% of basic',
  `philhealth_employer` decimal(12,2) DEFAULT '0.00' COMMENT '2.5% of basic',
  `pagibig_employee` decimal(12,2) DEFAULT '200.00' COMMENT 'Fixed 200',
  `pagibig_employer` decimal(12,2) DEFAULT '200.00' COMMENT 'Fixed 200',
  `withholding_tax` decimal(12,2) DEFAULT '0.00',
  `sss_salary_loan` decimal(12,2) DEFAULT '0.00',
  `sss_calamity_loan` decimal(12,2) DEFAULT '0.00',
  `sss_consolidated_loan` decimal(12,2) DEFAULT '0.00',
  `pagibig_multipurpose_loan` decimal(12,2) DEFAULT '0.00',
  `pagibig_calamity_loan` decimal(12,2) DEFAULT '0.00',
  `employee_sale` decimal(12,2) DEFAULT '0.00',
  `cash_advance` decimal(12,2) DEFAULT '0.00',
  `inventory_loss` decimal(12,2) DEFAULT '0.00',
  `store_penalty` decimal(12,2) DEFAULT '0.00',
  `uniform` decimal(12,2) DEFAULT '0.00',
  `overpaid` decimal(12,2) DEFAULT '0.00',
  `damage` decimal(12,2) DEFAULT '0.00',
  `other_deductions` decimal(12,2) DEFAULT '0.00',
  `total_deductions` decimal(12,2) DEFAULT '0.00',
  `net_pay` decimal(12,2) DEFAULT '0.00',
  `deductions_paused` tinyint(1) DEFAULT '0' COMMENT 'True if deductions would cause negative net pay',
  `paused_deductions_amount` decimal(12,2) DEFAULT '0.00' COMMENT 'Amount of deductions paused',
  `remarks` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `id` int(11) NOT NULL,
  `period_name` varchar(100) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `pay_date` date NOT NULL,
  `cutoff_type` enum('Semi-Monthly','Monthly') NOT NULL,
  `status` enum('Draft','Processing','Approved','Posted','Paid') DEFAULT 'Draft',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `payslips`
--

CREATE TABLE `payslips` (
  `id` bigint(20) NOT NULL,
  `payroll_detail_id` bigint(20) NOT NULL COMMENT 'Link to payroll_details',
  `employee_id` int(11) NOT NULL,
  `payroll_period_id` int(11) NOT NULL,
  `payslip_number` varchar(50) NOT NULL COMMENT 'Unique payslip number',
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `generated_by` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Path to generated PDF payslip',
  `is_viewed` tinyint(1) DEFAULT '0',
  `viewed_at` timestamp NULL DEFAULT NULL,
  `is_downloaded` tinyint(1) DEFAULT '0',
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `is_sent_email` tinyint(1) DEFAULT '0',
  `sent_email_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Generated payslips for employees to view/download';

-- --------------------------------------------------------

--
-- Table structure for table `performance_criteria`
--

CREATE TABLE `performance_criteria` (
  `id` int(11) NOT NULL,
  `criteria_code` varchar(20) NOT NULL,
  `criteria_name` varchar(100) NOT NULL,
  `description` text,
  `category` enum('Productivity','Quality','Attendance','Behavior','Leadership','Technical Skills') NOT NULL,
  `max_score` decimal(5,2) DEFAULT '5.00',
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `performance_reviews`
--

CREATE TABLE `performance_reviews` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `review_period_start` date NOT NULL,
  `review_period_end` date NOT NULL,
  `review_type` enum('1st Month (Probationary)','3rd Month (Probationary)','5th Month (Probationary)','Annual Review','Management Review') NOT NULL,
  `review_date` date NOT NULL,
  `evaluator_id` int(11) DEFAULT NULL COMMENT 'Immediate supervisor',
  `reviewer_id` int(11) DEFAULT NULL COMMENT 'Department head or higher',
  `overall_score` decimal(5,2) DEFAULT NULL,
  `overall_rating` enum('Outstanding','Exceeds Expectations','Meets Expectations','Needs Improvement','Unsatisfactory') DEFAULT NULL,
  `strengths` text,
  `areas_for_improvement` text,
  `goals_for_next_period` text,
  `recommendation` enum('Recommend for Regularization','Continue Probation','Not Recommend','Promote','Salary Increase') DEFAULT NULL COMMENT 'Based on review type',
  `status` enum('Draft','Submitted','Under Review','Completed','Acknowledged') DEFAULT 'Draft',
  `employee_acknowledged_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `performance_review_details`
--

CREATE TABLE `performance_review_details` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `comments` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `philhealth_rates`
--

CREATE TABLE `philhealth_rates` (
  `id` int(11) NOT NULL,
  `effective_year` year(4) NOT NULL,
  `total_rate` decimal(5,2) DEFAULT '5.00' COMMENT 'Total premium rate (5%)',
  `employee_share` decimal(5,2) DEFAULT '2.50' COMMENT 'Employee share (2.5%)',
  `employer_share` decimal(5,2) DEFAULT '2.50' COMMENT 'Employer share (2.5%)',
  `effective_date` date NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_code` varchar(20) DEFAULT NULL,
  `position_title` varchar(100) NOT NULL,
  `job_description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `psgc_cache`
--

CREATE TABLE `psgc_cache` (
  `id` int(11) NOT NULL,
  `cache_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cache_data` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text,
  `is_predefined` tinyint(1) DEFAULT '0' COMMENT 'Predefined roles can be modified',
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `module` varchar(100) NOT NULL COMMENT 'Page/Module name',
  `can_view` tinyint(1) DEFAULT '0',
  `can_create` tinyint(1) DEFAULT '0',
  `can_edit` tinyint(1) DEFAULT '0',
  `can_delete` tinyint(1) DEFAULT '0',
  `can_approve` tinyint(1) DEFAULT '0',
  `can_export` tinyint(1) DEFAULT '0',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_override_requests`
--

CREATE TABLE `schedule_override_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `override_date` date NOT NULL,
  `original_time_in` time DEFAULT NULL,
  `original_time_out` time DEFAULT NULL,
  `new_time_in` time DEFAULT NULL,
  `new_time_out` time DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `approver_remarks` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `security_audit_log`
--

CREATE TABLE `security_audit_log` (
  `id` bigint(20) NOT NULL,
  `event_type` enum('Failed Login','Account Locked','Password Changed','Password Reset','Unauthorized Access','Data Export','Suspicious Activity') NOT NULL,
  `severity` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `user_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `location_latitude` decimal(10,8) DEFAULT NULL,
  `location_longitude` decimal(11,8) DEFAULT NULL,
  `device_info` json DEFAULT NULL,
  `user_agent` text,
  `is_resolved` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `shift_change_requests`
--

CREATE TABLE `shift_change_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `request_type` enum('Change Shift Schedule','Change to Custom Schedule') NOT NULL,
  `current_shift_schedule_id` int(11) DEFAULT NULL COMMENT 'Current shift if applicable',
  `requested_shift_schedule_id` int(11) DEFAULT NULL COMMENT 'Requested new shift',
  `effective_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected','Implemented') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `approver_remarks` text,
  `implemented_date` date DEFAULT NULL COMMENT 'When change was actually implemented',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Permanent shift/schedule change requests (not just one-day overrides)';

-- --------------------------------------------------------

--
-- Table structure for table `shift_schedules`
--

CREATE TABLE `shift_schedules` (
  `id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL COMMENT 'e.g., Morning Shift, Night Shift, Graveyard',
  `shift_type` enum('Fixed','Rotating','Flexible') DEFAULT 'Fixed',
  `monday_time_in` time DEFAULT NULL,
  `monday_lunch_out` time DEFAULT NULL,
  `monday_lunch_in` time DEFAULT NULL,
  `monday_time_out` time DEFAULT NULL,
  `monday_hours` decimal(4,2) DEFAULT NULL COMMENT 'Total hours for Monday',
  `monday_is_rest_day` tinyint(1) DEFAULT '0',
  `tuesday_time_in` time DEFAULT NULL,
  `tuesday_lunch_out` time DEFAULT NULL,
  `tuesday_lunch_in` time DEFAULT NULL,
  `tuesday_time_out` time DEFAULT NULL,
  `tuesday_hours` decimal(4,2) DEFAULT NULL,
  `tuesday_is_rest_day` tinyint(1) DEFAULT '0',
  `wednesday_time_in` time DEFAULT NULL,
  `wednesday_lunch_out` time DEFAULT NULL,
  `wednesday_lunch_in` time DEFAULT NULL,
  `wednesday_time_out` time DEFAULT NULL,
  `wednesday_hours` decimal(4,2) DEFAULT NULL,
  `wednesday_is_rest_day` tinyint(1) DEFAULT '0',
  `thursday_time_in` time DEFAULT NULL,
  `thursday_lunch_out` time DEFAULT NULL,
  `thursday_lunch_in` time DEFAULT NULL,
  `thursday_time_out` time DEFAULT NULL,
  `thursday_hours` decimal(4,2) DEFAULT NULL,
  `thursday_is_rest_day` tinyint(1) DEFAULT '0',
  `friday_time_in` time DEFAULT NULL,
  `friday_lunch_out` time DEFAULT NULL,
  `friday_lunch_in` time DEFAULT NULL,
  `friday_time_out` time DEFAULT NULL,
  `friday_hours` decimal(4,2) DEFAULT NULL,
  `friday_is_rest_day` tinyint(1) DEFAULT '0',
  `saturday_time_in` time DEFAULT NULL,
  `saturday_lunch_out` time DEFAULT NULL,
  `saturday_lunch_in` time DEFAULT NULL,
  `saturday_time_out` time DEFAULT NULL,
  `saturday_hours` decimal(4,2) DEFAULT NULL,
  `saturday_schedule` enum('odd','even','all','none') DEFAULT 'none' COMMENT 'odd=1st,3rd,5th week; even=2nd,4th week; all=every Saturday; none=rest day',
  `sunday_time_in` time DEFAULT NULL,
  `sunday_lunch_out` time DEFAULT NULL,
  `sunday_lunch_in` time DEFAULT NULL,
  `sunday_time_out` time DEFAULT NULL,
  `sunday_hours` decimal(4,2) DEFAULT NULL,
  `sunday_is_rest_day` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `sss_contribution_tables`
--

CREATE TABLE `sss_contribution_tables` (
  `id` int(11) NOT NULL,
  `effective_year` year(4) NOT NULL,
  `salary_range_from` decimal(12,2) NOT NULL,
  `salary_range_to` decimal(12,2) DEFAULT NULL,
  `employee_contribution` decimal(12,2) NOT NULL,
  `employer_contribution` decimal(12,2) NOT NULL,
  `ec_contribution` decimal(12,2) DEFAULT '10.00' COMMENT 'Employees Compensation',
  `effective_date` date NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tax_tables`
--

CREATE TABLE `tax_tables` (
  `id` int(11) NOT NULL,
  `effective_year` year(4) NOT NULL,
  `compensation_from` decimal(12,2) NOT NULL COMMENT 'Annualized taxable income FROM',
  `compensation_to` decimal(12,2) DEFAULT NULL COMMENT 'Annualized taxable income TO (NULL for highest bracket)',
  `base_tax` decimal(12,2) DEFAULT '0.00' COMMENT 'Fixed tax for this bracket',
  `rate_percentage` decimal(5,2) DEFAULT '0.00' COMMENT 'Tax rate % on excess',
  `effective_date` date NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Example: 250k-400k = 0 + 15% of excess over 250k';

-- --------------------------------------------------------

--
-- Table structure for table `thirteenth_month_pay`
--

CREATE TABLE `thirteenth_month_pay` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `total_basic_pay` decimal(12,2) DEFAULT '0.00',
  `net_amount` decimal(12,2) DEFAULT '0.00',
  `payment_date` date DEFAULT NULL,
  `status` enum('Computed','Paid','Released') DEFAULT 'Computed',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `timekeeping_summary`
--

CREATE TABLE `timekeeping_summary` (
  `id` bigint(20) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_hours_worked` decimal(8,2) DEFAULT '0.00' COMMENT 'Total regular hours',
  `total_minutes_late` int(11) DEFAULT '0',
  `total_minutes_undertime` int(11) DEFAULT '0',
  `total_days_present` int(11) DEFAULT '0',
  `total_days_absent` int(11) DEFAULT '0',
  `regular_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Regular day OT',
  `night_diff_hours` decimal(8,2) DEFAULT '0.00' COMMENT '10pm-6am hours on regular days',
  `night_diff_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'OT during night diff hours',
  `rest_day_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Work on rest day (no OT)',
  `rest_day_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'OT on rest day',
  `rest_day_night_diff_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff on rest day',
  `rest_day_night_diff_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff + OT on rest day',
  `regular_holiday_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Work on regular holiday (no OT)',
  `regular_holiday_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'OT on regular holiday',
  `regular_holiday_night_diff_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff on regular holiday',
  `regular_holiday_night_diff_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff + OT on regular holiday',
  `regular_holiday_rest_day_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Work on regular holiday that falls on rest day',
  `regular_holiday_rest_day_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'OT on regular holiday that falls on rest day',
  `regular_holiday_rest_day_night_diff_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff on regular holiday rest day',
  `regular_holiday_rest_day_night_diff_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff + OT on regular holiday rest day',
  `special_holiday_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Work on special holiday (no OT)',
  `special_holiday_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'OT on special holiday',
  `special_holiday_night_diff_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff on special holiday',
  `special_holiday_night_diff_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff + OT on special holiday',
  `special_holiday_rest_day_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Work on special holiday that falls on rest day',
  `special_holiday_rest_day_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'OT on special holiday that falls on rest day',
  `special_holiday_rest_day_night_diff_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff on special holiday rest day',
  `special_holiday_rest_day_night_diff_overtime_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Night diff + OT on special holiday rest day',
  `obt_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Official Business Trip hours',
  `obt_count` int(11) DEFAULT '0' COMMENT 'Number of OBT trips',
  `field_hours` decimal(8,2) DEFAULT '0.00' COMMENT 'Field work hours',
  `field_work_count` int(11) DEFAULT '0' COMMENT 'Number of field work visits',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('Active','Inactive','Locked') DEFAULT 'Active',
  `last_login` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_login_latitude` decimal(10,8) DEFAULT NULL,
  `last_login_longitude` decimal(11,8) DEFAULT NULL,
  `last_login_device` json DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT '0',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `location_latitude` decimal(10,8) DEFAULT NULL,
  `location_longitude` decimal(11,8) DEFAULT NULL,
  `device_info` json DEFAULT NULL COMMENT 'Device name, browser, OS, etc.',
  `user_agent` text,
  `login_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logout_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcement_acknowledgments`
--
ALTER TABLE `announcement_acknowledgments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `applicant_exam_results`
--
ALTER TABLE `applicant_exam_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `applicant_interviews`
--
ALTER TABLE `applicant_interviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_assignments`
--
ALTER TABLE `asset_assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset_maintenance`
--
ALTER TABLE `asset_maintenance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_date` (`employee_id`,`attendance_date`,`is_deleted`);

--
-- Indexes for table `attendance_field_work`
--
ALTER TABLE `attendance_field_work`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance_obt`
--
ALTER TABLE `attendance_obt`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance_override_requests`
--
ALTER TABLE `attendance_override_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company_assets`
--
ALTER TABLE `company_assets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company_deductions`
--
ALTER TABLE `company_deductions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company_deduction_payments`
--
ALTER TABLE `company_deduction_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `data_access_logs`
--
ALTER TABLE `data_access_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `data_privacy_consents`
--
ALTER TABLE `data_privacy_consents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_allowances`
--
ALTER TABLE `employee_allowances`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_attachments`
--
ALTER TABLE `employee_attachments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_benefits`
--
ALTER TABLE `employee_benefits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_dependents`
--
ALTER TABLE `employee_dependents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_education`
--
ALTER TABLE `employee_education`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_exit_interview`
--
ALTER TABLE `employee_exit_interview`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_final_pay`
--
ALTER TABLE `employee_final_pay`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_leaves`
--
ALTER TABLE `employee_leaves`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_leave_credits`
--
ALTER TABLE `employee_leave_credits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_memos`
--
ALTER TABLE `employee_memos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_movements`
--
ALTER TABLE `employee_movements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_offboarding`
--
ALTER TABLE `employee_offboarding`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_overtimes`
--
ALTER TABLE `employee_overtimes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_requests`
--
ALTER TABLE `employee_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_request_comments`
--
ALTER TABLE `employee_request_comments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_salary_history`
--
ALTER TABLE `employee_salary_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_schedule`
--
ALTER TABLE `employee_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_work_history`
--
ALTER TABLE `employee_work_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employment_statuses`
--
ALTER TABLE `employment_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employment_types`
--
ALTER TABLE `employment_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment_service_requests`
--
ALTER TABLE `equipment_service_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `face_data`
--
ALTER TABLE `face_data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `government_loans`
--
ALTER TABLE `government_loans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `government_loan_payments`
--
ALTER TABLE `government_loan_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `import_batches`
--
ALTER TABLE `import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `incident_reports`
--
ALTER TABLE `incident_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `manpower_requests`
--
ALTER TABLE `manpower_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `minimum_wage_rates`
--
ALTER TABLE `minimum_wage_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_location_codes` (`region_code`,`province_code`,`city_code`),
  ADD KEY `idx_import_batch` (`import_batch_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient_unread` (`recipient_user_id`,`is_read`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_type` (`user_id`,`notification_type`);

--
-- Indexes for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_template_code` (`template_code`);

--
-- Indexes for table `offboarding_clearance`
--
ALTER TABLE `offboarding_clearance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `office_supplies`
--
ALTER TABLE `office_supplies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `office_supplies_movements`
--
ALTER TABLE `office_supplies_movements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `office_supplies_requests`
--
ALTER TABLE `office_supplies_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `office_supplies_request_items`
--
ALTER TABLE `office_supplies_request_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `office_supplies_stock`
--
ALTER TABLE `office_supplies_stock`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `organizational_units`
--
ALTER TABLE `organizational_units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `organizational_unit_equipment`
--
ALTER TABLE `organizational_unit_equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `organizational_unit_groups`
--
ALTER TABLE `organizational_unit_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `organizational_unit_group_members`
--
ALTER TABLE `organizational_unit_group_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pagibig_rates`
--
ALTER TABLE `pagibig_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payroll_details`
--
ALTER TABLE `payroll_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payslips`
--
ALTER TABLE `payslips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_payslip` (`payroll_period_id`,`employee_id`);

--
-- Indexes for table `performance_criteria`
--
ALTER TABLE `performance_criteria`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `performance_review_details`
--
ALTER TABLE `performance_review_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `philhealth_rates`
--
ALTER TABLE `philhealth_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `psgc_cache`
--
ALTER TABLE `psgc_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cache_key` (`cache_key`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedule_override_requests`
--
ALTER TABLE `schedule_override_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shift_change_requests`
--
ALTER TABLE `shift_change_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shift_schedules`
--
ALTER TABLE `shift_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sss_contribution_tables`
--
ALTER TABLE `sss_contribution_tables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tax_tables`
--
ALTER TABLE `tax_tables`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `thirteenth_month_pay`
--
ALTER TABLE `thirteenth_month_pay`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `timekeeping_summary`
--
ALTER TABLE `timekeeping_summary`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcement_acknowledgments`
--
ALTER TABLE `announcement_acknowledgments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicant_exam_results`
--
ALTER TABLE `applicant_exam_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicant_interviews`
--
ALTER TABLE `applicant_interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_assignments`
--
ALTER TABLE `asset_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_maintenance`
--
ALTER TABLE `asset_maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_field_work`
--
ALTER TABLE `attendance_field_work`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_obt`
--
ALTER TABLE `attendance_obt`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_override_requests`
--
ALTER TABLE `attendance_override_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_assets`
--
ALTER TABLE `company_assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_deductions`
--
ALTER TABLE `company_deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_deduction_payments`
--
ALTER TABLE `company_deduction_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_access_logs`
--
ALTER TABLE `data_access_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_privacy_consents`
--
ALTER TABLE `data_privacy_consents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_allowances`
--
ALTER TABLE `employee_allowances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_attachments`
--
ALTER TABLE `employee_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_benefits`
--
ALTER TABLE `employee_benefits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_dependents`
--
ALTER TABLE `employee_dependents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_education`
--
ALTER TABLE `employee_education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_exit_interview`
--
ALTER TABLE `employee_exit_interview`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_final_pay`
--
ALTER TABLE `employee_final_pay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_leaves`
--
ALTER TABLE `employee_leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_leave_credits`
--
ALTER TABLE `employee_leave_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_memos`
--
ALTER TABLE `employee_memos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_movements`
--
ALTER TABLE `employee_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_offboarding`
--
ALTER TABLE `employee_offboarding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_overtimes`
--
ALTER TABLE `employee_overtimes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_requests`
--
ALTER TABLE `employee_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_request_comments`
--
ALTER TABLE `employee_request_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_salary_history`
--
ALTER TABLE `employee_salary_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_schedule`
--
ALTER TABLE `employee_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_work_history`
--
ALTER TABLE `employee_work_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employment_statuses`
--
ALTER TABLE `employment_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employment_types`
--
ALTER TABLE `employment_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment_service_requests`
--
ALTER TABLE `equipment_service_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `face_data`
--
ALTER TABLE `face_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `government_loans`
--
ALTER TABLE `government_loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `government_loan_payments`
--
ALTER TABLE `government_loan_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `import_batches`
--
ALTER TABLE `import_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_reports`
--
ALTER TABLE `incident_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manpower_requests`
--
ALTER TABLE `manpower_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `minimum_wage_rates`
--
ALTER TABLE `minimum_wage_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_templates`
--
ALTER TABLE `notification_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offboarding_clearance`
--
ALTER TABLE `offboarding_clearance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_supplies`
--
ALTER TABLE `office_supplies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_supplies_movements`
--
ALTER TABLE `office_supplies_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_supplies_requests`
--
ALTER TABLE `office_supplies_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_supplies_request_items`
--
ALTER TABLE `office_supplies_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_supplies_stock`
--
ALTER TABLE `office_supplies_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organizational_units`
--
ALTER TABLE `organizational_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organizational_unit_equipment`
--
ALTER TABLE `organizational_unit_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organizational_unit_groups`
--
ALTER TABLE `organizational_unit_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organizational_unit_group_members`
--
ALTER TABLE `organizational_unit_group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pagibig_rates`
--
ALTER TABLE `pagibig_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_adjustments`
--
ALTER TABLE `payroll_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_details`
--
ALTER TABLE `payroll_details`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_periods`
--
ALTER TABLE `payroll_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payslips`
--
ALTER TABLE `payslips`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_criteria`
--
ALTER TABLE `performance_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_review_details`
--
ALTER TABLE `performance_review_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `philhealth_rates`
--
ALTER TABLE `philhealth_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `psgc_cache`
--
ALTER TABLE `psgc_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_override_requests`
--
ALTER TABLE `schedule_override_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shift_change_requests`
--
ALTER TABLE `shift_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shift_schedules`
--
ALTER TABLE `shift_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sss_contribution_tables`
--
ALTER TABLE `sss_contribution_tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_tables`
--
ALTER TABLE `tax_tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `thirteenth_month_pay`
--
ALTER TABLE `thirteenth_month_pay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timekeeping_summary`
--
ALTER TABLE `timekeeping_summary`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `minimum_wage_rates`
--
ALTER TABLE `minimum_wage_rates`
  ADD CONSTRAINT `fk_wage_rates_import_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `import_batches` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

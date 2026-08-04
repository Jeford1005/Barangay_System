SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `role` enum('admin','staff','resident') NOT NULL DEFAULT 'resident',
  `resident_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `twofa_secret` varchar(255) DEFAULT NULL,
  `twofa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `reset_code` varchar(6) DEFAULT NULL,
  `reset_code_expiry` datetime DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_resident_id` (`resident_id`),
  KEY `idx_reset_token` (`reset_token`),
  KEY `idx_reset_code` (`reset_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CORE: RESIDENTS PROFILING
-- ============================================================

CREATE TABLE `residents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `birth_place` varchar(200) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated','Divorced') NOT NULL DEFAULT 'Single',
  `citizenship` varchar(50) NOT NULL DEFAULT 'Filipino',
  `religion` varchar(50) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `voter_status` enum('Registered','Not Registered','Pending') NOT NULL DEFAULT 'Not Registered',
  `is_pwd` tinyint(1) NOT NULL DEFAULT 0,
  `is_senior` tinyint(1) NOT NULL DEFAULT 0,
  `is_indigent` tinyint(1) NOT NULL DEFAULT 0,
  `fourps_beneficiary` tinyint(1) NOT NULL DEFAULT 0,
  `household_id` int(11) DEFAULT NULL,
  `purok_id` int(11) DEFAULT NULL,
  `status` enum('Active','Deceased','Moved Out') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`last_name`,`first_name`),
  KEY `idx_household` (`household_id`),
  KEY `idx_purok` (`purok_id`),
  KEY `idx_status` (`status`),
  KEY `idx_birth_date` (`birth_date`),
  FULLTEXT KEY `idx_full_name` (`first_name`,`middle_name`,`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CORE: HOUSEHOLDS & PUROKS
-- ============================================================

CREATE TABLE `puroks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purok_name` varchar(100) NOT NULL,
  `zone_number` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_purok_name` (`purok_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `households` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `household_number` varchar(50) NOT NULL,
  `head_id` int(11) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `purok_id` int(11) NOT NULL,
  `number_of_members` int(11) NOT NULL DEFAULT 1,
  `house_type` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_household_number` (`household_number`),
  KEY `idx_purok` (`purok_id`),
  KEY `idx_head` (`head_id`),
  CONSTRAINT `fk_household_purok` FOREIGN KEY (`purok_id`) REFERENCES `puroks`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_household_head` FOREIGN KEY (`head_id`) REFERENCES `residents`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `residents`
  ADD CONSTRAINT `fk_resident_household` FOREIGN KEY (`household_id`) REFERENCES `households`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resident_purok` FOREIGN KEY (`purok_id`) REFERENCES `puroks`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================
-- CORE: BARANGAY OFFICIALS
-- ============================================================

CREATE TABLE `officials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `term_start` date DEFAULT NULL,
  `term_end` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_position` (`position`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DOCUMENTS: DOCUMENT TYPES & FEES
-- ============================================================

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `requires_or` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_document_name` (`document_name`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DOCUMENTS: DOCUMENT REQUESTS
-- ============================================================

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resident_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `or_number` varchar(50) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('Pending','Processing','Ready for Pickup','Released','Cancelled') NOT NULL DEFAULT 'Pending',
  `requested_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_resident` (`resident_id`),
  KEY `idx_doc_type` (`document_type_id`),
  KEY `idx_status` (`status`),
  KEY `idx_or` (`or_number`),
  KEY `idx_requested` (`requested_at`),
  CONSTRAINT `fk_doc_req_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_doc_req_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_doc_req_processed` FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DOCUMENTS: OFFICIAL RECEIPTS (OR)
-- ============================================================

CREATE TABLE `official_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `or_number` varchar(50) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','GCash','Bank Transfer','Others') NOT NULL DEFAULT 'Cash',
  `received_by` int(11) DEFAULT NULL,
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_or_number` (`or_number`),
  KEY `idx_resident` (`resident_id`),
  KEY `idx_doc_type` (`document_type_id`),
  KEY `idx_issued` (`issued_at`),
  CONSTRAINT `fk_or_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_or_doc_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_or_received` FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BLOTTER: CASES & MEDIATION
-- ============================================================

CREATE TABLE `blotter_cases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_number` varchar(50) NOT NULL,
  `case_type` enum('Dispute','Complaint','Incident','Disturbance','Theft','Others') NOT NULL DEFAULT 'Dispute',
  `status` enum('Open','Under Mediation','Conciliated','Arbitrated','Escalated','Closed') NOT NULL DEFAULT 'Open',
  `filing_date` date NOT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time DEFAULT NULL,
  `incident_location` varchar(255) NOT NULL,
  `involved_parties` text NOT NULL,
  `narrative` text NOT NULL,
  `complainant_id` int(11) DEFAULT NULL,
  `respondent_id` int(11) DEFAULT NULL,
  `assigned_official_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_case_number` (`case_number`),
  KEY `idx_status` (`status`),
  KEY `idx_complainant` (`complainant_id`),
  KEY `idx_respondent` (`respondent_id`),
  KEY `idx_official` (`assigned_official_id`),
  KEY `idx_filing_date` (`filing_date`),
  CONSTRAINT `fk_blotter_complainant` FOREIGN KEY (`complainant_id`) REFERENCES `residents`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blotter_respondent` FOREIGN KEY (`respondent_id`) REFERENCES `residents`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blotter_official` FOREIGN KEY (`assigned_official_id`) REFERENCES `officials`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blotter_created` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- HEALTH: COMMUNITY HEALTH
-- ============================================================

CREATE TABLE `health_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resident_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NOT NULL DEFAULT 'Unknown',
  `height_cm` int(11) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `vaccination_status` enum('Fully Vaccinated','Partially Vaccinated','Not Vaccinated','Unknown') NOT NULL DEFAULT 'Unknown',
  `medical_conditions` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `last_checkup` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resident` (`resident_id`),
  CONSTRAINT `fk_health_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WELFARE: PROGRAMS & BENEFICIARIES
-- ============================================================

CREATE TABLE `welfare_programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `program_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `beneficiary_type` varchar(100) DEFAULT NULL,
  `status` enum('Upcoming','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `welfare_beneficiaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `program_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `status` enum('Enrolled','Completed','Dropped') NOT NULL DEFAULT 'Enrolled',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_program` (`program_id`),
  KEY `idx_resident` (`resident_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_welfare_program` FOREIGN KEY (`program_id`) REFERENCES `welfare_programs`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_welfare_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECURITY: AUDIT LOGS
-- ============================================================

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SYSTEM: SETTINGS
-- ============================================================

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA: BARANGAY BIDDUANG SYSTEM SETTINGS
-- ============================================================

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('barangay_name', 'Barangay Bidduang', 'Official barangay name'),
('municipality', 'Municipality of Talavera', 'Municipality'),
('province', 'Nueva Ecija', 'Province'),
('logo_path', 'assets/img/Brgy_Logo.png', 'Path to official logo'),
('portal_title', 'Barangay Bidduang Portal', 'Portal browser tab title'),
('admin_email', 'admin@bidduang.gov.ph', 'Default administrator email'),
('or_prefix', 'OR-2026-', 'Official Receipt number prefix'),
('case_prefix', 'BLT-2026-', 'Blotter case number prefix'),
('doc_request_prefix', 'DR-2026-', 'Document request number prefix'),
('maintenance_mode', '0', '0 = off, 1 = on'),
('site_contact', 'Barangay Bidduang Hall', 'Contact information display');

-- ============================================================
-- SEED DATA: SAMPLE DOCUMENT TYPES
-- ============================================================

INSERT INTO `document_types` (`document_name`, `description`, `fee`, `requires_or`) VALUES
('Barangay Clearance', 'General identification for employment, banking, and government applications.', 50.00, 1),
('Certificate of Indigency', 'For medical, financial, educational, or legal assistance (DSWD, PCSO, scholarships).', 0.00, 0),
('Certificate of Residency', 'Proof of address for school enrollment, utility connections, and loans.', 30.00, 1),
('Barangay Business Permit / Clearance', 'For operating local businesses and sari-sari stores.', 100.00, 1),
('Certificate of Good Moral Character', 'For academic and employment background checks.', 40.00, 1),
('First-Time Jobseeker Certificate (RA 11261)', 'Fee waiver certificate for first-time job applicants.', 0.00, 0),
('Barangay Identification Card', 'Official resident identification card.', 20.00, 1);

-- ============================================================
-- SEED DATA: DEFAULT ADMIN ACCOUNT
-- Username: admin | Password: Admin@123 (change on first login)
-- Hash generated with password_hash('Admin@123', PASSWORD_DEFAULT)
-- ============================================================

INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `status`, `phone_number`) VALUES
('admin', 'admin@bidduang.gov.ph', '$2y$10$OGnaKFn8sF/SAc5yNjqpeOlfYFbmloXfgCAppTU9okVFSNEtaT8qW', 'System Administrator', 'admin', 'active', '+639XXXXXXXXX');

-- ============================================================
-- SEED DATA: SAMPLE PUROKS
-- ============================================================

INSERT INTO `puroks` (`purok_name`, `zone_number`, `description`) VALUES
('Purok 1', 1, ''),
('Purok 2', 2, ''),
('Purok 3', 3, ''),
('Purok 4', 4, ''),
('Purok 5', 5, ''),
('Purok 6', 6, '');

COMMIT;

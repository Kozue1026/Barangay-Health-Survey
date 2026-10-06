-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2026 at 02:13 PM
-- Server version: 10.4.32-MariaDB
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `survey_system`
--
CREATE DATABASE IF NOT EXISTS `survey_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `survey_system`;

-- Drop existing tables in reverse dependency order to avoid foreign key issues
DROP TABLE IF EXISTS `response_answers`, `survey_choices`, `survey_questions`, `responses`, `surveys`, `residents`, `login_history`, `activity_logs`, `notifications`, `admins`;

-- --------------------------------------------------------
--
-- Table structure for table `notifications`
--
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('admin','resident') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `icon` varchar(100) DEFAULT 'bi-bell',
  `is_read` tinyint(1) DEFAULT 0,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_lookup` (`user_id`, `user_type`, `is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('admin','resident') NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_type`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'admin', 'Login', 'User logged in successfully', '::1', '2026-07-29 11:45:56'),
(2, 1, 'admin', 'create_resident', 'Created resident: RES-0006', '::1', '2026-07-29 11:48:28'),
(5, 1, 'admin', 'Logout', 'User logged out', '::1', '2026-07-29 11:55:34'),
(6, 1, 'admin', 'Login', 'User logged in successfully', '::1', '2026-07-29 11:55:41'),
(7, 1, 'admin', 'Login', 'User logged in successfully', '::1', '2026-07-29 12:02:01'),
(8, 1, 'admin', 'Logout', 'User logged out', '::1', '2026-07-29 12:11:18'),
(9, 1, 'admin', 'Login', 'User logged in successfully', '::1', '2026-07-29 12:11:48'),
(10, 1, 'admin', 'Logout', 'User logged out', '::1', '2026-07-29 12:11:51'),
(11, 1, 'resident', 'Login', 'User logged in successfully', '::1', '2026-07-29 12:12:04'),
(12, 1, 'resident', 'Logout', 'User logged out', '::1', '2026-07-29 12:12:11'),
(13, 1, 'resident', 'Login', 'User logged in successfully', '::1', '2026-07-29 12:12:26');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$5MdPsJHguLsfJMQAyz5WF.piqA9R9RIhIyXlx6p7Qi8P24FAz7CJW', 'Dr. Roberto Santos', 'admin@barangay.gov.ph', 'super_admin', 'active', '2026-07-29 11:45:35', '2026-07-29 11:45:35');

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('admin','resident') NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `user_id`, `user_type`, `login_time`, `logout_time`, `ip_address`) VALUES
(1, 1, 'admin', '2026-07-29 11:45:56', '2026-07-29 11:55:34', '::1'),
(2, 1, 'admin', '2026-07-29 11:55:41', '2026-07-29 12:11:18', '::1'),
(3, 1, 'admin', '2026-07-29 12:02:01', NULL, '::1'),
(4, 1, 'admin', '2026-07-29 12:11:48', '2026-07-29 12:11:51', '::1'),
(5, 1, 'resident', '2026-07-29 12:12:04', '2026-07-29 12:12:11', '::1'),
(6, 1, 'resident', '2026-07-29 12:12:26', NULL, '::1');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(11) NOT NULL,
  `resident_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `extension_name` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `employer` varchar(150) DEFAULT NULL,
  `employer_address` text DEFAULT NULL,
  `spouse_name` varchar(150) DEFAULT NULL,
  `spouse_occupation` varchar(100) DEFAULT NULL,
  `spouse_employer` varchar(150) DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `reference1_name` varchar(150) DEFAULT NULL,
  `reference1_contact` varchar(100) DEFAULT NULL,
  `reference2_name` varchar(150) DEFAULT NULL,
  `reference2_contact` varchar(100) DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `gender` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `first_login` tinyint(1) DEFAULT 1,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resident_children`
--

CREATE TABLE `resident_children` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `child_name` varchar(150) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `resident_number`, `password`, `first_name`, `last_name`, `middle_name`, `email`, `phone`, `address`, `gender`, `birthdate`, `status`, `first_login`, `security_question`, `security_answer`, `created_at`, `updated_at`) VALUES
(1, 'RES-0001', '$2y$10$5MdPsJHguLsfJMQAyz5WF.piqA9R9RIhIyXlx6p7Qi8P24FAz7CJW', 'Juan', 'Dela Cruz', 'P', 'juan@example.com', '09123456789', '123 Rizal St', 'Male', '1990-01-01', 'active', 1, NULL, NULL, '2026-07-29 11:45:35', '2026-07-29 11:45:35'),
(2, 'RES-0002', '$2y$10$5MdPsJHguLsfJMQAyz5WF.piqA9R9RIhIyXlx6p7Qi8P24FAz7CJW', 'Maria', 'Santos', 'R', 'maria@example.com', '09123456788', '456 Bonifacio St', 'Female', '1992-05-15', 'active', 1, NULL, NULL, '2026-07-29 11:45:35', '2026-07-29 11:45:35'),
(3, 'RES-0003', '$2y$10$5MdPsJHguLsfJMQAyz5WF.piqA9R9RIhIyXlx6p7Qi8P24FAz7CJW', 'Pedro', 'Reyes', 'A', 'pedro@example.com', '09123456787', '789 Mabini St', 'Male', '1985-10-20', 'active', 1, NULL, NULL, '2026-07-29 11:45:35', '2026-07-29 11:45:35'),
(4, 'RES-0004', '$2y$10$5MdPsJHguLsfJMQAyz5WF.piqA9R9RIhIyXlx6p7Qi8P24FAz7CJW', 'Ana', 'Garcia', 'L', 'ana@example.com', '09123456786', '101 Quezon St', 'Female', '1995-12-05', 'active', 1, NULL, NULL, '2026-07-29 11:45:35', '2026-07-29 11:45:35'),
(5, 'RES-0005', '$2y$10$5MdPsJHguLsfJMQAyz5WF.piqA9R9RIhIyXlx6p7Qi8P24FAz7CJW', 'Jose', 'Mendoza', 'S', 'jose@example.com', '09123456785', '202 Luna St', 'Male', '1988-08-08', 'active', 1, NULL, NULL, '2026-07-29 11:45:35', '2026-07-29 11:45:35'),
(6, 'RES-0006', '$2y$10$imBSBUbm2aeBZVZu2/ZeG.4LFVaHflYNU4pI4iMUBjtrJ7bCcTZWy', 'Gojo', 'Sataro', 'G.', 'gojo@gmail.com', '2324332423', 'tokyophiliphines', 'Male', '2017-06-21', 'active', 1, NULL, NULL, '2026-07-29 11:48:28', '2026-07-29 11:48:28');

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

CREATE TABLE `responses` (
  `id` int(11) NOT NULL,
  `survey_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `status` enum('completed') DEFAULT 'completed',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_answers`
--

CREATE TABLE `response_answers` (
  `id` int(11) NOT NULL,
  `response_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text DEFAULT NULL,
  `choice_id` int(11) DEFAULT NULL,
  `rating_value` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surveys`
--

CREATE TABLE `surveys` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `opening_date` date DEFAULT NULL,
  `closing_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `surveys`
--

INSERT INTO `surveys` (`id`, `title`, `description`, `category`, `status`, `opening_date`, `closing_date`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Community Health Assessment 2025', 'Survey to assess the health services in our barangay.', 'Health', 'active', '2025-01-01', '2025-12-31', 1, '2026-07-29 11:45:35', '2026-07-29 11:45:35'),
(2, 'COVID-19 Vaccination Survey', 'Survey regarding COVID-19 vaccination status and experience.', 'Vaccination', 'active', '2025-01-01', '2025-12-31', 1, '2026-07-29 11:45:35', '2026-07-29 11:45:35');

-- --------------------------------------------------------

--
-- Table structure for table `survey_choices`
--

CREATE TABLE `survey_choices` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `choice_text` varchar(255) NOT NULL,
  `choice_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `survey_choices`
--

INSERT INTO `survey_choices` (`id`, `question_id`, `choice_text`, `choice_order`, `created_at`) VALUES
(1, 3, 'General Checkup', 1, '2026-07-29 11:45:35'),
(2, 3, 'Vaccination', 2, '2026-07-29 11:45:35'),
(3, 3, 'Prenatal Care', 3, '2026-07-29 11:45:35'),
(4, 3, 'Dental Services', 4, '2026-07-29 11:45:35'),
(5, 3, 'Laboratory Tests', 5, '2026-07-29 11:45:35'),
(6, 7, 'Pfizer', 1, '2026-07-29 11:45:35'),
(7, 7, 'Moderna', 2, '2026-07-29 11:45:35'),
(8, 7, 'AstraZeneca', 3, '2026-07-29 11:45:35'),
(9, 7, 'Sinovac', 4, '2026-07-29 11:45:35'),
(10, 7, 'Johnson & Johnson', 5, '2026-07-29 11:45:35'),
(11, 7, 'Not Vaccinated', 6, '2026-07-29 11:45:35');

-- Clean up any invalid or junk test choices (e.g. 11111)
DELETE FROM `survey_choices` WHERE `choice_text` REGEXP '^(.)\\1{3,}$' OR `choice_text` = '11111';

-- --------------------------------------------------------

--
-- Table structure for table `survey_questions`
--

CREATE TABLE `survey_questions` (
  `id` int(11) NOT NULL,
  `survey_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','yes_no','rating','short_answer') NOT NULL,
  `question_order` int(11) DEFAULT 0,
  `required` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `survey_questions`
--

INSERT INTO `survey_questions` (`id`, `survey_id`, `question_text`, `question_type`, `question_order`, `required`, `created_at`) VALUES
(1, 1, 'How would you rate the overall health services in our barangay?', 'rating', 1, 1, '2026-07-29 11:45:35'),
(2, 1, 'Have you visited the Barangay Health Center in the past 6 months?', 'yes_no', 2, 1, '2026-07-29 11:45:35'),
(3, 1, 'What health service do you use most frequently?', 'multiple_choice', 3, 1, '2026-07-29 11:45:35'),
(4, 1, 'What improvements would you suggest for our health services?', 'short_answer', 4, 0, '2026-07-29 11:45:35'),
(5, 2, 'Have you been fully vaccinated against COVID-19?', 'yes_no', 1, 1, '2026-07-29 11:45:35'),
(6, 2, 'Which COVID-19 vaccine did you receive?', 'multiple_choice', 2, 1, '2026-07-29 11:45:35'),
(7, 2, 'How would you rate your experience at the vaccination center?', 'rating', 3, 1, '2026-07-29 11:45:35'),
(8, 2, 'Did you experience any side effects after vaccination?', 'yes_no', 4, 1, '2026-07-29 11:45:35'),
(9, 2, 'Please describe any side effects you experienced.', 'short_answer', 5, 0, '2026-07-29 11:45:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`user_type`,`created_at`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`user_type`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `resident_number` (`resident_number`);

--
-- Indexes for table `resident_children`
--
ALTER TABLE `resident_children`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `responses`
--
ALTER TABLE `responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `survey_id` (`survey_id`,`resident_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `survey_id_2` (`survey_id`,`resident_id`);

--
-- Indexes for table `response_answers`
--
ALTER TABLE `response_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `choice_id` (`choice_id`),
  ADD KEY `response_id` (`response_id`,`question_id`);

--
-- Indexes for table `surveys`
--
ALTER TABLE `surveys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`,`opening_date`,`closing_date`);

--
-- Indexes for table `survey_choices`
--
ALTER TABLE `survey_choices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `survey_questions`
--
ALTER TABLE `survey_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `survey_id` (`survey_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `resident_children`
--
ALTER TABLE `resident_children`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `responses`
--
ALTER TABLE `responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `response_answers`
--
ALTER TABLE `response_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `surveys`
--
ALTER TABLE `surveys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `survey_choices`
--
ALTER TABLE `survey_choices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `survey_questions`
--
ALTER TABLE `survey_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `resident_children`
--
ALTER TABLE `resident_children`
  ADD CONSTRAINT `resident_children_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `responses`
--
ALTER TABLE `responses`
  ADD CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `responses_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `response_answers`
--
ALTER TABLE `response_answers`
  ADD CONSTRAINT `response_answers_ibfk_1` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `response_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `survey_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `response_answers_ibfk_3` FOREIGN KEY (`choice_id`) REFERENCES `survey_choices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `surveys`
--
ALTER TABLE `surveys`
  ADD CONSTRAINT `surveys_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `survey_choices`
--
ALTER TABLE `survey_choices`
  ADD CONSTRAINT `survey_choices_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `survey_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `survey_questions`
--
ALTER TABLE `survey_questions`
  ADD CONSTRAINT `survey_questions_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE;
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
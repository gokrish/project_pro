-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 13, 2025 at 10:31 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u124637954_payroll_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `can_code` varchar(50) DEFAULT NULL,
  `candidate_name` text NOT NULL,
  `contact_details` text DEFAULT NULL,
  `alternate_contact_details` text DEFAULT NULL,
  `email_id` varchar(191) NOT NULL,
  `alternate_email_id` varchar(255) DEFAULT NULL,
  `linkedin` varchar(191) NOT NULL,
  `languages` text DEFAULT NULL,
  `role_addressed` text DEFAULT NULL,
  `current_location` text DEFAULT NULL,
  `preferred_location` text DEFAULT NULL,
  `current_position` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `notice_period` text DEFAULT NULL,
  `current_employer` text DEFAULT NULL,
  `current_salary` text DEFAULT NULL,
  `expected_salary` text DEFAULT NULL,
  `can_join` text DEFAULT NULL,
  `current_daily_rate` text DEFAULT NULL,
  `expected_daily_rate` text DEFAULT NULL,
  `current_working_status` text DEFAULT NULL,
  `current_agency` text DEFAULT NULL,
  `lead_type_role` text DEFAULT NULL,
  `lead_type` text DEFAULT NULL,
  `work_auth_status` text DEFAULT NULL,
  `follow_up` text DEFAULT NULL,
  `follow_up_date` text DEFAULT NULL,
  `consent` text DEFAULT NULL,
  `candidate_cv` text DEFAULT NULL,
  `consultancy_cv` text DEFAULT NULL,
  `face_to_face` text DEFAULT NULL,
  `extra_details` text DEFAULT NULL,
  `skill_set` text DEFAULT NULL,
  `created_by` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `candidates_edit_info`
--

CREATE TABLE `candidates_edit_info` (
  `id` int(11) NOT NULL,
  `can_code` text DEFAULT NULL,
  `candidate_name` text DEFAULT NULL,
  `contact_details` text DEFAULT NULL,
  `alternate_contact_details` text DEFAULT NULL,
  `email_id` text DEFAULT NULL,
  `alternate_email_id` text DEFAULT NULL,
  `linkedin` text DEFAULT NULL,
  `languages` text DEFAULT NULL,
  `role_addressed` text DEFAULT NULL,
  `current_location` text DEFAULT NULL,
  `preferred_location` text DEFAULT NULL,
  `current_position` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `notice_period` text DEFAULT NULL,
  `current_employer` text DEFAULT NULL,
  `current_salary` text DEFAULT NULL,
  `expected_salary` text DEFAULT NULL,
  `can_join` text DEFAULT NULL,
  `current_daily_rate` text DEFAULT NULL,
  `expected_daily_rate` text DEFAULT NULL,
  `current_working_status` text DEFAULT NULL,
  `current_agency` text DEFAULT NULL,
  `lead_type_role` text DEFAULT NULL,
  `lead_type` text DEFAULT NULL,
  `work_auth_status` text DEFAULT NULL,
  `follow_up` text DEFAULT NULL,
  `follow_up_date` text DEFAULT NULL,
  `consent` text DEFAULT NULL,
  `candidate_cv` text DEFAULT NULL,
  `consultancy_cv` text DEFAULT NULL,
  `face_to_face` text DEFAULT NULL,
  `extra_details` text DEFAULT NULL,
  `skill_set` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `candidate_addons`
--

CREATE TABLE `candidate_addons` (
  `id` int(11) NOT NULL,
  `column_id` varchar(255) NOT NULL,
  `can_code` varchar(50) DEFAULT NULL,
  `data` text NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



--
-- Table structure for table `candidate_assignments`
--

CREATE TABLE `candidate_assignments` (
  `id` int(11) NOT NULL,
  `can_code` varchar(50) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `usercode` varchar(255) NOT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



--
-- Table structure for table `candidate_column`
--

CREATE TABLE `candidate_column` (
  `id` int(11) NOT NULL,
  `column_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidate_column`
--

INSERT INTO `candidate_column` (`id`, `column_name`, `created_at`, `updated_at`) VALUES
(1, 'Payroll Comment', '2024-09-04 12:41:51', '2024-09-20 02:43:10');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_field_edits`
--

CREATE TABLE `candidate_field_edits` (
  `id` int(11) NOT NULL,
  `can_code` varchar(255) NOT NULL,
  `field_name` varchar(255) NOT NULL,
  `edited_by` varchar(255) NOT NULL,
  `edited_name` varchar(255) NOT NULL,
  `edited_at` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `can_skill_set`
--

CREATE TABLE `can_skill_set` (
  `id` int(11) NOT NULL,
  `skill` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `can_skill_set`
--

INSERT INTO `can_skill_set` (`id`, `skill`, `created_at`, `updated_at`) VALUES
(1, 'Core Java', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(2, 'Python', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(3, 'C#', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(4, 'C++', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(5, 'JavaScript', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(6, '.NET Framework', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(7, '.NET Core', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(8, 'ASP.NET', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(9, 'PHP', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(10, 'Ruby on Rails', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(11, 'Swift', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(12, 'Objective-C', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(13, 'Kotlin', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(14, 'SDK', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(15, 'Node.js', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(16, 'Angular.js', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(17, 'React.js', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(18, 'Ember.js', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(19, 'Vue.js', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(20, 'Ext.js', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(21, 'Bootstrap', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(22, 'jQuery', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(23, 'TypeScript', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(24, 'Django', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(25, 'Flask', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(26, 'Spring', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(27, 'Struts', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(28, 'Hibernate', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(29, 'JSF', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(30, 'Swing', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(31, 'Ionic', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(32, 'Cordova', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(33, 'SQL', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(34, 'NoSQL', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(35, 'Oracle DB', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(36, 'DB2', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(37, 'Redis', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(38, 'MariaDB', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(39, 'Elasticsearch', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(40, 'RESTful Web Services', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(41, 'SOAP Web Services', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(42, 'MVC Architecture', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(43, 'WebSockets', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(44, 'WebRTC', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(45, 'Docker', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(46, 'Kubernetes', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(47, 'CI/CD Pipelines', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(48, 'Jenkins', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(49, 'Ansible', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(50, 'Puppet', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(51, 'Terraform', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(52, 'GitLab CI', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(53, 'AWS', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(54, 'Microsoft Azure', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(55, 'Google Cloud Platform', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(56, 'OpenStack', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(57, 'Cloudera', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(58, 'Salesforce Cloud', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(59, 'Apache Kafka', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(60, 'Nagios', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(61, 'Vagrant', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(62, 'Selenium WebDriver', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(63, 'TestNG', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(64, 'SoapUI', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(65, 'QTP', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(66, 'VSTS', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(67, 'Functional Testing', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(68, 'Non-Functional Testing', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(69, 'Black Box Testing', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(70, 'White Box Testing', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(71, 'Load Testing', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(72, 'Security Testing', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(73, 'Penetration Testing', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(74, 'Agile Testing Methodologies', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(75, 'Automation Testing', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(76, 'LAN/WAN', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(77, 'Routers & Switches', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(78, 'Cisco ASA', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(79, 'Fortinet', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(80, 'Checkpoint', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(81, 'VPN Configuration', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(82, 'Network Protocols', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(83, 'IDS/IPS', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(84, 'Load Balancers', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(85, 'Informatica PowerCenter', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(86, 'DataStage', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(87, 'Ab Initio', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(88, 'QlikView', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(89, 'Microsoft BI', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(90, 'Tableau', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(91, 'Cognos', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(92, 'Teradata', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(93, 'Hadoop', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(94, 'Spark', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(95, 'SAP FICO', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(96, 'SAP SD', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(97, 'SAP MM', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(98, 'SAP ABAP', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(99, 'SAP Fiori', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(100, 'Oracle E-Business Suite', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(101, 'JD Edwards', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(102, 'Microsoft Dynamics', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(103, 'PeopleSoft', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(104, 'OpenText', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(105, 'Requirements Analysis', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(106, 'Functional Analysis', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(107, 'Agile Methodologies', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(108, 'Scaled Agile Framework', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(109, 'Waterfall Methodology', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(110, 'Scrum', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(111, 'Kanban', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(112, 'Project Planning', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(113, 'Risk Management', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(114, 'UML', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(115, 'BPMN', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(116, 'Microsoft Project', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(117, 'JIRA', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(118, 'Linux', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(119, 'UNIX', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(121, 'MacOS', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(122, 'Android OS', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(123, 'iOS', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(124, 'CentOS', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(125, 'Fedora', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(126, 'Selenium', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(127, 'UFT', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(128, 'JUnit', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(129, 'Cucumber', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(130, 'Postman', '2024-09-15 12:30:20', '2024-09-15 12:30:20'),
(131, 'Windows', '2024-09-15 12:37:47', '2024-09-15 12:37:47'),
(132, 'mysql', '2024-09-16 12:46:47', '2024-09-16 12:46:47'),
(133, 'Servicenow', '2024-09-20 02:43:38', '2024-09-20 02:43:38'),
(134, 'Full Stack Developer', '2024-09-20 02:44:12', '2024-09-20 02:44:12'),
(136, 'SAP BW', '2025-01-28 15:36:15', '2025-01-28 15:36:15'),
(137, 'DATA Analyst', '2025-01-28 15:36:47', '2025-01-28 15:36:47');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `job_refno` longtext DEFAULT NULL,
  `heading` longtext DEFAULT NULL,
  `company_name` longtext DEFAULT NULL,
  `experience` longtext DEFAULT NULL,
  `annual_package` longtext DEFAULT NULL,
  `job_location` longtext DEFAULT NULL,
  `job_opening` longtext DEFAULT NULL,
  `posted_date` longtext DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `job_status` longtext DEFAULT NULL,
  `created_by` longtext NOT NULL,
  `created` timestamp NULL DEFAULT current_timestamp(),
  `updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `tokens`
--

CREATE TABLE `tokens` (
  `id` int(11) NOT NULL,
  `user_code` varchar(255) NOT NULL,
  `level` varchar(45) DEFAULT NULL,
  `token` varchar(64) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `user_code` varchar(45) DEFAULT NULL,
  `csrf` varchar(45) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `mobile` varchar(45) DEFAULT NULL,
  `password` varchar(45) DEFAULT NULL,
  `level` varchar(45) DEFAULT NULL,
  `active` text DEFAULT NULL,
  `show_comment_flag` varchar(45) DEFAULT NULL,
  `created` timestamp NULL DEFAULT current_timestamp(),
  `updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-
-- --------------------------------------------------------

--
-- Table structure for table `user_login`
--

CREATE TABLE `user_login` (
  `id` int(11) NOT NULL,
  `user_code` varchar(45) DEFAULT NULL,
  `user_name` varchar(45) DEFAULT NULL,
  `user_login` varchar(45) DEFAULT NULL,
  `user_login_time` varchar(45) DEFAULT NULL,
  `user_logout` varchar(45) DEFAULT NULL,
  `created` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;


-- Table structure for table `user_role`
--

CREATE TABLE `user_role` (
  `id` int(11) NOT NULL,
  `user_code` varchar(50) NOT NULL,
  `role` varchar(255) NOT NULL,
  `modules` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `whitelist`
--

CREATE TABLE `whitelist` (
  `id` int(11) NOT NULL,
  `user_code` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `whitelist`
--

INSERT INTO `whitelist` (`id`, `user_code`, `ip_address`, `created_at`) VALUES
(1, '#007', '62.235.51.143', '2024-11-09 01:15:22'),
(2, '#007', '2a02:a03f:6bcf:d701:c0d8:5339:23ee:3c57', '2024-11-09 01:15:22'),
(3, '#007', '2405:201:6038:a074:2927:6d90:73a3:2f17', '2024-11-09 01:15:22'),
(4, '#007', '49.36.168.198', '2024-11-09 01:15:22'),
(5, '#007', '49.36.170.61', '2024-11-09 09:38:54'),
(7, '#007', '109.133.117.249', '2024-11-11 14:29:01'),
(8, '#007', '109.140.158.222', '2024-11-11 14:29:01'),
(9, '#007', '109.139.45.232', '2024-11-12 17:47:22'),
(10, '#007', '109.138.58.28', '2024-11-15 15:25:06'),
(11, '#007', '109.142.162.220', '2024-11-15 15:25:06'),
(12, '#007', '178.145.233.178', '2024-11-15 17:06:18');

-- --------------------------------------------------------

--
-- Table structure for table `work_auth`
--

CREATE TABLE `work_auth` (
  `id` int(11) NOT NULL,
  `auth_status` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_auth`
--

INSERT INTO `work_auth` (`id`, `auth_status`, `created_at`, `updated_at`) VALUES
(3, 'Not Needed', '2024-09-11 21:40:36', '2024-09-11 21:40:36'),
(4, 'Needed', '2024-09-11 21:40:44', '2024-09-11 21:40:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_id` (`email_id`),
  ADD UNIQUE KEY `linkedin_UNIQUE` (`linkedin`),
  ADD KEY `idx_candidates_code` (`can_code`);

--
-- Indexes for table `candidates_edit_info`
--
ALTER TABLE `candidates_edit_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `candidate_addons`
--
ALTER TABLE `candidate_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_candidate_addons_code_date` (`can_code`,`updated_at`);

--
-- Indexes for table `candidate_assignments`
--
ALTER TABLE `candidate_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_candidate_assignments_code` (`can_code`);

--
-- Indexes for table `candidate_column`
--
ALTER TABLE `candidate_column`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `candidate_field_edits`
--
ALTER TABLE `candidate_field_edits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `can_skill_set`
--
ALTER TABLE `can_skill_set`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `skill` (`skill`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `queries`
--
ALTER TABLE `queries`
  ADD PRIMARY KEY (`query_id`);

--
-- Indexes for table `submittedcv`
--
ALTER TABLE `submittedcv`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tokens`
--
ALTER TABLE `tokens`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_code` (`user_code`);

--
-- Indexes for table `user_login`
--
ALTER TABLE `user_login`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_role`
--
ALTER TABLE `user_role`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `whitelist`
--
ALTER TABLE `whitelist`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `work_auth`
--
ALTER TABLE `work_auth`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5978;

--
-- AUTO_INCREMENT for table `candidates_edit_info`
--
ALTER TABLE `candidates_edit_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3510;

--
-- AUTO_INCREMENT for table `candidate_addons`
--
ALTER TABLE `candidate_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14153;

--
-- AUTO_INCREMENT for table `candidate_assignments`
--
ALTER TABLE `candidate_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7036;

--
-- AUTO_INCREMENT for table `candidate_column`
--
ALTER TABLE `candidate_column`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `candidate_field_edits`
--
ALTER TABLE `candidate_field_edits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13648;

--
-- AUTO_INCREMENT for table `can_skill_set`
--
ALTER TABLE `can_skill_set`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- AUTO_INCREMENT for table `queries`
--
ALTER TABLE `queries`
  MODIFY `query_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `submittedcv`
--
ALTER TABLE `submittedcv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;

--
-- AUTO_INCREMENT for table `tokens`
--
ALTER TABLE `tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6645;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `user_login`
--
ALTER TABLE `user_login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1722;

--
-- AUTO_INCREMENT for table `user_role`
--
ALTER TABLE `user_role`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=806;

--
-- AUTO_INCREMENT for table `whitelist`
--
ALTER TABLE `whitelist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `work_auth`
--
ALTER TABLE `work_auth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

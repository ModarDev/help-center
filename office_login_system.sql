-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2026 at 07:23 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `office_login_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_type` varchar(100) NOT NULL,
  `address_line` varchar(255) NOT NULL,
  `amphoe` varchar(100) NOT NULL,
  `room_no` varchar(100) NOT NULL,
  `subdistrict` varchar(100) NOT NULL,
  `district_area` varchar(100) NOT NULL,
  `road` varchar(150) NOT NULL,
  `province` varchar(100) NOT NULL,
  `tax_id` varchar(20) NOT NULL,
  `branch_no` varchar(50) NOT NULL,
  `office_phone` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `data_year` varchar(4) NOT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `branch_id`, `company_name`, `company_type`, `address_line`, `amphoe`, `room_no`, `subdistrict`, `district_area`, `road`, `province`, `tax_id`, `branch_no`, `office_phone`, `email`, `logo_path`, `data_year`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'BR2026-0001', 'บริษัท เอ พี เอ็ม ออโตโมบิล จำกัด (สำนักงานใหญ่)', 'บริษัท', '99/8', 'บางปะอิน', '-', 'บ้านกรด', '-', 'สายเอเซีย', 'พระนครศรีอยุธยา', '010000000000', '0', '0923967219', 'info@apmofficial.com', 'assets/images/logo/branches/branch_logo_20260513074644_5d180555.png', '2026', '123456', '123456', '2026-05-13 07:46:44', '2026-05-13 07:46:44'),
(2, 'BR2026-0002', 'บริษัท เอ พี เอ็ม คาร์มาร์ท จำกัด (สำนักงานใหญ่)', 'บริษัท', '99', 'บางปะอิน', '-', 'บ้านกรด', '-', 'เอเซีย', 'พระนครศรีอยุธยา', '0145536000127', '0', '035880900', 'info@apmgroup.com', 'assets/images/logo/branches/branch_logo_20260513083951_7697759a.jpg', '2026', 'panurut2547', 'panurut2547', '2026-05-13 08:39:51', '2026-05-13 08:40:17');

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `company_type` varchar(50) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `tax_id` varchar(20) NOT NULL,
  `trade_registration_no` varchar(20) NOT NULL,
  `branch_code` varchar(20) NOT NULL,
  `branch_name` varchar(255) NOT NULL,
  `address_no` varchar(20) NOT NULL,
  `moo` varchar(20) NOT NULL,
  `subdistrict` varchar(100) NOT NULL,
  `district` varchar(100) NOT NULL,
  `road` varchar(150) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(10) NOT NULL,
  `office_phone` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `header_logo_path` varchar(255) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `company_type`, `business_name`, `tax_id`, `trade_registration_no`, `branch_code`, `branch_name`, `address_no`, `moo`, `subdistrict`, `district`, `road`, `province`, `postal_code`, `office_phone`, `email`, `header_logo_path`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'บริษัท', 'เอพี เอ็ม ออโต้มอลล์ จำกัด', '0145537000350', '000000000', '01', 'สำนักงานใหญ่', '99/9', '3', 'บ้านกรด', 'บางปะอิน', 'สายเอเซีย', 'พระนครศรีอยุธยา', '13160', '035880909', 'panurutnoipol2547@gmail.com', 'assets/images/logo/company-settings/header_20260423164900_15de0809.png', '123456', '123456', '2026-04-23 14:37:50', '2026-04-23 16:26:01');

-- --------------------------------------------------------

--
-- Table structure for table `discord_webhook_settings`
--

CREATE TABLE `discord_webhook_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `webhook_key` varchar(50) NOT NULL,
  `webhook_url` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `discord_webhook_settings`
--

INSERT INTO `discord_webhook_settings` (`id`, `webhook_key`, `webhook_url`, `is_active`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'login', 'https://discord.com/api/webhooks/1504055535211647017/9UJ0_XJZ4udSKLBDPJ0bNxbcO6cYCvXUyoXxQ5icBBPNbWoQ-l9CRwjgCx3j9UdrQeU9', 1, 'panurut2547', 'panurut2547', '2026-05-13 09:38:06', '2026-05-13 09:46:27'),
(2, 'logout', 'https://discord.com/api/webhooks/1504050961730244608/9qIG4kDLMCeL-rVNuxGi6Dzj2SYvLIqbkbnTxW8gHxGd3KEvzwclxZAALSNJ8zjeKy_C', 1, 'panurut2547', 'panurut2547', '2026-05-13 09:38:06', '2026-05-13 09:46:27'),
(7, 'user_setup', 'https://discord.com/api/webhooks/1504057051116994630/sOGIFrbDtlCxvL2_lPYxXl_xij6ExmF50nt2iMDiznEZTq4cTGp38cTImYbtG2ek6jDy', 1, 'panurut2547', 'panurut2547', '2026-05-13 09:46:27', '2026-05-13 09:46:27');

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL,
  `request_no` varchar(25) NOT NULL,
  `request_ref_no` varchar(20) NOT NULL,
  `document_no` varchar(25) NOT NULL,
  `document_ref_no` varchar(20) NOT NULL,
  `transaction_date` date NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `employee_code` varchar(30) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_detail` text DEFAULT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `system_name` varchar(150) NOT NULL,
  `usage_level` varchar(150) NOT NULL,
  `access_levels` text NOT NULL,
  `access_other` varchar(255) DEFAULT NULL,
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_requests`
--

INSERT INTO `document_requests` (`id`, `request_no`, `request_ref_no`, `document_no`, `document_ref_no`, `transaction_date`, `first_name`, `last_name`, `employee_code`, `company_name`, `company_detail`, `contact_phone`, `email`, `system_name`, `usage_level`, `access_levels`, `access_other`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'FAP-TM29TEZQKJUGOHK', 'TN6CT4OPY8EDVRM', 'APP-THMQUT2Z7WW77I2', 'JUPE5XQTH0GSE8W', '2026-04-23', 'Panurut', 'Noipol', '97', 'APM Official', '', '0972697268', 'panurutnoipol2547@gmail.com', 'DMS', 'ธรรมดา', '[\"normal\"]', '', 'active', '123456', '123456', '2026-04-23 09:30:50', '2026-04-23 09:30:50'),
(2, 'FAP-8ESZ2EWH66ALSB7', 'CZPSF5GMT3J7D6S', 'APP-MK7YB3MKUOW3FBY', '4SNTY712XC77R32', '2026-04-23', 'Panurut', 'Noipol', '914206002192', 'บริษัท เอพี เอ็ม คาร์มาร์ท จำกัด', 'ทะเบียน: 0145536000127 | โทร: 035880900 | ที่อยู่: 99 | อำเภอ: บางปะอิน | ตำบล: บ้านกรด | ถนน: สายเอเซีย | ซอย: - | หมู่: 9 | จังหวัด: พระนครศรีอยุธยา | รหัสไปรษณีย์: 13160 | สาขา: สำนักงานใหญ่', '0972697268', 'panurutnoipol2547@gmail.com', 'ADS', 'CFO', '[\"other\"]', 'AUDIT', 'active', '123456', '123456', '2026-04-23 16:28:49', '2026-04-23 16:28:49');

-- --------------------------------------------------------

--
-- Table structure for table `document_request_companies`
--

CREATE TABLE `document_request_companies` (
  `id` int(11) NOT NULL,
  `reg_type` varchar(50) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `registration_no` varchar(50) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address_no` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `subdistrict` varchar(100) DEFAULT NULL,
  `road` varchar(100) DEFAULT NULL,
  `soi` varchar(100) DEFAULT NULL,
  `moo` varchar(50) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `branch_no` varchar(100) DEFAULT NULL,
  `company_detail` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_request_companies`
--

INSERT INTO `document_request_companies` (`id`, `reg_type`, `company_name`, `registration_no`, `phone`, `address_no`, `district`, `subdistrict`, `road`, `soi`, `moo`, `province`, `postcode`, `branch_no`, `company_detail`, `created_at`, `updated_at`) VALUES
(1, 'บริษัท', 'บริษัท เอพี เอ็ม คาร์มาร์ท จำกัด', '0145536000127', '035880900', '99', 'บางปะอิน', 'บ้านกรด', 'สายเอเซีย', '-', '9', 'พระนครศรีอยุธยา', '13160', 'สำนักงานใหญ่', 'ทะเบียน: 0145536000127 | โทร: 035880900 | ที่อยู่: 99 | อำเภอ: บางปะอิน | ตำบล: บ้านกรด | ถนน: สายเอเซีย | ซอย: - | หมู่: 9 | จังหวัด: พระนครศรีอยุธยา | รหัสไปรษณีย์: 13160 | สาขา: สำนักงานใหญ่', '2026-04-23 09:43:16', '2026-04-23 16:26:37');

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `login_time` timestamp NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_status` enum('success','failed') NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `login_time`, `ip_address`, `user_agent`, `login_status`, `failure_reason`) VALUES
(1, 'admin001', '2026-04-23 03:12:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(2, 'admin001', '2026-04-23 03:12:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(3, 'admin001', '2026-04-23 03:13:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'Invalid password'),
(4, 'admin001', '2026-04-23 03:13:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'Invalid password'),
(5, 'admin001', '2026-04-23 03:13:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'Invalid password'),
(6, 'mgr001', '2026-04-23 03:13:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(7, 'mgr001', '2026-04-23 03:14:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'Invalid password'),
(8, 'apmgroup', '2026-04-23 03:14:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User registration'),
(9, 'apmgroup', '2026-04-23 03:14:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(10, 'apmgroup', '2026-04-23 03:14:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(11, 'apmgroup', '2026-04-23 03:15:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User logout'),
(12, 'apmgroup', '2026-04-23 03:57:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(13, 'apmgroup', '2026-04-23 03:58:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(14, 'apmgroup', '2026-04-23 03:58:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(15, 'apmgroup', '2026-04-23 03:58:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(16, 'apmgroup', '2026-04-23 03:59:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(17, 'apmgroup', '2026-04-23 04:00:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(18, 'apmgroup', '2026-04-23 04:00:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(19, 'apmgroup', '2026-04-23 04:07:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(20, 'apmgroup', '2026-04-23 04:10:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(21, '123456', '2026-04-23 04:11:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User registration'),
(22, '123456', '2026-04-23 04:11:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(23, '123456', '2026-04-23 04:11:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(24, '123456', '2026-04-23 04:13:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User logout'),
(25, '123456', '2026-04-23 04:13:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(26, '123456', '2026-04-23 04:13:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(27, '123456', '2026-04-23 04:13:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User logout'),
(28, '123456', '2026-04-23 04:13:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(29, '123456', '2026-04-23 04:13:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(42, 'apmgroup', '2026-04-23 07:53:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(43, 'apmgroup', '2026-04-23 07:53:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'Invalid password'),
(44, 'apmgroup', '2026-04-23 07:53:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'Invalid password'),
(45, '123456', '2026-04-23 07:53:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(46, '123456', '2026-04-23 07:53:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(47, '123456', '2026-04-23 08:12:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User logout'),
(48, '789456', '2026-04-23 08:12:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(49, '789456', '2026-04-23 08:12:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'Invalid password'),
(50, '789456', '2026-04-23 08:12:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(51, '789456', '2026-04-23 08:12:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User logout'),
(52, '123456', '2026-04-23 08:12:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(53, '123456', '2026-04-23 08:12:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(54, '123456', '2026-04-23 08:26:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User logout'),
(56, '123456', '2026-04-23 08:27:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(57, '123456', '2026-04-23 08:27:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(58, '123456', '2026-04-23 08:34:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User logout'),
(59, '789456', '2026-04-23 08:35:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(60, '789456', '2026-04-23 08:35:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'Invalid password'),
(61, '789456', '2026-04-23 08:35:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(62, '789456', '2026-04-23 08:40:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', 'User logout'),
(64, '123456', '2026-04-23 08:40:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'failed', 'User ID found'),
(65, '123456', '2026-04-23 08:40:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', 'success', NULL),
(66, '123456', '2026-04-23 12:38:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'failed', 'User ID found'),
(67, '123456', '2026-04-23 12:38:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(68, '123456', '2026-05-07 12:33:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'failed', 'User ID found'),
(69, '123456', '2026-05-07 12:33:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(70, '123456', '2026-05-07 15:07:51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', 'User logout'),
(71, '123456', '2026-05-07 15:29:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'failed', 'User ID found'),
(72, '123456', '2026-05-07 15:29:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(73, '123456', '2026-05-07 15:29:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', 'User logout'),
(74, '123456', '2026-05-07 15:43:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'failed', 'User ID found'),
(75, '123456', '2026-05-07 15:43:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(76, '123456', '2026-05-12 11:22:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'failed', 'User ID found'),
(77, '123456', '2026-05-12 11:22:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'failed', 'Invalid password'),
(78, '123456', '2026-05-12 11:22:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(79, '123456', '2026-05-12 14:20:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'failed', 'Invalid password'),
(80, '123456', '2026-05-12 14:20:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(81, '123456', '2026-05-12 14:21:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(82, '123456', '2026-05-12 14:22:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(83, 'admin001', '2026-05-12 14:23:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'failed', 'Invalid password'),
(84, 'admin001', '2026-05-12 14:23:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'failed', 'Invalid password'),
(85, '123456', '2026-05-12 14:24:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(86, '123456', '2026-05-12 14:40:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(87, '123456', '2026-05-12 14:41:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(88, '123456', '2026-05-12 14:50:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(89, '123456', '2026-05-12 14:56:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', 'User logout'),
(90, 'admin011', '2026-05-12 14:56:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(91, 'admin011', '2026-05-12 15:36:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', 'User logout'),
(92, 'admin011', '2026-05-12 15:36:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'success', NULL),
(93, '123456', '2026-05-13 01:14:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(94, '123456', '2026-05-13 01:22:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(95, 'admin011', '2026-05-13 01:22:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(96, 'admin011', '2026-05-13 01:23:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(97, 'admin011', '2026-05-13 01:23:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(98, 'admin011', '2026-05-13 01:39:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(99, '123456', '2026-05-13 01:40:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(100, 'admin011', '2026-05-13 05:38:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(101, 'admin011', '2026-05-13 06:03:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(102, '123456', '2026-05-13 06:04:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(103, '123456', '2026-05-13 07:26:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(104, '123456', '2026-05-13 07:27:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(105, '123456', '2026-05-13 07:29:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(106, '123456', '2026-05-13 07:29:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(107, '123456', '2026-05-13 07:33:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'failed', 'Invalid password'),
(108, '123456', '2026-05-13 07:33:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(109, '123456', '2026-05-13 07:55:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(110, '123456', '2026-05-13 07:55:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(111, '123456', '2026-05-13 08:05:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(112, '123456', '2026-05-13 08:08:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(113, 'panurut2547', '2026-05-13 08:08:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(114, 'panurut2547', '2026-05-13 08:40:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(115, 'panurut2547', '2026-05-13 08:40:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(116, 'panurut2547', '2026-05-13 08:41:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(117, '123456', '2026-05-13 08:41:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'failed', 'Invalid password'),
(118, '123456', '2026-05-13 08:41:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(119, '123456', '2026-05-13 08:50:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(120, '123456', '2026-05-13 08:50:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(121, '123456', '2026-05-13 09:17:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(122, 'panurut2547', '2026-05-13 09:17:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'failed', 'Invalid password'),
(123, 'panurut2547', '2026-05-13 09:17:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(124, 'panurut2547', '2026-05-13 09:38:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(125, '123456', '2026-05-13 09:40:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(126, '123456', '2026-05-13 09:40:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(127, 'panurut2547', '2026-05-13 09:40:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(128, 'panurut2547', '2026-05-13 09:51:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', 'User logout'),
(129, '123456', '2026-05-13 09:51:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'success', NULL),
(130, '123456', '2026-05-13 11:39:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(131, '123456', '2026-05-13 11:40:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(132, 'panurut2547', '2026-05-13 11:40:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'failed', 'Invalid password'),
(133, 'panurut2547', '2026-05-13 11:40:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(134, 'panurut2547', '2026-05-13 11:43:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'failed', 'Invalid password'),
(135, 'panurut2547', '2026-05-13 11:43:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'failed', 'Invalid password'),
(136, 'panurut2547', '2026-05-13 11:43:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(137, 'panurut2547', '2026-05-13 11:43:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(138, '123456', '2026-05-13 11:43:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(139, '123456', '2026-05-13 12:09:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(140, '123456', '2026-05-13 12:10:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(141, '123456', '2026-05-13 12:10:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(142, '123456', '2026-05-13 12:10:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(143, '123456', '2026-05-13 12:10:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(144, '123456', '2026-05-13 12:10:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(145, '123456', '2026-05-13 12:10:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(146, '123456', '2026-05-13 12:12:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(147, '123456', '2026-05-13 12:30:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(148, '123456', '2026-05-13 12:30:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(149, '123456', '2026-05-13 12:30:45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(150, '123456', '2026-05-13 12:31:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'failed', 'Invalid password'),
(151, '123456', '2026-05-13 12:31:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(152, '123456', '2026-05-13 12:39:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(153, 'salemanager', '2026-05-13 12:39:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(154, 'salemanager', '2026-05-13 12:40:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(155, 'salemanager', '2026-05-13 12:40:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(156, 'salemanager', '2026-05-13 13:31:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(157, '123456', '2026-05-13 13:31:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(158, '123456', '2026-05-13 13:31:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(159, '123456', '2026-05-13 13:32:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(160, '123456', '2026-05-13 13:53:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(161, 'apmgroup', '2026-05-13 13:54:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(162, 'apmgroup', '2026-05-13 13:54:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(163, 'apmgroup', '2026-05-13 13:54:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(164, 'panurut2547', '2026-05-13 14:28:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(165, 'salemanager', '2026-05-13 14:28:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(166, 'salemanager', '2026-05-13 14:50:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(167, 'panurut2547', '2026-05-13 14:51:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'failed', 'Invalid password'),
(168, 'panurut2547', '2026-05-13 14:51:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(169, 'panurut2547', '2026-05-13 14:51:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(170, 'apmgroup', '2026-05-13 14:51:23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(171, 'apmgroup', '2026-05-13 14:51:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(172, 'salemanager', '2026-05-13 14:51:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(173, 'salemanager', '2026-05-13 14:51:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(174, 'salemanager', '2026-05-13 14:52:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(175, 'salemanager', '2026-05-13 14:52:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(176, '123456', '2026-05-13 14:52:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(177, '123456', '2026-05-13 14:52:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(178, 'panurut2547', '2026-05-13 14:52:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(179, '123456', '2026-05-13 15:01:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(180, 'salemanager', '2026-05-13 16:28:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(181, '123456', '2026-05-13 16:45:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(182, '123456', '2026-05-13 16:55:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(183, 'salemanager', '2026-05-13 16:55:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(184, 'salemanager', '2026-05-13 17:15:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout'),
(185, '123456', '2026-05-13 17:15:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', NULL),
(186, '123456', '2026-05-13 17:16:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'User logout');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_key` varchar(50) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `dashboard_path` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_key`, `role_name`, `dashboard_path`, `is_active`, `created_at`, `updated_at`) VALUES
('admin', 'Administrator', '../app/admin/menuadmin', 1, '2026-05-12 12:01:26', '2026-05-13 01:44:32'),
('employee', 'Employee', '../app/employee/menuemployee', 1, '2026-05-12 12:01:26', '2026-05-12 12:01:26'),
('manager', 'Manager', '../app/manager/menumanager', 1, '2026-05-12 12:01:26', '2026-05-12 12:01:26'),
('sales_manager', 'Sale Manager', '../app/sales_manager/page_sell_manager', 1, '2026-05-13 12:20:47', '2026-05-13 12:20:47'),
('sell_car', 'Sell Car', '../app/sell/pagesell', 1, '2026-05-13 09:04:24', '2026-05-13 09:04:24'),
('system_admin', 'System Admin', '../app/SYSTEM/index', 1, '2026-05-12 14:55:59', '2026-05-12 14:55:59');

-- --------------------------------------------------------

--
-- Table structure for table `sales_customer_records`
--

CREATE TABLE `sales_customer_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `owner_user_id` varchar(50) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(30) NOT NULL,
  `customer_line` varchar(80) DEFAULT NULL,
  `customer_province` varchar(100) DEFAULT NULL,
  `lead_source` enum('facebook','walk_in','refer','line','website','other') NOT NULL DEFAULT 'other',
  `interested_model` varchar(150) NOT NULL,
  `budget_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `down_payment` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monthly_budget` decimal(12,2) NOT NULL DEFAULT 0.00,
  `target_purchase_date` date DEFAULT NULL,
  `pipeline_status` enum('new_lead','contacted','interested','test_drive','quotation','booking','delivered','lost') NOT NULL DEFAULT 'new_lead',
  `last_contact_at` datetime DEFAULT NULL,
  `next_followup_at` datetime DEFAULT NULL,
  `next_followup_note` varchar(255) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approval_note` varchar(255) DEFAULT NULL,
  `approved_by` varchar(50) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` varchar(50) NOT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_customer_records`
--

INSERT INTO `sales_customer_records` (`id`, `branch_id`, `group_id`, `owner_user_id`, `customer_name`, `customer_phone`, `customer_line`, `customer_province`, `lead_source`, `interested_model`, `budget_amount`, `down_payment`, `monthly_budget`, `target_purchase_date`, `pipeline_status`, `last_contact_at`, `next_followup_at`, `next_followup_note`, `approval_status`, `approval_note`, `approved_by`, `approved_at`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'BR2026-0002', 1, '123456', 'มานะ ทองดี', '0892184835', 'yyus2547', 'พระนครศรีอยุธยา', 'other', 'MAZDA 5E', 790000.00, 350000.00, 23500.00, '2026-07-25', 'new_lead', '2026-05-13 23:13:32', '2026-05-16 23:13:00', 'สอบถามเงินดาว', 'approved', NULL, 'salemanager', '2026-05-13 23:33:42', '123456', 'salemanager', '2026-05-13 15:33:52', '2026-05-13 16:33:42');

-- --------------------------------------------------------

--
-- Table structure for table `sales_customer_sla_alerts`
--

CREATE TABLE `sales_customer_sla_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `owner_user_id` varchar(50) NOT NULL,
  `alert_type` enum('followup_overdue') NOT NULL DEFAULT 'followup_overdue',
  `severity` enum('warning','critical','breach') NOT NULL DEFAULT 'warning',
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `due_at` datetime NOT NULL,
  `triggered_at` datetime NOT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` varchar(50) DEFAULT NULL,
  `last_seen_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_customer_sla_assignments`
--

CREATE TABLE `sales_customer_sla_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `from_owner_user_id` varchar(50) NOT NULL,
  `to_owner_user_id` varchar(50) NOT NULL,
  `assign_reason` varchar(500) NOT NULL,
  `assigned_by` varchar(50) NOT NULL,
  `assigned_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_customer_timeline`
--

CREATE TABLE `sales_customer_timeline` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `actor_user_id` varchar(50) NOT NULL,
  `activity_type` enum('call','chat','meeting','test_drive','note') NOT NULL DEFAULT 'note',
  `activity_action` varchar(255) DEFAULT NULL,
  `discussion_topic` varchar(255) DEFAULT NULL,
  `activity_note` varchar(500) NOT NULL,
  `next_followup_at` datetime DEFAULT NULL,
  `next_followup_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_customer_timeline`
--

INSERT INTO `sales_customer_timeline` (`id`, `customer_id`, `branch_id`, `group_id`, `actor_user_id`, `activity_type`, `activity_action`, `discussion_topic`, `activity_note`, `next_followup_at`, `next_followup_note`, `created_at`) VALUES
(1, 1, 'BR2026-0002', 1, '123456', 'note', NULL, NULL, 'นัดเทสไดร์ฟ', '2026-05-13 22:33:00', NULL, '2026-05-13 15:33:52'),
(2, 1, 'BR2026-0002', 1, '123456', 'call', 'โทร', 'สอบถามเงินดาว', 'กกกก', '2026-05-16 23:13:00', 'สอบถามเงินดาว', '2026-05-13 16:13:32');

-- --------------------------------------------------------

--
-- Table structure for table `sales_followup_digest_logs`
--

CREATE TABLE `sales_followup_digest_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `digest_date` date NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `manager_user_id` varchar(50) NOT NULL,
  `overdue_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `critical_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `high_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_group_invites`
--

CREATE TABLE `sales_group_invites` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `group_name` varchar(150) NOT NULL,
  `invite_code` varchar(40) NOT NULL,
  `manager_user_id` varchar(50) NOT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_group_invites`
--

INSERT INTO `sales_group_invites` (`id`, `branch_id`, `group_name`, `invite_code`, `manager_user_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'BR2026-0002', 'Mazda', 'GROUP-APM-901939', 'salemanager', 'active', '2026-05-13 13:08:42', '2026-05-13 14:40:51');

-- --------------------------------------------------------

--
-- Table structure for table `sales_group_members`
--

CREATE TABLE `sales_group_members` (
  `id` int(10) UNSIGNED NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `member_user_id` varchar(50) NOT NULL,
  `member_title` varchar(100) NOT NULL DEFAULT 'Sales',
  `status` enum('pending','active','suspended') NOT NULL DEFAULT 'active',
  `created_by` varchar(50) NOT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_group_members`
--

INSERT INTO `sales_group_members` (`id`, `group_id`, `branch_id`, `member_user_id`, `member_title`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'BR2026-0002', '123456', 'Sales', 'active', '123456', '123456', '2026-05-13 13:32:50', '2026-05-13 13:32:50'),
(2, 1, 'BR2026-0002', 'apmgroup', 'Sales', 'active', 'apmgroup', 'salemanager', '2026-05-13 14:51:29', '2026-05-13 14:52:16');

-- --------------------------------------------------------

--
-- Table structure for table `sales_group_member_logs`
--

CREATE TABLE `sales_group_member_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `group_id` int(10) UNSIGNED DEFAULT NULL,
  `member_user_id` varchar(50) NOT NULL,
  `actor_user_id` varchar(50) NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `event_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_group_member_logs`
--

INSERT INTO `sales_group_member_logs` (`id`, `branch_id`, `group_id`, `member_user_id`, `actor_user_id`, `event_type`, `event_note`, `created_at`) VALUES
(1, 'BR2026-0002', 1, '123456', '123456', 'join_by_invite', 'Join via invite code GROUP-APM-901939', '2026-05-13 13:32:50'),
(2, 'BR2026-0002', 1, 'apmgroup', 'apmgroup', 'request_join_by_invite', 'Request join via invite code GROUP-APM-901939', '2026-05-13 14:51:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `company` varchar(100) NOT NULL,
  `user_role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'employee',
  `password_hash` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_id`, `first_name`, `last_name`, `phone`, `email`, `position`, `department`, `company`, `user_role`, `password_hash`, `profile_image`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'admin001', 'ผู้ดูแล', 'ระบบ', '0800000000', 'admin@company.com', 'System Administrator', 'IT', 'Office Plus', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '2026-04-23 02:21:23', '2026-04-23 02:21:23', 1),
(2, 'emp001', 'พนักงาน', 'ทดสอบ', '0811111111', 'employee@company.com', 'Staff', 'Sales', 'Office Plus', 'employee', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '2026-04-23 02:21:23', '2026-04-23 02:21:23', 1),
(3, 'mgr001', 'หัวหน้า', 'ทดสอบ', '0822222222', 'manager@company.com', 'Manager', 'Marketing', 'Office Plus', 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '2026-04-23 02:21:23', '2026-04-23 02:21:23', 1),
(4, 'apmgroup', 'apmgroup', 'testadmin', '0988542569', 'APM@gmail.com', 'IT', 'ห้องธุรการ', 'APM GROUP THALIAND', 'sell_car', '$2y$10$b1c7aB.Nt.YhiPn1Z5OWkeEh4.iJJKBwWVuEgw/6xVj0CAVFjbIs.', NULL, '2026-04-23 03:14:44', '2026-05-13 09:05:28', 1),
(5, '123456', 'Panurut', 'Noipol', '0353322058', 'info@apmofficial.com', 'OPA', 'MAIZ', 'APM GROUP', 'sell_car', '$2y$10$O8dLhbYL/QV.HC2YeRf1k.UyhAipLqXV6Y6qliVZPFAsDWmfPm2FC', 'assets/images/profiles/profile_123456_20260507154554_e78ca800.webp', '2026-04-23 04:11:27', '2026-05-13 09:51:31', 1),
(6, '789456', 'asdfdfhRSFDH', '-', '0943623006', 'jayrit42@gmail.com', 'Staff', 'General', 'Office Plus', 'employee', '$2y$10$0cm9Lt5LoLK7cE47Grzx.uEEegWZw09eYkywZtkDuRiKRIArQQ5b.', NULL, '2026-04-23 08:12:14', '2026-05-13 09:47:12', 1),
(7, 'admin011', 'Panurutu', '-', '0988542568', 'panurut_work@hotmail.com', 'Staff', 'General', 'Office Plus', 'system_admin', '$2y$10$hnhF1DjK7GDzT93yKkDArO5ArFbjYAHqJtRo59uqDDPstOWlQy3A6', 'assets/images/profiles/profile_admin011_20260512173625_86fdfaa6.png', '2026-05-12 14:56:39', '2026-05-12 15:36:25', 1),
(8, 'panurut2547', 'Panurut', 'Noiphon', '0972697268', 'panurut_official@charoenkit.com', 'Staff', 'General', 'Office Plus', 'admin', '$2y$10$g84DxMlnMEdWN1B5NigZN.pnABbXRx0qtVwDxr54dpYD01ja21tvG', 'assets/images/profiles/profile_panurut2547_20260513080902_72cb8b63.jpg', '2026-05-13 08:08:23', '2026-05-13 08:09:02', 1),
(9, 'salemanager', 'Sale', 'Manager Mazda', '0892184836', 'salemanager@apmofficial.com', 'Staff', 'General', 'Office Plus', 'sales_manager', '$2y$10$BWQ2yJoRMPIAciJdYgobNeBS7gbvd43dTNsRAKhOaaUIuAUwUglUu', 'assets/images/profiles/profile_salemanager_20260513144052_d97c9ca4.webp', '2026-05-13 12:39:32', '2026-05-13 12:40:52', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_branch_access`
--

CREATE TABLE `user_branch_access` (
  `user_id` varchar(50) NOT NULL,
  `branch_id` varchar(30) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_branch_access`
--

INSERT INTO `user_branch_access` (`user_id`, `branch_id`, `created_at`) VALUES
('123456', 'BR2026-0001', '2026-05-13 13:32:15'),
('123456', 'BR2026-0002', '2026-05-13 13:32:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`session_id`, `user_id`, `created_at`, `expires_at`, `ip_address`, `user_agent`) VALUES
('1fugtfff0v4fr3860pm4sgdtd7', 'salemanager', '2026-05-13 16:28:30', '2026-05-13 17:44:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
('edlrutao2g8598jjku28kvmeq0', '123456', '2026-05-13 15:01:49', '2026-05-13 17:29:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_id` (`branch_id`),
  ADD KEY `idx_data_year` (`data_year`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `discord_webhook_settings`
--
ALTER TABLE `discord_webhook_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `webhook_key` (`webhook_key`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_no` (`request_no`),
  ADD UNIQUE KEY `document_no` (`document_no`);

--
-- Indexes for table `document_request_companies`
--
ALTER TABLE `document_request_companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_name` (`company_name`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_key`);

--
-- Indexes for table `sales_customer_records`
--
ALTER TABLE `sales_customer_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scr_branch_group` (`branch_id`,`group_id`),
  ADD KEY `idx_scr_owner_branch` (`owner_user_id`,`branch_id`),
  ADD KEY `idx_scr_pipeline` (`pipeline_status`),
  ADD KEY `idx_scr_approval` (`approval_status`),
  ADD KEY `idx_scr_next_followup` (`next_followup_at`),
  ADD KEY `idx_scr_branch_group_owner` (`branch_id`,`group_id`,`owner_user_id`),
  ADD KEY `idx_scr_branch_group_updated` (`branch_id`,`group_id`,`updated_at`),
  ADD KEY `idx_scr_branch_group_followup` (`branch_id`,`group_id`,`next_followup_at`),
  ADD KEY `idx_scr_branch_owner_updated` (`branch_id`,`owner_user_id`,`updated_at`),
  ADD KEY `idx_scr_branch_group_phone` (`branch_id`,`group_id`,`customer_phone`),
  ADD KEY `idx_scr_branch_group_line` (`branch_id`,`group_id`,`customer_line`);

--
-- Indexes for table `sales_customer_sla_alerts`
--
ALTER TABLE `sales_customer_sla_alerts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_sales_customer_sla_customer_type` (`customer_id`,`alert_type`),
  ADD KEY `idx_sales_customer_sla_scope_status` (`branch_id`,`group_id`,`status`),
  ADD KEY `idx_sales_customer_sla_owner_status` (`owner_user_id`,`status`);

--
-- Indexes for table `sales_customer_sla_assignments`
--
ALTER TABLE `sales_customer_sla_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scsa_customer_assigned` (`customer_id`,`assigned_at`),
  ADD KEY `idx_scsa_branch_group` (`branch_id`,`group_id`),
  ADD KEY `idx_scsa_to_owner` (`to_owner_user_id`,`assigned_at`);

--
-- Indexes for table `sales_customer_timeline`
--
ALTER TABLE `sales_customer_timeline`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sct_customer_created` (`customer_id`,`created_at`),
  ADD KEY `idx_sct_branch_group` (`branch_id`,`group_id`),
  ADD KEY `idx_sct_customer_id_id` (`customer_id`,`id`);

--
-- Indexes for table `sales_followup_digest_logs`
--
ALTER TABLE `sales_followup_digest_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_sales_followup_digest` (`digest_date`,`branch_id`,`manager_user_id`),
  ADD KEY `idx_sales_followup_digest_manager` (`manager_user_id`,`digest_date`);

--
-- Indexes for table `sales_group_invites`
--
ALTER TABLE `sales_group_invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_sales_group_invite_code` (`invite_code`),
  ADD KEY `idx_sales_group_manager_branch` (`manager_user_id`,`branch_id`),
  ADD KEY `idx_sales_group_branch_status` (`branch_id`,`status`);

--
-- Indexes for table `sales_group_members`
--
ALTER TABLE `sales_group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_sales_group_member` (`group_id`,`member_user_id`),
  ADD KEY `idx_sales_group_member_branch` (`branch_id`,`member_user_id`);

--
-- Indexes for table `sales_group_member_logs`
--
ALTER TABLE `sales_group_member_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sgml_branch_group` (`branch_id`,`group_id`),
  ADD KEY `idx_sgml_member` (`member_user_id`),
  ADD KEY `idx_sgml_created` (`created_at`),
  ADD KEY `fk_sales_group_member_logs_group` (`group_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_users_role` (`user_role`);

--
-- Indexes for table `user_branch_access`
--
ALTER TABLE `user_branch_access`
  ADD PRIMARY KEY (`user_id`,`branch_id`),
  ADD KEY `idx_uba_branch` (`branch_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `discord_webhook_settings`
--
ALTER TABLE `discord_webhook_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `document_request_companies`
--
ALTER TABLE `document_request_companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=187;

--
-- AUTO_INCREMENT for table `sales_customer_records`
--
ALTER TABLE `sales_customer_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sales_customer_sla_alerts`
--
ALTER TABLE `sales_customer_sla_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_customer_sla_assignments`
--
ALTER TABLE `sales_customer_sla_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_customer_timeline`
--
ALTER TABLE `sales_customer_timeline`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales_followup_digest_logs`
--
ALTER TABLE `sales_followup_digest_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_group_invites`
--
ALTER TABLE `sales_group_invites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sales_group_members`
--
ALTER TABLE `sales_group_members`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales_group_member_logs`
--
ALTER TABLE `sales_group_member_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `sales_customer_sla_alerts`
--
ALTER TABLE `sales_customer_sla_alerts`
  ADD CONSTRAINT `fk_sales_customer_sla_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customer_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_customer_sla_assignments`
--
ALTER TABLE `sales_customer_sla_assignments`
  ADD CONSTRAINT `fk_sales_customer_sla_assign_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customer_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_customer_timeline`
--
ALTER TABLE `sales_customer_timeline`
  ADD CONSTRAINT `fk_sales_customer_timeline_customer` FOREIGN KEY (`customer_id`) REFERENCES `sales_customer_records` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_group_members`
--
ALTER TABLE `sales_group_members`
  ADD CONSTRAINT `fk_sales_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `sales_group_invites` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_group_member_logs`
--
ALTER TABLE `sales_group_member_logs`
  ADD CONSTRAINT `fk_sales_group_member_logs_group` FOREIGN KEY (`group_id`) REFERENCES `sales_group_invites` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`user_role`) REFERENCES `roles` (`role_key`) ON UPDATE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

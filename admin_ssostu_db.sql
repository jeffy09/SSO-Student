-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 24, 2025 at 11:10 AM
-- Server version: 5.7.31
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `admin_ssostu`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `access_token` text COLLATE utf8mb4_unicode_ci,
  `refresh_token` text COLLATE utf8mb4_unicode_ci,
  `token_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `name`, `email`, `google_id`, `last_login`, `created_at`, `updated_at`, `access_token`, `refresh_token`, `token_expires_at`) VALUES
(1, 'admin', '$2y$10$UuYnJAllDHOpRlcgHlUWme1Izyg4TlD8y80nPzz12ss0JP/6unI7m', 'ผู้ดูแลระบบ', 'jetsada.ta@mbu.ac.th', NULL, '2025-05-22 13:45:23', '2025-05-21 16:05:35', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_data` text COLLATE utf8mb4_unicode_ci,
  `new_data` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `old_data`, `new_data`, `created_at`) VALUES
(1, 1, 'ยกเลิกการเชื่อมต่อ Google: ผู้ดูแลระบบ', NULL, NULL, '2025-05-22 08:41:06'),
(2, 1, 'ยกเลิกการเชื่อมต่อ Google: ผู้ดูแลระบบ', NULL, NULL, '2025-05-22 08:41:30'),
(3, 1, 'ยกเลิกการเชื่อมต่อ Google: ผู้ดูแลระบบ', NULL, NULL, '2025-05-22 08:58:06'),
(4, 1, 'ยกเลิกการเชื่อมต่อ Google: ผู้ดูแลระบบ', NULL, NULL, '2025-05-22 08:58:17'),
(5, 1, 'แก้ไขข้อมูลนักศึกษา: 631001003 - มานะ มานี', '{\"id\":3,\"student_id\":\"631001003\",\"id_card\":\"1234567890125\",\"firstname\":\"\\u0e21\\u0e32\\u0e19\\u0e30\",\"lastname\":\"\\u0e21\\u0e32\\u0e19\\u0e35\",\"email\":\"mana@example.com\",\"phone\":\"0654321987\",\"faculty\":\"\\u0e1a\\u0e23\\u0e34\\u0e2b\\u0e32\\u0e23\\u0e18\\u0e38\\u0e23\\u0e01\\u0e34\\u0e08\",\"department\":\"\\u0e01\\u0e32\\u0e23\\u0e08\\u0e31\\u0e14\\u0e01\\u0e32\\u0e23\",\"address\":null,\"google_id\":null,\"first_login\":1,\"created_at\":\"2025-05-21 16:05:35\",\"updated_at\":null}', '{\"firstname\":\"\\u0e21\\u0e32\\u0e19\\u0e30\",\"lastname\":\"\\u0e21\\u0e32\\u0e19\\u0e35\",\"email\":\"mana@example.co.th\",\"phone\":\"0654321987\",\"faculty\":\"\\u0e1a\\u0e23\\u0e34\\u0e2b\\u0e32\\u0e23\\u0e18\\u0e38\\u0e23\\u0e01\\u0e34\\u0e08\",\"department\":\"\\u0e01\\u0e32\\u0e23\\u0e08\\u0e31\\u0e14\\u0e01\\u0e32\\u0e23\",\"address\":\"\"}', '2025-05-22 08:59:12'),
(6, 1, 'ยกเลิกการเชื่อมต่อ Google: 631001001', NULL, NULL, '2025-05-22 09:00:16'),
(7, 1, 'แก้ไขข้อมูลนักศึกษา: 631001001 - สมชาย รักเรียน', '{\"id\":1,\"student_id\":\"631001001\",\"id_card\":\"1234567890123\",\"firstname\":\"\\u0e2a\\u0e21\\u0e0a\\u0e32\\u0e22\",\"lastname\":\"\\u0e23\\u0e31\\u0e01\\u0e40\\u0e23\\u0e35\\u0e22\\u0e19\",\"email\":\"jetsada.ta@mbu.ac.th\",\"phone\":\"0812345671\",\"faculty\":\"\\u0e27\\u0e34\\u0e28\\u0e27\\u0e01\\u0e23\\u0e23\\u0e21\\u0e28\\u0e32\\u0e2a\\u0e15\\u0e23\\u0e4c\",\"department\":\"\\u0e27\\u0e34\\u0e28\\u0e27\\u0e01\\u0e23\\u0e23\\u0e21\\u0e04\\u0e2d\\u0e21\\u0e1e\\u0e34\\u0e27\\u0e40\\u0e15\\u0e2d\\u0e23\\u0e4c\",\"address\":\"\",\"google_id\":null,\"first_login\":0,\"created_at\":\"2025-05-21 16:05:35\",\"updated_at\":null}', '{\"firstname\":\"\\u0e2a\\u0e21\\u0e0a\\u0e32\\u0e22\",\"lastname\":\"\\u0e23\\u0e31\\u0e01\\u0e40\\u0e23\\u0e35\\u0e22\\u0e19\",\"email\":\"jetsada.ta@mbu.ac.th\",\"phone\":\"081-234-5671\",\"faculty\":\"\\u0e27\\u0e34\\u0e28\\u0e27\\u0e01\\u0e23\\u0e23\\u0e21\\u0e28\\u0e32\\u0e2a\\u0e15\\u0e23\\u0e4c\",\"department\":\"\\u0e27\\u0e34\\u0e28\\u0e27\\u0e01\\u0e23\\u0e23\\u0e21\\u0e04\\u0e2d\\u0e21\\u0e1e\\u0e34\\u0e27\\u0e40\\u0e15\\u0e2d\\u0e23\\u0e4c\",\"address\":\"\"}', '2025-05-22 09:23:47'),
(8, 1, 'เพิ่มนักศึกษาใหม่: 65000004 - ดีใจ ใจดี', NULL, NULL, '2025-05-22 09:24:39'),
(9, 1, 'นำเข้าข้อมูลนักศึกษาแบบกลุ่ม: สำเร็จ 0 รายการ จากทั้งหมด 3 รายการ', NULL, NULL, '2025-05-22 13:20:08'),
(10, 1, 'นำเข้าข้อมูลนักศึกษาแบบกลุ่ม: สำเร็จ 3 รายการ จากทั้งหมด 3 รายการ', NULL, NULL, '2025-05-22 13:21:07'),
(11, 1, 'ยกเลิกการเชื่อมต่อ Google: ผู้ดูแลระบบ', NULL, NULL, '2025-05-22 13:49:21');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_card` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firstname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faculty` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `google_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_login` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `access_token` text COLLATE utf8mb4_unicode_ci,
  `refresh_token` text COLLATE utf8mb4_unicode_ci,
  `token_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `id_card`, `password_hash`, `firstname`, `lastname`, `email`, `phone`, `faculty`, `department`, `address`, `google_id`, `first_login`, `created_at`, `updated_at`, `access_token`, `refresh_token`, `token_expires_at`) VALUES
(1, '631001001', '1234567890123', NULL, 'สมชาย', 'รักเรียน', 'somchai.ruk@student.mbu.ac.th', '081-234-5672', 'วิศวกรรมศาสตร์', 'วิศวกรรมคอมพิวเตอร์', NULL, NULL, 1, '2025-05-21 16:05:35', NULL, NULL, NULL, NULL),
(2, '631001002', '1234567890124', NULL, 'สมหญิง', 'ใจดี', 'somying@example.com', '0898765432', 'วิทยาศาสตร์', 'วิทยาการคอมพิวเตอร์', NULL, NULL, 1, '2025-05-21 16:05:35', NULL, NULL, NULL, NULL),
(3, '631001003', '1234567890125', NULL, 'มานะ', 'มานี', 'mana@example.co.th', '0654321987', 'บริหารธุรกิจ', 'การจัดการ', '', NULL, 1, '2025-05-21 16:05:35', '2025-05-22 08:59:12', NULL, NULL, NULL),
(4, '65000004', '123123123123', NULL, 'ดีใจ', 'ใจดี', 'natnaree.ta@mbu.ac.th', '0654321987', 'วิศวกรรมศาสตร์', 'การจัดการ', '', NULL, 1, '2025-05-22 09:24:39', NULL, NULL, NULL, NULL),
(5, '631234567', '1234567890126', NULL, 'สมชาย', 'มีสุข', 'somchai@email.com', '0812345678', 'วิศวกรรมศาสตร์', 'วิศวกรรมคอมพิวเตอร์', 'กรุงเทพฯ', NULL, 1, '2025-05-22 13:21:07', NULL, NULL, NULL, NULL),
(6, '631234568', '1234567890127', NULL, 'สมหญิง', 'จริงใจ', 'somying@email.com', '0898765432', 'บริหารธุรกิจ', 'การจัดการ', 'กรุงเทพฯ', NULL, 1, '2025-05-22 13:21:07', NULL, NULL, NULL, NULL),
(7, '631234569', '1234567890128', NULL, 'สมปอง', 'ใจดี', 'sompong@email.com', '0876543210', 'ครุศาสตร์', 'การศึกษา', 'นนทบุรี', NULL, 1, '2025-05-22 13:21:07', NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

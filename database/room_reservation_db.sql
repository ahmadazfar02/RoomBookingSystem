-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 14, 2025 at 03:19 PM
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
-- Database: `room_reservation_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `booking_id`, `action`, `note`, `ip_address`, `created_at`) VALUES
(11, 4, 47, 'reject', 'full', '::1', '2025-11-25 23:21:43'),
(12, 4, 48, 'reject', 'full', '::1', '2025-11-25 23:21:43'),
(13, 4, 39, 'delete', 'Deleted booking via admin_timetable', '::1', '2025-11-26 17:31:30'),
(14, 4, 40, 'delete', 'Deleted booking via admin_timetable', '::1', '2025-11-26 17:31:33'),
(15, 4, 42, 'delete', 'Admin deleted booking', '::1', '2025-11-26 22:46:46'),
(16, 4, 50, 'create', 'Admin created booking (ADM-4BC85FB897)', '::1', '2025-11-26 23:23:30'),
(17, 4, 51, 'create', 'Admin created booking (ADM-06F89F89BD)', '::1', '2025-11-26 23:23:30'),
(18, 4, 52, 'create', 'Admin created booking (ADM-DA9A22817A)', '::1', '2025-11-26 23:23:30'),
(19, 4, 53, 'create', 'Created (ADM-3A63B80B82)', '::1', '2025-11-27 00:17:40'),
(20, 4, 54, 'create', 'Created (ADM-93F8AC6FA5)', '::1', '2025-11-27 00:17:40'),
(21, 4, 54, 'delete', 'Deleted via admin_timetable', '::1', '2025-11-27 14:05:46'),
(22, 4, 55, 'create', 'Created (ADM-08FE501F09)', '::1', '2025-11-27 14:06:34'),
(23, 4, 56, 'create', 'Created (ADM-3A1BD89C04)', '::1', '2025-11-27 14:06:34'),
(24, 4, 57, 'create', 'Created (ADM-9081CB2FC9)', '::1', '2025-11-27 14:06:34'),
(25, 4, 58, 'create', 'Created (ADM-232A285382)', '::1', '2025-11-27 14:06:34'),
(26, 4, 59, 'create', 'Created (ADM-68D0368952)', '::1', '2025-11-27 14:06:34'),
(27, 4, 60, 'create', 'Created (ADM-9A7D7C7093)', '::1', '2025-11-27 14:06:34'),
(28, 4, 61, 'create', 'Created (ADM-43992408FB)', '::1', '2025-11-27 14:06:34'),
(29, 5, 64, 'create', 'Created (ADM-9EE7B85F1F)', '::1', '2025-12-05 00:07:10'),
(30, 5, 65, 'create', 'Created (ADM-18F475DBFE)', '::1', '2025-12-05 00:07:10'),
(31, 5, 65, 'delete', 'Deleted via admin_timetable', '::1', '2025-12-05 00:09:53'),
(32, 5, 64, 'delete', 'Deleted via admin_timetable', '::1', '2025-12-05 00:09:56'),
(33, 5, 62, 'approve', 'Approved | Ticket: B0062', '::1', '2025-12-05 00:12:38'),
(34, 5, 63, 'approve', 'Approved | Ticket: B0063', '::1', '2025-12-05 00:12:38'),
(35, 5, 66, 'approve', 'Approved | Ticket: B0066', '::1', '2025-12-05 00:22:31'),
(36, 5, 67, 'approve', 'Approved | Ticket: B0067', '::1', '2025-12-05 00:22:31'),
(37, 5, 68, 'reject', 'Test', '::1', '2025-12-05 00:27:24'),
(38, 5, 69, 'reject', 'Test', '::1', '2025-12-05 00:27:24'),
(39, 5, 70, 'reject', 'Test', '::1', '2025-12-05 00:27:24'),
(40, 5, 73, 'approve', 'Approved | Ticket: B0073', '::1', '2025-12-08 18:25:27'),
(41, 5, 74, 'approve', 'Approved | Ticket: B0074', '::1', '2025-12-08 18:25:27'),
(42, 5, 71, 'approve', 'Approved | Ticket: B0071', '::1', '2025-12-08 18:25:30'),
(43, 5, 72, 'approve', 'Approved | Ticket: B0072', '::1', '2025-12-08 18:25:30'),
(44, 5, 75, 'approve', 'Approved | Ticket: B0075', '::1', '2025-12-08 18:33:51'),
(45, 5, 76, 'approve', 'Approved | Ticket: B0076', '::1', '2025-12-08 18:33:51'),
(46, 5, 77, 'approve', 'Approved | Ticket: B0077', '::1', '2025-12-08 18:39:21'),
(47, 5, 78, 'approve', 'Approved | Ticket: B0078', '::1', '2025-12-08 18:39:21'),
(48, 5, 81, 'approve', 'Approved | Ticket: B0081', '::1', '2025-12-08 18:41:05'),
(49, 5, 82, 'approve', 'Approved | Ticket: B0082', '::1', '2025-12-08 18:41:05'),
(50, 5, 79, 'approve', 'Approved | Ticket: B0079', '::1', '2025-12-08 18:41:20'),
(51, 5, 80, 'approve', 'Approved | Ticket: B0080', '::1', '2025-12-08 18:41:20'),
(52, 5, 83, 'approve', 'Approved | Ticket: B0083', '::1', '2025-12-08 18:42:29'),
(53, 5, 84, 'approve', 'Approved | Ticket: B0084', '::1', '2025-12-08 18:42:29'),
(54, 5, 85, 'approve', 'Approved | Ticket: B0085', '::1', '2025-12-08 18:45:53'),
(55, 5, 86, 'approve', 'Approved | Ticket: B0086', '::1', '2025-12-08 18:45:53'),
(56, 5, 87, 'approve', 'Approved | Ticket: B0087', '::1', '2025-12-08 18:47:12'),
(57, 5, 88, 'approve', 'Approved | Ticket: B0088', '::1', '2025-12-08 18:47:12'),
(58, 5, 93, 'approve', 'Approved | Ticket: B0093', '::1', '2025-12-09 18:59:46'),
(59, 5, 94, 'approve', 'Approved | Ticket: B0094', '::1', '2025-12-09 18:59:46'),
(60, 5, 91, 'reject', 'test', '::1', '2025-12-09 19:00:51'),
(61, 5, 92, 'reject', 'test', '::1', '2025-12-09 19:00:51'),
(62, 5, 104, 'approve', 'Approved | Ticket: B0104', '::1', '2025-12-14 19:58:17'),
(63, 5, 101, 'approve', 'Approved | Ticket: B0101', '::1', '2025-12-14 19:58:50'),
(64, 5, 102, 'approve', 'Approved | Ticket: B0102', '::1', '2025-12-14 19:58:50'),
(65, 5, 103, 'approve', 'Approved | Ticket: B0103', '::1', '2025-12-14 19:58:50'),
(66, 5, 97, 'approve', 'Approved | Ticket: B0097', '::1', '2025-12-14 19:59:44'),
(67, 5, 98, 'approve', 'Approved | Ticket: B0098', '::1', '2025-12-14 19:59:44'),
(68, 5, 99, 'approve', 'Approved | Ticket: B0099', '::1', '2025-12-14 20:02:09'),
(69, 5, 100, 'approve', 'Approved | Ticket: B0100', '::1', '2025-12-14 20:02:09'),
(70, 5, 95, 'approve', 'Approved | Ticket: B0095', '::1', '2025-12-14 20:18:25'),
(71, 5, 96, 'approve', 'Approved | Ticket: B0096', '::1', '2025-12-14 20:18:25'),
(72, 5, 105, 'approve', 'Approved | Ticket: B0105', '::1', '2025-12-14 20:26:08'),
(73, 5, 106, 'approve', 'Approved | Ticket: B0106', '::1', '2025-12-14 20:26:08'),
(74, 5, 111, 'approve', 'Approved | Ticket: B0111', '::1', '2025-12-14 21:07:47'),
(75, 5, 112, 'approve', 'Approved | Ticket: B0112', '::1', '2025-12-14 21:07:47'),
(76, 5, 107, 'approve', 'Approved | Ticket: B0107', '::1', '2025-12-14 21:22:11'),
(77, 5, 108, 'approve', 'Approved | Ticket: B0108', '::1', '2025-12-14 21:22:11'),
(78, 5, 109, 'reject', 'Testing 9:22pm 14/12/2025 Adam', '::1', '2025-12-14 21:22:48'),
(79, 5, 110, 'reject', 'Testing 9:22pm 14/12/2025 Adam', '::1', '2025-12-14 21:22:48');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `ticket` varchar(20) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` varchar(20) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `tel` varchar(20) DEFAULT NULL,
  `slot_date` date NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `status` enum('pending','booked','cancelled','rejected','maintenance') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `active_key` varchar(255) DEFAULT NULL,
  `session_id` varchar(36) DEFAULT NULL,
  `recurring_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `ticket`, `user_id`, `room_id`, `purpose`, `description`, `tel`, `slot_date`, `time_start`, `time_end`, `status`, `created_at`, `updated_at`, `cancelled_by`, `cancelled_at`, `cancel_reason`, `active_key`, `session_id`, `recurring_id`) VALUES
(1, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-06-02', '08:00:00', '08:50:00', 'cancelled', '2025-11-08 18:31:42', '2025-11-08 20:49:48', 2, '2025-11-08 13:49:48', 'Cancelled by user', 'BK00000001', NULL, NULL),
(2, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-06-02', '09:00:00', '09:50:00', 'cancelled', '2025-11-08 18:31:42', '2025-11-08 20:49:50', 2, '2025-11-08 13:49:50', 'Cancelled by user', 'BK00000002', NULL, NULL),
(3, NULL, 2, '07.61.01', 'test', 'testing', '0172345678', '2025-11-03', '08:00:00', '08:50:00', 'cancelled', '2025-11-08 18:32:11', '2025-11-08 20:49:45', 2, '2025-11-08 13:49:45', 'Cancelled by user', 'BK00000003', NULL, NULL),
(4, NULL, 2, '07.61.01', 'test', 'testing', '0172345678', '2025-11-03', '09:00:00', '09:50:00', 'cancelled', '2025-11-08 18:32:11', '2025-11-08 20:49:47', 2, '2025-11-08 13:49:47', 'Cancelled by user', 'BK00000004', NULL, NULL),
(5, NULL, 2, '02.31.01', 'Lab session', 'Exam', '0172345678', '2025-11-10', '08:00:00', '08:50:00', 'cancelled', '2025-11-08 20:18:30', '2025-11-08 20:59:43', 2, '2025-11-08 13:59:43', 'Cancelled by user', 'BK00000005', NULL, NULL),
(6, NULL, 2, '02.31.01', 'Lab session', 'Exam', '0172345678', '2025-11-10', '09:00:00', '09:50:00', 'cancelled', '2025-11-08 20:18:30', '2025-11-08 20:49:43', 2, '2025-11-08 13:49:43', 'Cancelled by user', 'BK00000006', NULL, NULL),
(7, NULL, 2, '02.31.01', 'test1', 'testing1', '0172345678', '2025-11-11', '08:00:00', '08:50:00', 'cancelled', '2025-11-08 20:21:45', '2025-11-10 16:11:57', 2, '2025-11-10 09:11:57', 'Cancelled by user', 'BK00000007', '0d6113b35e431424df85605a0a61a800', NULL),
(8, NULL, 2, '02.31.01', 'test1', 'testing1', '0172345678', '2025-11-11', '09:00:00', '09:50:00', 'cancelled', '2025-11-08 20:21:45', '2025-11-10 16:11:57', 2, '2025-11-10 09:11:57', 'Cancelled by user', 'BK00000008', '0d6113b35e431424df85605a0a61a800', NULL),
(9, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-10', '08:00:00', '08:50:00', 'cancelled', '2025-11-10 16:12:19', '2025-11-10 18:11:30', 2, '2025-11-10 11:11:30', 'User started edit - freed for rebooking', 'BK00000009', 'ccbf4013494e9ae99a47d5fe36dd103c', NULL),
(10, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-10', '09:00:00', '09:50:00', 'cancelled', '2025-11-10 16:12:19', '2025-11-10 17:23:25', 2, '2025-11-10 10:23:25', 'User started edit - freed for rebooking', 'BK00000010', 'ccbf4013494e9ae99a47d5fe36dd103c', NULL),
(11, NULL, 2, '02.31.01', 'Lab session', 'testing', '0172345678', '2025-11-11', '08:00:00', '08:50:00', 'cancelled', '2025-11-10 17:25:11', '2025-11-10 18:11:01', 2, '2025-11-10 11:11:01', 'User started edit - freed for rebooking', 'BK00000011', 'f57e97d12bb1c8039d063297273d8e32', NULL),
(12, NULL, 2, '02.31.01', 'Lab session', 'testing', '0172345678', '2025-11-11', '09:00:00', '09:50:00', 'cancelled', '2025-11-10 17:25:11', '2025-11-10 17:29:16', 2, '2025-11-10 10:29:16', 'User started edit - freed for rebooking', 'BK00000012', 'f57e97d12bb1c8039d063297273d8e32', NULL),
(13, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-12', '08:00:00', '08:50:00', 'cancelled', '2025-11-10 18:11:23', '2025-11-11 16:01:43', 2, '2025-11-11 09:01:43', 'freed_for_edit:2:2025-11-11 09:01:43', 'BK00000013', 'e3887d997b929cf74eb2da9fbb8ee117', NULL),
(14, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-10', '08:00:00', '08:50:00', 'cancelled', '2025-11-10 18:11:51', '2025-11-10 18:17:44', 2, '2025-11-10 11:17:44', 'freed_for_edit:2:2025-11-10 11:17:44', 'BK00000014', 'f7f724668de68b5a20b4ed534ca24414', NULL),
(15, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-10', '09:00:00', '09:50:00', 'cancelled', '2025-11-10 18:11:51', '2025-11-10 18:17:44', 2, '2025-11-10 11:17:44', 'freed_for_edit:2:2025-11-10 11:17:44', 'BK00000015', 'f7f724668de68b5a20b4ed534ca24414', NULL),
(16, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-10', '08:00:00', '08:50:00', 'cancelled', '2025-11-10 18:19:09', '2025-11-11 16:01:22', 2, '2025-11-11 09:01:22', 'freed_for_edit:2:2025-11-11 09:01:22', 'BK00000016', '9b3b5acc1878ddc52b272217d0d7ad34', NULL),
(17, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-10', '09:00:00', '09:50:00', 'cancelled', '2025-11-10 18:19:09', '2025-11-11 16:01:22', 2, '2025-11-11 09:01:22', 'freed_for_edit:2:2025-11-11 09:01:22', 'BK00000017', '9b3b5acc1878ddc52b272217d0d7ad34', NULL),
(18, NULL, 2, '02.31.01', 'exam', 'exam', '0172345678', '2025-11-11', '08:00:00', '08:50:00', 'cancelled', '2025-11-11 15:30:59', '2025-11-11 15:31:08', 2, '2025-11-11 08:31:08', 'freed_for_edit:2:2025-11-11 08:31:08', 'BK00000018', '04f0b60f616097a833158206fd36b7c3', NULL),
(19, NULL, 2, '02.31.01', 'exam', 'exam', '0172345678', '2025-11-11', '09:00:00', '09:50:00', 'cancelled', '2025-11-11 15:30:59', '2025-11-11 15:31:08', 2, '2025-11-11 08:31:08', 'freed_for_edit:2:2025-11-11 08:31:08', 'BK00000019', '04f0b60f616097a833158206fd36b7c3', NULL),
(20, NULL, 2, '02.31.01', 'exam', 'exam', '0172345678', '2025-11-12', '12:00:00', '12:50:00', 'cancelled', '2025-11-11 15:31:20', '2025-11-11 15:31:26', 2, '2025-11-11 08:31:26', 'Cancelled by user', 'BK00000020', '0e2f7c580b3c3e16aa6fae5887a3e710', NULL),
(21, NULL, 2, '02.31.01', 'exam', 'exam', '0172345678', '2025-11-12', '13:00:00', '13:50:00', 'cancelled', '2025-11-11 15:31:20', '2025-11-11 15:31:32', 2, '2025-11-11 08:31:32', 'Cancelled by user', 'BK00000021', '0e2f7c580b3c3e16aa6fae5887a3e710', NULL),
(22, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-12', '08:00:00', '08:50:00', 'booked', '2025-11-11 16:01:53', '2025-11-11 16:06:23', NULL, NULL, NULL, 'BK00000022', '924942e646264421cbd335ec7d74f2d4', NULL),
(23, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-12', '09:00:00', '09:50:00', 'booked', '2025-11-11 16:01:53', '2025-11-11 16:06:41', NULL, NULL, NULL, 'BK00000023', '924942e646264421cbd335ec7d74f2d4', NULL),
(24, NULL, 2, '02.31.01', 'test', 'testing', '0172345678', '2025-11-12', '10:00:00', '10:50:00', 'booked', '2025-11-11 16:01:53', '2025-11-11 16:06:45', NULL, NULL, NULL, 'BK00000024', '924942e646264421cbd335ec7d74f2d4', NULL),
(25, NULL, 2, '02.31.01', 'Tutorial', 'tutorial 1', '0172345678', '2025-11-12', '12:00:00', '12:50:00', 'cancelled', '2025-11-12 09:08:40', '2025-11-12 09:08:53', 2, '2025-11-12 02:08:53', 'Cancelled by user', 'BK00000025', 'e231364d233c5cd35e51b131376585ee', NULL),
(26, NULL, 2, '02.31.01', 'Tutorial', 'tutorial 1', '0172345678', '2025-11-12', '13:00:00', '13:50:00', 'cancelled', '2025-11-12 09:08:40', '2025-11-12 09:08:56', 2, '2025-11-12 02:08:56', 'Cancelled by user', 'BK00000026', 'e231364d233c5cd35e51b131376585ee', NULL),
(27, NULL, 2, '02.36.01', 'Lab session', 'ad', '0172345678', '2025-11-12', '08:00:00', '08:50:00', 'cancelled', '2025-11-12 09:43:04', '2025-11-12 09:44:20', 2, '2025-11-12 02:44:20', 'freed_for_edit:2:2025-11-12 02:44:20', 'BK00000027', '63c5d32dd102761cee8c1d39abea3e95', NULL),
(28, NULL, 2, '02.36.01', 'Lab session', 'ad', '0172345678', '2025-11-12', '09:00:00', '09:50:00', 'cancelled', '2025-11-12 09:43:04', '2025-11-12 09:44:20', 2, '2025-11-12 02:44:20', 'freed_for_edit:2:2025-11-12 02:44:20', 'BK00000028', '63c5d32dd102761cee8c1d39abea3e95', NULL),
(29, NULL, 2, '02.36.01', 'Lab session', 'ad', '0172345678', '2025-11-12', '12:00:00', '12:50:00', 'cancelled', '2025-11-12 09:44:43', '2025-11-12 09:44:52', 2, '2025-11-12 02:44:52', 'Cancelled by user', 'BK00000029', '0d75a2a716002a899d1985ffe50b378c', NULL),
(30, NULL, 2, '02.36.01', 'Lab session', 'ad', '0172345678', '2025-11-12', '13:00:00', '13:50:00', 'cancelled', '2025-11-12 09:44:43', '2025-11-12 09:44:55', 2, '2025-11-12 02:44:55', 'Cancelled by user', 'BK00000030', '0d75a2a716002a899d1985ffe50b378c', NULL),
(31, NULL, 2, '03.63.01', 'Lab session', 'exam', '0172345678', '2025-11-13', '13:00:00', '13:50:00', 'cancelled', '2025-11-13 11:37:20', '2025-11-13 11:37:45', 2, '2025-11-13 04:37:45', 'freed_for_edit:2:2025-11-13 04:37:45', 'BK00000031', 'b9032490eda06912b1be58ca50fe5a20', NULL),
(32, NULL, 2, '03.63.01', 'Lab session', 'exam', '0172345678', '2025-11-13', '14:00:00', '14:50:00', 'cancelled', '2025-11-13 11:37:20', '2025-11-13 11:37:45', 2, '2025-11-13 04:37:45', 'freed_for_edit:2:2025-11-13 04:37:45', 'BK00000032', 'b9032490eda06912b1be58ca50fe5a20', NULL),
(33, NULL, 2, '03.63.01', 'Lab session', 'Exam', '0172345678', '2025-11-13', '13:00:00', '13:50:00', 'cancelled', '2025-11-13 11:38:02', '2025-11-13 11:38:15', 2, '2025-11-13 04:38:15', 'Cancelled by user', 'BK00000033', '04cc7861941d2f92860853bd6b20aac4', NULL),
(34, NULL, 2, '03.63.01', 'Lab session', 'Exam', '0172345678', '2025-11-13', '14:00:00', '14:50:00', 'cancelled', '2025-11-13 11:38:02', '2025-11-13 11:38:20', 2, '2025-11-13 04:38:20', 'Cancelled by user', 'BK00000034', '04cc7861941d2f92860853bd6b20aac4', NULL),
(35, NULL, 2, '03.63.01', 'Lab session', 'Exam', '0172345678', '2025-11-13', '15:00:00', '15:50:00', 'cancelled', '2025-11-13 11:38:02', '2025-11-13 11:38:24', 2, '2025-11-13 04:38:24', 'Cancelled by user', 'BK00000035', '04cc7861941d2f92860853bd6b20aac4', NULL),
(36, NULL, 2, '03.63.01', 'Lab session', 'Exam', '0172345678', '2025-11-13', '16:00:00', '16:50:00', 'cancelled', '2025-11-13 11:38:02', '2025-11-13 11:38:27', 2, '2025-11-13 04:38:27', 'Cancelled by user', 'BK00000036', '04cc7861941d2f92860853bd6b20aac4', NULL),
(37, NULL, 2, '02.31.01', 'Lab session', 'Exam', '0172345678', '2025-11-13', '08:00:00', '08:50:00', 'booked', '2025-11-13 15:16:56', '2025-11-25 17:04:09', NULL, NULL, NULL, 'BK00000037', '143c916b0296c5c30df8b3bda069f7db', NULL),
(38, NULL, 2, '02.31.01', 'Lab session', 'Exam', '0172345678', '2025-11-13', '09:00:00', '09:50:00', 'rejected', '2025-11-13 15:16:56', '2025-11-25 17:07:24', NULL, '2025-11-25 17:05:55', 'Rejected by admin', 'BK00000038', '143c916b0296c5c30df8b3bda069f7db', NULL),
(41, 'B0041', 2, '02.31.01', 'Lab session', 'Exam', '0172345678', '2025-11-27', '08:00:00', '08:50:00', 'booked', '2025-11-25 18:23:28', '2025-11-25 18:49:57', NULL, NULL, NULL, 'BK00000041', 'a7219313908fcb94a44bd1fd9820b229', NULL),
(43, 'B0043', 2, '02.31.01', 'exam', 'testing1', '0172345678', '2025-11-26', '11:00:00', '11:50:00', 'booked', '2025-11-25 18:35:03', '2025-11-25 18:50:00', NULL, NULL, NULL, 'BK00000043', '28f61097ff83a83918c5d35cda16c655', NULL),
(44, 'B0044', 2, '02.31.01', 'exam', 'testing1', '0172345678', '2025-11-26', '12:00:00', '12:50:00', 'booked', '2025-11-25 18:35:03', '2025-11-25 18:50:00', NULL, NULL, NULL, 'BK00000044', '28f61097ff83a83918c5d35cda16c655', NULL),
(45, 'B0045', 2, '02.31.01', 'dr zatul', 'exam', '0172345678', '2025-11-26', '14:00:00', '14:50:00', 'booked', '2025-11-25 18:49:13', '2025-11-25 18:50:01', NULL, NULL, NULL, 'BK00000045', 'B045', NULL),
(46, 'B0046', 2, '02.31.01', 'dr zatul', 'exam', '0172345678', '2025-11-26', '15:00:00', '15:50:00', 'booked', '2025-11-25 18:49:13', '2025-11-25 18:50:01', NULL, NULL, NULL, 'BK00000046', 'B045', NULL),
(47, 'B0047', 2, '02.36.01', 'test', 'testing1', '0172345678', '2025-11-26', '08:00:00', '08:50:00', 'rejected', '2025-11-25 22:50:53', '2025-11-25 23:21:43', 4, '2025-11-25 23:21:43', 'full', 'BK00000047', 'B047', NULL),
(48, 'B0048', 2, '02.36.01', 'test', 'testing1', '0172345678', '2025-11-26', '09:00:00', '09:50:00', 'rejected', '2025-11-25 22:50:53', '2025-11-25 23:21:43', 4, '2025-11-25 23:21:43', 'full', 'BK00000048', 'B047', NULL),
(50, 'ADM-4BC85FB897', 4, '02.31.01', 'Maintenance', 'test', '', '2025-11-27', '11:00:00', '11:50:00', '', '2025-11-26 23:23:30', '2025-11-26 23:23:30', NULL, NULL, NULL, 'ADM16619709', 'ADM-4BC85FB897', NULL),
(51, 'ADM-06F89F89BD', 4, '02.31.01', 'Maintenance', 'test', '', '2025-11-27', '12:00:00', '12:50:00', '', '2025-11-26 23:23:30', '2025-11-26 23:23:30', NULL, NULL, NULL, 'ADM68792931', 'ADM-06F89F89BD', NULL),
(52, 'ADM-DA9A22817A', 4, '02.31.01', 'Maintenance', 'test', '', '2025-11-27', '13:00:00', '13:50:00', '', '2025-11-26 23:23:30', '2025-11-26 23:23:30', NULL, NULL, NULL, 'ADM97728692', 'ADM-DA9A22817A', NULL),
(53, 'ADM-3A63B80B82', 4, '02.31.01', 'Maintanance', 'test', '', '2025-11-27', '15:00:00', '15:50:00', 'maintenance', '2025-11-27 00:17:40', '2025-11-27 13:41:11', NULL, NULL, NULL, 'ADM78377189', 'ADM-3A63B80B82', NULL),
(55, 'ADM-08FE501F09', 4, '02.31.01', 'Maintenance', 'Fixing The Air Conditioning', '', '2025-11-28', '12:00:00', '12:50:00', 'maintenance', '2025-11-27 14:06:34', '2025-11-27 14:06:34', NULL, NULL, NULL, 'ADM20844651', 'ADM-08FE501F09', NULL),
(56, 'ADM-3A1BD89C04', 4, '02.31.01', 'Maintenance', 'Fixing The Air Conditioning', '', '2025-11-28', '13:00:00', '13:50:00', 'maintenance', '2025-11-27 14:06:34', '2025-11-27 14:06:34', NULL, NULL, NULL, 'ADM88998631', 'ADM-3A1BD89C04', NULL),
(57, 'ADM-9081CB2FC9', 4, '02.31.01', 'Maintenance', 'Fixing The Air Conditioning', '', '2025-11-28', '14:00:00', '14:50:00', 'maintenance', '2025-11-27 14:06:34', '2025-11-27 14:06:34', NULL, NULL, NULL, 'ADM21715391', 'ADM-9081CB2FC9', NULL),
(58, 'ADM-232A285382', 4, '02.31.01', 'Maintenance', 'Fixing The Air Conditioning', '', '2025-11-28', '15:00:00', '15:50:00', 'maintenance', '2025-11-27 14:06:34', '2025-11-27 14:06:34', NULL, NULL, NULL, 'ADM32557847', 'ADM-232A285382', NULL),
(59, 'ADM-68D0368952', 4, '02.31.01', 'Maintenance', 'Fixing The Air Conditioning', '', '2025-11-28', '16:00:00', '16:50:00', 'maintenance', '2025-11-27 14:06:34', '2025-11-27 14:06:34', NULL, NULL, NULL, 'ADM95952196', 'ADM-68D0368952', NULL),
(60, 'ADM-9A7D7C7093', 4, '02.31.01', 'Maintenance', 'Fixing The Air Conditioning', '', '2025-11-28', '17:00:00', '17:50:00', 'maintenance', '2025-11-27 14:06:34', '2025-11-27 14:06:34', NULL, NULL, NULL, 'ADM59062454', 'ADM-9A7D7C7093', NULL),
(61, 'ADM-43992408FB', 4, '02.31.01', 'Maintenance', 'Fixing The Air Conditioning', '', '2025-11-28', '18:00:00', '18:50:00', 'maintenance', '2025-11-27 14:06:34', '2025-11-27 14:06:34', NULL, NULL, NULL, 'ADM43389189', 'ADM-43992408FB', NULL),
(62, 'B0062', 2, '06.50.02', 'test', 'testing', '0172345678', '2025-12-01', '08:00:00', '08:50:00', 'booked', '2025-11-30 15:43:47', '2025-12-05 00:12:38', NULL, NULL, NULL, 'BK00000062', 'B062', NULL),
(63, 'B0063', 2, '06.50.02', 'test', 'testing', '0172345678', '2025-12-01', '09:00:00', '09:50:00', 'booked', '2025-11-30 15:43:47', '2025-12-05 00:12:38', NULL, NULL, NULL, 'BK00000063', 'B062', NULL),
(66, 'B0066', 6, '02.31.01', 'Replacement Class', 'IP Class', '0133801098', '2025-12-11', '12:00:00', '12:50:00', 'booked', '2025-12-05 00:22:12', '2025-12-05 00:22:31', NULL, NULL, NULL, 'BK00000066', 'B064', NULL),
(67, 'B0067', 6, '02.31.01', 'Replacement Class', 'IP Class', '0133801098', '2025-12-11', '13:00:00', '13:50:00', 'booked', '2025-12-05 00:22:12', '2025-12-05 00:22:31', NULL, NULL, NULL, 'BK00000067', 'B064', NULL),
(68, 'B0068', 6, '02.36.01', 'Tribo Class Replacement', 'Class', '0133801098', '2025-12-05', '16:00:00', '16:50:00', 'rejected', '2025-12-05 00:26:17', '2025-12-05 00:27:24', 5, '2025-12-05 00:27:24', 'Test', 'BK00000068', 'B068', NULL),
(69, 'B0069', 6, '02.36.01', 'Tribo Class Replacement', 'Class', '0133801098', '2025-12-05', '17:00:00', '17:50:00', 'rejected', '2025-12-05 00:26:17', '2025-12-05 00:27:24', 5, '2025-12-05 00:27:24', 'Test', 'BK00000069', 'B068', NULL),
(70, 'B0070', 6, '02.36.01', 'Tribo Class Replacement', 'Class', '0133801098', '2025-12-05', '15:00:00', '15:50:00', 'rejected', '2025-12-05 00:26:17', '2025-12-05 00:27:24', 5, '2025-12-05 00:27:24', 'Test', 'BK00000070', 'B068', NULL),
(71, 'B0071', 6, '02.31.01', 'Test 1', 'Test 1', '013932910312', '2025-12-12', '12:00:00', '12:50:00', 'booked', '2025-12-07 05:33:17', '2025-12-08 18:25:30', NULL, NULL, NULL, 'BK00000071', 'B071', NULL),
(72, 'B0072', 6, '02.31.01', 'Test 1', 'Test 1', '013932910312', '2025-12-12', '13:00:00', '13:50:00', 'booked', '2025-12-07 05:33:17', '2025-12-08 18:25:30', NULL, NULL, NULL, 'BK00000072', 'B071', NULL),
(73, 'B0073', 6, '02.31.01', 'test 123', 'test 123', '013380198', '2025-12-15', '11:00:00', '11:50:00', 'booked', '2025-12-08 18:24:59', '2025-12-08 18:25:27', NULL, NULL, NULL, 'BK00000073', 'B073', NULL),
(74, 'B0074', 6, '02.31.01', 'test 123', 'test 123', '013380198', '2025-12-15', '12:00:00', '12:50:00', 'booked', '2025-12-08 18:24:59', '2025-12-08 18:25:27', NULL, NULL, NULL, 'BK00000074', 'B073', NULL),
(75, 'B0075', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-10', '12:00:00', '12:50:00', 'booked', '2025-12-08 18:28:29', '2025-12-08 18:33:51', NULL, NULL, NULL, 'BK00000075', 'B075', NULL),
(76, 'B0076', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-10', '13:00:00', '13:50:00', 'booked', '2025-12-08 18:28:29', '2025-12-08 18:33:51', NULL, NULL, NULL, 'BK00000076', 'B075', NULL),
(77, 'B0077', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-09', '12:00:00', '12:50:00', 'booked', '2025-12-08 18:39:06', '2025-12-08 18:39:21', NULL, NULL, NULL, 'BK00000077', 'B077', NULL),
(78, 'B0078', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-09', '13:00:00', '13:50:00', 'booked', '2025-12-08 18:39:06', '2025-12-08 18:39:21', NULL, NULL, NULL, 'BK00000078', 'B077', NULL),
(79, 'B0079', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-09', '15:00:00', '15:50:00', 'booked', '2025-12-08 18:40:42', '2025-12-08 18:41:20', NULL, NULL, NULL, 'BK00000079', 'B079', NULL),
(80, 'B0080', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-09', '16:00:00', '16:50:00', 'booked', '2025-12-08 18:40:42', '2025-12-08 18:41:20', NULL, NULL, NULL, 'BK00000080', 'B079', NULL),
(81, 'B0081', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-10', '16:00:00', '16:50:00', 'booked', '2025-12-08 18:40:52', '2025-12-08 18:41:05', NULL, NULL, NULL, 'BK00000081', 'B081', NULL),
(82, 'B0082', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-10', '15:00:00', '15:50:00', 'booked', '2025-12-08 18:40:52', '2025-12-08 18:41:05', NULL, NULL, NULL, 'BK00000082', 'B081', NULL),
(83, 'B0083', 6, '02.31.01', 'Midterm Test for PT1', 'Class', '0133801098', '2025-12-11', '15:00:00', '15:50:00', 'booked', '2025-12-08 18:42:17', '2025-12-08 18:42:29', NULL, NULL, NULL, 'BK00000083', 'B083', NULL),
(84, 'B0084', 6, '02.31.01', 'Midterm Test for PT1', 'Class', '0133801098', '2025-12-11', '16:00:00', '16:50:00', 'booked', '2025-12-08 18:42:17', '2025-12-08 18:42:29', NULL, NULL, NULL, 'BK00000084', 'B083', NULL),
(85, 'B0085', 6, '02.36.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-09', '09:00:00', '09:50:00', 'booked', '2025-12-08 18:45:27', '2025-12-08 18:45:53', NULL, NULL, NULL, 'BK00000085', 'B085', NULL),
(86, 'B0086', 6, '02.36.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-09', '10:00:00', '10:50:00', 'booked', '2025-12-08 18:45:27', '2025-12-08 18:45:53', NULL, NULL, NULL, 'BK00000086', 'B085', NULL),
(87, 'B0087', 6, '02.36.01', 'Lab Session', 'Class', '0133801098', '2025-12-10', '09:00:00', '09:50:00', 'booked', '2025-12-08 18:45:38', '2025-12-08 18:47:12', NULL, NULL, NULL, 'BK00000087', 'B087', NULL),
(88, 'B0088', 6, '02.36.01', 'Lab Session', 'Class', '0133801098', '2025-12-10', '10:00:00', '10:50:00', 'booked', '2025-12-08 18:45:38', '2025-12-08 18:47:12', NULL, NULL, NULL, 'BK00000088', 'B087', NULL),
(89, 'B0089', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-12', '15:00:00', '15:50:00', 'cancelled', '2025-12-09 18:45:03', '2025-12-09 18:58:20', 6, '2025-12-09 11:58:20', 'freed_for_edit:6:2025-12-09 11:58:20', 'BK00000089', 'B089', NULL),
(90, 'B0090', 6, '02.31.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-12', '16:00:00', '16:50:00', 'cancelled', '2025-12-09 18:45:03', '2025-12-09 18:58:20', 6, '2025-12-09 11:58:20', 'freed_for_edit:6:2025-12-09 11:58:20', 'BK00000090', 'B089', NULL),
(91, 'B0091', 6, '02.31.01', 'Lab Test 34', 'Test 13', '0133801098', '2025-12-12', '15:00:00', '15:50:00', 'rejected', '2025-12-09 18:58:30', '2025-12-09 19:00:51', 5, '2025-12-09 19:00:51', 'test', 'BK00000091', 'B091', NULL),
(92, 'B0092', 6, '02.31.01', 'Lab Test 34', 'Test 13', '0133801098', '2025-12-12', '16:00:00', '16:50:00', 'rejected', '2025-12-09 18:58:30', '2025-12-09 19:00:51', 5, '2025-12-09 19:00:51', 'test', 'BK00000092', 'B091', NULL),
(93, 'B0093', 6, '02.36.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-12', '14:00:00', '14:50:00', 'booked', '2025-12-09 18:58:43', '2025-12-09 18:59:46', NULL, NULL, NULL, 'BK00000093', 'B093', NULL),
(94, 'B0094', 6, '02.36.01', 'Lab Test', 'Test 1', '0133801098', '2025-12-12', '15:00:00', '15:50:00', 'booked', '2025-12-09 18:58:43', '2025-12-09 18:59:46', NULL, NULL, NULL, 'BK00000094', 'B093', NULL),
(95, 'B0095', 6, '03.63.01', 'dasdasdas', 'dasdasdas', 'dasdasda', '2025-12-15', '08:00:00', '08:50:00', 'booked', '2025-12-14 19:45:17', '2025-12-14 20:18:25', NULL, NULL, NULL, 'BK00000095', 'B095', NULL),
(96, 'B0096', 6, '03.63.01', 'dasdasdas', 'dasdasdas', 'dasdasda', '2025-12-15', '09:00:00', '09:50:00', 'booked', '2025-12-14 19:45:17', '2025-12-14 20:18:25', NULL, NULL, NULL, 'BK00000096', 'B095', NULL),
(97, 'B0097', 6, '03.63.01', 'dasdasda', 'dasdada', '312312321312312312', '2025-12-17', '11:00:00', '11:50:00', 'cancelled', '2025-12-14 19:45:24', '2025-12-14 20:49:21', 6, '2025-12-14 13:49:21', 'Cancelled by user', 'BK00000097', 'B097', NULL),
(98, 'B0098', 6, '03.63.01', 'dasdasda', 'dasdada', '312312321312312312', '2025-12-17', '12:00:00', '12:50:00', 'cancelled', '2025-12-14 19:45:24', '2025-12-14 20:49:23', 6, '2025-12-14 13:49:23', 'Cancelled by user', 'BK00000098', 'B097', NULL),
(99, 'B0099', 6, '03.63.01', 'Lab Test', 'dasdas', 'a312312321312', '2025-12-16', '10:00:00', '10:50:00', 'cancelled', '2025-12-14 19:45:31', '2025-12-14 20:49:17', 6, '2025-12-14 13:49:17', 'Cancelled by user', 'BK00000099', 'B099', NULL),
(100, 'B0100', 6, '03.63.01', 'Lab Test', 'dasdas', 'a312312321312', '2025-12-16', '09:00:00', '09:50:00', 'cancelled', '2025-12-14 19:45:31', '2025-12-14 20:49:19', 6, '2025-12-14 13:49:19', 'Cancelled by user', 'BK00000100', 'B099', NULL),
(101, 'B0101', 6, '03.63.01', 'adasdasda', 'asdasdasd', '312312312312312', '2025-12-18', '15:00:00', '15:50:00', 'cancelled', '2025-12-14 19:45:39', '2025-12-14 20:49:11', 6, '2025-12-14 13:49:11', 'Cancelled by user', 'BK00000101', 'B101', NULL),
(102, 'B0102', 6, '03.63.01', 'adasdasda', 'asdasdasd', '312312312312312', '2025-12-18', '16:00:00', '16:50:00', 'cancelled', '2025-12-14 19:45:39', '2025-12-14 20:49:13', 6, '2025-12-14 13:49:13', 'Cancelled by user', 'BK00000102', 'B101', NULL),
(103, 'B0103', 6, '03.63.01', 'adasdasda', 'asdasdasd', '312312312312312', '2025-12-18', '17:00:00', '17:50:00', 'cancelled', '2025-12-14 19:45:39', '2025-12-14 20:49:15', 6, '2025-12-14 13:49:15', 'Cancelled by user', 'BK00000103', 'B101', NULL),
(104, 'B0104', 6, '03.63.01', 'dadasdas', 'dasdas', '3123213123123', '2025-12-19', '14:00:00', '14:50:00', 'cancelled', '2025-12-14 19:45:46', '2025-12-14 20:49:09', 6, '2025-12-14 13:49:09', 'Cancelled by user', 'BK00000104', 'B104', NULL),
(105, 'B0105', 6, '03.63.01', 'asdasdasdas', 'dadasdasdas', '312312312312312312', '2025-12-15', '17:00:00', '17:50:00', 'cancelled', '2025-12-14 19:45:52', '2025-12-14 20:49:04', 6, '2025-12-14 13:49:04', 'Cancelled by user', 'BK00000105', 'B105', NULL),
(106, 'B0106', 6, '03.63.01', 'asdasdasdas', 'dadasdasdas', '312312312312312312', '2025-12-15', '16:00:00', '16:50:00', 'cancelled', '2025-12-14 19:45:52', '2025-12-14 20:49:07', 6, '2025-12-14 13:49:07', 'Cancelled by user', 'BK00000106', 'B105', NULL),
(107, 'B0107', 6, '02.31.01', 'dadasdas', 'dasdasdsa', 'dasdasdasd', '2025-12-18', '11:00:00', '11:50:00', 'booked', '2025-12-14 20:48:59', '2025-12-14 21:22:11', NULL, NULL, NULL, 'BK00000107', 'B107', NULL),
(108, 'B0108', 6, '02.31.01', 'dadasdas', 'dasdasdsa', 'dasdasdasd', '2025-12-18', '12:00:00', '12:50:00', 'booked', '2025-12-14 20:48:59', '2025-12-14 21:22:11', NULL, NULL, NULL, 'BK00000108', 'B107', NULL),
(109, 'B0109', 6, '02.31.01', 'sdadasdasd', 'dsadadasd', '31231312312', '2025-12-16', '11:00:00', '11:50:00', 'rejected', '2025-12-14 20:49:40', '2025-12-14 21:22:48', 5, '2025-12-14 21:22:48', 'Testing 9:22pm 14/12/2025 Adam', 'BK00000109', 'B109', NULL),
(110, 'B0110', 6, '02.31.01', 'sdadasdasd', 'dsadadasd', '31231312312', '2025-12-16', '12:00:00', '12:50:00', 'rejected', '2025-12-14 20:49:40', '2025-12-14 21:22:48', 5, '2025-12-14 21:22:48', 'Testing 9:22pm 14/12/2025 Adam', 'BK00000110', 'B109', NULL),
(111, 'B0111', 6, '02.31.01', 'dasdadsa', 'dsadasdasd', '32131321321312', '2025-12-15', '15:00:00', '15:50:00', 'booked', '2025-12-14 20:49:48', '2025-12-14 21:07:47', NULL, NULL, NULL, 'BK00000111', 'B111', NULL),
(112, 'B0112', 6, '02.31.01', 'dasdadsa', 'dsadasdasd', '32131321321312', '2025-12-15', '16:00:00', '16:50:00', 'booked', '2025-12-14 20:49:48', '2025-12-14 21:07:47', NULL, NULL, NULL, 'BK00000112', 'B111', NULL),
(113, 'B0113', 6, '02.31.01', 'sdasdasda', 'dasdasdas', '21321312312321', '2025-12-16', '13:00:00', '13:50:00', 'cancelled', '2025-12-14 20:50:02', '2025-12-14 20:50:11', 6, '2025-12-14 13:50:11', 'freed_for_edit:6:2025-12-14 13:50:11', 'BK00000113', 'B113', NULL),
(114, 'B0114', 6, '02.31.01', 'sdasdasda', 'dasdasdas', '21321312312321', '2025-12-16', '14:00:00', '14:50:00', 'cancelled', '2025-12-14 20:50:02', '2025-12-14 20:50:11', 6, '2025-12-14 13:50:11', 'freed_for_edit:6:2025-12-14 13:50:11', 'BK00000114', 'B113', NULL),
(115, 'B0115', 6, '02.31.01', 'sdasdasd', 'asdasdas', '321312312312', '2025-12-16', '13:00:00', '13:50:00', 'pending', '2025-12-14 20:50:21', '2025-12-14 20:50:21', NULL, NULL, NULL, 'BK00000115', 'B115', NULL),
(116, 'B0116', 6, '02.31.01', 'sdasdasd', 'asdasdas', '321312312312', '2025-12-16', '14:00:00', '14:50:00', 'pending', '2025-12-14 20:50:21', '2025-12-14 20:50:21', NULL, NULL, NULL, 'BK00000116', 'B115', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `recurring_bookings`
--

CREATE TABLE `recurring_bookings` (
  `id` int(11) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `tel` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recurring_bookings`
--

INSERT INTO `recurring_bookings` (`id`, `room_id`, `day_of_week`, `time_start`, `time_end`, `purpose`, `description`, `tel`, `status`, `created_by`, `created_at`) VALUES
(1, '02.31.01', 'Monday', '12:00:00', '12:50:00', 'SECJ1213', 'Computer Science', '', 'active', 4, '2025-11-27 09:14:02'),
(2, '02.31.01', 'Monday', '13:00:00', '13:50:00', 'SECJ1213', 'Computer Science', '', 'active', 4, '2025-11-27 09:14:02'),
(3, '02.31.01', 'Monday', '14:00:00', '14:50:00', 'SECJ1213', 'Computer Science', '', 'active', 4, '2025-11-27 09:14:02'),
(4, '02.31.01', 'Tuesday', '15:00:00', '15:50:00', 'SECJ1213', 'Test', '', 'active', 4, '2025-11-27 09:29:19'),
(5, '02.31.01', 'Tuesday', '16:00:00', '16:50:00', 'SECJ1213', 'Test', '', 'active', 4, '2025-11-27 09:29:19'),
(6, '02.31.01', 'Wednesday', '11:00:00', '11:50:00', 'SECD1234', 'Data Structure', '', 'active', 4, '2025-11-29 18:47:54'),
(7, '02.31.01', 'Wednesday', '12:00:00', '12:50:00', 'SECD1234', 'Data Structure', '', 'active', 4, '2025-11-29 18:47:54'),
(10, '02.31.01', 'Thursday', '14:00:00', '14:50:00', 'Group Azfar Meeting with MJIIT Stakeholder', 'Progress Update', '0133801098', 'active', 5, '2025-12-04 16:11:37'),
(11, '02.31.01', 'Thursday', '15:00:00', '15:50:00', 'Group Azfar Meeting with MJIIT Stakeholder', 'Progress Update', '0133801098', 'active', 5, '2025-12-04 16:11:37'),
(12, '02.31.01', 'Thursday', '16:00:00', '16:50:00', 'SECJ3303', 'Class Replacment', '0133801098', 'active', 5, '2025-12-09 10:59:28'),
(13, '02.31.01', 'Wednesday', '14:00:00', '14:50:00', 'sda', 'dsas', 'asdasd', 'active', 5, '2025-12-14 11:43:29');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `capacity` int(11) NOT NULL,
  `floor` varchar(10) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `name`, `capacity`, `floor`, `active`) VALUES
('02.31.01', 'BILIK KULIAH 01', 58, '2', 1),
('02.36.01', 'BILIK KULIAH 02', 60, NULL, 1),
('02.37.01', 'BILIK KULIAH 03', 70, NULL, 1),
('03.63.01', 'BILIK KULIAH 04', 41, '3', 1),
('03.64.01', 'BILIK KULIAH 05', 41, NULL, 1),
('04.37.01', 'BILIK KULIAH 06', 44, '4', 1),
('04.38.01', 'BILIK KULIAH 07', 46, NULL, 1),
('04.41.01', 'BILIK KULIAH 08', 45, NULL, 1),
('05.44.01', 'BILIK KULIAH 09', 42, '5', 1),
('05.45.01', 'BILIK KULIAH 10', 42, NULL, 1),
('06.50.02', 'BILIK KULIAH 12', 42, '6', 1),
('06.51.01', 'BILIK KULIAH 13', 42, NULL, 1),
('06.56.01', 'BILIK KULIAH 14', 67, NULL, 1),
('06.57.01', 'BILIK KULIAH 15', 45, NULL, 1),
('06.62.01', 'BILIK KULIAH 17', 39, NULL, 1),
('06.63.02', 'BILIK KULIAH 16', 36, NULL, 1),
('07.61.01', 'BILIK KULIAH 18', 40, '7', 1),
('08.44.01', 'BILIK KULIAH 19', 42, '8', 1),
('08.45.01', 'BILIK KULIAH 21', 42, NULL, 1),
('08.47.01', 'BILIK KULIAH 20', 43, NULL, 1),
('08.48.01', 'BILIK KULIAH 22', 69, NULL, 1),
('09.45.01', 'BILIK KULIAH 23', 42, '9', 1),
('09.52.02', 'BILIK SEMINAR 1', 109, NULL, 1),
('DK 1', 'DEWAN KULIAH 01', 100, NULL, 1),
('DK 2', 'DEWAN KULIAH 02', 100, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `room_problems`
--

CREATE TABLE `room_problems` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` varchar(20) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `status` enum('Pending','In Progress','Resolved') NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_problems`
--

INSERT INTO `room_problems` (`id`, `user_id`, `room_id`, `title`, `description`, `status`, `created_at`, `resolved_at`) VALUES
(1, 6, '02.31.01', 'AC Broken', 'No cool air, ketiak belecak', 'Resolved', '2025-12-08 18:13:04', '2025-12-08 18:13:48'),
(2, 6, '02.31.01', 'ac brioke', 'sdada', 'Resolved', '2025-12-08 21:49:23', '2025-12-08 22:11:57'),
(3, 6, '02.31.01', 'ac brioke', 'sdada', 'Resolved', '2025-12-08 21:51:57', '2025-12-08 22:11:58'),
(4, 6, '02.31.01', 'test broke', 'i want some free chipsssuuuhhhhh', 'Resolved', '2025-12-08 22:03:19', '2025-12-08 22:11:59'),
(5, 6, '02.31.01', 'air cond no work', 'panas bro', 'Resolved', '2025-12-14 19:53:39', '2025-12-14 21:53:54'),
(6, 6, '02.37.01', 'Meja dan lantai ktoro', 'please bersihkan', 'Resolved', '2025-12-14 19:53:55', '2025-12-14 21:53:53');

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` varchar(20) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `tel` varchar(20) DEFAULT NULL,
  `slot_date` date NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `status` enum('pending','booked','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `active_key` varchar(255) GENERATED ALWAYS AS (concat(`room_id`,'-',`slot_date`,'-',`time_start`,'-',`status`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetable_backup`
--

CREATE TABLE `timetable_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `room` varchar(80) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `tel` varchar(20) DEFAULT NULL,
  `slot_date` date NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `status` enum('pending','booked','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable_backup`
--

INSERT INTO `timetable_backup` (`id`, `user_id`, `room`, `purpose`, `description`, `tel`, `slot_date`, `time_start`, `time_end`, `status`, `created_at`, `updated_at`, `cancelled_by`, `cancelled_at`, `cancel_reason`) VALUES
(28, 2, 'bk1', 'test1', 'testing1', '0172345678', '2025-06-02', '08:00:00', '08:50:00', 'cancelled', '2025-11-05 23:06:17', '2025-11-05 23:06:24', 2, '2025-11-05 16:06:24', 'Cancelled by user'),
(29, 2, 'bk1', 'test1', 'testing1', '0172345678', '2025-06-02', '09:00:00', '09:50:00', 'cancelled', '2025-11-05 23:06:17', '2025-11-05 23:06:23', 2, '2025-11-05 16:06:23', 'Cancelled by user'),
(30, 2, 'bk1', 'test', 'testing', '0172345678', '2025-06-02', '08:00:00', '08:50:00', 'pending', '2025-11-05 23:20:08', '2025-11-05 23:20:08', NULL, NULL, NULL),
(31, 2, 'bk1', 'test', 'testing', '0172345678', '2025-06-02', '09:00:00', '09:50:00', 'pending', '2025-11-05 23:20:08', '2025-11-05 23:20:08', NULL, NULL, NULL),
(32, 2, 'bk1', 'test', 'testing', '0172345678', '2025-06-04', '08:00:00', '08:50:00', 'cancelled', '2025-11-05 23:20:50', '2025-11-05 23:21:48', 2, '2025-11-05 16:21:48', 'Cancelled by user'),
(33, 2, 'bk1', 'test', 'testing', '0172345678', '2025-06-04', '09:00:00', '09:50:00', 'cancelled', '2025-11-05 23:20:50', '2025-11-05 23:20:55', 2, '2025-11-05 16:20:55', 'Cancelled by user');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `Fullname` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `User_Type` enum('Admin','Lecturer','Student','Staff') NOT NULL DEFAULT 'Student',
  `Phone_Number` varchar(15) DEFAULT NULL,
  `Created_At` datetime DEFAULT current_timestamp(),
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_token_expiry` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `Fullname`, `Email`, `password_hash`, `User_Type`, `Phone_Number`, `Created_At`, `Updated_At`, `remember_token`, `remember_token_expiry`, `reset_token`, `reset_token_expiry`) VALUES
(1, 'admin', '', '', '$2y$10$Bd77XAVxlSBjDeUPAh22pOq1/PMxY7Kpd1EQ9e3tND0Up21fhou/W', 'Admin', NULL, '2025-10-22 21:33:30', '2025-10-22 21:33:48', NULL, NULL, NULL, NULL),
(2, 'ahmad.azfar', 'Ahmad Azfar Bin Azmi', 'ahmadazfar02@gmail.com', '$2y$10$JBC5YNZZNDsw9k6S6IgE8ejnYVY2IJy9l./NaJyygtQwD3pjk5PEi', 'Admin', '0172672980', '2025-10-22 21:42:45', '2025-12-09 18:59:01', '64440d4b85054649fd145ef703097527bcb117743393327fd88f7fbacd3fefd7', '2025-12-11 04:15:52', NULL, NULL),
(4, 'Admin2', 'admin', 'admin@gmail.com', '$2y$10$Rk/SjleBMyYHAq.9tXl3eOD9Mdd5B9zAr23gF8jxKRzuzxXGmlyv2', 'Admin', '012345678', '2025-10-27 12:11:04', '2025-10-27 12:11:04', NULL, NULL, NULL, NULL),
(5, 'superadmin', 'superadmin', 'superadmin@utm.my', '$2y$10$N9hrQdLJcyQ7D/wE.h98IuT54WC9aw2lU0eNtltMEXkkvDdIDwuLq', 'Admin', NULL, '2025-11-30 12:53:15', '2025-11-30 12:54:28', NULL, NULL, NULL, NULL),
(6, 'adamazraei', 'Adam Azraei', 'adam.azrae@gmail.com', '$2y$10$/z/cmPoDs4xxZjUkVUTRp.vmmpUmsZtEOwfZYPJKQjwRgQ77P6YEm', 'Student', '0133801098', '2025-12-05 00:02:00', '2025-12-14 22:10:56', NULL, NULL, '8eae08c4a3bbbdf28c62a9c20c9d964ffd8aa554366d1215f2d8d0b361a96208', '2025-12-14 23:10:56'),
(7, 'hussein', 'Hussein Nazif', 'hussein@gmail.com', '$2y$10$xeCqP8/GD5Lhy9GkhNq.KOoYTorrcdphiJVx16.8ZP4dEZ3357ul6', 'Lecturer', '0123855749', '2025-12-14 19:55:23', '2025-12-14 19:55:23', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users_backup`
--

CREATE TABLE `users_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `username` varchar(50) NOT NULL,
  `Fullname` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `User_Type` enum('Admin','Lecturer','Student','Staff') NOT NULL DEFAULT 'Student',
  `Phone_Number` varchar(15) DEFAULT NULL,
  `Created_At` datetime DEFAULT current_timestamp(),
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_backup`
--

INSERT INTO `users_backup` (`id`, `username`, `Fullname`, `Email`, `password_hash`, `User_Type`, `Phone_Number`, `Created_At`, `Updated_At`) VALUES
(1, 'admin', '', '', '$2y$10$Bd77XAVxlSBjDeUPAh22pOq1/PMxY7Kpd1EQ9e3tND0Up21fhou/W', 'Admin', NULL, '2025-10-22 21:33:30', '2025-10-22 21:33:48'),
(2, 'ahmad.azfar', 'Ahmad Azfar Bin Azmi', 'ahmadazfar02@gmail.com', '$2y$10$b4JojEP6/TTCrTHqxqHgxeKPDJtIQsAVXkGI1Hvit2RRJKvLaAeBu', 'Student', '0172672980', '2025-10-22 21:42:45', '2025-10-22 21:42:45'),
(3, 'ali', 'muhammad ali', 'ali@gmail.com', '$2y$10$wDr3NdeXoh2wPQEF5OFd2.f8rbdJ7a5.wLMbRv1z0ZwEIz9F/x.Bm', 'Student', '012345678', '2025-10-22 21:45:08', '2025-11-01 11:41:00'),
(4, 'Admin2', 'admin', 'admin@gmail.com', '$2y$10$Rk/SjleBMyYHAq.9tXl3eOD9Mdd5B9zAr23gF8jxKRzuzxXGmlyv2', 'Admin', '012345678', '2025-10-27 12:11:04', '2025-10-27 12:11:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_logs_booking` (`booking_id`),
  ADD KEY `fk_admin` (`admin_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_room_date_start` (`room_id`,`slot_date`,`time_start`),
  ADD KEY `idx_status_date` (`status`,`slot_date`),
  ADD KEY `fk_bookings_cancelled_by` (`cancelled_by`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `fk_booking_recurring` (`recurring_id`);

--
-- Indexes for table `recurring_bookings`
--
ALTER TABLE `recurring_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_recurring_room` (`room_id`),
  ADD KEY `fk_recurring_admin` (`created_by`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`);

--
-- Indexes for table `room_problems`
--
ALTER TABLE `room_problems`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_room_date_start_status` (`active_key`),
  ADD KEY `fk_tt_room` (`room_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_email` (`Email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `recurring_bookings`
--
ALTER TABLE `recurring_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `room_problems`
--
ALTER TABLE `room_problems`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `fk_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_recurring` FOREIGN KEY (`recurring_id`) REFERENCES `recurring_bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bookings_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookings_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `recurring_bookings`
--
ALTER TABLE `recurring_bookings`
  ADD CONSTRAINT `fk_recurring_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recurring_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `fk_tt_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

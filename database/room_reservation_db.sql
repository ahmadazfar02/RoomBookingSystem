-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2025 at 05:41 PM
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
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `id` int(11) NOT NULL,
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
  `cancel_reason` varchar(255) DEFAULT NULL,
  `active_key` varchar(255) GENERATED ALWAYS AS (case when `status` in ('pending','booked') then concat(`room`,'|',`slot_date`,'|',time_format(`time_start`,'%H:%i:%s')) else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`id`, `user_id`, `room`, `purpose`, `description`, `tel`, `slot_date`, `time_start`, `time_end`, `status`, `created_at`, `updated_at`, `cancelled_by`, `cancelled_at`, `cancel_reason`) VALUES
(28, 2, 'bk1', 'test1', 'testing1', '0172345678', '2025-06-02', '08:00:00', '08:50:00', 'cancelled', '2025-11-05 23:06:17', '2025-11-05 23:06:24', 2, '2025-11-05 16:06:24', 'Cancelled by user'),
(29, 2, 'bk1', 'test1', 'testing1', '0172345678', '2025-06-02', '09:00:00', '09:50:00', 'cancelled', '2025-11-05 23:06:17', '2025-11-05 23:06:23', 2, '2025-11-05 16:06:23', 'Cancelled by user'),
(30, 2, 'bk1', 'test', 'testing', '0172345678', '2025-06-02', '08:00:00', '08:50:00', 'cancelled', '2025-11-05 23:20:08', '2025-11-05 23:47:58', 2, '2025-11-05 16:47:58', 'Cancelled by user'),
(31, 2, 'bk1', 'test', 'testing', '0172345678', '2025-06-02', '09:00:00', '09:50:00', 'cancelled', '2025-11-05 23:20:08', '2025-11-05 23:47:56', 2, '2025-11-05 16:47:56', 'Cancelled by user'),
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
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_token_expiry` datetime DEFAULT NULL,
  `Phone_Number` varchar(15) DEFAULT NULL,
  `Created_At` datetime DEFAULT current_timestamp(),
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `Fullname`, `Email`, `password_hash`, `User_Type`, `remember_token`, `remember_token_expiry`, `Phone_Number`, `Created_At`, `Updated_At`, `reset_token`, `reset_token_expiry`) VALUES
(1, 'admin', '', '', '$2y$10$Bd77XAVxlSBjDeUPAh22pOq1/PMxY7Kpd1EQ9e3tND0Up21fhou/W', 'Admin', NULL, NULL, NULL, '2025-10-22 21:33:30', '2025-10-22 21:33:48', NULL, NULL),
(2, 'ahmad.azfar', 'Ahmad Azfar Bin Azmi', 'ahmadazfar02@gmail.com', '$2y$10$b4JojEP6/TTCrTHqxqHgxeKPDJtIQsAVXkGI1Hvit2RRJKvLaAeBu', 'Student', NULL, NULL, '0172672980', '2025-10-22 21:42:45', '2025-10-22 21:42:45', NULL, NULL),
(3, 'ali', 'muhammad ali', 'ali@gmail.com', '$2y$10$wDr3NdeXoh2wPQEF5OFd2.f8rbdJ7a5.wLMbRv1z0ZwEIz9F/x.Bm', 'Staff', NULL, NULL, '012345678', '2025-10-22 21:45:08', '2025-10-22 21:45:08', NULL, NULL),
(9, 'adamazraei', 'Adam Azraei', 'adam.azrae@gmail.com', '$2y$10$RvC4k1ucYk2dBiuRwuzeS.w9zTlpjhm9gkCWzr4vSAQrqXmZMoG2y', 'Student', 'cedc6cd791ba3a1f9e7444a0ece42b26c2ffa66a11187d351232e8762154c69a', '2025-12-05 14:42:32', '0133801098', '2025-11-05 21:34:19', '2025-11-06 00:38:47', NULL, NULL),
(10, 'admin2', 'admin2', 'admin123@gmail.com', '$2y$10$JAiA.nQOVUi10pfAQZHXo.dSxYyB4603IvZkTyJeMBP9NRtHpn9ze', 'Admin', NULL, NULL, '1243124124124', '2025-11-05 21:44:58', '2025-11-05 21:44:58', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_active_booking` (`active_key`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `fk_timetable_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 23, 2025 at 06:39 AM
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
  `Updated_At` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `Fullname`, `Email`, `password_hash`, `User_Type`, `Phone_Number`, `Created_At`, `Updated_At`) VALUES
(1, 'admin', '', '', '$2y$10$Bd77XAVxlSBjDeUPAh22pOq1/PMxY7Kpd1EQ9e3tND0Up21fhou/W', 'Admin', NULL, '2025-10-22 21:33:30', '2025-10-22 21:33:48'),
(2, 'ahmad.azfar', 'Ahmad Azfar Bin Azmi', 'ahmadazfar02@gmail.com', '$2y$10$b4JojEP6/TTCrTHqxqHgxeKPDJtIQsAVXkGI1Hvit2RRJKvLaAeBu', 'Student', '0172672980', '2025-10-22 21:42:45', '2025-10-22 21:42:45'),
(3, 'ali', 'muhammad ali', 'ali@gmail.com', '$2y$10$wDr3NdeXoh2wPQEF5OFd2.f8rbdJ7a5.wLMbRv1z0ZwEIz9F/x.Bm', 'Staff', '012345678', '2025-10-22 21:45:08', '2025-10-22 21:45:08'),
(5, 'adamazraei', 'Adam Azraei', 'adamazraei@yahoo.com', '$2y$10$M6J4Yt13hbOmZN1yznO3Hee0jJ/boUi4dIpEae6R9I4ZgxQUt3/gq', 'Admin', '0132458690', '2025-10-22 22:55:46', '2025-10-22 22:55:46'),
(6, 'hussein', 'Hussein Nazif', 'hussein@gmail.com', '$2y$10$OXQ75o3Bndgs18J3iUzCGuwJ2yuEbjXj3FuiqvlVKGWl17H6YDQbK', 'Student', '0138910224', '2025-10-23 00:09:13', '2025-10-23 00:09:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

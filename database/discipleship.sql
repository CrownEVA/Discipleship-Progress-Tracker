-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 09, 2026 at 07:10 PM
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
-- Database: `discipleship`
--
CREATE DATABASE IF NOT EXISTS `discipleship` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `discipleship`;

-- --------------------------------------------------------

--
-- Table structure for table `journeys`
--

DROP TABLE IF EXISTS `journeys`;
CREATE TABLE `journeys` (
  `id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `journeys`
--

INSERT INTO `journeys` (`id`, `image`, `title`, `description`) VALUES
(4, 'uploads/journeys/journey_69d7d794270b56.23867435.jpg', 'title of journey', 'description of journey');

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `journey_id` int(11) NOT NULL,
  `lesson` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `journey_id`, `lesson`, `title`, `content`) VALUES
(5, 4, 1, 'lesson title', 'dhhhk jfkfkf nssjsjjs');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `points` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `points`) VALUES
(1, 'Jaythoon', 'Sahibul', 'jaythoonsahibul@gmail.com', '$2y$10$BBlX5tlaxd/Cei4T6vPKNOA59izOVJYeH9l6e7ae2tYALFzJ7u.D6', 'admin', 80),
(2, 'Jay', 'Shin', 'jayshin@gmail.com', '$2y$10$kx34zoF8O.Sk/E6Aa.tUhuNt2yk0rzvyLypapQJLqcH/WlxVekfX6', 'user', 20);

-- --------------------------------------------------------

--
-- Table structure for table `user_journeys`
--

DROP TABLE IF EXISTS `user_journeys`;
CREATE TABLE `user_journeys` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `journey_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'enrolled',
  `progress_percent` int(11) NOT NULL DEFAULT 0,
  `enrolled_at` timestamp NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_journeys`
--

INSERT INTO `user_journeys` (`id`, `user_id`, `journey_id`, `status`, `progress_percent`, `enrolled_at`, `started_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(10, 1, 4, 'completed', 100, '2026-04-09 16:46:21', NULL, '2026-04-09 16:46:26', '2026-04-09 16:46:21', '2026-04-09 16:46:26'),
(11, 2, 4, 'completed', 100, '2026-04-09 16:54:02', NULL, '2026-04-09 16:54:06', '2026-04-09 16:54:02', '2026-04-09 16:54:06');

-- --------------------------------------------------------

--
-- Table structure for table `user_lesson_progress`
--

DROP TABLE IF EXISTS `user_lesson_progress`;
CREATE TABLE `user_lesson_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_lesson_progress`
--

INSERT INTO `user_lesson_progress` (`id`, `user_id`, `lesson_id`, `is_completed`, `completed_at`, `created_at`, `updated_at`) VALUES
(10, 1, 5, 1, '2026-04-09 16:46:26', '2026-04-09 16:46:26', '2026-04-09 16:46:26'),
(11, 2, 5, 1, '2026-04-09 16:54:06', '2026-04-09 16:54:06', '2026-04-09 16:54:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `journeys`
--
ALTER TABLE `journeys`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journey_id` (`journey_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_journeys`
--
ALTER TABLE `user_journeys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_journey` (`user_id`,`journey_id`),
  ADD KEY `fk_user_journeys_journey` (`journey_id`);

--
-- Indexes for table `user_lesson_progress`
--
ALTER TABLE `user_lesson_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_lesson` (`user_id`,`lesson_id`),
  ADD KEY `fk_user_lesson_progress_lesson` (`lesson_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `journeys`
--
ALTER TABLE `journeys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_journeys`
--
ALTER TABLE `user_journeys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_lesson_progress`
--
ALTER TABLE `user_lesson_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`journey_id`) REFERENCES `journeys` (`id`);

--
-- Constraints for table `user_journeys`
--
ALTER TABLE `user_journeys`
  ADD CONSTRAINT `fk_user_journeys_journey` FOREIGN KEY (`journey_id`) REFERENCES `journeys` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_journeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_lesson_progress`
--
ALTER TABLE `user_lesson_progress`
  ADD CONSTRAINT `fk_user_lesson_progress_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_lesson_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

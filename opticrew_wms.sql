-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 28, 2025 at 05:25 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `opticrew_wms`
--

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills`)),
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `pay_period` varchar(50) DEFAULT NULL,
  `max_hours_per_3weeks` int(11) DEFAULT NULL,
  `trial_period_months` int(11) DEFAULT NULL,
  `collective_agreement` varchar(255) DEFAULT NULL,
  `insurance` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `principal_duties` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `skills`, `hourly_rate`, `pay_period`, `max_hours_per_3weeks`, `trial_period_months`, `collective_agreement`, `insurance`, `start_date`, `end_date`, `principal_duties`, `created_at`) VALUES
(1, 'Vincent Digol', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-01-15', NULL, 'Senior cleaner with maintenance skills', '2025-09-21 11:20:26'),
(2, 'Mikaela Y. Leonardo', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-02-01', NULL, 'Accommodation specialist', '2025-09-21 11:20:26'),
(3, 'Martin Leonardo', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2023-08-10', NULL, 'Team leader', '2025-09-21 11:20:26'),
(4, 'Anna Korhonen', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-03-01', NULL, 'Specialist in deep cleaning', '2025-09-21 11:20:26'),
(5, 'Jukka Virtanen', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2023-05-15', NULL, 'Maintenance specialist', '2025-09-21 11:20:26'),
(6, 'Liisa Peltonen', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-01-20', NULL, 'Multi-skilled restaurant worker', '2025-09-21 11:20:26'),
(7, 'Mikael Saarinen', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-02-15', NULL, 'Outdoor specialist', '2025-09-21 11:20:26'),
(8, 'Sari Nieminen', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2023-09-01', NULL, 'Quality assurance specialist', '2025-09-21 11:20:26'),
(9, 'Heikki Laine', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-03-10', '2025-12-31', 'Temporary worker', '2025-09-21 11:20:26'),
(14, 'Lotis Digol', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2025-09-01', NULL, 'Cleaning accommodations, Maintenance, Restaurant Staff', '2025-09-21 08:39:54'),
(15, 'Rey Digol', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2025-09-01', NULL, 'Cleaning accommodations, Maintenance, Restaurant Staff', '2025-09-21 08:41:27'),
(17, 'Therese Digol', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2025-09-01', NULL, 'Cleaning accommodations, Maintenance, Restaurant Staff', '2025-09-21 09:52:00'),
(18, 'Ashley Bulalacao', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2025-09-01', NULL, 'Cleaning accommodations, Maintenance, Restaurant Staff', '2025-09-21 09:52:45'),
(19, 'Adam Balona', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2025-09-10', NULL, 'Cleaning accommodations, Maintenance, Restaurant Staff', '2025-09-21 09:53:10'),
(20, 'Leiramarie San Buenaventura', '[\"cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2025-09-10', NULL, 'Cleaning accommodations, Maintenance, Restaurant Staff', '2025-09-21 09:53:56');

-- --------------------------------------------------------

--
-- Table structure for table `employee_availability`
--

CREATE TABLE `employee_availability` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_slot` varchar(50) NOT NULL,
  `is_available` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_availability`
--

INSERT INTO `employee_availability` (`id`, `employee_id`, `date`, `time_slot`, `is_available`) VALUES
(1, 1, '2024-12-15', '09:00-17:00', 1),
(5, 2, '2024-12-15', '09:00-17:00', 1),
(9, 4, '2024-12-15', '09:00-17:00', 0),
(13, 3, '2024-12-15', '09:00-17:00', 1),
(17, 5, '2024-12-15', '09:00-17:00', 1),
(21, 9, '2024-12-15', '09:00-17:00', 1),
(25, 6, '2024-12-15', '09:00-17:00', 1),
(29, 7, '2024-12-15', '09:00-17:00', 1),
(33, 8, '2024-12-15', '09:00-17:0', 1),
(37, 9, '2025-09-22', '09:00-21:00', 1),
(43, 9, '2025-09-21', '09:00-21:00', 1),
(49, 4, '2025-09-21', '09:00-21:00', 1),
(55, 5, '2025-09-21', '09:00-21:00', 1),
(61, 6, '2025-09-21', '09:00-21:00', 1),
(67, 3, '2025-09-21', '09:00-21:00', 1),
(73, 7, '2025-09-21', '09:00-21:00', 1),
(79, 2, '2025-09-21', '09:00-21:00', 1),
(85, 8, '2025-09-21', '09:00-21:00', 1),
(91, 1, '2025-09-21', '09:00-21:00', 1),
(103, 2, '2025-09-22', '09:00-21:00', 1),
(109, 4, '2025-09-22', '09:00-21:00', 1),
(115, 5, '2025-09-24', '09:00-21:00', 1),
(121, 3, '2025-09-24', '09:00-17:00', 1),
(122, 9, '2025-09-24', '09:00-17:00', 1),
(123, 6, '2025-09-24', '09:00-17:00', 1),
(124, 4, '2025-09-24', '09:00-17:43', 1),
(125, 1, '2025-09-24', '09:00-17:01', 1),
(126, 8, '2025-09-24', '09:00-17:00', 1),
(138, 4, '2025-09-23', '09:00-', 1),
(139, 2, '2025-09-23', '09:00-', 1),
(140, 1, '2025-09-23', '09:00-', 1),
(141, 9, '2025-09-23', '09:00-', 1),
(142, 5, '2025-09-23', '09:00-', 1),
(143, 3, '2025-09-23', '09:00-', 1),
(144, 6, '2025-09-23', '09:00-', 1),
(145, 7, '2025-09-23', '09:00-', 1),
(146, 8, '2025-09-23', '09:00-', 1),
(149, 20, '2025-09-23', '09:00-17:00', 1),
(150, 14, '2025-09-23', '09:00-17:00', 1),
(152, 17, '2025-09-23', '09:00-17:00', 1),
(153, 1, '2025-09-23', '09:00-17:00', 1),
(154, 15, '2025-09-23', '09:00-17:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `required_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_skills`)),
  `estimated_duration` int(11) DEFAULT NULL,
  `priority_level` varchar(50) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `time_slot` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `required_skills`, `estimated_duration`, `priority_level`, `scheduled_date`, `time_slot`, `location`, `team_id`, `status`, `created_at`) VALUES
(1, 'URGENT: Glass Igloo #7 Deep Clean', '[\"deep_cleaning\", \"window_cleaning\"]', 150, 'urgent', '2024-12-15', '09:00-11:30', 'Aikamatkat Glass Igloo Park', 3, 'completed', '2025-09-21 03:20:26'),
(2, 'URGENT: Emergency Suite Prep', '[\"deep_cleaning\", \"room_preparation\"]', 150, 'urgent', '2024-12-15', '09:00-11:30', 'Kakslautanen Arctic Resort', 2, 'completed', '2025-09-21 03:20:26'),
(3, 'Restaurant Patio Maintenance', '[\"maintenance\", \"restaurant_service\"]', 120, 'high', '2024-12-15', '12:00-14:00', 'Kakslautanen Main Restaurant', 2, 'completed', '2025-09-21 03:20:26'),
(4, 'Lobby Maintenance Check', '[\"maintenance\", \"cleaning\"]', 60, 'low', '2024-12-15', '14:00-15:00', 'Main Lobby', 2, 'completed', '2025-09-21 03:20:26'),
(5, 'VIP Welcome Prep - CRITICAL', '[\"customer_service\", \"quality_control\"]', 45, 'high', '2024-12-15', '16:00-17:00', 'VIP Reception Area', 1, 'completed', '2025-09-21 03:20:26'),
(6, '[Daily Cleaning] Aikamatkat Travel Services - Waiting Lounge', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Waiting Lounge', NULL, 'pending', '2025-09-22 05:19:12'),
(7, '[Daily Cleaning] Aikamatkat Travel Services - Office 1', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Office 1', NULL, 'pending', '2025-09-22 05:19:12'),
(8, '[Daily Cleaning] Aikamatkat Travel Services - Office 3', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Office 3', 1, 'completed', '2025-09-22 05:19:12'),
(9, '[Daily Cleaning] Aikamatkat Travel Services - Meeting Room A', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Meeting Room A', 3, 'completed', '2025-09-22 05:19:12'),
(10, '[Daily Cleaning] Aikamatkat Travel Services - Kitchen Area', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Kitchen Area', NULL, 'pending', '2025-09-22 05:19:12'),
(11, '[Daily Cleaning] Aikamatkat Travel Services - Common Areas', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Common Areas', NULL, 'pending', '2025-09-22 05:19:12'),
(12, '[Daily Cleaning] Aikamatkat Travel Services - Storage Room', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Storage Room', NULL, 'pending', '2025-09-22 05:19:12'),
(13, '[Daily Cleaning] Aikamatkat Travel Services - Parking Area', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Parking Area', NULL, 'pending', '2025-09-22 05:19:12'),
(14, '[Deep Cleaning] Kakslautanen Arctic Resort - Cabin 5', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Cabin 5', NULL, 'pending', '2025-09-22 07:32:48'),
(15, '[Deep Cleaning] Kakslautanen Arctic Resort - Sauna Area', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '13:00-15:00', 'Sauna Area', NULL, 'pending', '2025-09-22 07:32:48'),
(16, '[Deep Cleaning] for Emmaus Digol', '[\"cleaning\"]', 60, 'high', '2025-09-23', '13:00-15:00', 'External Client', NULL, 'pending', '2025-09-22 07:34:31'),
(17, '[Daily Cleaning] Glass Igloo Village - Igloo 1', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '09:00-11:00', 'Igloo 1', NULL, 'pending', '2025-09-22 09:49:43'),
(18, '[Daily Cleaning] Glass Igloo Village - Igloo 2', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '09:00-11:00', 'Igloo 2', NULL, 'pending', '2025-09-22 09:49:43'),
(19, '[Daily Cleaning] Glass Igloo Village - Igloo 3', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '09:00-11:00', 'Igloo 3', NULL, 'pending', '2025-09-22 09:49:43'),
(20, '[Snow-Out] Aikamatkat Travel Services - Reception Area', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '11:00-13:00', 'Reception Area', NULL, 'pending', '2025-09-22 09:51:24'),
(21, '[Snow-Out] Aikamatkat Travel Services - Waiting Lounge', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '11:00-13:00', 'Waiting Lounge', NULL, 'pending', '2025-09-22 09:51:24'),
(22, '[Snow-Out] Aikamatkat Travel Services - Office 1', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '11:00-13:00', 'Office 1', NULL, 'pending', '2025-09-22 09:51:24'),
(23, '[Snow-Out] Aikamatkat Travel Services - Office 2', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '11:00-13:00', 'Office 2', NULL, 'pending', '2025-09-22 09:51:24'),
(24, '[Snow-Out] for Lotis Digol', '[\"cleaning\"]', 60, 'high', '2025-09-23', '13:00-15:00', 'External Client', NULL, 'pending', '2025-09-22 10:36:04'),
(25, '[Deep Cleaning] for Rey Digol', '[\"cleaning\"]', 120, 'high', '2025-09-23', '15:00-17:00', 'External Client', NULL, 'pending', '2025-09-22 10:48:51'),
(26, '[Snow-Out] for Therese Digol', '[\"cleaning\"]', 60, 'low', '2025-09-23', '17:00-19:00', 'External Client', NULL, 'pending', '2025-09-22 10:49:14'),
(27, '[Snow-Out] Kakslautanen Arctic Resort - Reception', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '19:00-21:00', 'Reception', NULL, 'pending', '2025-09-22 10:52:26'),
(28, '[Snow-Out] Kakslautanen Arctic Resort - Restaurant', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '19:00-21:00', 'Restaurant', NULL, 'pending', '2025-09-22 10:52:26'),
(29, '[Snow-Out] Kakslautanen Arctic Resort - Sauna Area', '[\"cleaning\"]', 120, 'medium', '2025-09-23', '19:00-21:00', 'Sauna Area', NULL, 'pending', '2025-09-22 10:52:26'),
(30, '[Public/Common Area] for Ashley Bulalacao', '[\"cleaning\"]', 60, 'high', '2025-09-23', '19:00-21:00', 'External Client', NULL, 'pending', '2025-09-22 10:53:04');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `team_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `team_name`, `created_at`) VALUES
(1, 'Alpha Team - Accommodation Specialists', '2025-09-21 11:20:26'),
(2, 'Beta Team - Maintenance & Deep Clean', '2025-09-21 11:20:26'),
(3, 'Gamma Team - Restaurant & Guest Services', '2025-09-21 11:20:26'),
(9, 'Auto-Team 2025-09-23', '2025-09-23 08:41:27'),
(11, 'Temp-Team 2025-09-23 11:52', '2025-09-23 09:52:45');

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `team_id`, `employee_id`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 4),
(4, 2, 3),
(5, 2, 5),
(6, 2, 9),
(7, 3, 6),
(8, 3, 7),
(9, 3, 8),
(31, 11, 18),
(36, 11, 20),
(54, 9, 15),
(58, 9, 19),
(59, 11, 14),
(60, 9, 17);

-- --------------------------------------------------------

--
-- Table structure for table `temporary_assignments`
--

CREATE TABLE `temporary_assignments` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `original_team_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `temporary_assignments`
--

INSERT INTO `temporary_assignments` (`id`, `employee_id`, `original_team_id`) VALUES
(5, 7, 3),
(6, 14, 9);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_availability`
--
ALTER TABLE `employee_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `temporary_assignments`
--
ALTER TABLE `temporary_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `employee_availability`
--
ALTER TABLE `employee_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `temporary_assignments`
--
ALTER TABLE `temporary_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employee_availability`
--
ALTER TABLE `employee_availability`
  ADD CONSTRAINT `employee_availability_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `team_members_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_members_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

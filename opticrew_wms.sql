-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2025 at 08:05 AM
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
(1, 'Vincent Digol', '[\"cleaning\", \"maintenance\", \"customer_service\", \"deep_cleaning\", \"restroom_cleaning\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-01-15', NULL, 'Senior cleaner with maintenance skills', '2025-09-21 11:20:26'),
(2, 'Mikaela Y. Leonardo', '[\"cleaning\", \"restaurant_service\", \"customer_service\", \"laundry\", \"room_preparation\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-02-01', NULL, 'Accommodation specialist', '2025-09-21 11:20:26'),
(3, 'Martin Leonardo', '[\"cleaning\", \"maintenance\", \"inventory_management\", \"quality_control\", \"team_leadership\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2023-08-10', NULL, 'Team leader', '2025-09-21 11:20:26'),
(4, 'Anna Korhonen', '[\"cleaning\", \"deep_cleaning\", \"window_cleaning\", \"floor_care\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-03-01', NULL, 'Specialist in deep cleaning', '2025-09-21 11:20:26'),
(5, 'Jukka Virtanen', '[\"maintenance\", \"plumbing\", \"electrical_basic\", \"repair_work\", \"inventory_management\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2023-05-15', NULL, 'Maintenance specialist', '2025-09-21 11:20:26'),
(6, 'Liisa Peltonen', '[\"cleaning\", \"restaurant_service\", \"laundry\", \"customer_service\", \"food_handling\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-01-20', NULL, 'Multi-skilled restaurant worker', '2025-09-21 11:20:26'),
(7, 'Mikael Saarinen', '[\"cleaning\", \"maintenance\", \"garden_work\", \"snow_removal\", \"vehicle_maintenance\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-02-15', NULL, 'Outdoor specialist', '2025-09-21 11:20:26'),
(8, 'Sari Nieminen', '[\"cleaning\", \"quality_control\", \"customer_service\", \"training\", \"scheduling\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2023-09-01', NULL, 'Quality assurance specialist', '2025-09-21 11:20:26'),
(9, 'Heikki Laine', '[\"cleaning\", \"deep_cleaning\", \"carpet_cleaning\", \"upholstery_care\"]', 13.00, 'monthly', 90, 3, 'Property Service Sector Collective Agreement', 'Ilmarinen, TyEL, Työtapaturma, Työllisyysrahasto, Unemployment Benefits', '2024-03-10', '2025-12-31', 'Temporary worker', '2025-09-21 11:20:26');

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
(126, 8, '2025-09-24', '09:00-17:00', 1);

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
(1, 'URGENT: Glass Igloo #7 Deep Clean', '[\"deep_cleaning\", \"window_cleaning\"]', 150, 'urgent', '2024-12-15', '09:00-11:30', 'Aikamatkat Glass Igloo Park', NULL, 'completed', '2025-09-21 11:20:26'),
(2, 'URGENT: Emergency Suite Prep', '[\"deep_cleaning\", \"room_preparation\"]', 150, 'urgent', '2024-12-15', '09:00-11:30', 'Kakslautanen Arctic Resort', NULL, 'completed', '2025-09-21 11:20:26'),
(3, 'Restaurant Patio Maintenance', '[\"maintenance\", \"restaurant_service\"]', 120, 'high', '2024-12-15', '12:00-14:00', 'Kakslautanen Main Restaurant', NULL, 'completed', '2025-09-21 11:20:26'),
(4, 'Lobby Maintenance Check', '[\"maintenance\", \"cleaning\"]', 60, 'low', '2024-12-15', '14:00-15:00', 'Main Lobby', 3, 'completed', '2025-09-21 11:20:26'),
(5, 'VIP Welcome Prep - CRITICAL', '[\"customer_service\", \"quality_control\"]', 45, 'high', '2024-12-15', '16:00-17:00', 'VIP Reception Area', NULL, 'completed', '2025-09-21 11:20:26'),
(29, '[Daily Cleaning] Aikamatkat Travel Services - Waiting Lounge', '[\"general\"]', 120, 'medium', '2025-09-23', '11:00-13:00', 'Waiting Lounge', 3, 'pending', '2025-09-22 04:13:45'),
(30, '[Daily Cleaning] Aikamatkat Travel Services - Office 2', '[\"general\"]', 120, 'medium', '2025-09-23', '11:00-13:00', 'Office 2', 2, 'pending', '2025-09-22 04:13:45'),
(31, '[Deep Cleaning] Kakslautanen Arctic Resort - Sauna Area', '[\"general\"]', 120, 'medium', '2025-09-23', '11:00-13:00', 'Sauna Area', NULL, 'completed', '2025-09-22 04:14:10'),
(32, '[Snow-Out] for Emmaus Digol', '[\"general\"]', 120, 'high', '2025-09-23', '13:00-15:00', 'External Client', 1, 'pending', '2025-09-22 04:14:44'),
(33, '[Daily Cleaning] Glass Igloo Village - Igloo 3', '[\"cleaning\"]', 120, 'medium', '2025-09-24', '13:00-15:00', 'Igloo 3', 1, 'pending', '2025-09-22 04:22:13'),
(34, '[Daily Cleaning] Glass Igloo Village - Igloo 4', '[\"cleaning\"]', 120, 'medium', '2025-09-24', '13:00-15:00', 'Igloo 4', 3, 'pending', '2025-09-22 04:22:13'),
(35, '[Daily Cleaning] Glass Igloo Village - Igloo 5', '[\"cleaning\"]', 120, 'medium', '2025-09-24', '13:00-15:00', 'Igloo 5', NULL, 'pending', '2025-09-22 04:22:13'),
(36, '[Daily Cleaning] Glass Igloo Village - Igloo 9', '[\"cleaning\"]', 120, 'medium', '2025-09-24', '13:00-15:00', 'Igloo 9', 2, 'pending', '2025-09-22 04:22:13');

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
(3, 'Gamma Team - Restaurant & Guest Services', '2025-09-21 11:20:26');

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
(9, 3, 8);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employee_availability`
--
ALTER TABLE `employee_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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

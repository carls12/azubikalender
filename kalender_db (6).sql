-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 22, 2026 at 07:40 PM
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
-- Database: `kalender_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `azubis`
--

CREATE TABLE `azubis` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `default_code` varchar(100) NOT NULL,
  `role` enum('azubi') DEFAULT 'azubi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_code` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `azubis`
--

INSERT INTO `azubis` (`id`, `name`, `username`, `password_hash`, `default_code`, `role`, `created_at`, `updated_at`, `reset_code`) VALUES
(1, 'Sophos Budde', NULL, NULL, 'SOPHOS257A', 'azubi', '2025-10-06 17:34:17', '2026-01-22 02:59:04', 'SOPHOS257A'),
(2, 'Erik-Noah Hildebrandt', NULL, NULL, 'ERIKN638B', 'azubi', '2025-10-06 17:34:17', '2026-01-22 02:59:04', 'ERIKN638B'),
(3, 'Luca Ferreira Wolters', NULL, NULL, 'LUCAF291C', 'azubi', '2025-10-06 17:34:17', '2026-01-22 02:59:04', 'LUCAF291C'),
(4, 'Erik Koitka', 'erika', '$2y$10$bFGN7eJ8Cep4WxIfffKea.V5z7Td8yOPuQeyHtSP8CFeZbunQv0JO', '', 'azubi', '2025-10-06 17:34:17', '2026-01-22 02:59:04', ''),
(5, 'Jasper Göbel', NULL, NULL, 'JASPER569E', 'azubi', '2025-10-06 17:34:17', '2026-01-22 02:59:04', 'JASPER569E'),
(6, 'Nikolai Bahtinov', NULL, NULL, 'NIKOLAI340F', 'azubi', '2025-10-06 17:34:17', '2026-01-22 02:59:04', 'NIKOLAI340F'),
(7, 'Sofiia Harmatiuk', NULL, NULL, 'SOFIIA972G', 'azubi', '2025-10-06 17:34:17', '2026-01-22 02:59:04', 'SOFIIA972G'),
(15, 'Fru Ndu', 'john12', '$2y$10$irXpUIb66GIunUxBg8upq.qlcJKIo9U2HGolDsgD9RTPHIb3BNDlm', 'RFNYTFLM9', 'azubi', '2026-01-22 02:35:09', '2026-01-22 02:59:04', 'RFNYTFLM9'),
(17, 'Calson Ubejum', 'johnccc', '$2y$10$o3iFEcYUychpKV0fdWFRtOgTk9SACpbT3Ki4etv9nAItiVL1ugfqa', 'FBB47864', 'azubi', '2026-01-22 03:11:19', '2026-01-22 03:13:07', 'CE9BF0F5');

-- --------------------------------------------------------

--
-- Table structure for table `azubi_notes`
--

CREATE TABLE `azubi_notes` (
  `id` int(11) NOT NULL,
  `azubi_name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `subject` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `azubi_notes`
--

INSERT INTO `azubi_notes` (`id`, `azubi_name`, `date`, `subject`) VALUES
(2, 'Sophos Budde', '2025-03-05', 'Berufsschule'),
(3, 'Sophos Budde', '2025-03-06', 'Berufsschule'),
(4, 'Sophos Budde', '2025-09-03', 'Berufsschule'),
(6, 'Sophos Budde', '2025-09-05', 'Berufsschule'),
(8, 'Sophos Budde', '2025-09-09', 'Berufsschule'),
(9, 'Sophos Budde', '2025-09-10', 'Berufsschule'),
(10, 'Sophos Budde', '2025-09-11', 'Berufsschule'),
(11, 'Sophos Budde', '2025-09-12', 'Berufsschule'),
(43, 'Erik Koitka', '2025-10-02', 'SOC - Teil 2'),
(44, 'Erik Koitka', '2025-10-06', 'SOC - Teil 2'),
(45, 'Erik Koitka', '2025-10-07', 'SOC - Teil 2'),
(46, 'Erik Koitka', '2025-10-08', 'SOC - Teil 2'),
(47, 'Erik Koitka', '2025-10-09', 'SOC - Teil 2'),
(48, 'Frida Friday', '2025-02-06', 'SOC - Teil 2'),
(49, 'Frida Friday', '2025-02-07', 'SOC - Teil 2'),
(50, 'Frida Friday', '2025-02-10', 'SOC - Teil 2'),
(51, 'Frida Friday', '2025-02-11', 'SOC - Teil 2'),
(52, 'Frida Friday', '2025-02-12', 'SOC - Teil 2'),
(53, 'Frida Friday', '2025-02-13', 'SOC - Teil 2'),
(54, 'Frida Friday', '2025-02-14', 'SOC - Teil 2'),
(55, 'Frida Friday', '2025-02-17', 'SOC - Teil 2'),
(56, 'Frida Friday', '2025-02-18', 'SOC - Teil 2'),
(57, 'Frida Friday', '2025-02-19', 'SOC - Teil 2'),
(58, 'Frida Friday', '2025-02-20', 'SOC - Teil 2'),
(59, 'Erik Koitka', '2025-01-02', 'Urlaub'),
(60, 'Erik Koitka', '2025-01-03', 'Urlaub'),
(61, 'Erik Koitka', '2025-01-06', 'Urlaub'),
(62, 'Erik Koitka', '2025-01-07', 'Urlaub'),
(63, 'Erik Koitka', '2025-01-08', 'Urlaub'),
(64, 'Erik Koitka', '2025-01-09', 'Urlaub'),
(65, 'Erik Koitka', '2025-01-10', 'Urlaub'),
(66, 'Jasper Göbel', '2025-10-01', 'Urlaub'),
(67, 'Jasper Göbel', '2025-10-02', 'Urlaub'),
(68, 'Jasper Göbel', '2025-10-06', 'Urlaub'),
(69, 'Jasper Göbel', '2025-10-07', 'Urlaub'),
(70, 'Jasper Göbel', '2025-10-08', 'Urlaub'),
(71, 'Jasper Göbel', '2025-10-09', 'Urlaub'),
(72, 'Erik Koitka', '2025-10-10', 'Scheduling'),
(73, 'Erik Koitka', '2025-10-13', 'Scheduling'),
(74, 'Erik Koitka', '2025-10-14', 'Scheduling'),
(75, 'Erik Koitka', '2025-10-15', 'Scheduling'),
(76, 'Erik Koitka', '2025-10-16', 'Scheduling'),
(77, 'Erik Koitka', '2025-10-17', 'Scheduling'),
(78, 'Erik Koitka', '2026-01-09', 'SAP');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('admin','ausbilder') DEFAULT 'ausbilder',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_code` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `created_at`, `reset_code`) VALUES
(1, 'carlson', '$2y$10$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', 'Carlson Tantoh', 'admin', '2025-10-06 17:53:00', 'ADMIN001'),
(2, 'john', '$2y$10$vPiIoMHBFn8C3d1mwVIe1.0nXOXHmCHHbpluZc56U.ofUDz2qlCpC', 'John Ndi', 'admin', '2025-10-06 18:25:49', NULL),
(3, 'Michel', '$2y$10$ZgibrFqgX5mVo5EHev4QA.8nXp6skCTS4FEGcarHgpbtBZ7sTNmiq', 'Momo Michel', 'admin', '2025-10-07 23:43:48', 'ADN123'),
(4, 'jiji', '$2y$10$HT0j0XCIasrGceOM1SpkYev5qCjYZqoXGx4u6EiIC/BE2CpQBmYF.', 'Jiji', 'ausbilder', '2026-01-22 02:19:08', NULL),
(5, 'ente', '$2y$10$weZZMag1nOS8rp3ZfLBHG.PfxrEkZv5H6h5C2hmF85TOLF5z/VOCG', 'Ente', 'admin', '2026-01-22 02:20:11', NULL),
(6, 'hrons', '$2y$10$U6rplhPgwaL3gAZXU3o1MO/OwOidwv7tvwb6qMuXwjEgOBK/vHO0G', 'Helga Rons', '', '2026-01-22 02:29:21', NULL),
(7, 'hellohons', '$2y$10$8c3Gh0r.CGtrn1P/MTnOiefsKAWB4PkLT5sUrHWNytOFPHt9BJuHK', 'Hons Hallo', 'admin', '2026-01-22 02:34:35', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `azubis`
--
ALTER TABLE `azubis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `default_code` (`default_code`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `reset_code` (`reset_code`);

--
-- Indexes for table `azubi_notes`
--
ALTER TABLE `azubi_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_note` (`azubi_name`,`date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `reset_code` (`reset_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `azubis`
--
ALTER TABLE `azubis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `azubi_notes`
--
ALTER TABLE `azubi_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

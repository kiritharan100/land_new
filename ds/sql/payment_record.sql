-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 23, 2025 at 11:00 AM
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
-- Database: `land_administration`
--

-- --------------------------------------------------------

--
-- Table structure for table `payment_record`
--

CREATE TABLE `payment_record` (
  `id` int(11) NOT NULL,
  `pay_cat_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `location_id` int(11) NOT NULL,
  `location_serial` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `create_by` int(11) NOT NULL,
  `created_on` datetime NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_record`
--

INSERT INTO `payment_record` (`id`, `pay_cat_id`, `payment_date`, `location_id`, `location_serial`, `amount`, `create_by`, `created_on`, `status`) VALUES
(1, 1, '2025-12-23', 40, 1, 360.00, 1, '2025-12-23 14:58:31', 1),
(2, 1, '2025-12-23', 40, 40, 25000.00, 1, '2025-12-23 15:04:29', 0),
(3, 1, '2025-12-22', 40, 41, 22.00, 1, '2025-12-23 15:08:11', 1),
(4, 1, '2025-12-23', 40, 42, 223.00, 1, '2025-12-23 15:20:22', 1),
(5, 1, '2025-12-23', 40, 43, 22.00, 1, '2025-12-23 15:27:35', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `payment_record`
--
ALTER TABLE `payment_record`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `payment_record`
--
ALTER TABLE `payment_record`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

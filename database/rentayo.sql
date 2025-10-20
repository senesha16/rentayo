-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 13, 2025 at 03:10 PM
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
-- Database: `rentayo`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`) VALUES
(1, 'Accountancy'),
(2, 'Business Administration'),
(3, 'Civil Engineering'),
(4, 'Computer Science'),
(5, 'Information Technology'),
(6, 'Medical Technology'),
(7, 'Nursing'),
(8, 'Psychology');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `itemcategories`
--

CREATE TABLE `itemcategories` (
  `item_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `itemcategories`
--

INSERT INTO `itemcategories` (`item_id`, `category_id`) VALUES
(1, 1),
(1, 3),
(2, 1),
(3, 3),
(4, 3),
(5, 3),
(6, 5),
(7, 1),
(7, 5),
(8, 1),
(9, 4),
(10, 4),
(11, 4),
(12, 7),
(13, 7),
(14, 6),
(15, 6),
(16, 2),
(17, 1),
(18, 5),
(19, 7),
(19, 8),
(20, 5),
(21, 2),
(22, 1),
(22, 3),
(22, 6),
(25, 1),
(26, 1);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `lender_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `price_per_day` decimal(10,2) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `available_items_count` int(255) NOT NULL DEFAULT 1,
  `location_latitude` decimal(10,8) DEFAULT NULL,
  `location_longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `lender_id`, `title`, `description`, `price_per_day`, `is_available`, `available_items_count`, `location_latitude`, `location_longitude`, `created_at`, `image_url`) VALUES
(1, 1, 'Toilet', 'Pwede upuan pero walang butas', 100.00, 1, 50, NULL, NULL, '2025-08-24 15:44:21', ''),
(2, 1, 'Mega phone', 'Pero pabulong ang gamit', 300.00, 1, 1, NULL, NULL, '2025-08-24 15:44:51', ''),
(3, 1, 'Walis', 'Pero kalbo na', 290.00, 1, 1, NULL, NULL, '2025-08-24 15:45:10', ''),
(4, 1, 'Mop', 'kulang sa hugas', 345.00, 1, 33, NULL, NULL, '2025-08-24 15:45:30', ''),
(5, 1, 'vaccum', 'hindi nahigop', 230.00, 1, 1, NULL, NULL, '2025-08-24 15:45:57', ''),
(6, 1, 'Vase', 'Medjo Sinumpa', 345.00, 1, 1, NULL, NULL, '2025-08-24 15:46:12', ''),
(7, 1, 'Mona lisa', 'Minsan mona Madalas Lisa', 456.00, 1, 1, NULL, NULL, '2025-08-24 15:46:40', ''),
(8, 1, 'Regine Velasquez', 'MAHAL NA MAHAL KO TOH', 99999999.99, 1, 1, NULL, NULL, '2025-08-24 15:47:04', ''),
(9, 1, 'frying pan', 'pero d nakaka luto', 95.00, 1, 1, NULL, NULL, '2025-08-24 15:47:36', ''),
(10, 1, 'Blender', 'Pero Mano mano', 76.00, 1, 1, NULL, NULL, '2025-08-24 15:48:18', ''),
(11, 1, 'pepper shaker', 'D mo kaya pepper shaker', 12345.00, 1, 1, NULL, NULL, '2025-08-24 15:48:56', ''),
(12, 1, 'Payong', 'butas butas', 785.00, 1, 1, NULL, NULL, '2025-08-24 15:49:20', ''),
(13, 1, 'Bazooka', 'Pag bored ka pwede toh', 124.00, 1, 1, NULL, NULL, '2025-08-24 15:49:45', ''),
(14, 1, 'Monitor', 'Mahal', 1.00, 1, 1, NULL, NULL, '2025-08-24 15:50:12', ''),
(15, 1, 'Kareoke booth', 'kanta lang ni regine meron', 3000.00, 1, 1, NULL, NULL, '2025-08-24 15:50:56', ''),
(16, 1, 'RJ 45', 'gusto are ni sir', 85.00, 1, 1, NULL, NULL, '2025-08-24 15:51:12', ''),
(17, 2, 'sir joel', 'everytime na may lesson maga cable', 150.00, 1, 1, NULL, NULL, '2025-09-23 15:41:00', 'uploads/items/item_68d5828cbeba7.jpg'),
(18, 1, 'Matcha', 'para sa mga IT student na nakakaramdam ng matchakittt', 100.00, 1, 1, NULL, NULL, '2025-09-25 13:00:48', 'uploads/items/item_68d53d00dbf27.png'),
(19, 1, 'my bf', 'guard may baliw (yung nagpost)', 143.00, 1, 143, NULL, NULL, '2025-09-25 13:19:45', 'uploads/items/item_68d5417140e9e.jpg'),
(20, 1, 'Kea', 'taga picture tuwing monthsary niyo', 1.00, 1, 1, NULL, NULL, '2025-09-25 15:21:58', 'uploads/items/item_68d55e16c70b9.jpg'),
(21, 1, 'ewan', 'xaxaxaxa', 100.00, 1, 199, NULL, NULL, '2025-09-25 15:51:13', 'uploads/items/item_68d564f153b66.jpg'),
(22, 1, 'WHAT', 'dadadaddddq2wrWQARTQ', 100.00, 1, 1201212, NULL, NULL, '2025-09-25 15:56:17', 'uploads/items/item_68d566219574c.jpg'),
(25, 2, '121', '12', 12312.00, 1, 1, NULL, NULL, '2025-10-05 22:03:21', 'uploads/items/item_68e2eb2979c7a.jpeg'),
(26, 3, '123', '23', 123.00, 1, 1, NULL, NULL, '2025-10-05 22:03:45', 'uploads/items/item_68e2eb4199c7b.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `item_images`
--

CREATE TABLE `item_images` (
  `image_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_images`
--

INSERT INTO `item_images` (`image_id`, `item_id`, `image_url`, `is_primary`, `sort_order`, `uploaded_at`) VALUES
(1, 22, 'uploads/items/item_68d566219574c.jpg', 1, 0, '2025-09-25 15:56:17'),
(2, 22, 'uploads/items/item_68d5662195a08.jpg', 0, 1, '2025-09-25 15:56:17'),
(3, 22, 'uploads/items/item_68d56621962a9.jpg', 0, 2, '2025-09-25 15:56:17'),
(4, 17, 'uploads/items/item_68d5828cbeba7.jpg', 1, 0, '2025-09-25 17:57:32'),
(5, 25, 'uploads/items/item_68e2eb2979c7a.jpeg', 1, 0, '2025-10-05 22:03:21'),
(6, 26, 'uploads/items/item_68e2eb4199c7b.jpeg', 1, 0, '2025-10-05 22:03:45'),
(7, 26, 'uploads/items/item_26_1759702394_0.png', 0, 1, '2025-10-05 22:13:14');

-- --------------------------------------------------------

--
-- Table structure for table `item_photos`
--

CREATE TABLE `item_photos` (
  `photo_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `photo_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `message_text` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `item_id`, `message_text`, `sent_at`, `is_read`) VALUES
(1, 2, 1, 16, 'hi', '2025-09-23 15:35:25', 1),
(2, 3, 2, 17, 'hi', '2025-09-23 15:41:11', 1),
(3, 2, 3, 17, 'hello po', '2025-09-23 15:41:25', 1),
(4, 3, 2, 17, 'hi pa kiss', '2025-09-23 15:48:01', 1),
(5, 2, 3, 17, 'ayoko yah', '2025-09-23 15:48:06', 1),
(6, 2, 2, 17, 'hi', '2025-09-23 15:58:52', 1),
(7, 3, 2, 17, 'hi po', '2025-09-23 15:59:21', 1),
(8, 2, 3, 17, 'ihh', '2025-09-23 15:59:32', 1),
(9, 3, 2, 17, 'hi po pabili po nyan', '2025-09-23 16:01:21', 1),
(10, 3, 2, 17, 'hi', '2025-09-23 16:03:29', 1),
(11, 2, 3, 17, 'panget', '2025-09-23 16:03:40', 1),
(12, 3, 2, 17, 'tite', '2025-09-23 16:22:18', 1),
(13, 2, 2, 17, 'ss', '2025-09-23 17:08:20', 1),
(14, 2, 2, 17, 'hi helo', '2025-09-23 17:08:23', 1),
(15, 2, 2, 17, 'fix ko pa to', '2025-09-23 17:08:29', 1),
(16, 1, 1, 16, 'yo', '2025-09-25 06:18:43', 1),
(17, 1, 2, 17, 'sup', '2025-09-25 06:19:01', 1),
(18, 2, 2, NULL, 'ano po', '2025-09-25 18:02:23', 1),
(19, 2, 1, 17, 'luh??', '2025-10-05 22:55:49', 0);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','gcash','bank_transfer') NOT NULL,
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `rating_id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `rated_by` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rentals`
--

CREATE TABLE `rentals` (
  `rental_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `renter_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `delivery_method` enum('meetup','pickup') NOT NULL,
  `meetup_location` varchar(255) DEFAULT NULL,
  `meetup_date` datetime DEFAULT NULL,
  `payment_method` enum('cash','gcash','bank_transfer') NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rental_statistics`
--

CREATE TABLE `rental_statistics` (
  `stat_id` int(11) NOT NULL,
  `lender_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `period` enum('weekly','monthly','yearly') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_rentals` int(11) DEFAULT 0,
  `total_income` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `ID` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `profile_picture_url` varchar(255) DEFAULT NULL,
  `location_latitude` decimal(10,8) DEFAULT NULL,
  `location_longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`ID`, `username`, `password`, `email`, `phone_number`, `profile_picture_url`, `location_latitude`, `location_longitude`, `created_at`) VALUES
(1, 'demoLender', '1234', 'lender@demo.com', NULL, NULL, NULL, NULL, '2025-08-24 15:41:29'),
(2, 'val', 'Bonnie.Steady', 'vldvga@gmail.com', '09109231812', 'C:\\xampp\\htdocs\\Rentayo/uploads/profile_68d2beb4b22c47.85226552.jpeg', NULL, NULL, '2025-09-23 15:37:24'),
(3, 'adolf', 'Bonnie.Steady', 'adolf@gmail.com', '09109231812', 'C:\\xampp\\htdocs\\Rentayo/uploads/profile_68d2bedb969f32.00538440.jpg', NULL, NULL, '2025-09-23 15:38:03'),
(4, 'kea', '1234', 'kea@gmail.com', '1234', 'C:\\xampp\\htdocs\\Rentayo/uploads/profile_68da18b76b8753.12330763.jpg', NULL, NULL, '2025-09-29 05:27:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `itemcategories`
--
ALTER TABLE `itemcategories`
  ADD PRIMARY KEY (`item_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `lender_id` (`lender_id`);

--
-- Indexes for table `item_images`
--
ALTER TABLE `item_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `is_primary` (`is_primary`),
  ADD KEY `sort_order` (`sort_order`);

--
-- Indexes for table `item_photos`
--
ALTER TABLE `item_photos`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `rental_id` (`rental_id`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `rental_id` (`rental_id`),
  ADD KEY `rated_by` (`rated_by`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `rentals`
--
ALTER TABLE `rentals`
  ADD PRIMARY KEY (`rental_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `renter_id` (`renter_id`);

--
-- Indexes for table `rental_statistics`
--
ALTER TABLE `rental_statistics`
  ADD PRIMARY KEY (`stat_id`),
  ADD KEY `lender_id` (`lender_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `item_images`
--
ALTER TABLE `item_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `item_photos`
--
ALTER TABLE `item_photos`
  MODIFY `photo_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rentals`
--
ALTER TABLE `rentals`
  MODIFY `rental_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rental_statistics`
--
ALTER TABLE `rental_statistics`
  MODIFY `stat_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `itemcategories`
--
ALTER TABLE `itemcategories`
  ADD CONSTRAINT `itemcategories_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itemcategories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`lender_id`) REFERENCES `users` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `item_photos`
--
ALTER TABLE `item_photos`
  ADD CONSTRAINT `item_photos_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`rental_id`) ON DELETE CASCADE;

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`rental_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`rated_by`) REFERENCES `users` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `ratings_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `rentals`
--
ALTER TABLE `rentals`
  ADD CONSTRAINT `rentals_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rentals_ibfk_2` FOREIGN KEY (`renter_id`) REFERENCES `users` (`ID`) ON DELETE CASCADE;

--
-- Constraints for table `rental_statistics`
--
ALTER TABLE `rental_statistics`
  ADD CONSTRAINT `rental_statistics_ibfk_1` FOREIGN KEY (`lender_id`) REFERENCES `users` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `rental_statistics_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2026 at 01:27 PM
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
-- Database: `orlene_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_approve_rental` (IN `p_rental_id` INT, IN `p_admin_id` INT)   BEGIN
  DECLARE v_status         VARCHAR(20);
  DECLARE v_product_id     INT;
  DECLARE v_product_status VARCHAR(30);

  SELECT status, product_id INTO v_status, v_product_id
  FROM rentals WHERE id = p_rental_id;

  SELECT status INTO v_product_status
  FROM products WHERE id = v_product_id;

  IF v_status != 'PENDING' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental is not in PENDING status';
  ELSEIF v_product_status != 'AVAILABLE' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Product is not available for rental';
  ELSE
    UPDATE rentals SET status = 'APPROVED' WHERE id = p_rental_id;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_complete_rental` (IN `p_rental_id` INT, IN `p_admin_id` INT, IN `p_result` VARCHAR(4), IN `p_notes` TEXT)   BEGIN
  DECLARE v_product_id INT;
  DECLARE v_status     VARCHAR(20);

  SELECT product_id, status INTO v_product_id, v_status
  FROM rentals WHERE id = p_rental_id;

  IF v_status != 'RETURNED' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental must be in RETURNED status to inspect';
  ELSE
    INSERT INTO inspections (rental_id, product_id, admin_id, result, notes)
    VALUES (p_rental_id, v_product_id, p_admin_id, p_result, p_notes);

    UPDATE rentals SET status = 'COMPLETED' WHERE id = p_rental_id;

    IF p_result = 'PASS' THEN
      
      
      
      UPDATE products SET status = 'AVAILABLE'
      WHERE id = v_product_id AND status = 'PENDING_INSPECTION';
    ELSE
      UPDATE products SET status = 'MAINTENANCE'
      WHERE id = v_product_id;

      INSERT INTO maintenance_log (product_id, reason)
      VALUES (v_product_id, CONCAT('Failed inspection after rental #', p_rental_id, ': ', p_notes));
    END IF;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_confirm_pickup` (IN `p_rental_id` INT, IN `p_user_id` INT)   BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE v_product_id INT;

  SELECT status, product_id INTO v_status, v_product_id
  FROM rentals WHERE id = p_rental_id;

  IF v_status != 'APPROVED' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental must be APPROVED before pickup';
  ELSE
    UPDATE rentals
    SET status = 'ACTIVE', pickup_at = NOW()
    WHERE id = p_rental_id;

    UPDATE products SET status = 'RENTED'
    WHERE id = v_product_id;

    INSERT INTO rental_confirmations (rental_id, confirmed_by, confirm_type)
    VALUES (p_rental_id, p_user_id, 'PICKUP');
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_confirm_return` (IN `p_rental_id` INT, IN `p_user_id` INT)   BEGIN
  DECLARE v_status     VARCHAR(20);
  DECLARE v_product_id INT;

  SELECT status, product_id INTO v_status, v_product_id
  FROM rentals WHERE id = p_rental_id;

  IF v_status NOT IN ('ACTIVE', 'OVERDUE') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental cannot be returned at this stage';
  ELSE
    UPDATE rentals
    SET status = 'RETURNED', returned_at = NOW()
    WHERE id = p_rental_id;

    UPDATE products SET status = 'PENDING_INSPECTION'
    WHERE id = v_product_id;

    INSERT INTO rental_confirmations (rental_id, confirmed_by, confirm_type)
    VALUES (p_rental_id, p_user_id, 'RETURN');
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reject_rental` (IN `p_rental_id` INT, IN `p_reason` TEXT)   BEGIN
  DECLARE v_status VARCHAR(20);

  SELECT status INTO v_status FROM rentals WHERE id = p_rental_id;

  IF v_status NOT IN ('PENDING','APPROVED') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rental cannot be rejected at this stage';
  ELSE
    UPDATE rentals
    SET status = 'REJECTED', rejection_reason = p_reason
    WHERE id = p_rental_id;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_resolve_maintenance` (IN `p_log_id` INT, IN `p_admin_id` INT, IN `p_notes` TEXT)   BEGIN
  DECLARE v_product_id INT;

  SELECT product_id INTO v_product_id
  FROM maintenance_log WHERE id = p_log_id;

  UPDATE maintenance_log
  SET resolved_at = NOW(),
      resolved_by = p_admin_id,
      resolution_notes = p_notes
  WHERE id = p_log_id;

  UPDATE products SET status = 'AVAILABLE'
  WHERE id = v_product_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_resolve_sos` (IN `p_alert_id` INT, IN `p_admin_id` INT, IN `p_notes` TEXT)   BEGIN
  UPDATE sos_alerts
  SET status = 'RESOLVED',
      resolved_at = NOW(),
      resolved_by = p_admin_id,
      resolution_notes = p_notes
  WHERE id = p_alert_id AND status = 'OPEN';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_send_maintenance` (IN `p_product_id` INT, IN `p_reason` TEXT)   BEGIN
  UPDATE products SET status = 'MAINTENANCE'
  WHERE id = p_product_id;

  INSERT INTO maintenance_log (product_id, reason)
  VALUES (p_product_id, p_reason);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'PHONE', 'Flagship smartphones and phone accessories for daily rentals'),
(2, 'GEAR', 'Adventure and outdoor equipment for hiking, camping, and cycling');

-- --------------------------------------------------------

--
-- Table structure for table `inspections`
--

CREATE TABLE `inspections` (
  `id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `result` enum('PASS','FAIL') NOT NULL,
  `notes` text DEFAULT NULL,
  `inspected_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inspections`
--

INSERT INTO `inspections` (`id`, `rental_id`, `product_id`, `admin_id`, `result`, `notes`, `inspected_at`) VALUES
(1, 1, 1, 1, 'PASS', 'Product returned in good condition, screen clean, no damage.', '2026-04-12 12:30:24'),
(2, 3, 13, 1, 'FAIL', '', '2026-04-18 15:04:52');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_log`
--

CREATE TABLE `maintenance_log` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_log`
--

INSERT INTO `maintenance_log` (`id`, `product_id`, `reason`, `sent_at`, `resolved_at`, `resolved_by`, `resolution_notes`) VALUES
(1, 13, 'Failed inspection after rental #3: ', '2026-04-18 15:04:52', '2026-04-18 15:14:35', 1, 'checked');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `subcategory_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `daily_rate` decimal(8,2) NOT NULL,
  `stock_qty` int(11) DEFAULT 1,
  `status` enum('AVAILABLE','RENTED','PENDING_INSPECTION','MAINTENANCE','RETIRED') DEFAULT 'AVAILABLE',
  `rental_count` int(11) DEFAULT 0,
  `maintenance_threshold` int(11) DEFAULT 5,
  `image_path` varchar(255) DEFAULT 'assets/img/products/default.jpg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `subcategory_id`, `name`, `description`, `daily_rate`, `stock_qty`, `status`, `rental_count`, `maintenance_threshold`, `image_path`, `created_at`) VALUES
(1, 1, 'Samsung Galaxy S25 Ultra', 'Latest Samsung flagship with 200MP camera, S Pen, 12GB RAM. Includes original charger and protective case.', 500.00, 3, 'AVAILABLE', 1, 5, 'assets/img/products/samsung_s25.jpg', '2026-04-18 06:30:24'),
(2, 1, 'Google Pixel 9 Pro', 'Google Pixel 9 Pro with AI photography features, 7 years of updates, 50MP main camera.', 450.00, 2, 'AVAILABLE', 0, 5, 'assets/img/products/pixel9.jpg', '2026-04-18 06:30:24'),
(3, 1, 'iPhone 16 Pro Max', 'Apple iPhone 16 Pro Max with A18 Pro chip, 48MP camera system, titanium design.', 550.00, 2, 'AVAILABLE', 0, 5, 'assets/img/products/iphone16.jpg', '2026-04-18 06:30:24'),
(4, 2, 'Anker 26800mAh Power Bank', 'High-capacity 26800mAh power bank. Charges 2 phones simultaneously. USB-C + USB-A ports.', 80.00, 5, 'AVAILABLE', 0, 10, 'assets/img/products/powerbank.jpg', '2026-04-18 06:30:24'),
(5, 2, 'Universal Phone Tripod Kit', 'Flexible tripod with Bluetooth remote. Works with all phones. Great for solo travel photography.', 60.00, 4, 'AVAILABLE', 0, 10, 'assets/img/products/tripod.jpg', '2026-04-18 06:30:24'),
(6, 2, 'Wide Angle Lens Kit', 'Clip-on 0.45x wide-angle lens + 15x macro. Compatible with all smartphones.', 50.00, 6, 'AVAILABLE', 0, 10, 'assets/img/products/lens_kit.jpg', '2026-04-18 06:30:24'),
(7, 3, 'Single Person Hiking Tent', 'Lightweight and easily foldable dome tent. Water-resistant. Easy 10-minute setup. Includes stakes and rainfly. Only for single person.', 200.00, 3, 'RENTED', 0, 5, 'assets/img/products/tent.jpg', '2026-04-18 06:30:24'),
(8, 3, 'Trekking Poles (Pair)', 'Aluminum anti-shock trekking poles. Adjustable 65–135cm. Comfortable cork grip.', 80.00, 5, 'RETIRED', 0, 10, 'assets/img/products/poles.jpg', '2026-04-18 06:30:24'),
(9, 3, 'Hiking Backpack 50L', 'OSPREY 50L trekking backpack with rain cover. Multiple compartments. Hip belt for comfort.', 150.00, 3, 'AVAILABLE', 0, 5, 'assets/img/products/backpack.jpg', '2026-04-18 06:30:24'),
(10, 4, 'Sleeping Bag (0°C rated)', 'Experience ultimate comfort on chilly nights with this high-performance 0°C-rated sleeping bag, designed to lock in heat while remaining incredibly lightweight. Whether you\'re trekking through mountains or car camping, its durable insulation and compact design make it the perfect gear for any adventure.', 120.00, 4, 'AVAILABLE', 0, 5, 'assets/img/products/sleeping_bag.gif', '2026-04-18 06:30:24'),
(11, 4, 'Camping Lantern + Stove Kit', 'LED lantern (500 lumens) + portable gas stove combo. Perfect for overnight camping.', 100.00, 4, 'AVAILABLE', 0, 5, 'assets/img/products/lantern_stove.jpg', '2026-04-18 06:30:24'),
(12, 4, 'Camping Cookware Set', '6-piece aluminum cookware: pot, pan, bowls, spork. Compact nesting design.', 70.00, 5, 'RENTED', 0, 10, 'assets/img/products/cookware.jpg', '2026-04-18 06:30:24'),
(13, 5, 'Trek Mountain Bike (M)', 'Trek Marlin 5 mountain bike, medium frame, 27.5\" wheels, 21-speed Shimano gears.', 350.00, 2, 'AVAILABLE', 1, 5, 'assets/img/products/prod_69e34a3d8a2e6.png', '2026-04-18 06:30:24'),
(14, 5, 'Cycling Helmet + Gloves', 'Giro cycling helmet (adjustable) + padded cycling gloves set. Multiple sizes available.', 60.00, 6, 'AVAILABLE', 0, 10, 'assets/img/products/helmet.jpg', '2026-04-18 06:30:24'),
(15, 6, 'Kayak Paddle (Fiberglass)', 'Lightweight fiberglass kayak paddle, 220cm, adjustable feather angle. For flatwater kayaking.', 130.00, 4, 'AVAILABLE', 0, 5, 'assets/img/products/kayak_paddle.jpg', '2026-04-18 06:30:24'),
(16, 7, 'Binoculars 10x42', 'High-quality 10x42 waterproof binoculars for birdwatching, hiking and camping. Wide field of view.', 90.00, 3, 'AVAILABLE', 0, 10, 'assets/img/products/binoculars.jpg', '2026-04-18 06:30:24'),
(17, 1, 'Xiaomi 15 Ultra', 'Professional-grade Leica optics meets a massive 200MP periscope zoom to turn every snapshot into a cinematic masterpiece. The 1-inch Sony LYT-900 sensor captures professional-level light and detail, while the 10cm macro mode reveals breathtaking textures.', 480.00, 3, 'AVAILABLE', 0, 5, 'assets/img/products/prod_69e3b2aba1a71.jpg', '2026-04-18 16:34:51');

-- --------------------------------------------------------

--
-- Table structure for table `rentals`
--

CREATE TABLE `rentals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` int(11) NOT NULL,
  `total_fee` decimal(10,2) NOT NULL,
  `status` enum('PENDING','APPROVED','ACTIVE','RETURNED','COMPLETED','REJECTED','OVERDUE') DEFAULT 'PENDING',
  `use_area` varchar(150) DEFAULT NULL,
  `use_city` varchar(100) DEFAULT NULL,
  `use_landmark` varchar(200) DEFAULT NULL,
  `use_lat` decimal(10,7) DEFAULT NULL,
  `use_lng` decimal(10,7) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pickup_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rentals`
--

INSERT INTO `rentals` (`id`, `user_id`, `product_id`, `start_date`, `end_date`, `days`, `total_fee`, `status`, `use_area`, `use_city`, `use_landmark`, `use_lat`, `use_lng`, `rejection_reason`, `created_at`, `pickup_at`, `returned_at`) VALUES
(1, 2, 1, '2026-04-08', '2026-04-11', 3, 1500.00, 'COMPLETED', 'Gulshan 1', 'Dhaka', 'Near Gulshan Circle 1', 23.7925000, 90.4078000, NULL, '2026-04-18 06:30:24', '2026-04-08 12:30:24', '2026-04-11 12:30:24'),
(2, 3, 7, '2026-04-16', '2026-04-19', 3, 600.00, 'ACTIVE', 'Bandarban Sadar', 'Bandarban', 'Near Nilgiri Resort', 22.1953000, 92.2184000, NULL, '2026-04-18 06:30:24', '2026-04-16 12:30:24', NULL),
(3, 2, 13, '2026-04-14', '2026-04-16', 2, 700.00, 'COMPLETED', 'Uttara Sector 11', 'Dhaka', 'Near Uttara Park', 23.8759000, 90.3795000, NULL, '2026-04-18 06:30:24', '2026-04-14 12:30:24', '2026-04-17 12:30:24'),
(4, 2, 12, '2026-04-16', '2026-04-17', 3, 210.00, 'OVERDUE', 'Savar', 'Dhaka', '', NULL, NULL, NULL, '2026-04-18 06:57:52', '2026-04-18 15:30:04', NULL),
(5, 2, 2, '2026-04-18', '2026-04-19', 1, 450.00, 'REJECTED', 'Nobinagar', 'Dhaka', '', NULL, NULL, 'no location found', '2026-04-18 06:59:20', NULL, NULL),
(6, 2, 17, '2026-04-19', '2026-05-01', 12, 5760.00, 'APPROVED', 'daffodil', 'dhaka', '', 23.8799288, 90.3218127, NULL, '2026-04-19 06:25:20', NULL, NULL);

--
-- Triggers `rentals`
--
DELIMITER $$
CREATE TRIGGER `trg_after_rental_complete` AFTER UPDATE ON `rentals` FOR EACH ROW BEGIN
  DECLARE v_threshold INT;
  DECLARE v_count     INT;

  IF NEW.status = 'COMPLETED' AND OLD.status != 'COMPLETED' THEN

    UPDATE products
    SET rental_count = rental_count + 1
    WHERE id = NEW.product_id;

    SELECT rental_count, maintenance_threshold
    INTO v_count, v_threshold
    FROM products WHERE id = NEW.product_id;

    IF (v_count % v_threshold) = 0 THEN
      UPDATE products
      SET status = 'MAINTENANCE'
      WHERE id = NEW.product_id;

      INSERT INTO maintenance_log (product_id, reason)
      VALUES (
        NEW.product_id,
        CONCAT('Auto-scheduled: completed ', v_count, ' total rentals (threshold: ', v_threshold, ')')
      );
    END IF;

  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_prevent_double_booking` BEFORE INSERT ON `rentals` FOR EACH ROW BEGIN
  DECLARE v_status VARCHAR(30);
  SELECT status INTO v_status FROM products WHERE id = NEW.product_id;

  IF v_status != 'AVAILABLE' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Product is not available for booking';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `rental_confirmations`
--

CREATE TABLE `rental_confirmations` (
  `id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `confirmed_by` int(11) NOT NULL,
  `confirm_type` enum('PICKUP','RETURN') NOT NULL,
  `confirmed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rental_confirmations`
--

INSERT INTO `rental_confirmations` (`id`, `rental_id`, `confirmed_by`, `confirm_type`, `confirmed_at`) VALUES
(1, 2, 3, 'PICKUP', '2026-04-16 12:30:24'),
(2, 4, 2, 'PICKUP', '2026-04-18 15:30:04');

-- --------------------------------------------------------

--
-- Table structure for table `sos_alerts`
--

CREATE TABLE `sos_alerts` (
  `id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trigger_type` enum('MANUAL','OVERDUE') NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('OPEN','RESOLVED') DEFAULT 'OPEN',
  `created_at` datetime DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sos_alerts`
--

INSERT INTO `sos_alerts` (`id`, `rental_id`, `user_id`, `trigger_type`, `message`, `status`, `created_at`, `resolved_at`, `resolved_by`, `resolution_notes`) VALUES
(1, 4, 2, 'MANUAL', 'broked', 'RESOLVED', '2026-04-18 15:30:26', '2026-04-18 16:17:00', 1, 'jhlnlkn'),
(2, 4, 2, 'OVERDUE', 'Rental overdue since 2026-04-17', 'OPEN', '2026-04-18 23:19:26', NULL, NULL, NULL),
(3, 4, 2, 'MANUAL', 'jgjgj', 'OPEN', '2026-04-19 12:25:51', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`id`, `category_id`, `name`) VALUES
(1, 1, 'Flagship Smartphones'),
(2, 1, 'Phone Accessories'),
(3, 2, 'Hiking'),
(4, 2, 'Camping'),
(5, 2, 'Cycling'),
(6, 2, 'Water Sports'),
(7, 2, 'Other Gear');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `nid` varchar(50) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `phone`, `nid`, `role`, `created_at`) VALUES
(1, 'Orlene Admin', 'admin@orlene.com', '$2y$10$9QuvFxN.nGmCrBb1OszS1OFdogT7ovmKIH9lXgAqwc4mZWQcHXimq', '01700000000', 'ADMIN001', 'admin', '2026-04-18 06:30:23'),
(2, 'Rahim Hossain', 'user@orlene.com', '$2y$10$l.JWgjwA0OQt9jPcTkCAle7Dq1x3bQiIVk4o3mXPCdZKND0uP2fh.', '01811111111', 'NID123456', 'user', '2026-04-18 06:30:23'),
(3, 'Fatema Begum', 'user2@orlene.com', '$2y$10$l.JWgjwA0OQt9jPcTkCAle7Dq1x3bQiIVk4o3mXPCdZKND0uP2fh.', '01722222222', 'NID789012', 'user', '2026-04-18 06:30:23'),
(4, 'Karim Uddin', 'user3@orlene.com', '$2y$10$l.JWgjwA0OQt9jPcTkCAle7Dq1x3bQiIVk4o3mXPCdZKND0uP2fh.', '01933333333', 'NID345678', 'user', '2026-04-18 06:30:23');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_active_rentals`
-- (See below for the actual view)
--
CREATE TABLE `v_active_rentals` (
`rental_id` int(11)
,`user_id` int(11)
,`user_name` varchar(100)
,`email` varchar(150)
,`phone` varchar(20)
,`product_id` int(11)
,`product_name` varchar(150)
,`category` varchar(50)
,`subcategory` varchar(100)
,`start_date` date
,`end_date` date
,`days` int(11)
,`total_fee` decimal(10,2)
,`status` enum('PENDING','APPROVED','ACTIVE','RETURNED','COMPLETED','REJECTED','OVERDUE')
,`use_area` varchar(150)
,`use_city` varchar(100)
,`use_landmark` varchar(200)
,`use_lat` decimal(10,7)
,`use_lng` decimal(10,7)
,`pickup_at` datetime
,`days_overdue` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_admin_dashboard`
-- (See below for the actual view)
--
CREATE TABLE `v_admin_dashboard` (
`pending_approvals` bigint(21)
,`active_rentals` bigint(21)
,`open_sos` bigint(21)
,`in_maintenance` bigint(21)
,`pending_inspection` bigint(21)
,`total_revenue` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_product_status_report`
-- (See below for the actual view)
--
CREATE TABLE `v_product_status_report` (
`product_id` int(11)
,`product_name` varchar(150)
,`category` varchar(50)
,`subcategory` varchar(100)
,`daily_rate` decimal(8,2)
,`stock_qty` int(11)
,`rental_count` int(11)
,`maintenance_threshold` int(11)
,`status` enum('AVAILABLE','RENTED','PENDING_INSPECTION','MAINTENANCE','RETIRED')
,`total_rentals` bigint(21)
,`active_rentals` decimal(22,0)
,`completed_rentals` decimal(22,0)
,`total_revenue` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_sos_open`
-- (See below for the actual view)
--
CREATE TABLE `v_sos_open` (
`alert_id` int(11)
,`trigger_type` enum('MANUAL','OVERDUE')
,`message` text
,`alert_time` datetime
,`alert_status` enum('OPEN','RESOLVED')
,`user_name` varchar(100)
,`user_phone` varchar(20)
,`product_name` varchar(150)
,`rental_id` int(11)
,`end_date` date
,`use_area` varchar(150)
,`use_city` varchar(100)
,`use_lat` decimal(10,7)
,`use_lng` decimal(10,7)
,`hours_since_alert` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_user_rental_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_user_rental_summary` (
`id` int(11)
,`name` varchar(100)
,`email` varchar(150)
,`phone` varchar(20)
,`member_since` timestamp
,`total_rentals` bigint(21)
,`completed` decimal(22,0)
,`active` decimal(22,0)
,`overdue_count` decimal(22,0)
,`total_spent` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Structure for view `v_active_rentals`
--
DROP TABLE IF EXISTS `v_active_rentals`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_active_rentals`  AS SELECT `r`.`id` AS `rental_id`, `u`.`id` AS `user_id`, `u`.`name` AS `user_name`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, `p`.`id` AS `product_id`, `p`.`name` AS `product_name`, `cat`.`name` AS `category`, `sub`.`name` AS `subcategory`, `r`.`start_date` AS `start_date`, `r`.`end_date` AS `end_date`, `r`.`days` AS `days`, `r`.`total_fee` AS `total_fee`, `r`.`status` AS `status`, `r`.`use_area` AS `use_area`, `r`.`use_city` AS `use_city`, `r`.`use_landmark` AS `use_landmark`, `r`.`use_lat` AS `use_lat`, `r`.`use_lng` AS `use_lng`, `r`.`pickup_at` AS `pickup_at`, to_days(curdate()) - to_days(`r`.`end_date`) AS `days_overdue` FROM ((((`rentals` `r` join `users` `u` on(`r`.`user_id` = `u`.`id`)) join `products` `p` on(`r`.`product_id` = `p`.`id`)) join `subcategories` `sub` on(`p`.`subcategory_id` = `sub`.`id`)) join `categories` `cat` on(`sub`.`category_id` = `cat`.`id`)) WHERE `r`.`status` in ('ACTIVE','OVERDUE') ;

-- --------------------------------------------------------

--
-- Structure for view `v_admin_dashboard`
--
DROP TABLE IF EXISTS `v_admin_dashboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_admin_dashboard`  AS SELECT (select count(0) from `rentals` where `rentals`.`status` = 'PENDING') AS `pending_approvals`, (select count(0) from `rentals` where `rentals`.`status` in ('ACTIVE','OVERDUE')) AS `active_rentals`, (select count(0) from `sos_alerts` where `sos_alerts`.`status` = 'OPEN') AS `open_sos`, (select count(0) from `maintenance_log` where `maintenance_log`.`resolved_at` is null) AS `in_maintenance`, (select count(0) from `rentals` where `rentals`.`status` = 'RETURNED') AS `pending_inspection`, (select coalesce(sum(`rentals`.`total_fee`),0) from `rentals` where `rentals`.`status` = 'COMPLETED') AS `total_revenue` ;

-- --------------------------------------------------------

--
-- Structure for view `v_product_status_report`
--
DROP TABLE IF EXISTS `v_product_status_report`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_product_status_report`  AS SELECT `p`.`id` AS `product_id`, `p`.`name` AS `product_name`, `cat`.`name` AS `category`, `sub`.`name` AS `subcategory`, `p`.`daily_rate` AS `daily_rate`, `p`.`stock_qty` AS `stock_qty`, `p`.`rental_count` AS `rental_count`, `p`.`maintenance_threshold` AS `maintenance_threshold`, `p`.`status` AS `status`, count(`r`.`id`) AS `total_rentals`, sum(case when `r`.`status` = 'ACTIVE' then 1 else 0 end) AS `active_rentals`, sum(case when `r`.`status` = 'COMPLETED' then 1 else 0 end) AS `completed_rentals`, coalesce(sum(`r`.`total_fee`),0) AS `total_revenue` FROM (((`products` `p` join `subcategories` `sub` on(`p`.`subcategory_id` = `sub`.`id`)) join `categories` `cat` on(`sub`.`category_id` = `cat`.`id`)) left join `rentals` `r` on(`r`.`product_id` = `p`.`id`)) GROUP BY `p`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_sos_open`
--
DROP TABLE IF EXISTS `v_sos_open`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_sos_open`  AS SELECT `sa`.`id` AS `alert_id`, `sa`.`trigger_type` AS `trigger_type`, `sa`.`message` AS `message`, `sa`.`created_at` AS `alert_time`, `sa`.`status` AS `alert_status`, `u`.`name` AS `user_name`, `u`.`phone` AS `user_phone`, `p`.`name` AS `product_name`, `r`.`id` AS `rental_id`, `r`.`end_date` AS `end_date`, `r`.`use_area` AS `use_area`, `r`.`use_city` AS `use_city`, `r`.`use_lat` AS `use_lat`, `r`.`use_lng` AS `use_lng`, timestampdiff(HOUR,`sa`.`created_at`,current_timestamp()) AS `hours_since_alert` FROM (((`sos_alerts` `sa` join `rentals` `r` on(`sa`.`rental_id` = `r`.`id`)) join `users` `u` on(`sa`.`user_id` = `u`.`id`)) join `products` `p` on(`r`.`product_id` = `p`.`id`)) WHERE `sa`.`status` = 'OPEN' ORDER BY `sa`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_user_rental_summary`
--
DROP TABLE IF EXISTS `v_user_rental_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_user_rental_summary`  AS SELECT `u`.`id` AS `id`, `u`.`name` AS `name`, `u`.`email` AS `email`, `u`.`phone` AS `phone`, `u`.`created_at` AS `member_since`, count(`r`.`id`) AS `total_rentals`, sum(case when `r`.`status` = 'COMPLETED' then 1 else 0 end) AS `completed`, sum(case when `r`.`status` in ('ACTIVE','OVERDUE') then 1 else 0 end) AS `active`, sum(case when `r`.`status` = 'OVERDUE' then 1 else 0 end) AS `overdue_count`, coalesce(sum(`r`.`total_fee`),0) AS `total_spent` FROM (`users` `u` left join `rentals` `r` on(`r`.`user_id` = `u`.`id`)) WHERE `u`.`role` = 'user' GROUP BY `u`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inspections`
--
ALTER TABLE `inspections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rental_id` (`rental_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `maintenance_log`
--
ALTER TABLE `maintenance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subcategory_id` (`subcategory_id`);

--
-- Indexes for table `rentals`
--
ALTER TABLE `rentals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `rental_confirmations`
--
ALTER TABLE `rental_confirmations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rental_id` (`rental_id`),
  ADD KEY `confirmed_by` (`confirmed_by`);

--
-- Indexes for table `sos_alerts`
--
ALTER TABLE `sos_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rental_id` (`rental_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inspections`
--
ALTER TABLE `inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `maintenance_log`
--
ALTER TABLE `maintenance_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `rentals`
--
ALTER TABLE `rentals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rental_confirmations`
--
ALTER TABLE `rental_confirmations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sos_alerts`
--
ALTER TABLE `sos_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inspections`
--
ALTER TABLE `inspections`
  ADD CONSTRAINT `inspections_ibfk_1` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inspections_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `inspections_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `maintenance_log`
--
ALTER TABLE `maintenance_log`
  ADD CONSTRAINT `maintenance_log_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_log_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`);

--
-- Constraints for table `rentals`
--
ALTER TABLE `rentals`
  ADD CONSTRAINT `rentals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `rentals_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `rental_confirmations`
--
ALTER TABLE `rental_confirmations`
  ADD CONSTRAINT `rental_confirmations_ibfk_1` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rental_confirmations_ibfk_2` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sos_alerts`
--
ALTER TABLE `sos_alerts`
  ADD CONSTRAINT `sos_alerts_ibfk_1` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sos_alerts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sos_alerts_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD CONSTRAINT `subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

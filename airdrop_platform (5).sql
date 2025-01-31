-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 31, 2025 at 10:17 PM
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
-- Database: `airdrop_platform`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `award_user_points` (IN `user_id` INT, IN `points` INT, IN `reward_type` VARCHAR(20), IN `description` TEXT)   BEGIN
    DECLARE current_daily_points INT;
    DECLARE daily_limit INT;
    
    START TRANSACTION;
    
    -- بررسی محدودیت روزانه
    SELECT COALESCE(SUM(points), 0) INTO current_daily_points
    FROM user_rewards
    WHERE user_id = user_id 
    AND DATE(created_at) = CURDATE();
    
    SELECT CAST(value AS SIGNED) INTO daily_limit
    FROM settings
    WHERE `key` = 'daily_points_limit';
    
    IF (current_daily_points + points) <= daily_limit THEN
        -- اضافه کردن پاداش
        INSERT INTO user_rewards (user_id, reward_type, points, description, created_at)
        VALUES (user_id, reward_type, points, description, NOW());
        
        -- به‌روزرسانی امتیازات کاربر
        UPDATE users 
        SET total_points = total_points + points
        WHERE id = user_id;
        
        -- به‌روزرسانی سطح کاربر
        CALL update_user_level(user_id);
        
        COMMIT;
        SELECT TRUE as success, 'Points awarded successfully' as message;
    ELSE
        ROLLBACK;
        SELECT FALSE as success, 'Daily points limit exceeded' as message;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `check_suspicious_activity` (IN `user_id` INT, IN `ip_address` VARCHAR(45))   BEGIN
    DECLARE total_today INT;
    DECLARE ip_count INT;
    
    -- بررسی تعداد تسک‌های تکمیل شده امروز
    SELECT COUNT(*) INTO total_today
    FROM task_completions
    WHERE user_id = user_id 
    AND DATE(completed_at) = CURDATE();
    
    -- بررسی تعداد کاربران با این IP
    SELECT COUNT(DISTINCT user_id) INTO ip_count
    FROM system_logs
    WHERE ip_address = ip_address 
    AND DATE(created_at) = CURDATE();
    
    IF total_today > 50 OR ip_count > 3 THEN
        INSERT INTO suspicious_ips (ip_address, reason, status, created_at)
        VALUES (ip_address, 
                CONCAT('High activity: ', total_today, ' tasks, ', ip_count, ' users'),
                'warning',
                NOW());
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `process_withdrawal` (IN `p_user_id` INT, IN `p_amount` INT, IN `p_admin_wallet` VARCHAR(42))   BEGIN
    DECLARE user_points INT;
    DECLARE user_level INT;
    DECLARE min_points INT;
    DECLARE min_level INT;
    
    -- بررسی شرایط برداشت
    SELECT total_points, level INTO user_points, user_level
    FROM users WHERE id = p_user_id;
    
    SELECT CAST(value AS SIGNED) INTO min_points
    FROM settings WHERE `key` = 'min_withdrawal_points';
    
    SELECT CAST(value AS SIGNED) INTO min_level
    FROM settings WHERE `key` = 'min_withdraw_level';
    
    IF user_points >= p_amount AND user_points >= min_points AND user_level >= min_level THEN
        START TRANSACTION;
        
        -- کم کردن امتیازات
        UPDATE users 
        SET total_points = total_points - p_amount
        WHERE id = p_user_id;
        
        -- ثبت درخواست برداشت
        INSERT INTO withdrawals (user_id, amount, wallet_address, status, created_at)
        SELECT id, p_amount, wallet_address, 'pending', NOW()
        FROM users WHERE id = p_user_id;
        
        -- ثبت در لاگ سیستم
        INSERT INTO system_logs (log_type, severity, user_id, action, description, created_at)
        VALUES ('reward', 'info', p_user_id, 'withdrawal_request', 
                CONCAT('Withdrawal request: ', p_amount, ' points'), NOW());
        
        COMMIT;
        SELECT TRUE as success, 'Withdrawal request submitted successfully' as message;
    ELSE
        SELECT FALSE as success, 
               CASE 
                   WHEN user_points < p_amount THEN 'Insufficient points'
                   WHEN user_points < min_points THEN 'Minimum withdrawal limit not reached'
                   ELSE 'Minimum level requirement not met'
               END as message;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_user_level` (IN `user_id` INT)   BEGIN
    DECLARE current_points INT;
    DECLARE new_level INT;
    
    -- دریافت امتیازات کاربر
    SELECT total_points INTO current_points
    FROM users WHERE id = user_id;
    
    -- محاسبه سطح جدید
    SELECT MAX(level_number) INTO new_level
    FROM levels
    WHERE points_required <= current_points;
    
    -- به‌روزرسانی سطح کاربر
    UPDATE users 
    SET level = COALESCE(new_level, 1)
    WHERE id = user_id;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_referral_code` () RETURNS VARCHAR(10) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC BEGIN
    DECLARE v_code VARCHAR(10);
    DECLARE v_exists INT;
    
    generate_loop: LOOP
        -- تولید کد 8 کاراکتری تصادفی
        SET v_code = UPPER(
            CONCAT(
                CHAR(65 + FLOOR(RAND() * 26)),
                CHAR(65 + FLOOR(RAND() * 26)),
                CHAR(65 + FLOOR(RAND() * 26)),
                FLOOR(RAND() * 10),
                FLOOR(RAND() * 10),
                CHAR(65 + FLOOR(RAND() * 26)),
                CHAR(65 + FLOOR(RAND() * 26)),
                FLOOR(RAND() * 10)
            )
        );
        
        -- بررسی تکراری نبودن کد
        SELECT COUNT(*) INTO v_exists 
        FROM users WHERE referral_code = v_code;
        
        IF v_exists = 0 THEN
            LEAVE generate_loop;
        END IF;
    END LOOP;
    
    RETURN v_code;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_users_view`
-- (See below for the actual view)
--
CREATE TABLE `active_users_view` (
`unique_id` int(11)
,`wallet_address` varchar(42)
,`total_points` int(11)
,`level` int(11)
,`profile_completed` tinyint(1)
,`registration_date` datetime
,`last_login` datetime
,`completed_tasks_count` bigint(21)
,`active_days_count` bigint(21)
,`total_earned_points` decimal(32,0)
,`today_completed_tasks` decimal(22,0)
,`today_earned_points` decimal(32,0)
,`successful_referrals` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `wallet_address` varchar(42) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `wallet_address`, `username`, `is_active`, `created_at`, `last_login`) VALUES
(3, '0x2102437E208B9e8284a0A1EDB99f1E11f29732f6', 'Admin', 1, '2025-01-30 22:19:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `wallet_address` varchar(42) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `wallet_address`, `action`, `description`, `created_at`) VALUES
(1, '0x2102437e208b9e8284a0a1edb99f1e11f29732f6', 'login', 'Admin logged in successfully', '2025-01-30 22:24:38'),
(2, '0x2102437e208b9e8284a0a1edb99f1e11f29732f6', 'login', 'Admin logged in successfully', '2025-01-30 22:43:22'),
(3, '0x2102437e208b9e8284a0a1edb99f1e11f29732f6', 'login', 'Admin logged in successfully', '2025-01-30 23:51:36'),
(4, '0x2102437e208b9e8284a0a1edb99f1e11f29732f6', 'login', 'Admin logged in successfully', '2025-01-31 20:55:43');

-- --------------------------------------------------------

--
-- Table structure for table `admin_wallets`
--

CREATE TABLE `admin_wallets` (
  `id` int(11) NOT NULL,
  `wallet_address` varchar(42) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `added_by` varchar(42) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_wallets`
--

INSERT INTO `admin_wallets` (`id`, `wallet_address`, `description`, `is_active`, `added_by`, `created_at`, `updated_at`) VALUES
(1, '0x2102437E208B9e8284a0A1EDB99f1E11f29732f6', 'Super Admin', 1, NULL, '2025-01-31 00:20:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `type` enum('info','warning','success','danger') NOT NULL DEFAULT 'info',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(42) NOT NULL,
  `priority` tinyint(4) DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `message`, `start_date`, `end_date`, `type`, `is_active`, `created_by`, `priority`, `created_at`, `updated_at`) VALUES
(1, 'Welcome to our Airdrop Platform! Complete tasks to earn points and rewards.', '2025-01-30 21:05:32', NULL, 'info', 1, '0x2102437E208B9e8284a0A1EDB99f1E11f29732f6', 1, '2025-01-30 21:05:32', '2025-01-30 21:05:32');

-- --------------------------------------------------------

--
-- Table structure for table `daily_statistics`
--

CREATE TABLE `daily_statistics` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `new_users` int(11) DEFAULT 0,
  `active_users` int(11) DEFAULT 0,
  `total_points_distributed` int(11) DEFAULT 0,
  `completed_tasks` int(11) DEFAULT 0,
  `successful_referrals` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_statistics`
--

INSERT INTO `daily_statistics` (`id`, `date`, `new_users`, `active_users`, `total_points_distributed`, `completed_tasks`, `successful_referrals`, `created_at`) VALUES
(1, '2025-01-30', 1, 0, 0, 0, 0, '2025-01-31 02:39:15'),
(2, '2025-01-31', 2, 0, 0, 0, 0, '2025-01-31 02:46:30');

-- --------------------------------------------------------

--
-- Table structure for table `daily_statistics_archive`
--

CREATE TABLE `daily_statistics_archive` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `new_users` int(11) DEFAULT 0,
  `active_users` int(11) DEFAULT 0,
  `total_points_distributed` int(11) DEFAULT 0,
  `completed_tasks` int(11) DEFAULT 0,
  `successful_referrals` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `archived_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_tasks`
--

CREATE TABLE `daily_tasks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `points_earned` int(11) DEFAULT 0,
  `claimed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `levels`
--

CREATE TABLE `levels` (
  `id` int(11) NOT NULL,
  `level_number` int(11) NOT NULL,
  `points_required` int(11) NOT NULL,
  `rewards` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `levels`
--

INSERT INTO `levels` (`id`, `level_number`, `points_required`, `rewards`, `created_at`) VALUES
(1, 1, 0, 'Basic level - No special rewards', '2025-01-30 21:05:32'),
(2, 2, 1000, 'Bronze level - 10% bonus on tasks', '2025-01-30 21:05:32'),
(3, 3, 2500, 'Silver level - 20% bonus on tasks', '2025-01-30 21:05:32'),
(4, 4, 5000, 'Gold level - 30% bonus on tasks', '2025-01-30 21:05:32'),
(5, 5, 10000, 'Platinum level - 50% bonus on tasks', '2025-01-30 21:05:32');

-- --------------------------------------------------------

--
-- Stand-in structure for view `platform_statistics_view`
-- (See below for the actual view)
--
CREATE TABLE `platform_statistics_view` (
`total_active_users` bigint(21)
,`users_joined_today` bigint(21)
,`active_tasks` bigint(21)
,`points_today` decimal(32,0)
,`total_referrals` bigint(21)
,`pending_withdrawals` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `referral_history`
--

CREATE TABLE `referral_history` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `status` enum('pending','completed','rejected') DEFAULT 'pending',
  `points_earned` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `referral_history`
--
DELIMITER $$
CREATE TRIGGER `after_referral_complete` AFTER UPDATE ON `referral_history` FOR EACH ROW BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        INSERT INTO daily_statistics (date, successful_referrals, created_at)
        VALUES (DATE(NEW.completed_at), 1, NOW())
        ON DUPLICATE KEY UPDATE
        successful_referrals = successful_referrals + 1;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `referral_performance_view`
-- (See below for the actual view)
--
CREATE TABLE `referral_performance_view` (
`unique_id` int(11)
,`wallet_address` varchar(42)
,`user_registered_at` datetime
,`total_referrals` bigint(21)
,`successful_referrals` bigint(21)
,`pending_referrals` bigint(21)
,`rejected_referrals` bigint(21)
,`total_referral_points` decimal(32,0)
,`active_referral_days` bigint(21)
,`last_referral_date` datetime
,`today_referrals` decimal(22,0)
,`today_referral_points` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(50) NOT NULL,
  `value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` varchar(42) DEFAULT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`, `description`, `updated_by`, `updated_at`) VALUES
('daily_points_limit', '1000', 'محدودیت امتیاز روزانه', NULL, '0000-00-00 00:00:00'),
('discord_server', '', 'آدرس سرور دیسکورد', NULL, '2025-01-31 00:19:22'),
('level_multiplier', '1.1', 'ضریب افزایش امتیاز در هر لول', NULL, '0000-00-00 00:00:00'),
('maintenance_message', 'Site is under maintenance', 'پیام حالت تعمیر و نگهداری', NULL, '2025-01-31 00:19:22'),
('maintenance_mode', '0', 'حالت تعمیر و نگهداری', NULL, '0000-00-00 00:00:00'),
('max_login_attempts', '5', 'حداکثر تلاش برای ورود', NULL, '0000-00-00 00:00:00'),
('min_withdrawal_points', '5000', 'حداقل امتیاز برای برداشت', NULL, '0000-00-00 00:00:00'),
('min_withdraw_level', '5', 'حداقل لول برای برداشت', NULL, '2025-01-31 00:19:22'),
('points_per_token', '100', 'امتیاز به ازای هر توکن', NULL, '0000-00-00 00:00:00'),
('referral_bonus', '50', 'امتیاز پاداش رفرال', NULL, '2025-01-31 00:19:22'),
('session_lifetime', '1800', 'طول عمر سشن (ثانیه)', NULL, '0000-00-00 00:00:00'),
('site_description', 'Airdrop Platform Description', 'توضیحات سایت', NULL, '2025-01-31 00:19:22'),
('site_name', 'Airdrop Platform', 'نام سایت', NULL, '0000-00-00 00:00:00'),
('telegram_group', '', 'آدرس گروه تلگرام', NULL, '2025-01-31 00:19:22'),
('twitter_account', '', 'آدرس اکانت توییتر', NULL, '2025-01-31 00:19:22');

-- --------------------------------------------------------

--
-- Table structure for table `suspicious_ips`
--

CREATE TABLE `suspicious_ips` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('warning','blocked') DEFAULT 'warning',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `log_type` enum('security','user','admin','task','reward') NOT NULL,
  `severity` enum('info','warning','error') NOT NULL DEFAULT 'info',
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `task_type` enum('social','daily','special') NOT NULL,
  `points` int(11) NOT NULL,
  `is_daily` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `platform` varchar(50) NOT NULL DEFAULT 'other',
  `created_by` varchar(42) NOT NULL,
  `required_proof` tinyint(1) DEFAULT 1,
  `minimum_level` int(11) DEFAULT 1,
  `maximum_completions` int(11) DEFAULT 1,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `updated_by` varchar(42) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `task_name`, `task_type`, `points`, `is_daily`, `is_active`, `description`, `created_at`, `platform`, `created_by`, `required_proof`, `minimum_level`, `maximum_completions`, `start_date`, `end_date`, `updated_by`, `updated_at`) VALUES
(1, 'Follow Twitter Account', 'social', 100, 0, 1, 'Follow our official Twitter account and like our pinned tweet', '2025-01-30 21:05:32', 'twitter', '0x2102437E208B9e8284a0A1EDB99f1E11f29732f6', 1, 1, 1, '2025-01-30 21:05:32', '2025-02-28 21:05:32', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `task_completions`
--

CREATE TABLE `task_completions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `proof` text DEFAULT NULL,
  `status` enum('pending','completed','rejected') NOT NULL DEFAULT 'pending',
  `points_earned` int(11) NOT NULL,
  `reviewed_by` varchar(42) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `completed_at` datetime NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `task_completions`
--
DELIMITER $$
CREATE TRIGGER `after_task_completion` AFTER UPDATE ON `task_completions` FOR EACH ROW BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        INSERT INTO daily_statistics (date, completed_tasks, total_points_distributed, created_at)
        VALUES (DATE(NEW.completed_at), 1, NEW.points_earned, NOW())
        ON DUPLICATE KEY UPDATE
        completed_tasks = completed_tasks + 1,
        total_points_distributed = total_points_distributed + NEW.points_earned;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `task_statistics_view`
-- (See below for the actual view)
--
CREATE TABLE `task_statistics_view` (
`unique_id` int(11)
,`task_name` varchar(255)
,`task_type` enum('social','daily','special')
,`platform` varchar(50)
,`reward_points` int(11)
,`is_active` tinyint(1)
,`created_at` datetime
,`total_attempts` bigint(21)
,`successful_completions` bigint(21)
,`pending_completions` bigint(21)
,`rejected_completions` bigint(21)
,`avg_points_earned` decimal(14,4)
,`unique_participants` bigint(21)
,`last_completion_date` datetime
,`current_status` varchar(8)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `wallet_address` varchar(42) NOT NULL,
  `username` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `total_points` int(11) NOT NULL DEFAULT 0,
  `referral_code` varchar(10) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `level` int(11) NOT NULL DEFAULT 1,
  `profile_completed` tinyint(1) NOT NULL DEFAULT 0,
  `telegram_username` varchar(255) DEFAULT NULL,
  `twitter_username` varchar(255) DEFAULT NULL,
  `discord_username` varchar(255) DEFAULT NULL,
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `ban_reason` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `banned_at` datetime DEFAULT NULL,
  `banned_by` varchar(42) DEFAULT NULL
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `wallet_address`, `username`, `status`, `total_points`, `referral_code`, `referred_by`, `created_at`, `last_login`, `updated_at`, `level`, `profile_completed`, `telegram_username`, `twitter_username`, `discord_username`, `is_banned`, `ban_reason`, `email`, `verified_at`, `banned_at`, `banned_by`) VALUES
(1, '0x2102437E208B9e8284a0A1EDB99f1E11f29732f6', 'Admin', 'active', 0, 'ENU05EHL', NULL, '2025-01-30 23:08:29', '2025-02-01 00:02:27', '2025-02-01 00:02:27', 5, 1, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(2, '0x1234567890123456789012345678901234567890', 'TestUser', 'active', 0, 'TEST1234', NULL, '2025-01-31 02:46:30', '2025-01-31 02:46:30', NULL, 1, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(3, '0x5678901234567890123456789012345678901234', 'TestUser2', 'active', 0, 'TEST5678', NULL, '2025-01-31 02:59:02', '2025-01-31 02:59:02', NULL, 1, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL);

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `after_points_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    DECLARE new_level INT;
    
    IF NEW.total_points != OLD.total_points THEN
        SELECT MAX(level_number) INTO new_level
        FROM levels
        WHERE points_required <= NEW.total_points;
        
        IF new_level > NEW.level THEN
            UPDATE users 
            SET level = new_level
            WHERE id = NEW.id;
            
            INSERT INTO system_logs (log_type, severity, user_id, action, description, created_at)
            VALUES ('user', 'info', NEW.id, 'level_up', 
                    CONCAT('Level up to: ', new_level), NOW());
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_user_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO daily_statistics (date, new_users, created_at)
    VALUES (DATE(NEW.created_at), 1, NOW())
    ON DUPLICATE KEY UPDATE
    new_users = new_users + 1;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users_backup`
--

CREATE TABLE `users_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `wallet_address` varchar(42) NOT NULL CHECK (`wallet_address` regexp '^0x[a-fA-F0-9]{40}$'),
  `total_points` int(11) DEFAULT 0,
  `referral_code` varchar(10) DEFAULT NULL CHECK (`referral_code` regexp '^[A-Z0-9]{8}$'),
  `referred_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `last_login` datetime NOT NULL,
  `level` int(11) DEFAULT 1 CHECK (`level` >= 1),
  `profile_completed` tinyint(1) DEFAULT 0,
  `telegram_username` varchar(255) DEFAULT NULL,
  `twitter_username` varchar(255) DEFAULT NULL,
  `discord_username` varchar(255) DEFAULT NULL,
  `is_banned` tinyint(1) DEFAULT 0,
  `ban_reason` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `banned_at` datetime DEFAULT NULL,
  `banned_by` varchar(42) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_backup`
--

INSERT INTO `users_backup` (`id`, `wallet_address`, `total_points`, `referral_code`, `referred_by`, `created_at`, `last_login`, `level`, `profile_completed`, `telegram_username`, `twitter_username`, `discord_username`, `is_banned`, `ban_reason`, `email`, `verified_at`, `banned_at`, `banned_by`, `updated_at`) VALUES
(1, '0x2102437e208b9e8284a0a1edb99f1e11f29732f6', 0, 'ENU05EHL', NULL, '2025-01-29 20:17:56', '2025-01-29 22:44:07', 1, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_activities`
--

CREATE TABLE `user_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `points_earned` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_points`
--

CREATE TABLE `user_points` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `type` enum('earned','spent','referral','task','bonus') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_rewards`
--

CREATE TABLE `user_rewards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reward_type` enum('daily','task','referral') NOT NULL,
  `points` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `wallet_address` varchar(42) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `tx_hash` varchar(66) DEFAULT NULL,
  `reviewed_by` varchar(42) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `active_users_view`
--
DROP TABLE IF EXISTS `active_users_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_users_view`  AS SELECT `u`.`id` AS `unique_id`, `u`.`wallet_address` AS `wallet_address`, `u`.`total_points` AS `total_points`, `u`.`level` AS `level`, `u`.`profile_completed` AS `profile_completed`, `u`.`created_at` AS `registration_date`, `u`.`last_login` AS `last_login`, count(distinct `tc`.`id`) AS `completed_tasks_count`, count(distinct cast(`tc`.`completed_at` as date)) AS `active_days_count`, coalesce(sum(`tc`.`points_earned`),0) AS `total_earned_points`, coalesce(sum(case when cast(`tc`.`completed_at` as date) = curdate() then 1 else 0 end),0) AS `today_completed_tasks`, coalesce(sum(case when cast(`tc`.`completed_at` as date) = curdate() then `tc`.`points_earned` else 0 end),0) AS `today_earned_points`, (select count(0) from `referral_history` `rh` where `rh`.`referrer_id` = `u`.`id` and `rh`.`status` = 'completed') AS `successful_referrals` FROM (`users` `u` left join `task_completions` `tc` on(`u`.`id` = `tc`.`user_id` and `tc`.`status` = 'completed')) WHERE `u`.`is_banned` = 0 GROUP BY `u`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `platform_statistics_view`
--
DROP TABLE IF EXISTS `platform_statistics_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `platform_statistics_view`  AS SELECT (select count(0) from `users` where `users`.`is_banned` = 0) AS `total_active_users`, (select count(0) from `users` where cast(`users`.`created_at` as date) = curdate()) AS `users_joined_today`, (select count(0) from `tasks` where `tasks`.`is_active` = 1) AS `active_tasks`, (select coalesce(sum(`task_completions`.`points_earned`),0) from `task_completions` where cast(`task_completions`.`completed_at` as date) = curdate()) AS `points_today`, (select count(0) from `referral_history` where `referral_history`.`status` = 'completed') AS `total_referrals`, (select count(0) from `withdrawals` where `withdrawals`.`status` = 'pending') AS `pending_withdrawals` ;

-- --------------------------------------------------------

--
-- Structure for view `referral_performance_view`
--
DROP TABLE IF EXISTS `referral_performance_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `referral_performance_view`  AS SELECT `u`.`id` AS `unique_id`, `u`.`wallet_address` AS `wallet_address`, `u`.`created_at` AS `user_registered_at`, count(`rh`.`id`) AS `total_referrals`, count(case when `rh`.`status` = 'completed' then 1 end) AS `successful_referrals`, count(case when `rh`.`status` = 'pending' then 1 end) AS `pending_referrals`, count(case when `rh`.`status` = 'rejected' then 1 end) AS `rejected_referrals`, coalesce(sum(case when `rh`.`status` = 'completed' then `rh`.`points_earned` end),0) AS `total_referral_points`, count(distinct cast(`rh`.`created_at` as date)) AS `active_referral_days`, max(`rh`.`created_at`) AS `last_referral_date`, coalesce(sum(case when cast(`rh`.`created_at` as date) = curdate() then 1 else 0 end),0) AS `today_referrals`, coalesce(sum(case when cast(`rh`.`created_at` as date) = curdate() and `rh`.`status` = 'completed' then `rh`.`points_earned` else 0 end),0) AS `today_referral_points` FROM (`users` `u` left join `referral_history` `rh` on(`u`.`id` = `rh`.`referrer_id`)) GROUP BY `u`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `task_statistics_view`
--
DROP TABLE IF EXISTS `task_statistics_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `task_statistics_view`  AS SELECT `t`.`id` AS `unique_id`, `t`.`task_name` AS `task_name`, `t`.`task_type` AS `task_type`, `t`.`platform` AS `platform`, `t`.`points` AS `reward_points`, `t`.`is_active` AS `is_active`, `t`.`created_at` AS `created_at`, count(`tc`.`id`) AS `total_attempts`, count(case when `tc`.`status` = 'completed' then 1 end) AS `successful_completions`, count(case when `tc`.`status` = 'pending' then 1 end) AS `pending_completions`, count(case when `tc`.`status` = 'rejected' then 1 end) AS `rejected_completions`, coalesce(avg(case when `tc`.`status` = 'completed' then `tc`.`points_earned` end),0) AS `avg_points_earned`, count(distinct `tc`.`user_id`) AS `unique_participants`, max(`tc`.`completed_at`) AS `last_completion_date`, CASE WHEN `t`.`end_date` is not null AND `t`.`end_date` < current_timestamp() THEN 'expired' WHEN `t`.`start_date` > current_timestamp() THEN 'upcoming' WHEN `t`.`is_active` = 1 THEN 'active' ELSE 'inactive' END AS `current_status` FROM (`tasks` `t` left join `task_completions` `tc` on(`t`.`id` = `tc`.`task_id`)) GROUP BY `t`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wallet_address` (`wallet_address`),
  ADD KEY `wallet_idx` (`wallet_address`),
  ADD KEY `active_idx` (`is_active`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `wallet_idx` (`wallet_address`),
  ADD KEY `action_idx` (`action`),
  ADD KEY `created_idx` (`created_at`);

--
-- Indexes for table `admin_wallets`
--
ALTER TABLE `admin_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wallet_address` (`wallet_address`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daily_statistics`
--
ALTER TABLE `daily_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`date`);

--
-- Indexes for table `daily_statistics_archive`
--
ALTER TABLE `daily_statistics_archive`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`date`);

--
-- Indexes for table `daily_tasks`
--
ALTER TABLE `daily_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `levels`
--
ALTER TABLE `levels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_history`
--
ALTER TABLE `referral_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referrer_id` (`referrer_id`),
  ADD KEY `referred_id` (`referred_id`),
  ADD KEY `status_date_idx` (`status`,`created_at`),
  ADD KEY `referral_date_status_idx` (`created_at`,`status`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `suspicious_ips`
--
ALTER TABLE `suspicious_ips`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at_idx` (`created_at`),
  ADD KEY `log_type_severity_idx` (`log_type`,`severity`),
  ADD KEY `log_date_type_idx` (`created_at`,`log_type`,`severity`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `active_date_idx` (`is_active`,`start_date`,`end_date`),
  ADD KEY `task_type_platform_idx` (`task_type`,`platform`);

--
-- Indexes for table `task_completions`
--
ALTER TABLE `task_completions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `status_date_idx` (`status`,`completed_at`),
  ADD KEY `completion_date_idx` (`completed_at`),
  ADD KEY `user_task_status_idx` (`user_id`,`task_id`,`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wallet_address` (`wallet_address`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `level_points_idx` (`level`,`total_points`),
  ADD KEY `status_idx` (`is_banned`,`profile_completed`),
  ADD KEY `user_social_idx` (`telegram_username`,`twitter_username`,`discord_username`),
  ADD KEY `user_status_idx` (`is_banned`,`profile_completed`,`level`),
  ADD KEY `referred_by_idx` (`referred_by`);

--
-- Indexes for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `user_points`
--
ALTER TABLE `user_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `user_rewards`
--
ALTER TABLE `user_rewards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reward_date_idx` (`created_at`),
  ADD KEY `reward_type_idx` (`reward_type`,`points`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status_date_idx` (`status`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `admin_wallets`
--
ALTER TABLE `admin_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `daily_statistics`
--
ALTER TABLE `daily_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `daily_statistics_archive`
--
ALTER TABLE `daily_statistics_archive`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_tasks`
--
ALTER TABLE `daily_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `levels`
--
ALTER TABLE `levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `referral_history`
--
ALTER TABLE `referral_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suspicious_ips`
--
ALTER TABLE `suspicious_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `task_completions`
--
ALTER TABLE `task_completions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_points`
--
ALTER TABLE `user_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_rewards`
--
ALTER TABLE `user_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `daily_tasks`
--
ALTER TABLE `daily_tasks`
  ADD CONSTRAINT `daily_tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `referral_history`
--
ALTER TABLE `referral_history`
  ADD CONSTRAINT `referral_history_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `referral_history_ibfk_2` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_completions`
--
ALTER TABLE `task_completions`
  ADD CONSTRAINT `task_completions_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_completions_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_referred_by_fk_new` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD CONSTRAINT `user_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_points`
--
ALTER TABLE `user_points`
  ADD CONSTRAINT `user_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_rewards`
--
ALTER TABLE `user_rewards`
  ADD CONSTRAINT `user_rewards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `clean_old_logs` ON SCHEDULE EVERY 1 DAY STARTS '2025-01-31 00:23:23' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    -- پاک کردن لاگ‌های قدیمی‌تر از 30 روز
    DELETE FROM system_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- آرشیو کردن آمار روزانه قدیمی‌تر از 90 روز
    INSERT INTO daily_statistics_archive
    SELECT * FROM daily_statistics
    WHERE date < DATE_SUB(CURDATE(), INTERVAL 90 DAY);
    
    DELETE FROM daily_statistics
    WHERE date < DATE_SUB(CURDATE(), INTERVAL 90 DAY);
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

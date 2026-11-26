-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2026 at 02:43 AM
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
-- Database: `handinhand_fsl`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `sp_create_class`$$
CREATE DEFINER=`` PROCEDURE `sp_create_class` (IN `p_created_by` INT, IN `p_class_title` VARCHAR(200), IN `p_description` TEXT, IN `p_max_capacity` INT, IN `p_teachers_json` JSON, IN `p_status` ENUM('Draft','Open','Ongoing','Completed','Cancelled'), IN `p_session_date` DATE, IN `p_start_time` TIME, IN `p_end_time` TIME, IN `p_venue` VARCHAR(255))   BEGIN
    DECLARE v_class_id INT;
    DECLARE v_idx INT DEFAULT 0;
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_teacher_id INT;
    DECLARE v_role VARCHAR(50);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Insert into class table
    INSERT INTO `class` (`created_by`, `class_title`, `description`, `max_capacity`, `current_enrolled`, `status`)
    VALUES (p_created_by, p_class_title, p_description, p_max_capacity, 0, IFNULL(p_status, 'Open'));

    SET v_class_id = LAST_INSERT_ID();

    -- Loop through teachers in the JSON array
    SET v_count = IFNULL(JSON_LENGTH(p_teachers_json), 0);
    WHILE v_idx < v_count DO
        SET v_teacher_id = JSON_UNQUOTE(JSON_EXTRACT(p_teachers_json, CONCAT('$[', v_idx, '].teacher_id')));
        SET v_role = JSON_UNQUOTE(JSON_EXTRACT(p_teachers_json, CONCAT('$[', v_idx, '].role')));

        INSERT INTO `class_teacher` (`class_id`, `teacher_id`, `assigned_role`)
        VALUES (v_class_id, v_teacher_id, v_role);

        SET v_idx = v_idx + 1;
    END WHILE;

    -- Insert first session into class_schedule table
    INSERT INTO `class_schedule` (`class_id`, `session_date`, `start_time`, `end_time`, `venue`, `status`)
    VALUES (v_class_id, p_session_date, p_start_time, p_end_time, p_venue, 'scheduled');

    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_enroll_learner`$$
CREATE DEFINER=`` PROCEDURE `sp_enroll_learner` (IN `p_class_id` INT, IN `p_learner_id` INT)   BEGIN
    DECLARE v_status VARCHAR(20);
    DECLARE v_enrolled INT;
    DECLARE v_capacity INT;

    SELECT `status`, `max_capacity`, `current_enrolled`
    INTO v_status, v_capacity, v_enrolled
    FROM `class`
    WHERE `class_id` = p_class_id
    FOR UPDATE;

    IF v_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class does not exist.';
    ELSEIF v_status <> 'Open' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class is not open for enrollment.';
    ELSEIF v_enrolled >= v_capacity THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class has reached maximum capacity.';
    ELSE
        INSERT INTO `class_enrollments` (`class_id`, `learner_id`, `status`)
        VALUES (p_class_id, p_learner_id, 'enrolled');
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_finalize_class_report`$$
CREATE DEFINER=`` PROCEDURE `sp_finalize_class_report` (IN `p_report_id` INT, IN `p_teacher_id` INT, IN `p_summary_notes` TEXT)   BEGIN
    DECLARE v_schedule_id INT DEFAULT NULL;
    DECLARE v_status VARCHAR(30) DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT `schedule_id`, `status`
    INTO v_schedule_id, v_status
    FROM `post_class_report`
    WHERE `report_id` = p_report_id
    FOR UPDATE;

    IF v_schedule_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Post-class report does not exist.';
    ELSEIF v_status NOT IN ('Pending', 'For Revision') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This report has already been submitted or verified.';
    END IF;

    UPDATE `post_class_report`
    SET `submitted_by` = p_teacher_id,
        `summary_notes` = p_summary_notes,
        `status` = 'Finished',
        `submission_timestamp` = CURRENT_TIMESTAMP
    WHERE `report_id` = p_report_id;

    UPDATE `class_schedule`
    SET `status` = 'completed'
    WHERE `schedule_id` = v_schedule_id;

    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_grade_learner_session`$$
CREATE DEFINER=`` PROCEDURE `sp_grade_learner_session` (IN `p_report_id` INT, IN `p_learner_id` INT, IN `p_session_rating` ENUM('Satisfactory','Needs Practice','Unsatisfactory'), IN `p_remarks` VARCHAR(255))   BEGIN
    DECLARE v_report_status VARCHAR(30) DEFAULT NULL;
    DECLARE v_learner_id INT DEFAULT NULL;
    DECLARE v_status ENUM('present', 'absent', 'late', 'excused') DEFAULT NULL;

    SELECT `status`
    INTO v_report_status
    FROM `post_class_report`
    WHERE `report_id` = p_report_id
    FOR UPDATE;

    IF v_report_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Post-class report does not exist.';
    ELSEIF v_report_status NOT IN ('Pending', 'For Revision') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot grade learners on a finished or verified report.';
    END IF;

    SELECT `learner_id`, `status`
    INTO v_learner_id, v_status
    FROM `session_attendance`
    WHERE `learner_id` = p_learner_id AND `report_id` = p_report_id
    FOR UPDATE;

    IF v_learner_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Learner has no attendance for this session.';
    ELSEIF v_status NOT IN ('present', 'late') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Absent or excused learners cannot be graded.';
    ELSE
        UPDATE `session_attendance`
        SET `session_rating` = p_session_rating,
            `remarks` = COALESCE(NULLIF(TRIM(p_remarks), ''), `remarks`)
        WHERE `report_id` = p_report_id
          AND `learner_id` = p_learner_id;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_record_attendance`$$
CREATE DEFINER=`` PROCEDURE `sp_record_attendance` (IN `p_report_id` INT, IN `p_learner_id` INT, IN `p_status` ENUM('present','absent','late','excused'), IN `p_remarks` VARCHAR(255))   BEGIN
    DECLARE v_report_id INT DEFAULT NULL;
    DECLARE v_status VARCHAR(30) DEFAULT NULL;

    SELECT `report_id`, `status`
    INTO v_report_id, v_status
    FROM `post_class_report`
    WHERE `report_id` = p_report_id
    FOR UPDATE;

    IF v_report_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Post-class report does not exist.';
    ELSEIF v_status IN ('Pending', 'For Revision') THEN
        INSERT INTO `session_attendance` (`report_id`, `learner_id`, `status`, `remarks`)
        VALUES (p_report_id, p_learner_id, p_status, p_remarks)
        ON DUPLICATE KEY UPDATE
            `status` = VALUES(`status`),
            `remarks` = VALUES(`remarks`);
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot edit attendance on a finished or verified report.';
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `username` varchar(30) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`account_id`, `role_id`, `username`, `email`, `password_hash`, `created_at`) VALUES
(1, 3, 'geolabalan', 'geolabalan@gmail.com', '$2y$10$2jbIBfgkQLbG9rz70ssWSu/wryC8knwMRY65ze7uIO7dsxfjdbERW', '2026-11-20 12:58:36'),
(3, 2, 'angelogeo', 'angelonavera@gmail.com', '$2y$10$7lC.GA0/RRwfv0rB2LKfLuOTYppzLouBStXIs04JGyGFj1jIDnad6', '2026-11-20 13:19:28'),
(4, 1, 'adminX', 'adminMarian@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-11-22 06:07:26'),
(5, 3, 'Bonnified', 'bonn@gmail.com', '$2y$10$1vNFGUhaERg0v4jKbEPRvujlTWhw7pLrvP7zINObUrEwYiwKPWXXS', '2026-11-25 07:28:33');

-- --------------------------------------------------------

--
-- Table structure for table `administrators`
--

DROP TABLE IF EXISTS `administrators`;
CREATE TABLE `administrators` (
  `admin_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `contact_number` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `administrators`
--

INSERT INTO `administrators` (`admin_id`, `account_id`, `first_name`, `middle_name`, `last_name`, `contact_number`) VALUES
(1, 4, 'Marian', NULL, 'Amsterdam', '09987654321');

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

DROP TABLE IF EXISTS `barangays`;
CREATE TABLE `barangays` (
  `barangay_id` int(11) NOT NULL,
  `municipality_id` int(11) NOT NULL,
  `barangay_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`barangay_id`, `municipality_id`, `barangay_name`) VALUES
(1, 1, 'Baclayon'),
(2, 1, 'Banao'),
(3, 1, 'Barangay 1 (Pob.)'),
(12, 1, 'Barangay 10 (Pob.)'),
(13, 1, 'Barangay 11 (Pob.)'),
(14, 1, 'Barangay 12 (Pob.)'),
(15, 1, 'Barangay 13 (Pob.)'),
(16, 1, 'Barangay 14 (Pob.)'),
(4, 1, 'Barangay 2 (Pob.)'),
(5, 1, 'Barangay 3 (Pob.)'),
(6, 1, 'Barangay 4 (Pob.)'),
(7, 1, 'Barangay 5 (Pob.)'),
(8, 1, 'Barangay 6 (Pob.)'),
(9, 1, 'Barangay 7 (Pob.)'),
(10, 1, 'Barangay 8 (Pob.)'),
(11, 1, 'Barangay 9 (Pob.)'),
(17, 1, 'Bariw'),
(18, 1, 'Basud'),
(19, 1, 'Bayandong'),
(20, 1, 'Bonga'),
(21, 1, 'Buang'),
(22, 1, 'Busdac'),
(23, 1, 'Cabasan'),
(24, 1, 'Cagbulacao'),
(25, 1, 'Cagraray'),
(26, 1, 'Cajogutan'),
(27, 1, 'Cawayan'),
(28, 1, 'Damacan'),
(29, 1, 'Gubat Ilawod'),
(30, 1, 'Gubat Iraya'),
(31, 1, 'Hindi'),
(32, 1, 'Igang'),
(33, 1, 'Langaton'),
(34, 1, 'Manaet'),
(35, 1, 'Mapulang Daga'),
(36, 1, 'Mataas'),
(37, 1, 'Misibis'),
(38, 1, 'Nahapunan'),
(39, 1, 'Namanday'),
(40, 1, 'Namantao'),
(41, 1, 'Napao'),
(42, 1, 'Panarayon'),
(43, 1, 'Pigcobohan'),
(44, 1, 'Pili Ilawod'),
(45, 1, 'Pili Iraya'),
(46, 1, 'Pongco'),
(47, 1, 'San Pablo'),
(48, 1, 'San Pedro'),
(49, 1, 'Sogod'),
(50, 1, 'Sula'),
(51, 1, 'Tambilagao'),
(52, 1, 'Tambongon'),
(53, 1, 'Tanagan'),
(54, 1, 'Uson'),
(55, 1, 'Vinisitahan-Basud'),
(56, 1, 'Vinisitahan-Napao'),
(57, 2, 'Anoling'),
(58, 2, 'Baligang'),
(59, 2, 'Bantonan'),
(60, 2, 'Barangay 1 (Pob.)'),
(61, 2, 'Barangay 2 (Pob.)'),
(62, 2, 'Barangay 3 (Pob.)'),
(63, 2, 'Barangay 4 (Pob.)'),
(64, 2, 'Barangay 5 (Pob.)'),
(65, 2, 'Barangay 6 (Pob.)'),
(66, 2, 'Barangay 7 (Pob.)'),
(67, 2, 'Bariw'),
(68, 2, 'Binanderahan'),
(69, 2, 'Binitayan'),
(70, 2, 'Bongabong'),
(71, 2, 'Cabagñan'),
(72, 2, 'Cabraran Pequeño'),
(73, 2, 'Caguiba'),
(74, 2, 'Calabidongan'),
(75, 2, 'Comun'),
(76, 2, 'Cotmon'),
(77, 2, 'Del Rosario'),
(78, 2, 'Gapo'),
(79, 2, 'Gotob'),
(80, 2, 'Ilawod'),
(81, 2, 'Iluluan'),
(82, 2, 'Libod'),
(83, 2, 'Ligban'),
(84, 2, 'Mabunga'),
(85, 2, 'Magogon'),
(86, 2, 'Manawan'),
(87, 2, 'Maninila'),
(88, 2, 'Mina'),
(89, 2, 'Miti'),
(90, 2, 'Palanog'),
(91, 2, 'Panoypoy'),
(92, 2, 'Pariaan'),
(93, 2, 'Quinartilan'),
(94, 2, 'Quirangay'),
(95, 2, 'Quitinday'),
(96, 2, 'Salugan'),
(97, 2, 'Solong'),
(98, 2, 'Sua'),
(99, 2, 'Sumlang'),
(100, 2, 'Tagaytay'),
(101, 2, 'Tagoytoy'),
(102, 2, 'Taladong'),
(103, 2, 'Taloto'),
(104, 2, 'Taplacon'),
(105, 2, 'Tinago'),
(106, 2, 'Tumpa'),
(107, 3, 'Alcala'),
(108, 3, 'Alobo'),
(109, 3, 'Anislag'),
(110, 3, 'Bagumbayan'),
(111, 3, 'Balinad'),
(113, 3, 'Bañadero'),
(114, 3, 'Bañag'),
(112, 3, 'Bascaran'),
(115, 3, 'Bigao'),
(116, 3, 'Binitayan'),
(117, 3, 'Bongalon'),
(118, 3, 'Budiao'),
(119, 3, 'Burgos'),
(120, 3, 'Busay'),
(121, 3, 'Canarom'),
(122, 3, 'Cullat'),
(123, 3, 'Dela Paz'),
(124, 3, 'Dinoronan'),
(125, 3, 'Gabawan'),
(126, 3, 'Gapo'),
(127, 3, 'Ibaugan'),
(128, 3, 'Ilawod Area Poblacion'),
(129, 3, 'Inarado'),
(130, 3, 'Kidaco'),
(131, 3, 'Kilicao'),
(132, 3, 'Kimantong'),
(133, 3, 'Kinawitan'),
(134, 3, 'Kiwalo'),
(135, 3, 'Lacag'),
(136, 3, 'Mabini'),
(137, 3, 'Malabog'),
(138, 3, 'Malobago'),
(139, 3, 'Maopi'),
(140, 3, 'Market Area Poblacion'),
(141, 3, 'Maroroy'),
(142, 3, 'Matnog'),
(143, 3, 'Mayon'),
(144, 3, 'Mi-isi'),
(145, 3, 'Nabasan'),
(146, 3, 'Namantao'),
(147, 3, 'Pandan'),
(148, 3, 'Peñafrancia'),
(149, 3, 'Sagpon'),
(150, 3, 'Salvacion'),
(151, 3, 'San Rafael'),
(152, 3, 'San Ramon'),
(153, 3, 'San Roque'),
(154, 3, 'San Vicente Grande'),
(155, 3, 'San Vicente Pequeño'),
(156, 3, 'Sipi'),
(157, 3, 'Tabon-tabon'),
(158, 3, 'Tagas'),
(159, 3, 'Talahib'),
(160, 3, 'Villahermosa'),
(161, 4, 'Agpay'),
(162, 4, 'Balite'),
(163, 4, 'Banao'),
(164, 4, 'Batbat'),
(165, 4, 'Binogsacan Lower'),
(166, 4, 'Binogsacan Upper'),
(167, 4, 'Bololo'),
(168, 4, 'Bubulusan'),
(169, 4, 'Calzada'),
(170, 4, 'Catomag'),
(171, 4, 'Doña Mercedes'),
(172, 4, 'Doña Tomasa'),
(173, 4, 'Ilawod'),
(174, 4, 'Inamnan Grande'),
(175, 4, 'Inamnan Pequeño'),
(176, 4, 'Inascan'),
(177, 4, 'Iraya'),
(178, 4, 'Lomacao'),
(179, 4, 'Maguiron'),
(180, 4, 'Maipon'),
(181, 4, 'Malabnig'),
(182, 4, 'Malipo'),
(183, 4, 'Malobago'),
(184, 4, 'Maninila'),
(185, 4, 'Mapaco'),
(186, 4, 'Marcial O. Rañola'),
(187, 4, 'Masarawag'),
(188, 4, 'Mauraro'),
(189, 4, 'Minto'),
(190, 4, 'Morera'),
(191, 4, 'Muladbucad Grande'),
(192, 4, 'Muladbucad Pequeño'),
(193, 4, 'Ongo'),
(194, 4, 'Palanas'),
(195, 4, 'Poblacion'),
(196, 4, 'Pood'),
(197, 4, 'Quibongbongan'),
(198, 4, 'Quitago'),
(199, 4, 'San Francisco'),
(200, 4, 'San Jose'),
(201, 4, 'San Rafael'),
(202, 4, 'Sinungtan'),
(203, 4, 'Tandarora'),
(204, 4, 'Travesia'),
(205, 5, 'Aurora Poblacion'),
(206, 5, 'Bagacay'),
(207, 5, 'Bautista'),
(208, 5, 'Cabraran'),
(209, 5, 'Calzada Poblacion'),
(210, 5, 'Del Rosario'),
(211, 5, 'Estrella'),
(212, 5, 'Florista'),
(213, 5, 'Mabini Poblacion'),
(214, 5, 'Magsaysay Poblacion'),
(215, 5, 'Mamlad'),
(216, 5, 'Maogog'),
(217, 5, 'Mercado Poblacion'),
(218, 5, 'Plaza Poblacion'),
(219, 5, 'Quitinday Poblacion'),
(220, 5, 'Rizal Poblacion'),
(221, 5, 'Salvacion'),
(222, 5, 'San Isidro'),
(223, 5, 'San Roque'),
(224, 5, 'San Vicente'),
(225, 5, 'Sinagaran'),
(226, 5, 'Villa Paz'),
(227, 5, 'White Deer Poblacion'),
(228, 6, 'Barangay 1-Em\'s Barrio'),
(237, 6, 'Barangay 10-Cabugao'),
(238, 6, 'Barangay 11-Maoyod Poblacion'),
(239, 6, 'Barangay 12-Tula-tula'),
(240, 6, 'Barangay 13-Ilawod West Poblacion'),
(241, 6, 'Barangay 14-Ilawod Poblacion'),
(242, 6, 'Barangay 15-Ilawod East Poblacion'),
(243, 6, 'Barangay 16-Kawit-East Washington Drive'),
(244, 6, 'Barangay 17-Rizal Street, Ilawod'),
(245, 6, 'Barangay 18-Cabagñan West'),
(246, 6, 'Barangay 19-Cabagñan'),
(229, 6, 'Barangay 2-Em\'s Barrio South'),
(247, 6, 'Barangay 20-Cabagñan East'),
(248, 6, 'Barangay 21-Binanuahan West'),
(249, 6, 'Barangay 22-Binanuahan East'),
(250, 6, 'Barangay 23-Imperial Court Subd.'),
(251, 6, 'Barangay 24-Rizal Street'),
(252, 6, 'Barangay 25-Lapu-lapu'),
(253, 6, 'Barangay 26-Dinagaan'),
(254, 6, 'Barangay 27-Victory Village South'),
(255, 6, 'Barangay 28-Victory Village North'),
(256, 6, 'Barangay 29-Sabang'),
(230, 6, 'Barangay 3-Em\'s Barrio East'),
(257, 6, 'Barangay 30-Pigcale'),
(258, 6, 'Barangay 31-Centro-Baybay'),
(259, 6, 'Barangay 32-San Roque'),
(260, 6, 'Barangay 33-PNR-Peñaranda St.-Iraya'),
(261, 6, 'Barangay 34-Oro Site-Magallanes St.'),
(262, 6, 'Barangay 35-Tinago'),
(263, 6, 'Barangay 36-Kapantawan'),
(264, 6, 'Barangay 37-Bitano'),
(265, 6, 'Barangay 38-Gogon'),
(266, 6, 'Barangay 39-Bonot'),
(231, 6, 'Barangay 4-Sagpon Poblacion'),
(267, 6, 'Barangay 40-Cruzada'),
(268, 6, 'Barangay 41-Bogtong'),
(269, 6, 'Barangay 42-Rawis'),
(270, 6, 'Barangay 43-Tamaoyan'),
(271, 6, 'Barangay 44-Pawa'),
(272, 6, 'Barangay 45-Dita'),
(273, 6, 'Barangay 46-San Joaquin'),
(274, 6, 'Barangay 47-Arimbay'),
(275, 6, 'Barangay 48-Bagong Abre'),
(276, 6, 'Barangay 49-Bigaa'),
(232, 6, 'Barangay 5-Sagmin Poblacion'),
(277, 6, 'Barangay 50-Padang'),
(278, 6, 'Barangay 51-Buyuan'),
(279, 6, 'Barangay 52-Matanag'),
(280, 6, 'Barangay 53-Bonga'),
(281, 6, 'Barangay 54-Mabinit'),
(282, 6, 'Barangay 55-Estanza'),
(283, 6, 'Barangay 56-Taysan'),
(284, 6, 'Barangay 57-Dap-dap'),
(285, 6, 'Barangay 58-Buragwis'),
(286, 6, 'Barangay 59-Puro'),
(233, 6, 'Barangay 6-Bañadero Poblacion'),
(287, 6, 'Barangay 60-Lamba'),
(288, 6, 'Barangay 61-Maslog'),
(289, 6, 'Barangay 62-Homapon'),
(290, 6, 'Barangay 63-Mariawa'),
(291, 6, 'Barangay 64-Bagacay'),
(292, 6, 'Barangay 65-Imalnod'),
(293, 6, 'Barangay 66-Banquerohan'),
(294, 6, 'Barangay 67-Bariis'),
(295, 6, 'Barangay 68-San Francisco'),
(296, 6, 'Barangay 69-Buenavista'),
(234, 6, 'Barangay 7-Baño'),
(297, 6, 'Barangay 70-Cagbacong'),
(235, 6, 'Barangay 8-Bagumbayan'),
(236, 6, 'Barangay 9-Pinaric'),
(298, 7, 'Alongong'),
(299, 7, 'Apud'),
(300, 7, 'Bacolod'),
(301, 7, 'Bariw'),
(302, 7, 'Bonbon'),
(303, 7, 'Buga'),
(304, 7, 'Bulusan'),
(305, 7, 'Burabod'),
(306, 7, 'Caguscos'),
(307, 7, 'East Carisac'),
(308, 7, 'Harigue'),
(309, 7, 'Libtong'),
(310, 7, 'Linao'),
(311, 7, 'Mabayawas'),
(312, 7, 'Macabugos'),
(313, 7, 'Magallang'),
(314, 7, 'Malabiga'),
(315, 7, 'Marayag'),
(316, 7, 'Matara'),
(317, 7, 'Molosbolos'),
(318, 7, 'Natasan'),
(319, 7, 'Niño Jesus'),
(320, 7, 'Nogpo'),
(321, 7, 'Pantao'),
(322, 7, 'Rawis'),
(323, 7, 'Sagrada Familia'),
(324, 7, 'Salvacion'),
(325, 7, 'Sampongan'),
(326, 7, 'San Agustin'),
(327, 7, 'San Antonio'),
(328, 7, 'San Isidro'),
(329, 7, 'San Jose'),
(330, 7, 'San Pascual'),
(331, 7, 'San Ramon'),
(332, 7, 'San Vicente'),
(333, 7, 'Santa Cruz'),
(334, 7, 'Talin-talin'),
(335, 7, 'Tambo'),
(336, 7, 'Villa Petrona'),
(337, 7, 'West Carisac'),
(338, 7, 'Zone I'),
(339, 7, 'Zone II'),
(340, 7, 'Zone III'),
(341, 7, 'Zone IV'),
(342, 7, 'Zone V'),
(343, 7, 'Zone VI'),
(344, 7, 'Zone VII'),
(345, 8, 'Abella'),
(346, 8, 'Allang'),
(347, 8, 'Amtic'),
(348, 8, 'Bacong'),
(349, 8, 'Bagumbayan'),
(350, 8, 'Balanac'),
(351, 8, 'Baligang'),
(352, 8, 'Barayong'),
(353, 8, 'Basag'),
(354, 8, 'Batang'),
(355, 8, 'Bay'),
(356, 8, 'Binanowan'),
(357, 8, 'Binatagan'),
(358, 8, 'Bobonsuran'),
(359, 8, 'Bonga'),
(360, 8, 'Busac'),
(361, 8, 'Busay'),
(362, 8, 'Cabarian'),
(363, 8, 'Calzada'),
(364, 8, 'Catburawan'),
(365, 8, 'Cavasi'),
(366, 8, 'Culliat'),
(367, 8, 'Dunao'),
(368, 8, 'Francia'),
(369, 8, 'Guilid'),
(370, 8, 'Herrera'),
(371, 8, 'Layon'),
(372, 8, 'Macalidong'),
(373, 8, 'Mahaba'),
(374, 8, 'Malama'),
(375, 8, 'Maonon'),
(376, 8, 'Nabonton'),
(377, 8, 'Nasisi'),
(378, 8, 'Oma-oma'),
(379, 8, 'Palapas'),
(380, 8, 'Pandan'),
(381, 8, 'Paulba'),
(382, 8, 'Paulog'),
(383, 8, 'Pinamaniquian'),
(384, 8, 'Pinit'),
(385, 8, 'Ranao-ranao'),
(386, 8, 'San Vicente'),
(387, 8, 'Santa Cruz'),
(388, 8, 'Tagpo'),
(389, 8, 'Tambo'),
(390, 8, 'Tandarura'),
(391, 8, 'Tastas'),
(392, 8, 'Tinago'),
(393, 8, 'Tinampo'),
(394, 8, 'Tiongson'),
(395, 8, 'Tomolin'),
(396, 8, 'Tuburan'),
(397, 8, 'Tula-tula Grande'),
(398, 8, 'Tula-tula Pequeño'),
(399, 8, 'Tupas'),
(400, 9, 'Barangay I (Pob.)'),
(401, 9, 'Barangay II (Pob.)'),
(402, 9, 'Barangay III (Pob.)'),
(403, 9, 'Barangay IV (Pob.)'),
(404, 9, 'Barangay V (Pob.)'),
(405, 9, 'Binitayan'),
(406, 9, 'Calbayog'),
(407, 9, 'Canaway'),
(408, 9, 'Salvacion'),
(409, 9, 'San Antonio Santicon'),
(410, 9, 'San Antonio Sulong'),
(411, 9, 'San Francisco'),
(412, 9, 'San Isidro Ilawod'),
(413, 9, 'San Isidro Iraya'),
(414, 9, 'San Jose'),
(415, 9, 'San Roque'),
(416, 9, 'Santa Cruz'),
(417, 9, 'Santa Teresa'),
(418, 10, 'Awang'),
(419, 10, 'Bagatangki'),
(420, 10, 'Bagumbayan'),
(421, 10, 'Balading'),
(422, 10, 'Balza'),
(423, 10, 'Bariw'),
(424, 10, 'Baybay'),
(425, 10, 'Bulang'),
(426, 10, 'Burabod'),
(427, 10, 'Cabunturan'),
(428, 10, 'Comun'),
(429, 10, 'Diaro'),
(430, 10, 'Estancia'),
(431, 10, 'Jonop'),
(432, 10, 'Labnig'),
(433, 10, 'Libod'),
(434, 10, 'Malolos'),
(435, 10, 'Matalipni'),
(436, 10, 'Ogob'),
(437, 10, 'Pawa'),
(438, 10, 'Payahan'),
(439, 10, 'Poblacion'),
(440, 10, 'Quinarabasahan'),
(441, 10, 'Santa Elena'),
(442, 10, 'Soa'),
(443, 10, 'Sugcad'),
(444, 10, 'Tagoytoy'),
(445, 10, 'Tanawan'),
(446, 10, 'Tuliw'),
(447, 11, 'Balabagon'),
(448, 11, 'Balasbas'),
(449, 11, 'Bamban'),
(450, 11, 'Buyo'),
(451, 11, 'Cabacongan'),
(452, 11, 'Cabit'),
(453, 11, 'Cawayan'),
(454, 11, 'Cawit'),
(455, 11, 'Holugan'),
(456, 11, 'It-ba'),
(457, 11, 'Malobago'),
(458, 11, 'Manumbalay'),
(459, 11, 'Nagotgot'),
(460, 11, 'Pawa'),
(461, 11, 'Tinapian'),
(462, 12, 'Badbad'),
(463, 12, 'Badian'),
(464, 12, 'Bagsa'),
(465, 12, 'Bagumbayan'),
(466, 12, 'Balogo'),
(467, 12, 'Banao'),
(468, 12, 'Bangiawon'),
(469, 12, 'Bogtong'),
(470, 12, 'Bongoran'),
(471, 12, 'Busac'),
(472, 12, 'Cadawag'),
(473, 12, 'Cagmanaba'),
(474, 12, 'Calaguimit'),
(475, 12, 'Calpi'),
(476, 12, 'Calzada'),
(477, 12, 'Camagong'),
(478, 12, 'Casinagan'),
(479, 12, 'Centro Poblacion'),
(480, 12, 'Coliat'),
(481, 12, 'Del Rosario'),
(482, 12, 'Gumabao'),
(483, 12, 'Ilaor Norte'),
(484, 12, 'Ilaor Sur'),
(485, 12, 'Iraya Norte'),
(486, 12, 'Iraya Sur'),
(487, 12, 'Manga'),
(488, 12, 'Maporong'),
(489, 12, 'Maramba'),
(490, 12, 'Matambo'),
(491, 12, 'Mayag'),
(492, 12, 'Mayao'),
(493, 12, 'Moroponros'),
(494, 12, 'Nagas'),
(495, 12, 'Obaliw-Rinas'),
(496, 12, 'Pistola'),
(497, 12, 'Ramay'),
(498, 12, 'Rizal'),
(499, 12, 'Saban'),
(500, 12, 'San Agustin'),
(501, 12, 'San Antonio'),
(502, 12, 'San Isidro'),
(503, 12, 'San Jose'),
(504, 12, 'San Juan'),
(505, 12, 'San Miguel'),
(506, 12, 'San Pascual'),
(507, 12, 'San Ramon'),
(508, 12, 'San Vicente'),
(509, 12, 'Tablon'),
(510, 12, 'Talisay'),
(511, 12, 'Talongog'),
(512, 12, 'Tapel'),
(513, 12, 'Tobgon'),
(514, 12, 'Tobog'),
(515, 13, 'Agol'),
(516, 13, 'Alabangpuro'),
(517, 13, 'Banawan'),
(518, 13, 'Barangay I (Pob.)'),
(519, 13, 'Barangay II (Pob.)'),
(520, 13, 'Barangay III (Pob.)'),
(521, 13, 'Barangay IV (Pob.)'),
(522, 13, 'Barangay V (Pob.)'),
(523, 13, 'Basicao Coastal'),
(524, 13, 'Basicao Interior'),
(525, 13, 'Binodegahan'),
(526, 13, 'Buenavista'),
(527, 13, 'Buyo'),
(528, 13, 'Caratagan'),
(529, 13, 'Cuyaoyao'),
(530, 13, 'Flores'),
(531, 13, 'La Medalla'),
(532, 13, 'Lawinon'),
(533, 13, 'Macasitas'),
(534, 13, 'Malapay'),
(535, 13, 'Malidong'),
(536, 13, 'Mamlad'),
(537, 13, 'Marigondon'),
(538, 13, 'Matanglad'),
(539, 13, 'Nablangbulod'),
(540, 13, 'Oringon'),
(541, 13, 'Palapas'),
(542, 13, 'Panganiran'),
(543, 13, 'Rawis'),
(544, 13, 'Salvacion'),
(545, 13, 'Santo Cristo'),
(546, 13, 'Sukip'),
(547, 13, 'Tibabo'),
(548, 14, 'Agos'),
(549, 14, 'Alnay'),
(550, 14, 'Alomon'),
(551, 14, 'Amoguis'),
(552, 14, 'Anopol'),
(553, 14, 'Apad'),
(554, 14, 'Balaba'),
(555, 14, 'Balangibang'),
(556, 14, 'Balinad'),
(557, 14, 'Basud'),
(558, 14, 'Binagbangan'),
(559, 14, 'Buyo'),
(560, 14, 'Centro Occidental'),
(561, 14, 'Centro Oriental'),
(562, 14, 'Cepres'),
(563, 14, 'Cotmon'),
(564, 14, 'Cotnogan'),
(565, 14, 'Danao'),
(566, 14, 'Gabon'),
(567, 14, 'Gamot'),
(568, 14, 'Itaran'),
(569, 14, 'Kinale'),
(570, 14, 'Kinuartilan'),
(571, 14, 'La Medalla'),
(572, 14, 'La Purisima'),
(573, 14, 'Lanigay'),
(574, 14, 'Lidong'),
(575, 14, 'Lourdes'),
(576, 14, 'Magpanambo'),
(577, 14, 'Magurang'),
(578, 14, 'Matacon'),
(579, 14, 'Maynaga'),
(580, 14, 'Maysua'),
(581, 14, 'Mendez'),
(582, 14, 'Napo'),
(583, 14, 'Pinagdapugan'),
(584, 14, 'Ponso'),
(585, 14, 'Salvacion'),
(586, 14, 'San Roque'),
(587, 14, 'Santa Cruz'),
(588, 14, 'Santa Teresita'),
(589, 14, 'Santicon'),
(590, 14, 'Sugcad'),
(591, 14, 'Ubaliw'),
(592, 15, 'Bagaobawan'),
(593, 15, 'Batan'),
(594, 15, 'Bilbao'),
(595, 15, 'Binosawan'),
(596, 15, 'Bogtong'),
(597, 15, 'Buenavista'),
(598, 15, 'Buhatan'),
(599, 15, 'Calanaga'),
(600, 15, 'Caracaran'),
(601, 15, 'Carogcog'),
(602, 15, 'Dap-dap'),
(603, 15, 'Gaba'),
(604, 15, 'Galicia'),
(605, 15, 'Guadalupe'),
(606, 15, 'Hamorawon'),
(607, 15, 'Lagundi'),
(608, 15, 'Liguan'),
(609, 15, 'Linao'),
(610, 15, 'Malobago'),
(611, 15, 'Mananao'),
(612, 15, 'Mancao'),
(613, 15, 'Manila'),
(614, 15, 'Masaga'),
(615, 15, 'Morocborocan'),
(616, 15, 'Nagcalsot'),
(617, 15, 'Pagcolbon'),
(618, 15, 'Poblacion'),
(619, 15, 'Sagrada'),
(620, 15, 'San Ramon'),
(621, 15, 'Santa Barbara'),
(622, 15, 'Tinocawan'),
(623, 15, 'Tinopan'),
(624, 15, 'Viga'),
(625, 15, 'Villahermosa'),
(626, 16, 'Alimsog'),
(627, 16, 'Bagong San Roque'),
(628, 16, 'Buhatan'),
(629, 16, 'Calayucay'),
(630, 16, 'Del Rosario Poblacion'),
(631, 16, 'Fidel Surtida'),
(632, 16, 'Lidong'),
(633, 16, 'Market Site Poblacion'),
(634, 16, 'Nagsiya Poblacion'),
(635, 16, 'Pandayan Poblacion'),
(636, 16, 'Salvacion'),
(637, 16, 'San Andres'),
(638, 16, 'San Fernando'),
(639, 16, 'San Francisco Poblacion'),
(640, 16, 'San Isidro'),
(641, 16, 'San Juan Poblacion'),
(642, 16, 'San Pedro Poblacion'),
(643, 16, 'San Rafael Poblacion'),
(644, 16, 'San Roque'),
(645, 16, 'San Vicente Poblacion'),
(646, 16, 'Santa Misericordia'),
(647, 16, 'Santo Domingo Poblacion'),
(648, 16, 'Santo Niño'),
(649, 17, 'Agnas'),
(650, 17, 'Bacolod'),
(651, 17, 'Bangkilingan'),
(652, 17, 'Bantayan'),
(653, 17, 'Baranghawon'),
(654, 17, 'Basagan'),
(655, 17, 'Basud'),
(656, 17, 'Bogñabong'),
(657, 17, 'Bombon'),
(658, 17, 'Bonot'),
(659, 17, 'Buang'),
(660, 17, 'Buhian'),
(661, 17, 'Cabagñan'),
(662, 17, 'Cobo'),
(663, 17, 'Comon'),
(664, 17, 'Cormidal'),
(665, 17, 'Divino Rostro'),
(666, 17, 'Fatima'),
(667, 17, 'Guinobat'),
(668, 17, 'Hacienda'),
(669, 17, 'Magapo'),
(670, 17, 'Mariroc'),
(671, 17, 'Matagbac'),
(672, 17, 'Oras'),
(673, 17, 'Oson'),
(674, 17, 'Panal'),
(675, 17, 'Pawa'),
(676, 17, 'Pinagbobong'),
(677, 17, 'Quinale Cabasan'),
(678, 17, 'Quinastillojan'),
(679, 17, 'Rawis'),
(680, 17, 'Sagurong'),
(681, 17, 'Salvacion'),
(682, 17, 'San Antonio'),
(683, 17, 'San Carlos'),
(684, 17, 'San Isidro'),
(685, 17, 'San Juan'),
(686, 17, 'San Lorenzo'),
(687, 17, 'San Ramon'),
(688, 17, 'San Roque'),
(689, 17, 'San Vicente'),
(690, 17, 'Santo Cristo'),
(691, 17, 'Sua-Igot'),
(692, 17, 'Tabiguian'),
(693, 17, 'Tagas'),
(694, 17, 'Tayhi'),
(695, 17, 'Visita'),
(696, 18, 'Bagumbayan'),
(697, 18, 'Bariis'),
(698, 18, 'Baybay'),
(699, 18, 'Belen'),
(700, 18, 'Biyong'),
(701, 18, 'Bolo'),
(702, 18, 'Cale'),
(703, 18, 'Cararayan'),
(704, 18, 'Coro-coro'),
(705, 18, 'Dap-dap'),
(706, 18, 'Gajo'),
(707, 18, 'Joroan'),
(708, 18, 'Libjo'),
(709, 18, 'Libtong'),
(710, 18, 'Matalibong'),
(711, 18, 'Maynonong'),
(712, 18, 'Mayong'),
(713, 18, 'Misibis'),
(714, 18, 'Naga'),
(715, 18, 'Nagas'),
(716, 18, 'Oyama'),
(717, 18, 'Putsan'),
(718, 18, 'San Bernardo'),
(719, 18, 'Sogod'),
(720, 18, 'Tigbi');

-- --------------------------------------------------------

--
-- Table structure for table `class`
--

DROP TABLE IF EXISTS `class`;
CREATE TABLE `class` (
  `class_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `class_title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `max_capacity` int(11) NOT NULL CHECK (`max_capacity` > 0),
  `current_enrolled` int(11) NOT NULL DEFAULT 0 CHECK (`current_enrolled` >= 0 and `current_enrolled` <= `max_capacity`),
  `status` enum('Draft','Open','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_enrollments`
--

DROP TABLE IF EXISTS `class_enrollments`;
CREATE TABLE `class_enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `learner_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL DEFAULT curdate(),
  `status` enum('enrolled','dropped','completed') NOT NULL DEFAULT 'enrolled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `class_enrollments`
--
DROP TRIGGER IF EXISTS `trg_enrollment_after_delete`;
DELIMITER $$
CREATE TRIGGER `trg_enrollment_after_delete` AFTER DELETE ON `class_enrollments` FOR EACH ROW BEGIN
    IF OLD.`status` = 'enrolled' THEN
        UPDATE `class`
        SET `current_enrolled` = GREATEST(`current_enrolled` - 1, 0)
        WHERE `class_id` = OLD.`class_id`;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_enrollment_after_insert`;
DELIMITER $$
CREATE TRIGGER `trg_enrollment_after_insert` AFTER INSERT ON `class_enrollments` FOR EACH ROW BEGIN
    IF NEW.`status` = 'enrolled' THEN
        UPDATE `class`
        SET `current_enrolled` = `current_enrolled` + 1
        WHERE `class_id` = NEW.`class_id`;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_enrollment_after_update`;
DELIMITER $$
CREATE TRIGGER `trg_enrollment_after_update` AFTER UPDATE ON `class_enrollments` FOR EACH ROW BEGIN
    IF OLD.`status` <> 'enrolled' AND NEW.`status` = 'enrolled' THEN
        UPDATE `class`
        SET `current_enrolled` = `current_enrolled` + 1
        WHERE `class_id` = NEW.`class_id`;
    ELSEIF OLD.`status` = 'enrolled' AND NEW.`status` <> 'enrolled' THEN
        UPDATE `class`
        SET `current_enrolled` = GREATEST(`current_enrolled` - 1, 0)
        WHERE `class_id` = NEW.`class_id`;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `class_schedule`
--

DROP TABLE IF EXISTS `class_schedule`;
CREATE TABLE `class_schedule` (
  `schedule_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL CHECK (`end_time` > `start_time`),
  `venue` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','rescheduled','cancelled') NOT NULL DEFAULT 'scheduled',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `class_schedule`
--
DROP TRIGGER IF EXISTS `trg_schedule_start_draft_report`;
DELIMITER $$
CREATE TRIGGER `trg_schedule_start_draft_report` AFTER UPDATE ON `class_schedule` FOR EACH ROW BEGIN
    DECLARE v_report_exists INT DEFAULT 0;

    IF OLD.`status` <> 'ongoing' AND NEW.`status` = 'ongoing' THEN
        SELECT COUNT(*) INTO v_report_exists
        FROM `post_class_report`
        WHERE `schedule_id` = NEW.`schedule_id`;

        IF v_report_exists = 0 THEN
            INSERT INTO `post_class_report` (`schedule_id`, `status`)
            VALUES (NEW.`schedule_id`, 'Pending');
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `class_teacher`
--

DROP TABLE IF EXISTS `class_teacher`;
CREATE TABLE `class_teacher` (
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `assigned_role` enum('Lead Instructor','Co-Teacher','Substitute') NOT NULL DEFAULT 'Lead Instructor',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `learners`
--

DROP TABLE IF EXISTS `learners`;
CREATE TABLE `learners` (
  `learner_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `birthdate` date NOT NULL,
  `gender` enum('Male','Female','Prefer not to say') NOT NULL,
  `contact_number` varchar(50) NOT NULL,
  `hearing_status` enum('Hearing','Mild to Moderate','Severe','Profoundly Deaf') NOT NULL,
  `speech_status` enum('Verbal','Hard of Speech','Non-Verbal / Mute') NOT NULL,
  `accessibility_notes` text DEFAULT NULL,
  `street_address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `learners`
--

INSERT INTO `learners` (`learner_id`, `account_id`, `barangay_id`, `first_name`, `middle_name`, `last_name`, `birthdate`, `gender`, `contact_number`, `hearing_status`, `speech_status`, `accessibility_notes`, `street_address`, `is_active`, `created_at`) VALUES
(1, 1, 245, 'Geo Angelo', 'Navera', 'LABALAN', '2005-03-09', 'Male', '09515482963', 'Hearing', 'Verbal', 'None', '21, P3 - Guevarra Subdivision', 1, '2026-11-20 12:58:36'),
(2, 5, 244, 'bon', 'BON', 'BONNN', '2005-05-09', 'Female', '09987456321', 'Hearing', 'Verbal', 'Clear speech clear hearing', 'Purok 1', 1, '2026-11-25 07:28:33');

-- --------------------------------------------------------

--
-- Table structure for table `municipalities`
--

DROP TABLE IF EXISTS `municipalities`;
CREATE TABLE `municipalities` (
  `municipality_id` int(11) NOT NULL,
  `municipality_name` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL DEFAULT 'Albay' CHECK (`province` = 'Albay')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `municipalities`
--

INSERT INTO `municipalities` (`municipality_id`, `municipality_name`, `province`) VALUES
(1, 'Bacacay', 'Albay'),
(2, 'Camalig', 'Albay'),
(3, 'Daraga', 'Albay'),
(4, 'Guinobatan', 'Albay'),
(5, 'Jovellar', 'Albay'),
(6, 'Legazpi City', 'Albay'),
(7, 'Libon', 'Albay'),
(8, 'Ligao City', 'Albay'),
(9, 'Malilipot', 'Albay'),
(10, 'Malinao', 'Albay'),
(11, 'Manito', 'Albay'),
(12, 'Oas', 'Albay'),
(13, 'Pio Duran', 'Albay'),
(14, 'Polangui', 'Albay'),
(15, 'Rapu-Rapu', 'Albay'),
(16, 'Santo Domingo', 'Albay'),
(17, 'Tabaco City', 'Albay'),
(18, 'Tiwi', 'Albay');

-- --------------------------------------------------------

--
-- Table structure for table `post_class_report`
--

DROP TABLE IF EXISTS `post_class_report`;
CREATE TABLE `post_class_report` (
  `report_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `status` enum('Pending','Finished','Verified','For Revision') NOT NULL DEFAULT 'Pending',
  `summary_notes` text DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `submission_timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_photos`
--

DROP TABLE IF EXISTS `report_photos`;
CREATE TABLE `report_photos` (
  `photo_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `photo_file_path` varchar(500) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'Admin'),
(3, 'Learner'),
(2, 'Teacher');

-- --------------------------------------------------------

--
-- Table structure for table `session_attendance`
--

DROP TABLE IF EXISTS `session_attendance`;
CREATE TABLE `session_attendance` (
  `attendance_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `learner_id` int(11) NOT NULL,
  `status` enum('present','absent','excused','late') NOT NULL,
  `session_rating` enum('Satisfactory','Needs Practice','Unsatisfactory') DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Prefer not to say') NOT NULL,
  `birthdate` date NOT NULL,
  `street_address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) NOT NULL,
  `specialization` varchar(150) NOT NULL,
  `years_of_experience` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `account_id`, `barangay_id`, `first_name`, `middle_name`, `last_name`, `gender`, `birthdate`, `street_address`, `contact_number`, `specialization`, `years_of_experience`, `is_active`) VALUES
(1, 3, 114, 'Angelo Geo', 'Dela', 'Cruz', 'Male', '2005-01-01', 'P2, Rizal St.', '09123456789', 'Basic FSL', 4, 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_class_enrolled_rosters`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_class_enrolled_rosters`;
CREATE TABLE `vw_class_enrolled_rosters` (
`class_id` int(11)
,`class_title` varchar(200)
,`learner_id` int(11)
,`full_name` varchar(201)
,`hearing_status` enum('Hearing','Mild to Moderate','Severe','Profoundly Deaf')
,`speech_status` enum('Verbal','Hard of Speech','Non-Verbal / Mute')
,`contact_number` varchar(50)
,`email` varchar(255)
,`barangay_name` varchar(100)
,`municipality_name` varchar(100)
,`enrollment_date` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_session_attendance_grades`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_session_attendance_grades`;
CREATE TABLE `vw_session_attendance_grades` (
`attendance_id` int(11)
,`learner_id` int(11)
,`learner_name` varchar(202)
,`class_id` int(11)
,`class_title` varchar(200)
,`schedule_id` int(11)
,`session_date` date
,`start_time` time
,`end_time` time
,`venue` varchar(255)
,`attendance_status` enum('present','absent','excused','late')
,`session_rating` enum('Satisfactory','Needs Practice','Unsatisfactory')
,`remarks` varchar(255)
,`report_id` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_session_attendance_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_session_attendance_summary`;
CREATE TABLE `vw_session_attendance_summary` (
`schedule_id` int(11)
,`class_id` int(11)
,`class_title` varchar(200)
,`session_date` date
,`start_time` time
,`end_time` time
,`venue` varchar(255)
,`session_status` enum('scheduled','completed','rescheduled','cancelled')
,`total_recorded` bigint(21)
,`present_count` decimal(22,0)
,`absent_count` decimal(22,0)
,`excused_count` decimal(22,0)
,`late_count` decimal(22,0)
,`attendance_rate_pct` decimal(28,2)
,`report_id` int(11)
,`report_status` enum('Pending','Finished','Verified','For Revision')
,`admin_remarks` text
,`submitted_by_teacher` varchar(201)
,`submission_timestamp` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_teacher_assigned_schedules`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_teacher_assigned_schedules`;
CREATE TABLE `vw_teacher_assigned_schedules` (
`teacher_id` int(11)
,`teacher_name` varchar(201)
,`assigned_role` enum('Lead Instructor','Co-Teacher','Substitute')
,`class_id` int(11)
,`class_title` varchar(200)
,`description` text
,`class_status` enum('Draft','Open','Ongoing','Completed','Cancelled')
,`current_enrolled` int(11)
,`max_capacity` int(11)
,`schedule_id` int(11)
,`session_date` date
,`start_time` time
,`end_time` time
,`venue` varchar(255)
,`session_status` enum('scheduled','completed','rescheduled','cancelled')
);

-- --------------------------------------------------------

--
-- Structure for view `vw_class_enrolled_rosters`
--
DROP TABLE IF EXISTS `vw_class_enrolled_rosters`;

DROP VIEW IF EXISTS `vw_class_enrolled_rosters`;
CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `vw_class_enrolled_rosters`  AS SELECT `ce`.`class_id` AS `class_id`, `c`.`class_title` AS `class_title`, `l`.`learner_id` AS `learner_id`, concat(`l`.`first_name`,' ',`l`.`last_name`) AS `full_name`, `l`.`hearing_status` AS `hearing_status`, `l`.`speech_status` AS `speech_status`, `l`.`contact_number` AS `contact_number`, `a`.`email` AS `email`, `b`.`barangay_name` AS `barangay_name`, `m`.`municipality_name` AS `municipality_name`, `ce`.`enrollment_date` AS `enrollment_date` FROM (((((`class_enrollments` `ce` join `class` `c` on(`c`.`class_id` = `ce`.`class_id`)) join `learners` `l` on(`l`.`learner_id` = `ce`.`learner_id`)) join `accounts` `a` on(`a`.`account_id` = `l`.`account_id`)) left join `barangays` `b` on(`b`.`barangay_id` = `l`.`barangay_id`)) left join `municipalities` `m` on(`m`.`municipality_id` = `b`.`municipality_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_session_attendance_grades`
--
DROP TABLE IF EXISTS `vw_session_attendance_grades`;

DROP VIEW IF EXISTS `vw_session_attendance_grades`;
CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `vw_session_attendance_grades`  AS SELECT `sa`.`attendance_id` AS `attendance_id`, `sa`.`learner_id` AS `learner_id`, concat(`l`.`last_name`,', ',`l`.`first_name`) AS `learner_name`, `c`.`class_id` AS `class_id`, `c`.`class_title` AS `class_title`, `cs`.`schedule_id` AS `schedule_id`, `cs`.`session_date` AS `session_date`, `cs`.`start_time` AS `start_time`, `cs`.`end_time` AS `end_time`, `cs`.`venue` AS `venue`, `sa`.`status` AS `attendance_status`, `sa`.`session_rating` AS `session_rating`, `sa`.`remarks` AS `remarks`, `pcr`.`report_id` AS `report_id` FROM ((((`session_attendance` `sa` join `learners` `l` on(`l`.`learner_id` = `sa`.`learner_id`)) join `post_class_report` `pcr` on(`pcr`.`report_id` = `sa`.`report_id`)) join `class_schedule` `cs` on(`cs`.`schedule_id` = `pcr`.`schedule_id`)) join `class` `c` on(`c`.`class_id` = `cs`.`class_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_session_attendance_summary`
--
DROP TABLE IF EXISTS `vw_session_attendance_summary`;

DROP VIEW IF EXISTS `vw_session_attendance_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `vw_session_attendance_summary`  AS SELECT `cs`.`schedule_id` AS `schedule_id`, `c`.`class_id` AS `class_id`, `c`.`class_title` AS `class_title`, `cs`.`session_date` AS `session_date`, `cs`.`start_time` AS `start_time`, `cs`.`end_time` AS `end_time`, `cs`.`venue` AS `venue`, `cs`.`status` AS `session_status`, count(`sa`.`attendance_id`) AS `total_recorded`, sum(case when `sa`.`status` = 'present' then 1 else 0 end) AS `present_count`, sum(case when `sa`.`status` = 'absent' then 1 else 0 end) AS `absent_count`, sum(case when `sa`.`status` = 'excused' then 1 else 0 end) AS `excused_count`, sum(case when `sa`.`status` = 'late' then 1 else 0 end) AS `late_count`, round(sum(case when `sa`.`status` = 'present' then 1 else 0 end) / nullif(count(`sa`.`attendance_id`),0) * 100,2) AS `attendance_rate_pct`, `pcr`.`report_id` AS `report_id`, `pcr`.`status` AS `report_status`, `pcr`.`admin_remarks` AS `admin_remarks`, concat(`t`.`first_name`,' ',`t`.`last_name`) AS `submitted_by_teacher`, `pcr`.`submission_timestamp` AS `submission_timestamp` FROM ((((`class_schedule` `cs` join `class` `c` on(`c`.`class_id` = `cs`.`class_id`)) left join `post_class_report` `pcr` on(`pcr`.`schedule_id` = `cs`.`schedule_id`)) left join `session_attendance` `sa` on(`sa`.`report_id` = `pcr`.`report_id`)) left join `teachers` `t` on(`t`.`teacher_id` = `pcr`.`submitted_by`)) GROUP BY `cs`.`schedule_id`, `c`.`class_id`, `c`.`class_title`, `cs`.`session_date`, `cs`.`start_time`, `cs`.`end_time`, `cs`.`venue`, `cs`.`status`, `pcr`.`report_id`, `pcr`.`status`, `pcr`.`admin_remarks`, `t`.`first_name`, `t`.`last_name`, `pcr`.`submission_timestamp` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_teacher_assigned_schedules`
--
DROP TABLE IF EXISTS `vw_teacher_assigned_schedules`;

DROP VIEW IF EXISTS `vw_teacher_assigned_schedules`;
CREATE ALGORITHM=UNDEFINED DEFINER=`` SQL SECURITY DEFINER VIEW `vw_teacher_assigned_schedules`  AS SELECT `ct`.`teacher_id` AS `teacher_id`, concat(`t`.`first_name`,' ',`t`.`last_name`) AS `teacher_name`, `ct`.`assigned_role` AS `assigned_role`, `c`.`class_id` AS `class_id`, `c`.`class_title` AS `class_title`, `c`.`description` AS `description`, `c`.`status` AS `class_status`, `c`.`current_enrolled` AS `current_enrolled`, `c`.`max_capacity` AS `max_capacity`, `cs`.`schedule_id` AS `schedule_id`, `cs`.`session_date` AS `session_date`, `cs`.`start_time` AS `start_time`, `cs`.`end_time` AS `end_time`, `cs`.`venue` AS `venue`, `cs`.`status` AS `session_status` FROM (((`class_teacher` `ct` join `teachers` `t` on(`t`.`teacher_id` = `ct`.`teacher_id`)) join `class` `c` on(`c`.`class_id` = `ct`.`class_id`)) join `class_schedule` `cs` on(`cs`.`class_id` = `c`.`class_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_accounts_role` (`role_id`);

--
-- Indexes for table `administrators`
--
ALTER TABLE `administrators`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `account_id` (`account_id`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`barangay_id`),
  ADD UNIQUE KEY `uq_muni_barangay` (`municipality_id`,`barangay_name`);

--
-- Indexes for table `class`
--
ALTER TABLE `class`
  ADD PRIMARY KEY (`class_id`),
  ADD KEY `fk_class_admin` (`created_by`);

--
-- Indexes for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `uq_class_learner` (`class_id`,`learner_id`),
  ADD KEY `fk_enroll_learner` (`learner_id`);

--
-- Indexes for table `class_schedule`
--
ALTER TABLE `class_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `fk_schedule_class` (`class_id`);

--
-- Indexes for table `class_teacher`
--
ALTER TABLE `class_teacher`
  ADD PRIMARY KEY (`class_id`,`teacher_id`),
  ADD KEY `fk_ct_teacher` (`teacher_id`);

--
-- Indexes for table `learners`
--
ALTER TABLE `learners`
  ADD PRIMARY KEY (`learner_id`),
  ADD KEY `fk_learner_account` (`account_id`),
  ADD KEY `fk_learner_barangay` (`barangay_id`);

--
-- Indexes for table `municipalities`
--
ALTER TABLE `municipalities`
  ADD PRIMARY KEY (`municipality_id`),
  ADD UNIQUE KEY `municipality_name` (`municipality_name`);

--
-- Indexes for table `post_class_report`
--
ALTER TABLE `post_class_report`
  ADD PRIMARY KEY (`report_id`),
  ADD UNIQUE KEY `schedule_id` (`schedule_id`),
  ADD KEY `fk_report_teacher` (`submitted_by`);

--
-- Indexes for table `report_photos`
--
ALTER TABLE `report_photos`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `fk_photos_report` (`report_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `session_attendance`
--
ALTER TABLE `session_attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `uq_report_learner` (`report_id`,`learner_id`),
  ADD KEY `fk_attendance_learner` (`learner_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `account_id` (`account_id`),
  ADD KEY `fk_teacher_barangay` (`barangay_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `administrators`
--
ALTER TABLE `administrators`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `barangay_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=721;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_schedule`
--
ALTER TABLE `class_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `learners`
--
ALTER TABLE `learners`
  MODIFY `learner_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `municipalities`
--
ALTER TABLE `municipalities`
  MODIFY `municipality_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `post_class_report`
--
ALTER TABLE `post_class_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_photos`
--
ALTER TABLE `report_photos`
  MODIFY `photo_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `session_attendance`
--
ALTER TABLE `session_attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE;

--
-- Constraints for table `administrators`
--
ALTER TABLE `administrators`
  ADD CONSTRAINT `fk_admin_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `barangays`
--
ALTER TABLE `barangays`
  ADD CONSTRAINT `fk_barangays_muni` FOREIGN KEY (`municipality_id`) REFERENCES `municipalities` (`municipality_id`) ON UPDATE CASCADE;

--
-- Constraints for table `class`
--
ALTER TABLE `class`
  ADD CONSTRAINT `fk_class_admin` FOREIGN KEY (`created_by`) REFERENCES `administrators` (`admin_id`) ON UPDATE CASCADE;

--
-- Constraints for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD CONSTRAINT `fk_enroll_class` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enroll_learner` FOREIGN KEY (`learner_id`) REFERENCES `learners` (`learner_id`) ON UPDATE CASCADE;

--
-- Constraints for table `class_schedule`
--
ALTER TABLE `class_schedule`
  ADD CONSTRAINT `fk_schedule_class` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `class_teacher`
--
ALTER TABLE `class_teacher`
  ADD CONSTRAINT `fk_ct_class` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ct_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `learners`
--
ALTER TABLE `learners`
  ADD CONSTRAINT `fk_learner_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_learner_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`barangay_id`) ON UPDATE CASCADE;

--
-- Constraints for table `post_class_report`
--
ALTER TABLE `post_class_report`
  ADD CONSTRAINT `fk_report_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `class_schedule` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_report_teacher` FOREIGN KEY (`submitted_by`) REFERENCES `teachers` (`teacher_id`) ON UPDATE CASCADE;

--
-- Constraints for table `report_photos`
--
ALTER TABLE `report_photos`
  ADD CONSTRAINT `fk_photos_report` FOREIGN KEY (`report_id`) REFERENCES `post_class_report` (`report_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `session_attendance`
--
ALTER TABLE `session_attendance`
  ADD CONSTRAINT `fk_attendance_learner` FOREIGN KEY (`learner_id`) REFERENCES `learners` (`learner_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_report` FOREIGN KEY (`report_id`) REFERENCES `post_class_report` (`report_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teacher_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_teacher_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`barangay_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

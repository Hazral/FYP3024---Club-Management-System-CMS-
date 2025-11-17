-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 08:44 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cms`
--

-- --------------------------------------------------------

--
-- Table structure for table `casactivity`
--

CREATE TABLE `casactivity` (
  `actID` int(11) NOT NULL,
  `actTime` time DEFAULT curtime(),
  `actDate` date DEFAULT curdate(),
  `actPosted_at` datetime DEFAULT current_timestamp(),
  `actDescription` text DEFAULT NULL,
  `actType` varchar(255) DEFAULT NULL,
  `clubID` int(11) NOT NULL,
  `actImg` varchar(255) DEFAULT NULL,
  `actVid` varchar(255) DEFAULT NULL,
  `actTitle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `casactivity`
--

INSERT INTO `casactivity` (`actID`, `actTime`, `actDate`, `actPosted_at`, `actDescription`, `actType`, `clubID`, `actImg`, `actVid`, `actTitle`) VALUES
(6, '16:00:00', '2025-11-06', '2025-11-11 04:00:26', 'A short introduction session for new members to learn about the club, activities, and committee.', 'Recruitment/Orientation', 9, NULL, NULL, 'Accounting Freshmen Welcome Session'),
(7, '16:00:00', '2025-11-06', '2025-11-11 04:01:02', 'Members form small groups and compete to solve a budget simulation case.', 'Leadership/Team Building', 9, NULL, NULL, 'Budget Simulation Team Challenge'),
(8, '16:01:00', '2025-11-11', '2025-11-11 04:04:00', 'A casual meeting to discuss personal finance topics and share tips.', 'Meeting', 9, 'uploads/activities/activity_69124530e9c1d4.20927976.jpg', 'uploads/activities/activity_video_69124530ea04f9.48495472.mp4', 'Monthly Financial Literacy Discussion'),
(10, '17:00:00', '2025-11-06', '2025-11-11 04:12:36', 'A beginner-friendly session to explain club structure and activities.', 'Recruitment/Orientation', 10, NULL, NULL, 'Intro to Chess Club'),
(11, '10:00:00', '2025-11-11', '2025-11-11 04:13:21', 'Teams race to solve chess puzzles in the fastest time.', 'Leadership/Team Building', 10, NULL, NULL, 'Puzzle Relay Challenge'),
(12, '08:20:00', '2025-11-15', '2025-11-11 04:13:57', 'Members meet to analyze openings, tactics, and classic matches.', 'Meeting', 10, NULL, NULL, 'Weekly Strategy Study'),
(13, '16:20:00', '2025-11-16', '2025-11-11 04:16:27', 'A relaxed free-play evening with friendly matches.', 'Social/Gathering', 10, NULL, NULL, 'Casual Chess Night'),
(14, '06:22:00', '2025-11-11', '2025-11-11 12:16:50', 'test', 'Leadership/Team Building', 9, NULL, NULL, 'test'),
(16, '09:10:00', '2025-11-12', '2025-11-12 20:10:03', 'test', 'Meeting', 9, NULL, NULL, 'test2'),
(17, '08:00:00', '2025-11-16', '2025-11-12 20:11:48', 'test', 'Leadership/Team Building', 9, NULL, NULL, 'test 2025');

-- --------------------------------------------------------

--
-- Table structure for table `casactivity_attendance`
--

CREATE TABLE `casactivity_attendance` (
  `actAttendanceID` int(11) NOT NULL,
  `actID` int(11) NOT NULL,
  `studID` int(11) NOT NULL,
  `status` enum('Present','Absent') DEFAULT 'Absent',
  `actAttendTime` time DEFAULT curtime(),
  `actAttendDate` date DEFAULT curdate(),
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `casactivity_attendance`
--

INSERT INTO `casactivity_attendance` (`actAttendanceID`, `actID`, `studID`, `status`, `actAttendTime`, `actAttendDate`, `remarks`) VALUES
(1, 8, 41, 'Present', '19:29:01', '2025-11-12', '');

-- --------------------------------------------------------

--
-- Table structure for table `casannouncement`
--

CREATE TABLE `casannouncement` (
  `annID` int(11) NOT NULL,
  `anntitle` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `annPosted_at` datetime DEFAULT current_timestamp(),
  `clubID` int(11) NOT NULL,
  `annImg` varchar(255) DEFAULT NULL,
  `annVid` varchar(255) DEFAULT NULL,
  `comment_count` int(11) DEFAULT 0,
  `annType` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `casannouncement`
--

INSERT INTO `casannouncement` (`annID`, `anntitle`, `content`, `annPosted_at`, `clubID`, `annImg`, `annVid`, `comment_count`, `annType`) VALUES
(12, 'Reminder to Update Member Information2', 'All registered members are reminded to update their personal details in the membership record. This ensures smooth communication throughout the semester.', '2025-11-10 14:15:23', 9, NULL, NULL, 0, 'Private'),
(13, 'Chess Tips & Weekly Highlights', 'Kelab Catur will be sharing weekly chess highlights and educational posts for students interested in improving their thinking and analytical skills.', '2025-11-10 14:22:29', 10, 'uploads/announcements/announcement_691184a5e4985.jpg', NULL, -2, 'Public'),
(14, 'Member Attendance Update', 'Members are reminded to confirm their attendance for the upcoming monthly meeting. This helps us ensure proper documentation and planning.', '2025-11-10 14:23:43', 10, NULL, NULL, 0, 'Private'),
(15, 'Kelab Badminton Official Communication Channel', 'Kelab Badminton has launched its official communication channel. Students may follow us for updates, announcements, and club-related information.', '2025-11-10 14:25:19', 11, NULL, NULL, 0, 'Public'),
(16, 'Uniform Collection Reminder', 'Members who submitted their names for the club uniform are required to confirm their size with the committee before Friday.', '2025-11-10 14:25:53', 11, NULL, NULL, 0, 'Private'),
(17, 'Cyber Awareness Information Board', 'Kelab Cyber Security has created an information board with weekly cyber safety tips for all UPTM students. Stay alert and practice safe digital habits.', '2025-11-10 14:28:08', 12, 'uploads/announcements/announcement_691185f8d91d9.jpg', NULL, 0, 'Public'),
(18, 'Committee Meeting Agenda Update', 'Dear committee members, the agenda for our upcoming internal meeting has been updated. Please review the revised points shared in the committee WhatsApp group before the meeting.', '2025-11-10 14:29:52', 12, NULL, NULL, 0, 'Private'),
(19, 'Club Information for New Students', 'Kelab Futsal is open to new members. Students may follow our page for updates, announcements, and club-related information throughout the semester.', '2025-11-10 14:33:36', 13, 'uploads/announcements/announcement_691187402272d.jpg', NULL, -1, 'Public'),
(20, 'Semester Member Reminder', 'All registered members are reminded to stay updated with our club’s activities and announcements. If you no longer wish to remain in the club, please inform the committee so we can update our records.', '2025-11-10 14:36:23', 13, NULL, NULL, 0, 'Private'),
(21, 'acc test', 'acc test', '2025-11-18 03:20:14', 9, 'uploads/announcements/announcement_691b756e342d9.jpg', NULL, 0, 'Public');

-- --------------------------------------------------------

--
-- Table structure for table `casevents`
--

CREATE TABLE `casevents` (
  `eventID` int(11) NOT NULL,
  `evTitle` varchar(250) NOT NULL,
  `evDescription` text NOT NULL,
  `evLocation` varchar(250) NOT NULL,
  `evPosted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `clubID` int(11) NOT NULL,
  `evImg` varchar(255) DEFAULT NULL,
  `evVid` varchar(255) DEFAULT NULL,
  `comment_count` int(11) DEFAULT 0,
  `evTime` time DEFAULT curtime(),
  `evDate` date DEFAULT curdate(),
  `evType` varchar(50) DEFAULT NULL,
  `evCapacity` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `casevents`
--

INSERT INTO `casevents` (`eventID`, `evTitle`, `evDescription`, `evLocation`, `evPosted_at`, `clubID`, `evImg`, `evVid`, `comment_count`, `evTime`, `evDate`, `evType`, `evCapacity`) VALUES
(12, 'Introduction to Stock Market Basics', 'A beginner-friendly session explaining how stocks work, how to start investing, and common mistakes to avoid.', 'Auditorium 2', '2025-11-10 19:27:40', 9, 'uploads/events/event_6912414f48771.jpg', 'uploads/events/event_vid_6912420b66d5f.mp4', 0, '08:00:00', '2025-11-11', 'Public', 150),
(13, 'Career Talk: Paths in Accounting & Finance', 'Guest speakers from professional firms share career tips and the skills needed in the accounting field.', 'Dewan Seminar', '2025-11-10 19:28:45', 9, 'uploads/events/event_6912419d3cad2.jpg', NULL, 0, '08:30:00', '2025-11-12', 'Public', 120),
(14, 'Committee Monthly Budget Review', 'Internal meeting to review club funds, event spending, and upcoming financial reports.', 'Meeting Room 1', '2025-11-10 19:31:22', 9, NULL, NULL, 0, '16:30:00', '2025-11-02', 'Private', 20),
(15, 'Planning Session for Upcoming Accounting Competition', 'Committee-only planning session for the annual inter-college accounting challenge.', 'Block C, Level 3', '2025-11-10 19:32:37', 9, NULL, NULL, 0, '07:30:00', '2025-11-11', 'Private', 15),
(16, 'Internal Training: Financial Reporting Practice', 'Members-only training to practice preparing financial statements for club activities.', 'Lab 1', '2025-11-10 19:33:38', 9, NULL, NULL, 0, '09:30:00', '2025-11-13', 'Private', 25),
(17, 'Beginner Chess Workshop', 'A basic introduction to chess rules, tactics, and beginner openings.', 'Library Discussion Room', '2025-11-10 19:40:01', 10, NULL, NULL, 0, '08:40:00', '2025-11-03', 'Public', 40),
(18, 'UPTM Mini Chess Tournament', 'A casual campus-wide chess tournament open to all students.', 'Block E Lobby', '2025-11-10 19:41:18', 10, NULL, NULL, 0, '08:40:00', '2025-11-11', 'Public', 60),
(19, 'Chess Strategy Talk: Thinking Ahead', 'A talk on planning, pattern recognition, and mid-game strategies.', 'Lecture Hall 2', '2025-11-10 19:42:07', 10, NULL, NULL, 0, '15:40:00', '2025-11-15', 'Public', 1),
(20, 'acc testt', 'acc', 'Auditorium 2', '2025-11-17 19:21:51', 9, 'uploads/events/event_691b75cf35ef3.jpg', 'uploads/events/event_vid_691b75cf36133.mp4', 0, '15:21:00', '2025-11-20', 'Public', 1);

-- --------------------------------------------------------

--
-- Table structure for table `clubsocieties`
--

CREATE TABLE `clubsocieties` (
  `clubID` int(11) NOT NULL,
  `clubName` varchar(50) NOT NULL,
  `clubDescription` varchar(350) DEFAULT NULL,
  `lectID` int(11) NOT NULL,
  `clubCapacity` int(10) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `clubLogo` varchar(255) DEFAULT NULL,
  `clubBanner` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clubsocieties`
--

INSERT INTO `clubsocieties` (`clubID`, `clubName`, `clubDescription`, `lectID`, `clubCapacity`, `created_at`, `clubLogo`, `clubBanner`) VALUES
(9, 'Kelab Accounting 2', 'A club for accounting students to enhance financial literacy, participate in academic workshops, and prepare for industry certifications.', 6, 100, '2025-11-10 13:26:54', 'uploads/clubs/logos/club_logo_9_1762753329.jpg', 'uploads/clubs/banners/club_banner_9_1762753697.png'),
(10, 'Kelab Catur', 'A chess club for strategic thinkers who want to learn, practice, and compete in chess tournaments.', 6, 500, '2025-11-10 13:28:35', 'uploads/clubs/logos/club_logo_10_1762753755.jpg', 'uploads/clubs/banners/club_banner_10_1762753755.jpg'),
(11, 'Kelab Badminton', 'A sports club for badminton enthusiasts offering training sessions, friendly matches, and inter-university competitions.', 7, 200, '2025-11-10 13:29:41', 'uploads/clubs/logos/club_logo_11_1762753871.jpg', 'uploads/clubs/banners/club_banner_11_1762753871.jpg'),
(12, 'Kelab Cyber Security', 'A tech club focusing on cybersecurity awareness, ethical hacking workshops, and digital safety training.', 7, 300, '2025-11-10 13:30:33', 'uploads/clubs/logos/club_logo_12_1762753958.jpg', 'uploads/clubs/banners/club_banner_12_1762753958.jpg'),
(13, 'Kelab Futsal', 'A futsal club providing training, friendly matches, and participation in futsal tournaments for all skill levels.', 7, 400, '2025-11-10 13:32:43', 'uploads/clubs/logos/club_logo_13_1762754060.png', 'uploads/clubs/banners/club_banner_13_1762754060.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `commentID` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_edited` tinyint(1) DEFAULT 0,
  `post_type` enum('announcement','event','activity') NOT NULL,
  `post_id` int(11) NOT NULL,
  `commenter_type` enum('student','lecturer') NOT NULL,
  `commenter_id` int(11) NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by_type` enum('student','lecturer') DEFAULT NULL,
  `deleted_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`commentID`, `content`, `created_at`, `updated_at`, `is_edited`, `post_type`, `post_id`, `commenter_type`, `commenter_id`, `parent_comment_id`, `is_deleted`, `deleted_at`, `deleted_by_type`, `deleted_by_id`) VALUES
(88, 'Hi', '2025-11-10 15:37:38', '2025-11-12 19:29:39', 0, 'announcement', 13, 'student', 41, NULL, 1, NULL, NULL, NULL),
(89, 'Hi Bro', '2025-11-10 15:38:41', '2025-11-10 15:38:41', 0, 'announcement', 13, 'student', 42, 88, 0, NULL, NULL, NULL),
(90, 'How are you?', '2025-11-10 15:41:38', '2025-11-12 19:30:14', 0, 'announcement', 13, 'student', 41, 88, 1, NULL, NULL, NULL),
(91, 'Hi guys', '2025-11-10 15:44:57', '2025-11-10 15:44:57', 0, 'announcement', 13, 'lecturer', 6, 88, 0, NULL, NULL, NULL),
(92, 'hi', '2025-11-11 10:11:51', '2025-11-11 10:11:51', 0, 'event', 12, 'student', 41, NULL, 0, NULL, NULL, NULL),
(93, 'HI', '2025-11-11 11:04:25', '2025-11-11 11:04:25', 0, 'announcement', 11, 'student', 41, NULL, 0, NULL, NULL, NULL),
(94, 'hi', '2025-11-11 12:08:00', '2025-11-11 12:08:00', 0, 'event', 19, 'student', 41, NULL, 0, NULL, NULL, NULL),
(95, 'miaw', '2025-11-11 12:13:31', '2025-11-11 12:13:31', 0, 'event', 19, 'student', 41, NULL, 0, NULL, NULL, NULL),
(96, 'yo', '2025-11-11 12:13:50', '2025-11-11 12:13:50', 0, 'announcement', 12, 'lecturer', 6, NULL, 0, NULL, NULL, NULL),
(97, 'yo', '2025-11-11 12:14:09', '2025-11-11 12:14:09', 0, 'event', 12, 'student', 41, NULL, 0, NULL, NULL, NULL),
(98, 'miaw', '2025-11-11 20:05:40', '2025-11-11 20:05:40', 0, 'event', 12, 'student', 41, 92, 0, NULL, NULL, NULL),
(99, 'hi', '2025-11-12 20:13:04', '2025-11-12 20:13:04', 0, 'event', 19, 'student', 41, NULL, 0, NULL, NULL, NULL),
(100, 'hi', '2025-11-12 20:17:17', '2025-11-12 20:17:17', 0, 'activity', 17, 'lecturer', 6, NULL, 0, NULL, NULL, NULL),
(101, 'hi', '2025-11-12 20:17:27', '2025-11-12 20:17:27', 0, 'announcement', 12, 'lecturer', 6, NULL, 0, NULL, NULL, NULL),
(102, 'hi', '2025-11-12 20:17:35', '2025-11-12 20:17:35', 0, 'event', 13, 'lecturer', 6, NULL, 0, NULL, NULL, NULL),
(103, 'hi', '2025-11-12 20:17:47', '2025-11-12 20:17:47', 0, 'announcement', 11, 'student', 41, NULL, 0, NULL, NULL, NULL),
(104, 'hi', '2025-11-12 20:26:16', '2025-11-12 20:26:16', 0, 'announcement', 11, 'student', 41, NULL, 0, NULL, NULL, NULL),
(105, 'hi', '2025-11-12 20:26:30', '2025-11-12 20:26:30', 0, 'announcement', 18, 'student', 41, NULL, 0, NULL, NULL, NULL),
(106, 'hi', '2025-11-12 20:32:03', '2025-11-12 20:32:03', 0, 'announcement', 18, 'student', 41, NULL, 0, NULL, NULL, NULL),
(107, 'hi', '2025-11-12 20:32:12', '2025-11-12 20:32:12', 0, 'event', 19, 'student', 41, NULL, 0, NULL, NULL, NULL),
(108, 'hi', '2025-11-12 20:32:22', '2025-11-12 20:32:22', 0, 'announcement', 18, 'student', 41, NULL, 0, NULL, NULL, NULL),
(109, 'miaw', '2025-11-17 00:47:14', '2025-11-17 00:47:14', 0, 'announcement', 19, 'student', 41, NULL, 0, NULL, NULL, NULL),
(110, 'miaw', '2025-11-17 00:47:33', '2025-11-17 00:47:33', 0, 'announcement', 19, 'student', 41, NULL, 0, NULL, NULL, NULL),
(111, 'hi', '2025-11-18 03:09:03', '2025-11-18 03:09:03', 0, 'event', 19, 'student', 43, NULL, 0, NULL, NULL, NULL),
(112, 'hi', '2025-11-18 03:09:55', '2025-11-18 03:10:28', 0, 'announcement', 19, 'student', 43, NULL, 1, NULL, NULL, NULL),
(113, 'hii', '2025-11-18 03:10:01', '2025-11-18 03:10:15', 1, 'announcement', 19, 'student', 43, 112, 1, NULL, NULL, NULL),
(114, 'hi', '2025-11-18 03:22:52', '2025-11-18 03:22:52', 0, 'event', 20, 'lecturer', 6, NULL, 0, NULL, NULL, NULL),
(115, 'hi sir', '2025-11-18 03:23:10', '2025-11-18 03:23:10', 0, 'event', 20, 'student', 43, 114, 0, NULL, NULL, NULL),
(116, 'hi', '2025-11-18 03:33:26', '2025-11-18 03:33:26', 0, 'announcement', 21, 'student', 43, NULL, 0, NULL, NULL, NULL),
(117, 'hi man', '2025-11-18 03:33:33', '2025-11-18 03:34:42', 0, 'announcement', 21, 'student', 43, 116, 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `comment_likes`
--

CREATE TABLE `comment_likes` (
  `likeID` int(11) NOT NULL,
  `commentID` int(11) NOT NULL,
  `liker_type` enum('student','lecturer') NOT NULL,
  `liker_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comment_likes`
--

INSERT INTO `comment_likes` (`likeID`, `commentID`, `liker_type`, `liker_id`, `created_at`) VALUES
(18, 92, 'student', 41, '2025-11-11 10:11:58'),
(19, 93, 'student', 41, '2025-11-11 11:04:31'),
(20, 97, 'student', 41, '2025-11-11 20:05:24'),
(21, 114, 'lecturer', 6, '2025-11-18 03:23:23'),
(22, 117, 'student', 43, '2025-11-18 03:33:37'),
(23, 116, 'student', 43, '2025-11-18 03:33:49');

-- --------------------------------------------------------

--
-- Table structure for table `events_attendance`
--

CREATE TABLE `events_attendance` (
  `attendanceID` int(11) NOT NULL,
  `partID` int(11) NOT NULL,
  `status` enum('Present','Absent') DEFAULT 'Absent',
  `attendTime` time DEFAULT NULL,
  `attendDate` date DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events_attendance`
--

INSERT INTO `events_attendance` (`attendanceID`, `partID`, `status`, `attendTime`, `attendDate`, `remarks`) VALUES
(8, 10, 'Present', '05:02:21', '2025-11-11', ''),
(9, 9, 'Absent', NULL, '2025-11-11', 'mistake'),
(10, 7, 'Present', '20:06:51', '2025-11-11', 'late');

-- --------------------------------------------------------

--
-- Table structure for table `events_participant`
--

CREATE TABLE `events_participant` (
  `partID` int(11) NOT NULL,
  `eventID` int(11) NOT NULL,
  `studID` int(11) NOT NULL,
  `partDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events_participant`
--

INSERT INTO `events_participant` (`partID`, `eventID`, `studID`, `partDate`) VALUES
(6, 19, 42, '2025-11-10 20:33:50'),
(7, 15, 41, '2025-11-10 20:46:08'),
(9, 12, 42, '2025-11-10 20:47:13'),
(10, 14, 41, '2025-11-10 21:02:21'),
(11, 16, 41, '2025-11-11 02:05:55'),
(14, 20, 43, '2025-11-17 19:22:10');

-- --------------------------------------------------------

--
-- Table structure for table `lecturer`
--

CREATE TABLE `lecturer` (
  `lectID` int(11) NOT NULL,
  `lectEmail` varchar(250) DEFAULT NULL,
  `lectName` varchar(250) NOT NULL,
  `lectPass` varchar(250) NOT NULL,
  `lectFaculty` varchar(250) NOT NULL,
  `lectProfileImg` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturer`
--

INSERT INTO `lecturer` (`lectID`, `lectEmail`, `lectName`, `lectPass`, `lectFaculty`, `lectProfileImg`) VALUES
(6, 'shazwan2@gmail.com', 'MUHAMMAD SHAZWAN', '$2y$10$dsM41z43G85cs68v1.2koeNYhIYJGt4Tbv05T02.mjAfef0ebjWm.', 'Faculty of Business & Accountancy', 'uploads/lecturer_profiles/lecturer_6_1763408114.jpg'),
(7, 'aliolabola@gmail.com', 'MUHAMMAD ALI', '$2y$10$lIX8hT0LnsYy.4U0n7EiO.zWUAAvMQNE384DhIIXDtb6RBN4nUCvO', 'Faculty of Computing & Multimedia (FCOM)', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `membership`
--

CREATE TABLE `membership` (
  `cmID` int(11) NOT NULL,
  `clubID` int(11) NOT NULL,
  `studID` int(11) NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership`
--

INSERT INTO `membership` (`cmID`, `clubID`, `studID`, `joined_at`) VALUES
(11, 9, 41, '2025-11-10 14:47:52'),
(12, 12, 41, '2025-11-11 11:05:51'),
(15, 9, 43, '2025-11-18 03:27:18');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `studID` int(11) NOT NULL,
  `studEmail` varchar(250) DEFAULT NULL,
  `studName` varchar(150) NOT NULL,
  `studPass` varchar(250) NOT NULL,
  `studNoID` varchar(20) NOT NULL,
  `studProgramme` varchar(255) NOT NULL,
  `studSem` int(3) NOT NULL,
  `register_date` datetime DEFAULT current_timestamp(),
  `studProfileImg` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`studID`, `studEmail`, `studName`, `studPass`, `studNoID`, `studProgramme`, `studSem`, `register_date`, `studProfileImg`) VALUES
(41, 'aral@gmail.com', 'MUHAMMAD HAZRAL BIN HASSAN', '$2y$10$OjWL/bKKQARhJh98SIwNIeWmDKf8HL9efStoirBo1zh8MPP8o/H4e', 'AM2307013959', 'Diploma in Computer Science (CC101)', 7, '2025-11-10 13:16:21', 'student_41_1762760273.png'),
(42, 'idzham@gmail.com', 'MUHAMMAD IDZHAM BIN AZLAN', '$2y$10$u5n.rOBPlWq0bSnHmRX4v.GxP6SCGmfb1tDEzjlBrV.HHoIsGDzY6', 'AM2307013962', 'Diploma in Computer Science (CC101)', 7, '2025-11-10 13:19:18', 'student_42_1762760430.png'),
(43, 'irfan2@gmail.com', 'MUHAMMAD IRFAN', '$2y$10$bJDBscUTYAPRYI.3nxC6lOAwqwhMnGs/.I54DR7TcB40JGerGjGYK', 'am2307013960', 'cc101', 5, '2025-11-18 02:58:47', 'student_43_1763406664.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `casactivity`
--
ALTER TABLE `casactivity`
  ADD PRIMARY KEY (`actID`),
  ADD KEY `clubID` (`clubID`);

--
-- Indexes for table `casactivity_attendance`
--
ALTER TABLE `casactivity_attendance`
  ADD PRIMARY KEY (`actAttendanceID`),
  ADD KEY `actID` (`actID`),
  ADD KEY `studID` (`studID`);

--
-- Indexes for table `casannouncement`
--
ALTER TABLE `casannouncement`
  ADD PRIMARY KEY (`annID`),
  ADD KEY `clubID` (`clubID`);

--
-- Indexes for table `casevents`
--
ALTER TABLE `casevents`
  ADD PRIMARY KEY (`eventID`),
  ADD KEY `clubID` (`clubID`);

--
-- Indexes for table `clubsocieties`
--
ALTER TABLE `clubsocieties`
  ADD PRIMARY KEY (`clubID`),
  ADD KEY `lectID` (`lectID`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`commentID`),
  ADD KEY `idx_post` (`post_type`,`post_id`),
  ADD KEY `idx_commenter` (`commenter_type`,`commenter_id`),
  ADD KEY `idx_parent` (`parent_comment_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_deleted` (`is_deleted`);

--
-- Indexes for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD PRIMARY KEY (`likeID`),
  ADD UNIQUE KEY `unique_like` (`commentID`,`liker_type`,`liker_id`),
  ADD KEY `idx_comment` (`commentID`),
  ADD KEY `idx_liker` (`liker_type`,`liker_id`);

--
-- Indexes for table `events_attendance`
--
ALTER TABLE `events_attendance`
  ADD PRIMARY KEY (`attendanceID`),
  ADD KEY `partID` (`partID`);

--
-- Indexes for table `events_participant`
--
ALTER TABLE `events_participant`
  ADD PRIMARY KEY (`partID`),
  ADD KEY `eventID` (`eventID`),
  ADD KEY `studID` (`studID`);

--
-- Indexes for table `lecturer`
--
ALTER TABLE `lecturer`
  ADD PRIMARY KEY (`lectID`);

--
-- Indexes for table `membership`
--
ALTER TABLE `membership`
  ADD PRIMARY KEY (`cmID`),
  ADD KEY `clubID` (`clubID`),
  ADD KEY `studID` (`studID`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`studID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `casactivity`
--
ALTER TABLE `casactivity`
  MODIFY `actID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `casactivity_attendance`
--
ALTER TABLE `casactivity_attendance`
  MODIFY `actAttendanceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `casannouncement`
--
ALTER TABLE `casannouncement`
  MODIFY `annID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `casevents`
--
ALTER TABLE `casevents`
  MODIFY `eventID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `clubsocieties`
--
ALTER TABLE `clubsocieties`
  MODIFY `clubID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `commentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT for table `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `likeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `events_attendance`
--
ALTER TABLE `events_attendance`
  MODIFY `attendanceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `events_participant`
--
ALTER TABLE `events_participant`
  MODIFY `partID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `lecturer`
--
ALTER TABLE `lecturer`
  MODIFY `lectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `membership`
--
ALTER TABLE `membership`
  MODIFY `cmID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `studID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `casactivity`
--
ALTER TABLE `casactivity`
  ADD CONSTRAINT `casactivity_ibfk_1` FOREIGN KEY (`clubID`) REFERENCES `clubsocieties` (`clubID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `casactivity_attendance`
--
ALTER TABLE `casactivity_attendance`
  ADD CONSTRAINT `casactivity_attendance_ibfk_1` FOREIGN KEY (`actID`) REFERENCES `casactivity` (`actID`) ON DELETE CASCADE,
  ADD CONSTRAINT `casactivity_attendance_ibfk_2` FOREIGN KEY (`studID`) REFERENCES `student` (`studID`) ON DELETE CASCADE;

--
-- Constraints for table `casannouncement`
--
ALTER TABLE `casannouncement`
  ADD CONSTRAINT `casannouncement_ibfk_1` FOREIGN KEY (`clubID`) REFERENCES `clubsocieties` (`clubID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `casevents`
--
ALTER TABLE `casevents`
  ADD CONSTRAINT `casevents_ibfk_1` FOREIGN KEY (`clubID`) REFERENCES `clubsocieties` (`clubID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `clubsocieties`
--
ALTER TABLE `clubsocieties`
  ADD CONSTRAINT `clubsocieties_ibfk_1` FOREIGN KEY (`lectID`) REFERENCES `lecturer` (`lectID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`commentID`) ON DELETE CASCADE;

--
-- Constraints for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`commentID`) REFERENCES `comments` (`commentID`) ON DELETE CASCADE;

--
-- Constraints for table `events_attendance`
--
ALTER TABLE `events_attendance`
  ADD CONSTRAINT `events_attendance_ibfk_1` FOREIGN KEY (`partID`) REFERENCES `events_participant` (`partID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `events_participant`
--
ALTER TABLE `events_participant`
  ADD CONSTRAINT `events_participant_ibfk_1` FOREIGN KEY (`eventID`) REFERENCES `casevents` (`eventID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `events_participant_ibfk_2` FOREIGN KEY (`studID`) REFERENCES `student` (`studID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `membership`
--
ALTER TABLE `membership`
  ADD CONSTRAINT `membership_ibfk_1` FOREIGN KEY (`clubID`) REFERENCES `clubsocieties` (`clubID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `membership_ibfk_2` FOREIGN KEY (`studID`) REFERENCES `student` (`studID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

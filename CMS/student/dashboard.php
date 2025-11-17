<?php
session_start();
require_once "../config/connect.php";

// Check if student is logged in
if (!isset($_SESSION['stud_id'])) {
    header("Location: ../user_access.php");
    exit();
}

$student_id = $_SESSION['stud_id'];

// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
    header('Content-Type: application/json');
    
    try {
        $comment_id = $_POST['comment_id'];
        
        // Verify comment belongs to student
        $verify_query = "SELECT commentID FROM comments WHERE commentID = ? AND commenter_type = 'student' AND commenter_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->execute([$comment_id, $student_id]);
        
        if (!$verify_stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Comment not found or unauthorized']);
            exit();
        }
        
        // Soft delete comment
        $delete_query = "UPDATE comments SET is_deleted = 1 WHERE commentID = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->execute([$comment_id]);
        
        // Update comment count
        $update_count = "UPDATE comments c
                        LEFT JOIN casannouncement a ON c.post_type = 'announcement' AND c.post_id = a.annID
                        LEFT JOIN casevents e ON c.post_type = 'event' AND c.post_id = e.eventID
                        SET a.comment_count = a.comment_count - 1,
                            e.comment_count = e.comment_count - 1
                        WHERE c.commentID = ?";
        $update_stmt = $conn->prepare($update_count);
        $update_stmt->execute([$comment_id]);
        
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Get student info
$query = "SELECT * FROM student WHERE studID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get joined clubs count
$query = "SELECT COUNT(*) as count FROM membership WHERE studID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$clubs_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get upcoming events count (Public events + Private events from joined clubs)
$query = "SELECT COUNT(DISTINCT e.eventID) as count 
          FROM casevents e
          LEFT JOIN membership m ON e.clubID = m.clubID AND m.studID = ?
          WHERE e.evDate >= CURDATE()
          AND (e.evType = 'Public' OR (e.evType = 'Private' AND m.studID IS NOT NULL))";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$events_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get attended events count
$query = "SELECT COUNT(DISTINCT ea.attendanceID) as count
          FROM events_attendance ea
          INNER JOIN events_participant ep ON ea.partID = ep.partID
          WHERE ep.studID = ? AND ea.status = 'present'";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$attended_events = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get attended activities count
$query = "SELECT COUNT(*) as count FROM casactivity_attendance 
          WHERE studID = ? AND status = 'present'";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$attended_activities = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$total_attended = $attended_events + $attended_activities;

// Get new announcements count (last 7 days) - Public for all + Private for club members
$query = "SELECT COUNT(DISTINCT a.annID) as count
          FROM casannouncement a
          LEFT JOIN membership m ON a.clubID = m.clubID AND m.studID = ?
          WHERE a.annPosted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND (a.annType = 'Public' OR (a.annType = 'Private' AND m.studID IS NOT NULL))";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$announcements_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get joined clubs with details
$query = "SELECT c.*, l.lectName,
          (SELECT COUNT(*) FROM membership WHERE clubID = c.clubID) as member_count
          FROM membership m
          JOIN clubsocieties c ON m.clubID = c.clubID
          LEFT JOIN lecturer l ON c.lectID = l.lectID
          WHERE m.studID = ?
          ORDER BY m.joined_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming events for calendar (Public events for all + Private events for club members)
$query = "SELECT DISTINCT e.*, c.clubName, c.clubLogo,
          ep.partDate as joined_date,
          (SELECT COUNT(*) FROM events_participant WHERE eventID = e.eventID) as participants,
          CASE WHEN m.studID IS NOT NULL THEN 1 ELSE 0 END as is_member
          FROM casevents e
          INNER JOIN clubsocieties c ON e.clubID = c.clubID
          LEFT JOIN events_participant ep ON e.eventID = ep.eventID AND ep.studID = ?
          LEFT JOIN membership m ON e.clubID = m.clubID AND m.studID = ?
          WHERE e.evDate >= CURDATE() 
          AND (e.evType = 'Public' OR (e.evType = 'Private' AND m.studID IS NOT NULL))
          ORDER BY e.evDate ASC, e.evTime ASC";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id, $student_id]);
$upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get latest announcements (Public for all + Private for club members) - for sidebar
$query = "SELECT DISTINCT a.*, c.clubName, c.clubLogo,
          CASE WHEN m.studID IS NOT NULL THEN 1 ELSE 0 END as is_member
          FROM casannouncement a
          INNER JOIN clubsocieties c ON a.clubID = c.clubID
          LEFT JOIN membership m ON a.clubID = m.clubID AND m.studID = ?
          WHERE a.annType = 'Public' OR (a.annType = 'Private' AND m.studID IS NOT NULL)
          ORDER BY a.annPosted_at DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ALL announcements for calendar (Public for all + Private for club members)
$query = "SELECT DISTINCT a.*, c.clubName, c.clubLogo,
          CASE WHEN m.studID IS NOT NULL THEN 1 ELSE 0 END as is_member
          FROM casannouncement a
          INNER JOIN clubsocieties c ON a.clubID = c.clubID
          LEFT JOIN membership m ON a.clubID = m.clubID AND m.studID = ?
          WHERE a.annType = 'Public' OR (a.annType = 'Private' AND m.studID IS NOT NULL)
          ORDER BY a.annPosted_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$all_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activities (Only for club members) - for sidebar display
$query = "SELECT ca.*, c.clubName,
          1 as is_member
          FROM casactivity ca
          INNER JOIN membership m ON ca.clubID = m.clubID
          INNER JOIN clubsocieties c ON ca.clubID = c.clubID
          WHERE m.studID = ? AND ca.actDate >= CURDATE()
          ORDER BY ca.actDate ASC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ALL activities for calendar (including past activities)
$query_all = "SELECT ca.*, c.clubName,
          1 as is_member
          FROM casactivity ca
          INNER JOIN membership m ON ca.clubID = m.clubID
          INNER JOIN clubsocieties c ON ca.clubID = c.clubID
          WHERE m.studID = ?
          ORDER BY ca.actDate ASC";
$stmt_all = $conn->prepare($query_all);
$stmt_all->execute([$student_id]);
$all_activities_for_calendar = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// Get complete history for modal - Fixed collation issues
$query = "SELECT 'comment' as type, c.commentID as id, c.created_at as date, 
          CAST(c.content AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description, 
          CAST(CASE 
            WHEN c.post_type = 'announcement' THEN (SELECT anntitle FROM casannouncement WHERE annID = c.post_id)
            WHEN c.post_type = 'event' THEN (SELECT evTitle FROM casevents WHERE eventID = c.post_id)
          END AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as title,
          CAST(c.post_type AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as post_type,
          c.post_id
          FROM comments c
          WHERE c.commenter_type = 'student' AND c.commenter_id = ? AND c.is_deleted = 0
          UNION ALL
          SELECT 'event_joined' as type, ep.partID as id, ep.partDate as date, 
          CAST(CONCAT('Joined event: ', e.evTitle) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
          CAST(e.evTitle AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as title, 
          'event' as post_type,
          e.eventID as post_id
          FROM events_participant ep
          INNER JOIN casevents e ON ep.eventID = e.eventID
          WHERE ep.studID = ?
          UNION ALL
          SELECT 'event_attended' as type, ea.attendanceID as id, ea.attendDate as date,
          CAST(CONCAT('Attended event: ', e.evTitle) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
          CAST(e.evTitle AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as title, 
          'event' as post_type,
          e.eventID as post_id
          FROM events_attendance ea
          INNER JOIN events_participant ep ON ea.partID = ep.partID
          INNER JOIN casevents e ON ep.eventID = e.eventID
          WHERE ep.studID = ? AND ea.status = 'present'
          UNION ALL
          SELECT 'activity_attended' as type, aa.actAttendanceID as id, aa.actAttendDate as date,
          CAST(CONCAT('Attended activity: ', ca.actTitle) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as description,
          CAST(ca.actTitle AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci as title,
          'activity' as post_type,
          aa.actID as post_id
          FROM casactivity_attendance aa
          INNER JOIN casactivity ca ON aa.actID = ca.actID
          WHERE aa.studID = ? AND aa.status = 'present'
          ORDER BY date DESC
          LIMIT 100";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id, $student_id, $student_id, $student_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Club Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png">
    <style>
        .welcome-header {
            background: linear-gradient(135deg, #667eea 0%, #562686ff 100%);
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }
        
        /* Calendar Container Styling */
        .calendar-container {
            max-width: 100%;
            margin: 0 auto;
        }

        /* Enhanced FullCalendar Styling */
        .fc {
            font-family: inherit;
            font-size: 0.9rem;
        }

        .fc .fc-toolbar {
            padding: 1rem 0;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .fc .fc-toolbar-title {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #562686ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Button Group Spacing - ADD THIS */
        .fc .fc-toolbar-chunk {
            display: flex;
            gap: 0.5rem;
        }

        .fc .fc-button-group {
            gap: 0.5rem;
        }

        /* Button Styling */
        .fc-button {
            background: linear-gradient(135deg, #667eea 0%, #562686ff 100%) !important;
            border: none !important;
            padding: 0.5rem 1rem !important;
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            text-transform: capitalize !important;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2) !important;
            transition: all 0.3s ease !important;
            margin: 0 !important; /* ADD THIS LINE */
        }

        .fc-button:hover {
            background: linear-gradient(135deg, #667eea 0%, #562686ff 100%) !important;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3) !important;
            transform: translateY(-2px) !important;
        }

        .fc-button:active,
        .fc-button.fc-button-active {
            background: linear-gradient(135deg, #667eea 0%, #562686ff 100%) !important;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.2) inset !important;
        }

        .fc-button:disabled {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }

        /* Day Cell Styling */
        .fc-daygrid-day {
            transition: background-color 0.2s ease;
        }

        .fc-daygrid-day:hover {
            background-color: #f0fdf4;
        }

        .fc-day-today {
            background-color: #babee7ff !important;
            border: 2px solid #0631a8ff !important;
        }

        .fc-daygrid-day-number {
            font-weight: 600;
            color: #1f2937;
            padding: 0.25rem;
        }

        /* Event Styling */
        .fc-event {
            border: none !important;
            border-radius: 6px !important;
            padding: 4px 6px !important;
            margin: 2px 0 !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }

        .fc-event:hover {
            transform: scale(1.05) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            z-index: 100 !important;
        }

        .fc-event-title {
            font-weight: 600 !important;
            white-space: normal !important;
            overflow: visible !important;
        }

        .fc-event-event {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: white !important;
            border-left: 4px solid #047857 !important;
        }

        .fc-event-announcement {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            color: white !important;
            border-left: 4px solid #1d4ed8 !important;
        }

        .fc-event-activity {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%) !important;
            color: white !important;
            border-left: 4px solid #ea580c !important;
        }

        /* List View Styling */
        .fc-list-event:hover td {
            background-color: #f0fdf4 !important;
        }

        .fc-list-event-dot {
            border-width: 6px !important;
            border-radius: 50% !important;
        }

        /* Header Row */
        .fc-col-header-cell {
            background: linear-gradient(135deg, #667eea 0%, #562686ff 100%);
            color: white;
            font-weight: 700;
            padding: 1rem 0.5rem;
            border: none !important;
        }

        /* Legend Styling */
        .calendar-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }

        .legend-dot {
            width: 1rem;
            height: 1rem;
            border-radius: 0.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .legend-dot.announcement {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .legend-dot.event {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .legend-dot.activity {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .fc .fc-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .fc .fc-toolbar-title {
                font-size: 1.25rem;
                text-align: center;
            }

            .fc-button {
                padding: 0.4rem 0.8rem !important;
                font-size: 0.875rem !important;
            }

            .fc-event {
                font-size: 0.7rem !important;
                padding: 2px 4px !important;
            }

            .calendar-legend {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        /* Tooltip Styling */
        .fc-event-tooltip {
            position: absolute;
            background: white;
            border: 2px solid #10b981;
            border-radius: 0.75rem;
            padding: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            min-width: 250px;
            pointer-events: none;
        }

        /* Loading State */
        .calendar-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
        }

        /* Scrollbar Styling */
        .fc-scroller::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .fc-scroller::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .fc-scroller::-webkit-scrollbar-thumb {
            background: #10b981;
            border-radius: 4px;
        }

        .fc-scroller::-webkit-scrollbar-thumb:hover {
            background: #059669;
        }
        
        .club-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .club-card:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .announcement-card {
            transition: all 0.2s ease;
            border-left: 4px solid #10b981;
        }
        .announcement-card:hover {
            background: #f0fdf4;
            transform: translateX(4px);
        }
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #562686ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .event-card {
            transition: all 0.2s ease;
        }
        .event-card:hover {
            background: #f0fdf4;
        }
        .history-item {
            transition: all 0.2s ease;
        }
        .history-item:hover {
            background: #f9fafb;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-green-50 min-h-screen">
    <div class="drawer">
        <input id="my-drawer-3" type="checkbox" class="drawer-toggle" /> 
        <div class="drawer-content flex flex-col">
            <?php include "includes/navbar.php"; ?>

            <main class="pt-16">
                <!-- Welcome Header -->
                <div class="welcome-header text-white py-8 shadow-xl">
                    <div class="container mx-auto px-6">
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <h1 class="text-3xl font-bold mb-1"><?php echo htmlspecialchars(explode(' ', $student['studName'])[0]); ?>'s Dashboard</h1>
                                <p class="text-green-50"><?php echo htmlspecialchars($student['studProgramme']); ?> - Semester <?php echo $student['studSem']; ?></p>
                            </div>
                            <button onclick="history_modal.showModal()" class="btn bg-white text-green-600 border-none hover:bg-gray-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                                View History
                            </button>
                        </div>
                    </div>
                </div>

                <div class="container mx-auto px-6 py-8">
                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-t-4 border-blue-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-600 text-xs font-semibold uppercase">My Clubs</p>
                                    <h3 class="text-3xl font-bold text-gray-800 mt-1"><?php echo $clubs_count; ?></h3>
                                </div>
                                <div class="bg-gradient-to-br from-blue-400 to-blue-600 p-3 rounded-xl">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-t-4 border-blue-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-600 text-xs font-semibold uppercase">Upcoming Events</p>
                                    <h3 class="text-3xl font-bold text-gray-800 mt-1"><?php echo $events_count; ?></h3>
                                </div>
                                <div class="bg-gradient-to-br from-blue-400 to-blue-600 p-3 rounded-xl">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-t-4 border-blue-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-600 text-xs font-semibold uppercase">Total Attended</p>
                                    <h3 class="text-3xl font-bold text-gray-800 mt-1"><?php echo $total_attended; ?></h3>
                                </div>
                                <div class="bg-gradient-to-br from-blue-400 to-blue-600 p-3 rounded-xl">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar Section -->
                    <div class="mb-8">
                        <div class="bg-white rounded-xl shadow-2xl p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h2 class="text-3xl font-bold mb-2">📅 My Activity Calendar</h2>
                                    <p class="text-gray-600">View all your announcements, events, and activities</p>
                                </div>
                            </div>

                            <!-- Legend -->
                            <div class="calendar-legend">
                                <div class="legend-item">
                                    <div class="legend-dot announcement"></div>
                                    <span>📢 Announcements</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-dot event"></div>
                                    <span>🎉 Events</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-dot activity"></div>
                                    <span>📋 Activities</span>
                                </div>
                            </div>

                            <!-- Calendar Container -->
                            <div class="calendar-container relative">
                                <div id="calendar"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Latest Announcements -->
                        <div class="bg-white rounded-xl shadow-xl p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-bold gradient-text">📢 Latest Announcements</h2>
                                <a href="all_announcement.php" class="text-green-600 hover:text-green-700 text-sm font-semibold">View All →</a>
                            </div>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php if (!empty($announcements)): ?>
                                    <?php foreach ($announcements as $ann): ?>
                                    <a href="announcement_post.php?id=<?php echo $ann['annID']; ?>" class="announcement-card bg-white rounded-lg p-4 border border-gray-100 shadow-sm block">
                                        <div class="flex items-start gap-3">
                                            <img src="../<?php echo htmlspecialchars($ann['clubLogo']); ?>" 
                                                 class="w-10 h-10 rounded-full"
                                                 onerror="this.src='../assets/images/default-club.png'">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="font-bold text-xs text-gray-900"><?php echo htmlspecialchars($ann['clubName']); ?></span>
                                                    <span class="text-xs text-gray-400">•</span>
                                                    <span class="text-xs text-gray-500"><?php echo date('M d', strtotime($ann['annPosted_at'])); ?></span>
                                                    <?php if (isset($ann['annType'])): ?>
                                                        <span class="badge <?php echo $ann['annType'] === 'Public' ? 'badge-success' : 'badge-warning'; ?> badge-xs">
                                                            <?php echo $ann['annType'] === 'Public' ? '🌐' : '🔒'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <h3 class="font-bold text-sm text-gray-900 mb-1 line-clamp-1"><?php echo htmlspecialchars($ann['anntitle']); ?></h3>
                                                <p class="text-xs text-gray-600 line-clamp-2"><?php echo htmlspecialchars(strip_tags($ann['content'])); ?></p>
                                            </div>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-8">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                        </svg>
                                        <p class="text-gray-500 text-sm">No announcements yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Upcoming Events -->
                        <div class="bg-white rounded-xl shadow-xl p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-bold gradient-text">🎉 Upcoming Events</h2>
                                <a href="all_event.php" class="text-green-600 hover:text-green-700 text-sm font-semibold">View All →</a>
                            </div>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php if (!empty($upcoming_events)): ?>
                                    <?php foreach (array_slice($upcoming_events, 0, 5) as $event): ?>
                                    <a href="event_post.php?id=<?php echo $event['eventID']; ?>" class="event-card border border-gray-200 rounded-lg p-3 block hover:shadow-md transition-all">
                                        <div class="flex gap-3">
                                            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-2 text-center flex-shrink-0" style="width: 45px; height: 45px;">
                                                <div class="text-xs font-bold text-white leading-tight"><?php echo strtoupper(date('M', strtotime($event['evDate']))); ?></div>
                                                <div class="text-base font-bold text-white leading-tight"><?php echo date('d', strtotime($event['evDate'])); ?></div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3 class="font-bold text-sm text-gray-900 line-clamp-1 mb-1"><?php echo htmlspecialchars($event['evTitle']); ?></h3>
                                                <p class="text-xs text-gray-600 mb-1"><?php echo htmlspecialchars($event['clubName']); ?></p>
                                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                                    <?php if (isset($event['evType'])): ?>
                                                        <span class="badge <?php echo $event['evType'] === 'Public' ? 'badge-success' : 'badge-warning'; ?> badge-xs">
                                                            <?php echo $event['evType'] === 'Public' ? '🌐' : '🔒'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span><?php echo date('g:i A', strtotime($event['evTime'])); ?></span>
                                                    <?php if ($event['evLocation']): ?>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        <span class="truncate"><?php echo htmlspecialchars($event['evLocation']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-8">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <p class="text-gray-500 text-sm">No upcoming events</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Upcoming Activities -->
                        <div class="bg-white rounded-xl shadow-xl p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-bold gradient-text">📋 Upcoming Activities</h2>
                            </div>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php if (!empty($activities)): ?>
                                    <?php foreach (array_slice($activities, 0, 5) as $activity): ?>
                                    <a href="activity_post.php?id=<?php echo $activity['actID']; ?>" class="event-card border border-gray-200 rounded-lg p-3 block hover:shadow-md transition-all">
                                        <div class="flex gap-3">
                                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-2 text-center flex-shrink-0" style="width: 45px; height: 45px;">
                                                <div class="text-xs font-bold text-white leading-tight"><?php echo strtoupper(date('M', strtotime($activity['actDate']))); ?></div>
                                                <div class="text-base font-bold text-white leading-tight"><?php echo date('d', strtotime($activity['actDate'])); ?></div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3 class="font-bold text-sm text-gray-900 line-clamp-1 mb-1"><?php echo htmlspecialchars($activity['actTitle']); ?></h3>
                                                <p class="text-xs text-gray-600 mb-1"><?php echo htmlspecialchars($activity['clubName']); ?></p>
                                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                                    <span class="badge badge-info badge-xs"><?php echo htmlspecialchars($activity['actType']); ?></span>
                                                    <?php if ($activity['actTime']): ?>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span><?php echo date('g:i A', strtotime($activity['actTime'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-8">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <p class="text-gray-500 text-sm">No upcoming activities</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- My Clubs Section -->
                    <div class="bg-white rounded-xl shadow-xl p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-2xl font-bold gradient-text">My Clubs</h2>
                            <a href="my_clubs.php" class="text-green-600 hover:text-green-700 text-sm font-semibold">View All →</a>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            <?php if (!empty($clubs)): ?>
                                <?php foreach (array_slice($clubs, 0, 6) as $club): ?>
                                <a href="club_profile.php?id=<?php echo $club['clubID']; ?>" class="club-card bg-gradient-to-br from-gray-50 to-green-50 rounded-xl p-4 text-center border-2 border-gray-100">
                                    <div class="avatar mb-2">
                                        <div class="w-16 h-16 rounded-full ring-2 ring-green-500">
                                            <img src="../<?php echo htmlspecialchars($club['clubLogo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($club['clubName']); ?>"
                                                 onerror="this.src='../assets/images/default-club.png'">
                                        </div>
                                    </div>
                                    <h3 class="font-bold text-sm text-gray-900 line-clamp-2"><?php echo htmlspecialchars($club['clubName']); ?></h3>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo $club['member_count']; ?> members</p>
                                </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-full text-center py-8">
                                    <p class="text-gray-500 mb-4">You haven't joined any clubs yet</p>
                                    <a href="available_clubs.php" class="btn btn-sm bg-green-600 text-white border-none">Discover Clubs</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>

            <?php include "includes/footer.php"; ?>
        </div>

        <?php include "includes/mobile_drawer.php"; ?>
    </div>

    <!-- History Modal -->
    <dialog id="history_modal" class="modal">
        <div class="modal-box max-w-4xl max-h-[85vh]">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-bold text-2xl mb-2 gradient-text">Activity History</h3>
            <p class="text-gray-600 mb-4">Your complete activity timeline</p>
            
            <!-- Tabs -->
            <div role="tablist" class="tabs tabs-boxed bg-green-50 mb-4">
                <a role="tab" class="tab tab-active" onclick="showHistoryTab(event, 'all')">All</a>
                <a role="tab" class="tab" onclick="showHistoryTab(event, 'comments')">Comments</a>
                <a role="tab" class="tab" onclick="showHistoryTab(event, 'events')">Events Joined</a>
                <a role="tab" class="tab" onclick="showHistoryTab(event, 'event_attended')">Events Attended</a>
                <a role="tab" class="tab" onclick="showHistoryTab(event, 'activity_attended')">Activities Attended</a>
            </div>

            <div class="overflow-y-auto max-h-96" id="history-content">
                <?php if (!empty($history)): ?>
                    <?php foreach ($history as $item): ?>
                    <div class="history-item border-l-4 <?php 
                        echo $item['type'] === 'comment' ? 'border-blue-500 bg-blue-50' : 
                            ($item['type'] === 'event_joined' ? 'border-green-500 bg-green-50' : 
                            ($item['type'] === 'event_attended' ? 'border-purple-500 bg-purple-50' : 'border-orange-500 bg-orange-50')); 
                    ?> rounded-r-lg p-4 mb-3" data-type="<?php echo $item['type']; ?>">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="badge <?php 
                                        echo $item['type'] === 'comment' ? 'badge-info' : 
                                            ($item['type'] === 'event_joined' ? 'badge-success' : 
                                            ($item['type'] === 'event_attended' ? 'badge-secondary' : 'badge-warning')); 
                                    ?> badge-sm">
                                        <?php 
                                            if ($item['type'] === 'comment') {
                                                echo '💬 Comment';
                                            } elseif ($item['type'] === 'event_joined') {
                                                echo '✅ Joined Event';
                                            } elseif ($item['type'] === 'event_attended') {
                                                echo '🎉 Event Attended';
                                            } else {
                                                echo '📋 Activity Attended';
                                            }
                                        ?>
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <?php echo date('M d, Y • g:i A', strtotime($item['date'])); ?>
                                    </span>
                                </div>
                                <?php if ($item['type'] === 'comment'): ?>
                                    <a href="<?php echo $item['post_type'] === 'announcement' ? 'announcement_post.php?id=' : 'event_post.php?id='; ?><?php echo $item['post_id']; ?>" class="font-bold text-gray-900 text-sm mb-1 hover:text-green-600 block"><?php echo htmlspecialchars($item['title']); ?></a>
                                    <p class="text-xs text-gray-700 bg-white p-2 rounded"><?php echo htmlspecialchars($item['description']); ?></p>
                                <?php elseif ($item['type'] === 'event_joined' || $item['type'] === 'event_attended'): ?>
                                    <a href="event_post.php?id=<?php echo $item['post_id']; ?>" class="font-bold text-gray-900 text-sm hover:text-green-600 block"><?php echo htmlspecialchars($item['title']); ?></a>
                                <?php else: ?>
                                    <a href="activity_post.php?id=<?php echo $item['post_id']; ?>" class="font-bold text-gray-900 text-sm hover:text-green-600 block"><?php echo htmlspecialchars($item['title']); ?></a>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['type'] === 'comment'): ?>
                                <button onclick="deleteComment(<?php echo $item['id']; ?>)" 
                                        class="btn btn-ghost btn-xs text-red-600 hover:bg-red-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-gray-500 font-semibold">No activity history yet</p>
                        <p class="text-gray-400 text-sm mt-1">Start participating to see your activity here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <script>
        // Calendar data from PHP database
        const calendarData = [
            <?php 
            $calendarItems = [];
            
            // Announcements
            if (!empty($all_announcements)) {
                foreach ($all_announcements as $ann) {
                    $calendarItems[] = json_encode([
                        'title' => '📢 ' . $ann['anntitle'],
                        'start' => $ann['annPosted_at'],
                        'type' => 'announcement',
                        'id' => $ann['annID'],
                        'clubName' => $ann['clubName'],
                        'description' => substr(strip_tags($ann['content']), 0, 150) . '...',
                        'url' => 'announcement_post.php?id=' . $ann['annID'],
                        'isPublic' => isset($ann['annType']) && $ann['annType'] === 'Public',
                        'isMember' => isset($ann['is_member']) && $ann['is_member'] == 1
                    ]);
                }
            }
            
            // Events
            if (!empty($upcoming_events)) {
                foreach ($upcoming_events as $event) {
                    $eventStart = $event['evDate'] . 'T' . $event['evTime'];
                    $eventData = [
                        'title' => '🎉 ' . $event['evTitle'],
                        'start' => $eventStart,
                        'type' => 'event',
                        'id' => $event['eventID'],
                        'clubName' => $event['clubName'],
                        'location' => $event['evLocation'] ?? '',
                        'participants' => intval($event['participants']),
                        'description' => substr(strip_tags($event['evDesc'] ?? ''), 0, 150),
                        'url' => 'event_post.php?id=' . $event['eventID'],
                        'isPublic' => isset($event['evType']) && $event['evType'] === 'Public',
                        'isMember' => isset($event['is_member']) && $event['is_member'] == 1
                    ];
                    
                    if (!empty($event['evEndTime'])) {
                        $eventData['end'] = $event['evDate'] . 'T' . $event['evEndTime'];
                    }
                    
                    $calendarItems[] = json_encode($eventData);
                }
            }
            
            // Activities - FIXED: Get ALL activities, not just limited to 5
            // Query ALL activities from joined clubs
            $all_activities_query = "SELECT ca.*, c.clubName,
                    1 as is_member
                    FROM casactivity ca
                    INNER JOIN membership m ON ca.clubID = m.clubID
                    INNER JOIN clubsocieties c ON ca.clubID = c.clubID
                    WHERE m.studID = ?
                    ORDER BY ca.actDate ASC";
            $stmt_all_act = $conn->prepare($all_activities_query);
            $stmt_all_act->execute([$student_id]);
            $all_activities = $stmt_all_act->fetchAll(PDO::FETCH_ASSOC);
            
            // Activities - Use ALL activities for calendar
            if (!empty($all_activities_for_calendar)) {
                foreach ($all_activities_for_calendar as $activity) {
                    $activityTime = !empty($activity['actTime']) ? $activity['actTime'] : '00:00:00';
                    $activityStart = $activity['actDate'] . 'T' . $activityTime;
                    
                    $activityData = [
                        'title' => '📋 ' . $activity['actTitle'],
                        'start' => $activityStart,
                        'type' => 'activity',
                        'id' => $activity['actID'],
                        'clubName' => $activity['clubName'],
                        'actType' => $activity['actType'] ?? '',
                        'description' => substr(strip_tags($activity['actDesc'] ?? ''), 0, 150),
                        'url' => 'activity_post.php?id=' . $activity['actID'],
                        'isPublic' => false,
                        'isMember' => true
                    ];
                    
                    if (!empty($activity['actEndTime'])) {
                        $activityData['end'] = $activity['actDate'] . 'T' . $activity['actEndTime'];
                    }
                    
                    $calendarItems[] = json_encode($activityData);
                }
            }
            
            // Output all items with proper comma separation
            echo implode(",\n", $calendarItems);
            ?>
        ];

        // Initialize calendar
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            
            if (!calendarEl) {
                console.error('Calendar element not found');
                return;
            }

            // Process events and add appropriate classes
            const events = calendarData.map(event => ({
                ...event,
                className: `fc-event-${event.type}`,
                extendedProps: {
                    type: event.type,
                    clubName: event.clubName,
                    description: event.description,
                    isPublic: event.isPublic || false,
                    isMember: event.isMember || false,
                    ...(event.location && { location: event.location }),
                    ...(event.participants && { participants: event.participants }),
                    ...(event.actType && { actType: event.actType })
                }
            }));

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                buttonText: {
                    today: 'Today',
                    month: 'Month',
                    week: 'Week',
                    list: 'List'
                },
                height: 'auto',
                events: events,
                eventDisplay: 'block',
                displayEventTime: true,
                displayEventEnd: true,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    meridiem: 'short'
                },
                
                // Event click handler
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    
                    if (info.event.url) {
                        // Navigate to the appropriate page
                        window.location.href = info.event.url;
                    }
                },
                
                // Event mouse enter (hover)
                eventMouseEnter: function(info) {
                    const event = info.event;
                    const props = event.extendedProps;
                    
                    // Create tooltip
                    const tooltip = document.createElement('div');
                    tooltip.className = 'fc-event-tooltip';
                    tooltip.id = 'event-tooltip-' + event.id;
                    
                    let content = `
                        <div class="font-bold text-lg mb-2 text-gray-900">
                            ${event.title}
                        </div>
                        <div class="space-y-1 text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">Club:</span>
                                <span>${props.clubName}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">Type:</span>
                                <span class="badge ${props.isPublic ? 'badge-success' : 'badge-warning'} badge-sm">
                                    ${props.isPublic ? '🌐 Public' : '🔒 Private'}
                                </span>
                            </div>
                    `;
                    
                    if (event.start) {
                        content += `
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">Date:</span>
                                <span>${event.start.toLocaleDateString('en-US', { 
                                    weekday: 'short', 
                                    year: 'numeric', 
                                    month: 'short', 
                                    day: 'numeric' 
                                })}</span>
                            </div>
                        `;
                        
                        if (event.start.toLocaleTimeString() !== '12:00:00 AM') {
                            content += `
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">Time:</span>
                                    <span>${event.start.toLocaleTimeString('en-US', {
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}</span>
                                </div>
                            `;
                        }
                    }
                    
                    if (props.location) {
                        content += `
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">Location:</span>
                                <span>${props.location}</span>
                            </div>
                        `;
                    }
                    
                    if (props.participants) {
                        content += `
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">Participants:</span>
                                <span>${props.participants} registered</span>
                            </div>
                        `;
                    }
                    
                    if (props.actType) {
                        content += `
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">Type:</span>
                                <span>${props.actType}</span>
                            </div>
                        `;
                    }
                    
                    if (props.description) {
                        content += `
                            <div class="mt-2 pt-2 border-t border-gray-200">
                                <p class="text-gray-700">${props.description}</p>
                            </div>
                        `;
                    }
                    
                    content += `
                        </div>
                        <div class="mt-3 pt-2 border-t border-gray-200">
                            <p class="text-xs text-green-600 font-semibold">Click to view details →</p>
                        </div>
                    `;
                    
                    tooltip.innerHTML = content;
                    document.body.appendChild(tooltip);
                    
                    // Position tooltip
                    const rect = info.el.getBoundingClientRect();
                    tooltip.style.position = 'fixed';
                    tooltip.style.left = (rect.left + rect.width / 2) + 'px';
                    tooltip.style.top = (rect.top - 10) + 'px';
                    tooltip.style.transform = 'translate(-50%, -100%)';
                    
                    // Adjust if tooltip goes off screen
                    const tooltipRect = tooltip.getBoundingClientRect();
                    if (tooltipRect.left < 10) {
                        tooltip.style.left = '10px';
                        tooltip.style.transform = 'translateY(-100%)';
                    }
                    if (tooltipRect.right > window.innerWidth - 10) {
                        tooltip.style.left = (window.innerWidth - 10) + 'px';
                        tooltip.style.transform = 'translate(-100%, -100%)';
                    }
                },
                
                // Event mouse leave
                eventMouseLeave: function(info) {
                    const tooltip = document.getElementById('event-tooltip-' + info.event.id);
                    if (tooltip) {
                        tooltip.remove();
                    }
                },
                
                // Day cell click
                dateClick: function(info) {
                    calendar.changeView('timeGridDay', info.dateStr);
                },
                
                // Loading state
                loading: function(isLoading) {
                    if (isLoading) {
                        const loader = document.createElement('div');
                        loader.className = 'calendar-loading';
                        loader.innerHTML = '<span class="loading loading-spinner loading-lg text-green-600"></span>';
                        loader.id = 'calendar-loader';
                        calendarEl.appendChild(loader);
                    } else {
                        const loader = document.getElementById('calendar-loader');
                        if (loader) loader.remove();
                    }
                }
            });

            calendar.render();

            // Add click to pointer cursor
            setTimeout(() => {
                document.querySelectorAll('.fc-event').forEach(el => {
                    el.style.cursor = 'pointer';
                });
            }, 100);
        });

        // History tab filtering
        function showHistoryTab(evt, type) {
            const items = document.querySelectorAll('.history-item');
            const tabs = document.querySelectorAll('.tabs .tab');
            
            tabs.forEach(tab => tab.classList.remove('tab-active'));
            evt.target.classList.add('tab-active');
            
            let visibleCount = 0;
            
            items.forEach(item => {
                if (type === 'all') {
                    item.style.display = 'block';
                    visibleCount++;
                } else if (type === 'comments' && item.dataset.type === 'comment') {
                    item.style.display = 'block';
                    visibleCount++;
                } else if (type === 'events' && item.dataset.type === 'event_joined') {
                    item.style.display = 'block';
                    visibleCount++;
                } else if (type === 'event_attended' && item.dataset.type === 'event_attended') {
                    item.style.display = 'block';
                    visibleCount++;
                } else if (type === 'activity_attended' && item.dataset.type === 'activity_attended') {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            const historyContent = document.getElementById('history-content');
            const existingEmpty = historyContent.querySelector('.empty-state');
            
            if (visibleCount === 0 && !existingEmpty) {
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state text-center py-12';
                
                let typeLabel = type === 'all' ? 'activity' : 
                               type === 'comments' ? 'comment' : 
                               type === 'events' ? 'event joined' :
                               type === 'event_attended' ? 'event attendance' : 
                               'activity attendance';
                
                emptyState.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-gray-500 font-semibold">No ${typeLabel} history yet</p>
                    <p class="text-gray-400 text-sm mt-1">Start participating to see your activity here</p>
                `;
                historyContent.appendChild(emptyState);
            } else if (visibleCount > 0 && existingEmpty) {
                existingEmpty.remove();
            }
        }

        // Delete comment function
        function deleteComment(commentId) {
            if (!confirm('Are you sure you want to delete this comment?')) {
                return;
            }

            const deleteBtn = window.event.target.closest('button');
            const originalContent = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<span class="loading loading-spinner loading-xs"></span>';

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete_comment&comment_id=' + commentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = deleteBtn.closest('.history-item');
                    item.style.transition = 'all 0.3s ease';
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        item.remove();
                        
                        const remainingItems = document.querySelectorAll('.history-item');
                        if (remainingItems.length === 0) {
                            const historyContent = document.getElementById('history-content');
                            historyContent.innerHTML = `
                                <div class="text-center py-12">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-gray-500 font-semibold">No activity history yet</p>
                                    <p class="text-gray-400 text-sm mt-1">Start participating to see your activity here</p>
                                </div>
                            `;
                        }
                    }, 300);
                    
                    showToast('Comment deleted successfully', 'success');
                } else {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalContent;
                    showToast('Failed to delete comment: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalContent;
                showToast('An error occurred while deleting the comment', 'error');
            });
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'error'} shadow-lg fixed bottom-4 right-4 w-auto max-w-sm z-50`;
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            toast.innerHTML = `
                <div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        ${type === 'success' ? 
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />' :
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />'
                        }
                    </svg>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '1';
            }, 10);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
<?php
session_start();
require_once "../config/connect.php";

if (!isset($_SESSION['lect_id'])) {
    header('Location: ../user_access.php');
    exit;
}

$lectID = $_SESSION['lect_id'];

// Get selected club filter (default is 'all')
$selected_club = isset($_GET['club_filter']) ? $_GET['club_filter'] : 'all';

// Get list of clubs for dropdown
$clubs_list_query = "SELECT clubID, clubName FROM clubsocieties WHERE lectID = ? ORDER BY clubName";
$stmt = $conn->prepare($clubs_list_query);
$stmt->execute([$lectID]);
$clubs_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build filter parameters
$filter_params = [$lectID];
$club_filter_condition = "";

if ($selected_club !== 'all') {
    $club_filter_condition = "AND c.clubID = ?";
    $filter_params[] = intval($selected_club);
}

// Initialize statistics array
$stats = [];

// --- Total Clubs (Only clubs this lecturer is in charge of) ---
if ($selected_club !== 'all') {
    $clubs_query = "SELECT COUNT(*) AS total FROM clubsocieties WHERE lectID = ? AND clubID = ?";
    $stmt = $conn->prepare($clubs_query);
    $stmt->execute([$lectID, $selected_club]);
} else {
    $clubs_query = "SELECT COUNT(*) AS total FROM clubsocieties WHERE lectID = ?";
    $stmt = $conn->prepare($clubs_query);
    $stmt->execute([$lectID]);
}
$stats['clubs'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// --- Total Members in Lecturer's Clubs ---
$members_query = "
    SELECT COUNT(DISTINCT cm.studID) AS total 
    FROM membership cm
    INNER JOIN clubsocieties c ON cm.clubID = c.clubID
    WHERE c.lectID = ? $club_filter_condition
";
$stmt = $conn->prepare($members_query);
$stmt->execute($filter_params);
$stats['total_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// --- Total Events in Lecturer's Clubs ---
$events_query = "
    SELECT COUNT(*) AS total 
    FROM casevents e
    INNER JOIN clubsocieties c ON e.clubID = c.clubID
    WHERE c.lectID = ? $club_filter_condition
";
$stmt = $conn->prepare($events_query);
$stmt->execute($filter_params);
$stats['total_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// --- Total Activities in Lecturer's Clubs ---
$activities_query = "
    SELECT COUNT(*) AS total 
    FROM casactivity a
    INNER JOIN clubsocieties c ON a.clubID = c.clubID
    WHERE c.lectID = ? $club_filter_condition
";
$stmt = $conn->prepare($activities_query);
$stmt->execute($filter_params);
$stats['total_activities'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// --- Total Announcements in Lecturer's Clubs ---
$announcements_query = "
    SELECT COUNT(*) AS total 
    FROM casannouncement a
    INNER JOIN clubsocieties c ON a.clubID = c.clubID
    WHERE c.lectID = ? $club_filter_condition
";
$stmt = $conn->prepare($announcements_query);
$stmt->execute($filter_params);
$stats['total_announcements'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// --- Event Attendance Statistics (Private = /participants, Public = just count) ---
$event_attendance_query = "
    SELECT 
        COUNT(DISTINCT ep.partID) as total_participants,
        COUNT(DISTINCT CASE WHEN ea.status = 'Present' THEN ea.attendanceID END) as present_count,
        COUNT(DISTINCT CASE WHEN ea.status = 'Absent' THEN ea.attendanceID END) as absent_count,
        SUM(CASE WHEN e.evType = 'Private' THEN 1 ELSE 0 END) as private_event_count,
        SUM(CASE WHEN e.evType = 'Public' THEN 1 ELSE 0 END) as public_event_count
    FROM casevents e
    INNER JOIN clubsocieties c ON e.clubID = c.clubID
    LEFT JOIN events_participant ep ON e.eventID = ep.eventID
    LEFT JOIN events_attendance ea ON ep.partID = ea.partID
    WHERE c.lectID = ? $club_filter_condition
";
$stmt = $conn->prepare($event_attendance_query);
$stmt->execute($filter_params);
$event_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Activity Attendance Statistics (with Club Capacity) ---
$activity_attendance_query = "
    SELECT 
        COUNT(DISTINCT aa.actAttendanceID) as total_records,
        COUNT(DISTINCT CASE WHEN aa.status = 'Present' THEN aa.actAttendanceID END) as present_count,
        COUNT(DISTINCT CASE WHEN aa.status = 'Absent' THEN aa.actAttendanceID END) as absent_count,
        COALESCE(SUM(DISTINCT c.clubCapacity), 0) as total_capacity
    FROM casactivity a
    INNER JOIN clubsocieties c ON a.clubID = c.clubID
    LEFT JOIN casactivity_attendance aa ON a.actID = aa.actID
    WHERE c.lectID = ? $club_filter_condition
";
$stmt = $conn->prepare($activity_attendance_query);
$stmt->execute($filter_params);
$activity_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate activity attendance display
$activity_attendance_display = $activity_attendance['present_count'] . ' / ' . $activity_attendance['total_capacity'];
$activity_attendance_rate = $activity_attendance['total_capacity'] > 0 
    ? round(($activity_attendance['present_count'] / $activity_attendance['total_capacity']) * 100, 1) 
    : 0;

// --- Comments Statistics for Lecturer's Clubs ---
$comment_filter_params = $selected_club !== 'all' ? [$lectID, $selected_club, $lectID, $selected_club] : [$lectID, $lectID];
$comment_stats_query = "
    SELECT 
        COUNT(*) as total_comments,
        SUM(CASE WHEN c.is_deleted = 0 THEN 1 ELSE 0 END) as active_comments,
        SUM(CASE WHEN c.is_deleted = 1 THEN 1 ELSE 0 END) as deleted_comments,
        SUM(CASE WHEN c.commenter_type = 'student' THEN 1 ELSE 0 END) as student_comments,
        SUM(CASE WHEN c.commenter_type = 'lecturer' THEN 1 ELSE 0 END) as lecturer_comments
    FROM comments c
    LEFT JOIN casannouncement a ON c.post_type = 'announcement' AND c.post_id = a.annID
    LEFT JOIN casevents e ON c.post_type = 'event' AND c.post_id = e.eventID
    LEFT JOIN clubsocieties cs1 ON a.clubID = cs1.clubID
    LEFT JOIN clubsocieties cs2 ON e.clubID = cs2.clubID
    WHERE ((c.post_type = 'announcement' AND cs1.lectID = ? " . ($selected_club !== 'all' ? 'AND cs1.clubID = ?' : '') . ") 
           OR (c.post_type = 'event' AND cs2.lectID = ? " . ($selected_club !== 'all' ? 'AND cs2.clubID = ?' : '') . "))
";
$stmt = $conn->prepare($comment_stats_query);
$stmt->execute($comment_filter_params);
$comment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Upcoming Events (Next 5) ---
$upcoming_events_query = "
    SELECT e.*, c.clubName,
           COUNT(DISTINCT ep.partID) as registered_count,
           COUNT(DISTINCT CASE WHEN ea.status = 'Present' THEN ea.attendanceID END) as attended_count
    FROM casevents e
    INNER JOIN clubsocieties c ON e.clubID = c.clubID
    LEFT JOIN events_participant ep ON e.eventID = ep.eventID
    LEFT JOIN events_attendance ea ON ep.partID = ea.partID
    WHERE c.lectID = ? $club_filter_condition AND e.evDate >= CURDATE()
    GROUP BY e.eventID
    ORDER BY e.evDate, e.evTime
    LIMIT 5
";
$stmt = $conn->prepare($upcoming_events_query);
$stmt->execute($filter_params);
$upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Recent Activities (Last 5) ---
$recent_activities_query = "
    SELECT a.*, c.clubName, c.clubCapacity,
           COUNT(DISTINCT aa.actAttendanceID) as attendance_count,
           COUNT(DISTINCT CASE WHEN aa.status = 'Present' THEN aa.actAttendanceID END) as present_count
    FROM casactivity a
    INNER JOIN clubsocieties c ON a.clubID = c.clubID
    LEFT JOIN casactivity_attendance aa ON a.actID = aa.actID
    WHERE c.lectID = ? $club_filter_condition
    GROUP BY a.actID
    ORDER BY a.actPosted_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($recent_activities_query);
$stmt->execute($filter_params);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Activity Types Distribution ---
$activity_types_query = "
    SELECT a.actType, COUNT(*) as count
    FROM casactivity a
    INNER JOIN clubsocieties c ON a.clubID = c.clubID
    WHERE c.lectID = ? $club_filter_condition
    GROUP BY a.actType
";
$stmt = $conn->prepare($activity_types_query);
$stmt->execute($filter_params);
$activity_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Event Types Distribution (Public vs Private) ---
$event_types_query = "
    SELECT e.evType, COUNT(*) as count
    FROM casevents e
    INNER JOIN clubsocieties c ON e.clubID = c.clubID
    WHERE c.lectID = ? $club_filter_condition
    GROUP BY e.evType
";
$stmt = $conn->prepare($event_types_query);
$stmt->execute($filter_params);
$event_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Monthly Activity Timeline (Last 6 months) ---
$timeline_filter_params = $selected_club !== 'all' 
    ? [$lectID, $selected_club, $lectID, $selected_club, $lectID, $selected_club]
    : [$lectID, $lectID, $lectID];

$monthly_timeline_query = "
    SELECT 
        DATE_FORMAT(date, '%b %Y') as month,
        DATE_FORMAT(date, '%Y-%m') as sort_month,
        SUM(events) as events,
        SUM(activities) as activities,
        SUM(announcements) as announcements
    FROM (
        SELECT DATE(evPosted_at) as date, 1 as events, 0 as activities, 0 as announcements
        FROM casevents e
        INNER JOIN clubsocieties c ON e.clubID = c.clubID
        WHERE c.lectID = ? " . ($selected_club !== 'all' ? 'AND c.clubID = ?' : '') . " AND evPosted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        
        UNION ALL
        
        SELECT DATE(actPosted_at) as date, 0 as events, 1 as activities, 0 as announcements
        FROM casactivity a
        INNER JOIN clubsocieties c ON a.clubID = c.clubID
        WHERE c.lectID = ? " . ($selected_club !== 'all' ? 'AND c.clubID = ?' : '') . " AND actPosted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        
        UNION ALL
        
        SELECT DATE(annPosted_at) as date, 0 as events, 0 as activities, 1 as announcements
        FROM casannouncement ann
        INNER JOIN clubsocieties c ON ann.clubID = c.clubID
        WHERE c.lectID = ? " . ($selected_club !== 'all' ? 'AND c.clubID = ?' : '') . " AND annPosted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ) combined
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY sort_month ASC
    LIMIT 6
";
$stmt = $conn->prepare($monthly_timeline_query);
$stmt->execute($timeline_filter_params);
$monthly_timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Recent Comments (Last 5) ---
$recent_comments_query = "
    SELECT 
        c.*,
        CASE 
            WHEN c.commenter_type = 'student' THEN s.studName
            WHEN c.commenter_type = 'lecturer' THEN l.lectName
        END as commenter_name,
        CASE 
            WHEN c.post_type = 'announcement' THEN a.anntitle
            WHEN c.post_type = 'event' THEN e.evTitle
        END as post_title,
        CASE 
            WHEN c.post_type = 'announcement' THEN club1.clubName
            WHEN c.post_type = 'event' THEN club2.clubName
        END as club_name,
        (SELECT COUNT(*) FROM comment_likes WHERE commentID = c.commentID) as like_count
    FROM comments c
    LEFT JOIN student s ON c.commenter_type = 'student' AND c.commenter_id = s.studID
    LEFT JOIN lecturer l ON c.commenter_type = 'lecturer' AND c.commenter_id = l.lectID
    LEFT JOIN casannouncement a ON c.post_type = 'announcement' AND c.post_id = a.annID
    LEFT JOIN casevents e ON c.post_type = 'event' AND c.post_id = e.eventID
    LEFT JOIN clubsocieties club1 ON a.clubID = club1.clubID
    LEFT JOIN clubsocieties club2 ON e.clubID = club2.clubID
    WHERE ((c.post_type = 'announcement' AND club1.lectID = ? " . ($selected_club !== 'all' ? 'AND club1.clubID = ?' : '') . ") 
           OR (c.post_type = 'event' AND club2.lectID = ? " . ($selected_club !== 'all' ? 'AND club2.clubID = ?' : '') . "))
    ORDER BY c.created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($recent_comments_query);
$stmt->execute($comment_filter_params);
$recent_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Club Performance (Members + Events + Activities) ---
$club_performance_query = "
    SELECT 
        c.clubID,
        c.clubName,
        c.clubCapacity,
        COUNT(DISTINCT cm.cmID) as member_count,
        COUNT(DISTINCT e.eventID) as event_count,
        COUNT(DISTINCT a.actID) as activity_count,
        COUNT(DISTINCT ann.annID) as announcement_count
    FROM clubsocieties c
    LEFT JOIN membership cm ON c.clubID = cm.clubID
    LEFT JOIN casevents e ON c.clubID = e.clubID
    LEFT JOIN casactivity a ON c.clubID = a.clubID
    LEFT JOIN casannouncement ann ON c.clubID = ann.clubID
    WHERE c.lectID = ? $club_filter_condition
    GROUP BY c.clubID, c.clubName, c.clubCapacity
    ORDER BY member_count DESC
";
$stmt = $conn->prepare($club_performance_query);
$stmt->execute($filter_params);
$club_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Recently Registered Students (Latest 10) ---
$recent_memberships_query = "
    SELECT cm.*, c.clubName, s.studName, s.studNoID
    FROM membership cm
    INNER JOIN clubsocieties c ON cm.clubID = c.clubID
    LEFT JOIN student s ON cm.studID = s.studID
    WHERE c.lectID = ? $club_filter_condition
    ORDER BY cm.joined_at DESC
    LIMIT 10
";
$stmt = $conn->prepare($recent_memberships_query);
$stmt->execute($filter_params);
$recent_memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
</head>
<body>
<div class="drawer lg:drawer-open">
    <input id="my-drawer-2" type="checkbox" class="drawer-toggle" />
    <div class="drawer-content">
        <div class="p-4" style="background-color: #bed3f3ff;">

            <!-- Mobile Nav -->
            <div class="navbar bg-base-100 shadow-lg rounded-box mb-4 lg:hidden">
                <div class="flex-1">
                    <label for="my-drawer-2" class="btn btn-ghost drawer-button">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </label>
                    <h1 class="text-xl font-bold px-4">Lecturer Dashboard</h1>
                </div>
            </div>

            <!-- Welcome Section -->
            <div class="card bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-xl mb-4">
                <div class="card-body">
                    <h1 class="text-3xl font-bold">Welcome Back <?php echo htmlspecialchars($_SESSION['lectName'] ?? 'Lecturer'); ?>!</h1>
                    <p class="text-lg opacity-90">Here's a comprehensive overview of your clubs, events, and activities</p>
                </div>
            </div>

            <!-- Club Filter Section -->
            <div class="card bg-base-100 shadow-xl mb-6">
                <div class="card-body p-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <span class="font-semibold">Filter Charts by Club:</span>
                        </div>
                        <form method="GET" action="" class="flex-1 flex items-center gap-3">
                            <select name="club_filter" class="select select-bordered select-sm flex-1 max-w-xs" onchange="this.form.submit()">
                                <option value="all" <?php echo $selected_club === 'all' ? 'selected' : ''; ?>>All Clubs</option>
                                <?php foreach ($clubs_list as $club): ?>
                                    <option value="<?php echo $club['clubID']; ?>" <?php echo $selected_club == $club['clubID'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($club['clubName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <?php if ($selected_club !== 'all'): ?>
                                <a href="?" class="btn btn-sm btn-ghost">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Clear Filter
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <?php if ($selected_club !== 'all'): ?>
                        <?php 
                        $selected_club_name = '';
                        foreach ($clubs_list as $club) {
                            if ($club['clubID'] == $selected_club) {
                                $selected_club_name = $club['clubName'];
                                break;
                            }
                        }
                        ?>
                        <div class="alert alert-info mt-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Currently viewing data for: <strong><?php echo htmlspecialchars($selected_club_name); ?></strong></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Primary Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
                <div class="stats shadow bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                    <div class="stat">
                        <div class="stat-figure text-white opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="stat-title text-white opacity-80">My Clubs</div>
                        <div class="stat-value"><?php echo $stats['clubs']; ?></div>
                        <div class="stat-desc text-white opacity-70">Clubs you manage</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-green-500 to-green-600 text-white">
                    <div class="stat">
                        <div class="stat-figure text-white opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div class="stat-title text-white opacity-80">Total Members</div>
                        <div class="stat-value"><?php echo $stats['total_members']; ?></div>
                        <div class="stat-desc text-white opacity-70">Across all your clubs</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-yellow-500 to-yellow-600 text-white">
                    <div class="stat">
                        <div class="stat-figure text-white opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="stat-title text-white opacity-80">Total Events</div>
                        <div class="stat-value"><?php echo $stats['total_events']; ?></div>
                        <div class="stat-desc text-white opacity-70">Events organized</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-blue-500 to-blue-600 text-white">
                    <div class="stat">
                        <div class="stat-figure text-white opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="stat-title text-white opacity-80">Total Activities</div>
                        <div class="stat-value"><?php echo $stats['total_activities']; ?></div>
                        <div class="stat-desc text-white opacity-70">Activities posted</div>
                    </div>
                </div>
                
                <div class="stats shadow bg-gradient-to-br from-green-500 to-green-600 text-white">
                    <div class="stat">
                        <div class="stat-figure text-white opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                            </svg>
                        </div>
                        <div class="stat-title text-white opacity-80">Announcements</div>
                        <div class="stat-value"><?php echo $stats['total_announcements']; ?></div>
                        <div class="stat-desc text-white opacity-70">Posted to clubs</div>
                    </div>
                </div>
                
                <div class="stats shadow bg-gradient-to-br from-yellow-500 to-yellow-600 text-white">
                    <div class="stat">
                        <div class="stat-figure text-white opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                        </div>
                        <div class="stat-title text-white opacity-80">Total Comments</div>
                        <div class="stat-value"><?php echo number_format($comment_stats['total_comments']); ?></div>
                        <div class="stat-desc text-white opacity-70"><?php echo $comment_stats['active_comments']; ?> active</div>
                    </div>
                </div>
            </div>

            <!-- Charts  -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
                <!-- Activity Types Distribution -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                            Activity Types Distribution
                        </h2>
                        <p class="text-sm text-gray-500 mb-2">Breakdown of activities by type</p>
                        <?php if (!empty($activity_types)): ?>
                            <canvas id="activityTypesChart"></canvas>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <p>No activities posted yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Event Types Distribution -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Event Types (Public vs Private)
                        </h2>
                        <p class="text-sm text-gray-500 mb-2">Distribution of event accessibility</p>
                        <?php if (!empty($event_types)): ?>
                            <canvas id="eventTypesChart"></canvas>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <p>No events created yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Club Performance Overview -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Club Activity Overview
                        </h2>
                        <p class="text-sm text-gray-500 mb-2">Events + Activities count per club</p>
                        <?php if (!empty($club_performance)): ?>
                            <canvas id="clubPerformanceChart"></canvas>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <p>No clubs assigned yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Monthly Activity Timeline -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                            </svg>
                            Activity Timeline (6 Months)
                        </h2>
                        <p class="text-sm text-gray-500 mb-2">Events, activities, and announcements over time</p>
                        <?php if (!empty($monthly_timeline)): ?>
                            <canvas id="monthlyTimelineChart"></canvas>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <p>No timeline data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events & Recent Activities -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <!-- Upcoming Events -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Upcoming Events
                            </h2>
                            <a href="event_log.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <?php if (!empty($upcoming_events)): ?>
                            <div class="space-y-3">
                                <?php foreach ($upcoming_events as $event): ?>
                                    <div class="border rounded-lg p-3 hover:shadow-md transition-shadow">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <h3 class="font-bold"><?php echo htmlspecialchars($event['evTitle']); ?></h3>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($event['clubName']); ?></p>
                                                <div class="flex items-center gap-2 mt-2">
                                                    <span class="badge badge-sm <?php echo $event['evType'] === 'Private' ? 'badge-info' : 'badge-warning'; ?>">
                                                        <?php echo $event['evType']; ?>
                                                    </span>
                                                    <span class="text-xs text-gray-500">
                                                        📅 <?php echo date('M j, Y', strtotime($event['evDate'])); ?> • 
                                                        🕐 <?php echo date('g:i A', strtotime($event['evTime'])); ?>
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    👥 <?php echo $event['registered_count']; ?> registered • 
                                                    ✅ <?php echo $event['attended_count']; ?> attended
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p>No upcoming events</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Recent Activities
                            </h2>
                            <a href="activity_log.php" class="btn btn-sm btn-secondary">View All</a>
                        </div>
                        <?php if (!empty($recent_activities)): ?>
                            <div class="space-y-3">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="border rounded-lg p-3 hover:shadow-md transition-shadow">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <h3 class="font-bold"><?php echo htmlspecialchars($activity['actTitle']); ?></h3>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($activity['clubName']); ?></p>
                                                <div class="flex items-center gap-2 mt-2">
                                                    <span class="badge badge-sm badge-primary">
                                                        <?php echo $activity['actType']; ?>
                                                    </span>
                                                    <span class="text-xs text-gray-500">
                                                        📅 <?php echo date('M j, Y', strtotime($activity['actDate'])); ?>
                                                        <?php if ($activity['actTime']): ?>
                                                            • 🕐 <?php echo date('g:i A', strtotime($activity['actTime'])); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    ✅ <?php echo $activity['present_count']; ?> / <?php echo $activity['clubCapacity']; ?> attended
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p>No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Comments Section -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                                Recent Comments
                            </h2>
                            <a href="comments_log.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <?php if (!empty($recent_comments)): ?>
                            <div class="space-y-3">
                                <?php foreach ($recent_comments as $comment): ?>
                                    <div class="border rounded-lg p-3 hover:shadow-md transition-shadow <?php echo $comment['is_deleted'] ? 'bg-red-50' : 'bg-white'; ?>">
                                        <div class="flex items-start gap-3">
                                            <div class="flex-1">
                                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                                    <span class="font-semibold text-sm"><?php echo htmlspecialchars($comment['commenter_name']); ?></span>
                                                    <span class="badge badge-xs <?php echo $comment['commenter_type'] === 'student' ? 'badge-info' : 'badge-warning'; ?>">
                                                        <?php echo ucfirst($comment['commenter_type']); ?>
                                                    </span>
                                                    <?php if ($comment['is_deleted']): ?>
                                                        <span class="badge badge-xs badge-error">Deleted</span>
                                                    <?php endif; ?>
                                                    <span class="text-xs text-gray-500">• <?php echo date('M j, g:i A', strtotime($comment['created_at'])); ?></span>
                                                </div>
                                                <p class="text-sm mb-1 <?php echo $comment['is_deleted'] ? 'line-through text-gray-500' : ''; ?>">
                                                    <?php echo htmlspecialchars(substr($comment['content'], 0, 150)); ?><?php echo strlen($comment['content']) > 150 ? '...' : ''; ?>
                                                </p>
                                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                                    <span class="badge badge-xs <?php echo $comment['post_type'] === 'announcement' ? 'badge-primary' : 'badge-secondary'; ?>">
                                                        <?php echo ucfirst($comment['post_type']); ?>
                                                    </span>
                                                    <span class="text-gray-600"><?php echo htmlspecialchars($comment['club_name']); ?></span>
                                                    <span class="text-gray-400">•</span>
                                                    <span class="text-gray-600">❤️ <?php echo $comment['like_count']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                                <p>No comments yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Recently Registered Students -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h2 class="card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                Recently Registered Students
                            </h2>
                            <p class="text-sm text-gray-500 mt-1">Latest members joining your clubs</p>
                        </div>
                        <a href="memberships.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <?php if (!empty($recent_memberships)): ?>
                            <table class="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Club Name</th>
                                        <th>Student Name</th>
                                        <th>Student No ID</th>
                                        <th>Joined Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_memberships as $membership): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-primary"><?php echo htmlspecialchars($membership['clubName']); ?></span>
                                            </td>
                                            <td class="font-semibold"><?php echo htmlspecialchars($membership['studName']); ?></td>
                                            <td><?php echo htmlspecialchars($membership['studNoID']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($membership['joined_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <p>No recent memberships</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
</div>

<script>
// Activity Types Distribution Chart (Pie Chart)
<?php if (!empty($activity_types)): ?>
const actTypesCtx = document.getElementById('activityTypesChart');
const actTypes = <?php echo json_encode(array_column($activity_types, 'actType')); ?>;
const actTypeCounts = <?php echo json_encode(array_column($activity_types, 'count')); ?>;

new Chart(actTypesCtx, {
    type: 'pie',
    data: {
        labels: actTypes,
        datasets: [{
            data: actTypeCounts,
            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
<?php endif; ?>

// Event Types Distribution Chart (Doughnut Chart)
<?php if (!empty($event_types)): ?>
const evTypesCtx = document.getElementById('eventTypesChart');
const evTypes = <?php echo json_encode(array_column($event_types, 'evType')); ?>;
const evTypeCounts = <?php echo json_encode(array_column($event_types, 'count')); ?>;

new Chart(evTypesCtx, {
    type: 'doughnut',
    data: {
        labels: evTypes,
        datasets: [{
            data: evTypeCounts,
            backgroundColor: ['#10b981', '#f59e0b'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
<?php endif; ?>

// Club Performance Chart (Grouped Bar Chart - 3 bars side by side)
<?php if (!empty($club_performance)): ?>
const clubPerfCtx = document.getElementById('clubPerformanceChart');
const clubNames = <?php echo json_encode(array_column($club_performance, 'clubName')); ?>;
const clubEvents = <?php echo json_encode(array_column($club_performance, 'event_count')); ?>;
const clubActivities = <?php echo json_encode(array_column($club_performance, 'activity_count')); ?>;
const clubAnnouncements = <?php echo json_encode(array_column($club_performance, 'announcement_count')); ?>;

new Chart(clubPerfCtx, {
    type: 'bar',
    data: {
        labels: clubNames,
        datasets: [
            {
                label: 'Events',
                data: clubEvents,
                backgroundColor: '#3b82f6',
                borderRadius: 5
            },
            {
                label: 'Activities',
                data: clubActivities,
                backgroundColor: '#10b981',
                borderRadius: 5
            },
            {
                label: 'Announcements',
                data: clubAnnouncements,
                backgroundColor: '#f59e0b',
                borderRadius: 5
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            x: { stacked: false },
            y: { 
                stacked: false,
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});
<?php endif; ?>

// Monthly Timeline Chart (Multi-line Chart)
<?php if (!empty($monthly_timeline)): ?>
const timelineCtx = document.getElementById('monthlyTimelineChart');
const months = <?php echo json_encode(array_column($monthly_timeline, 'month')); ?>;
const eventsData = <?php echo json_encode(array_column($monthly_timeline, 'events')); ?>;
const activitiesData = <?php echo json_encode(array_column($monthly_timeline, 'activities')); ?>;
const announcementsData = <?php echo json_encode(array_column($monthly_timeline, 'announcements')); ?>;

new Chart(timelineCtx, {
    type: 'line',
    data: {
        labels: months,
        datasets: [
            {
                label: 'Events',
                data: eventsData,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(156, 39, 176, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Activities',
                data: activitiesData,
                borderColor: '#10b981',
                backgroundColor: 'rgba(236, 72, 153, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Announcements',
                data: announcementsData,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        },
        scales: {
            y: { 
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});
<?php endif; ?>

</script>
</body>
</html>
<?php
session_start();
require_once "../config/connect.php";

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    header("Location: ../user_access.php");
    exit();
}

$lect_id = $_SESSION['lect_id'];

// Pagination settings
$items_per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Filter settings
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, announcement, event, activity
$filter_commenter = isset($_GET['commenter']) ? $_GET['commenter'] : 'all'; // all, student, lecturer
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all'; // all, active, deleted
$filter_club = isset($_GET['club']) ? $_GET['club'] : 'all'; // all, or specific clubID
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get clubs that the lecturer is assigned to (using lectID in clubsocieties)
$clubs_query = "SELECT clubID, clubName 
                FROM clubsocieties 
                WHERE lectID = ?
                ORDER BY clubName ASC";
$clubs_stmt = $conn->prepare($clubs_query);
$clubs_stmt->execute([$lect_id]);
$clubs = $clubs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build WHERE clause
$where_conditions = ["1=1"];
$params = [];

// Restrict to lecturer's assigned clubs only (including activities)
$where_conditions[] = "((c.post_type = 'announcement' AND a.clubID IN (SELECT clubID FROM clubsocieties WHERE lectID = ?)) 
                         OR (c.post_type = 'event' AND e.clubID IN (SELECT clubID FROM clubsocieties WHERE lectID = ?))
                         OR (c.post_type = 'activity' AND act.clubID IN (SELECT clubID FROM clubsocieties WHERE lectID = ?)))";
$params[] = $lect_id;
$params[] = $lect_id;
$params[] = $lect_id;

if ($filter_type !== 'all') {
    $where_conditions[] = "c.post_type = ?";
    $params[] = $filter_type;
}

if ($filter_commenter !== 'all') {
    $where_conditions[] = "c.commenter_type = ?";
    $params[] = $filter_commenter;
}

if ($filter_status === 'active') {
    $where_conditions[] = "c.is_deleted = 0";
} elseif ($filter_status === 'deleted') {
    $where_conditions[] = "c.is_deleted = 1";
}

if ($filter_club !== 'all') {
    $where_conditions[] = "((c.post_type = 'announcement' AND a.clubID = ?) OR (c.post_type = 'event' AND e.clubID = ?) OR (c.post_type = 'activity' AND act.clubID = ?))";
    $params[] = $filter_club;
    $params[] = $filter_club;
    $params[] = $filter_club;
}

if (!empty($search_query)) {
    $where_conditions[] = "(c.content LIKE ? OR s.studName LIKE ? OR l.lectName LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM comments c
                LEFT JOIN student s ON c.commenter_type = 'student' AND CAST(c.commenter_id AS UNSIGNED) = CAST(s.studID AS UNSIGNED)
                LEFT JOIN lecturer l ON c.commenter_type = 'lecturer' AND CAST(c.commenter_id AS UNSIGNED) = CAST(l.lectID AS UNSIGNED)
                LEFT JOIN casannouncement a ON c.post_type = 'announcement' AND c.post_id = a.annID
                LEFT JOIN casevents e ON c.post_type = 'event' AND c.post_id = e.eventID
                LEFT JOIN casactivity act ON c.post_type = 'activity' AND c.post_id = act.actID
                WHERE {$where_clause}";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_comments = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_comments / $items_per_page);

// Get comments with all details
$comments_query = "SELECT 
    c.*,
    CASE 
        WHEN c.commenter_type = 'student' THEN s.studName
        WHEN c.commenter_type = 'lecturer' THEN l.lectName
    END as commenter_name,
    CASE 
        WHEN c.commenter_type = 'student' THEN s.studEmail
        WHEN c.commenter_type = 'lecturer' THEN l.lectEmail
    END as commenter_email,
    CASE 
        WHEN c.commenter_type = 'student' THEN s.studProfileImg
        WHEN c.commenter_type = 'lecturer' THEN l.lectProfileImg
    END as commenter_profile_img,
    CASE 
        WHEN c.commenter_type = 'student' THEN s.studProgramme
        WHEN c.commenter_type = 'lecturer' THEN l.lectFaculty
    END as commenter_detail,
    CASE 
        WHEN c.post_type = 'announcement' THEN a.anntitle
        WHEN c.post_type = 'event' THEN e.evTitle
        WHEN c.post_type = 'activity' THEN act.actTitle
    END as post_title,
    CASE 
        WHEN c.post_type = 'announcement' THEN club.clubName
        WHEN c.post_type = 'event' THEN club2.clubName
        WHEN c.post_type = 'activity' THEN club3.clubName
    END as club_name,
    CASE 
        WHEN c.post_type = 'announcement' THEN club.clubID
        WHEN c.post_type = 'event' THEN club2.clubID
        WHEN c.post_type = 'activity' THEN club3.clubID
    END as club_id,
    (SELECT COUNT(*) FROM comment_likes WHERE commentID = c.commentID) as like_count,
    (SELECT COUNT(*) FROM comments WHERE parent_comment_id = c.commentID AND is_deleted = 0) as reply_count,
    parent.content as parent_content,
    CASE 
        WHEN parent.commenter_type = 'student' THEN ps.studName
        WHEN parent.commenter_type = 'lecturer' THEN pl.lectName
    END as parent_commenter_name,
    CASE 
        WHEN c.deleted_by_type = 'student' THEN ds.studName
        WHEN c.deleted_by_type = 'lecturer' THEN dl.lectName
    END as deleted_by_name
FROM comments c
LEFT JOIN student s ON c.commenter_type = 'student' AND CAST(c.commenter_id AS UNSIGNED) = CAST(s.studID AS UNSIGNED)
LEFT JOIN lecturer l ON c.commenter_type = 'lecturer' AND CAST(c.commenter_id AS UNSIGNED) = CAST(l.lectID AS UNSIGNED)
LEFT JOIN casannouncement a ON c.post_type = 'announcement' AND c.post_id = a.annID
LEFT JOIN casevents e ON c.post_type = 'event' AND c.post_id = e.eventID
LEFT JOIN casactivity act ON c.post_type = 'activity' AND c.post_id = act.actID
LEFT JOIN clubsocieties club ON a.clubID = club.clubID
LEFT JOIN clubsocieties club2 ON e.clubID = club2.clubID
LEFT JOIN clubsocieties club3 ON act.clubID = club3.clubID
LEFT JOIN comments parent ON c.parent_comment_id = parent.commentID
LEFT JOIN student ps ON parent.commenter_type = 'student' AND CAST(parent.commenter_id AS UNSIGNED) = CAST(ps.studID AS UNSIGNED)
LEFT JOIN lecturer pl ON parent.commenter_type = 'lecturer' AND CAST(parent.commenter_id AS UNSIGNED) = CAST(pl.lectID AS UNSIGNED)
LEFT JOIN student ds ON c.deleted_by_type = 'student' AND CAST(c.deleted_by_id AS UNSIGNED) = CAST(ds.studID AS UNSIGNED)
LEFT JOIN lecturer dl ON c.deleted_by_type = 'lecturer' AND CAST(c.deleted_by_id AS UNSIGNED) = CAST(dl.lectID AS UNSIGNED)
WHERE {$where_clause}
ORDER BY c.created_at DESC
LIMIT ? OFFSET ?";

// Create a copy of params for the main query
$query_params = $params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$comments_stmt = $conn->prepare($comments_query);

// Bind the parameters with explicit types
$param_index = 1;
foreach ($query_params as $key => $value) {
    if ($key === count($query_params) - 2 || $key === count($query_params) - 1) {
        // LIMIT and OFFSET should be integers
        $comments_stmt->bindValue($param_index, (int)$value, PDO::PARAM_INT);
    } else {
        $comments_stmt->bindValue($param_index, $value, PDO::PARAM_STR);
    }
    $param_index++;
}

$comments_stmt->execute();
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (only for lecturer's assigned clubs)
$stats_query = "SELECT 
    COUNT(*) as total_comments,
    SUM(CASE WHEN c.is_deleted = 0 THEN 1 ELSE 0 END) as active_comments,
    SUM(CASE WHEN c.is_deleted = 1 THEN 1 ELSE 0 END) as deleted_comments,
    SUM(CASE WHEN c.post_type = 'announcement' THEN 1 ELSE 0 END) as announcement_comments,
    SUM(CASE WHEN c.post_type = 'event' THEN 1 ELSE 0 END) as event_comments,
    SUM(CASE WHEN c.post_type = 'activity' THEN 1 ELSE 0 END) as activity_comments,
    SUM(CASE WHEN c.commenter_type = 'student' THEN 1 ELSE 0 END) as student_comments,
    SUM(CASE WHEN c.commenter_type = 'lecturer' THEN 1 ELSE 0 END) as lecturer_comments
FROM comments c
LEFT JOIN casannouncement a ON c.post_type = 'announcement' AND c.post_id = a.annID
LEFT JOIN casevents e ON c.post_type = 'event' AND c.post_id = e.eventID
LEFT JOIN casactivity act ON c.post_type = 'activity' AND c.post_id = act.actID
WHERE ((c.post_type = 'announcement' AND a.clubID IN (SELECT clubID FROM clubsocieties WHERE lectID = ?)) 
       OR (c.post_type = 'event' AND e.clubID IN (SELECT clubID FROM clubsocieties WHERE lectID = ?))
       OR (c.post_type = 'activity' AND act.clubID IN (SELECT clubID FROM clubsocieties WHERE lectID = ?)))";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$lect_id, $lect_id, $lect_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comments Log - Monitoring Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .drawer-side {
                display: none !important;
            }
            .drawer-content {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="drawer lg:drawer-open">
        <input id="my-drawer-2" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content">
            <div class="p-4" style="background-color: #bed3f3ff;">
                <!-- Navbar -->
                <div class="navbar bg-base-100 shadow-lg rounded-box mb-4 no-print">
                    <div class="flex-1">
                        <label for="my-drawer-2" class="btn btn-ghost drawer-button lg:hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </label>
                        <div class="text-sm breadcrumbs hidden sm:inline-block">
                            <ul>
                                <li><a href="dashboard.php">Dashboard</a></li>
                                <li>Comments Log</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Page Header -->
                <div class="card bg-base-100 shadow-xl mb-6">
                    <div class="card-body">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div>
                                <h1 class="text-3xl font-bold flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                    </svg>
                                    Comments Monitoring Dashboard
                                </h1>
                                <p class="text-gray-600 mt-1">Monitor and track all comments across announcements, events, and activities</p>
                            </div>
                            <div class="stats shadow">
                                <div class="stat place-items-center py-2 px-4">
                                    <div class="stat-title text-xs">Total Comments</div>
                                    <div class="stat-value text-2xl text-primary"><?php echo number_format($stats['total_comments']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="stats shadow">
                        <div class="stat">
                            <div class="stat-figure text-success">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="stat-title">Active Comments</div>
                            <div class="stat-value text-success"><?php echo number_format($stats['active_comments']); ?></div>
                            <div class="stat-desc">Currently visible</div>
                        </div>
                    </div>

                    <div class="stats shadow">
                        <div class="stat">
                            <div class="stat-figure text-error">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>
                            <div class="stat-title">Deleted Comments</div>
                            <div class="stat-value text-error"><?php echo number_format($stats['deleted_comments']); ?></div>
                            <div class="stat-desc">Removed by users</div>
                        </div>
                    </div>

                    <div class="stats shadow">
                        <div class="stat">
                            <div class="stat-figure text-info">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="stat-title">Student Comments</div>
                            <div class="stat-value text-info"><?php echo number_format($stats['student_comments']); ?></div>
                            <div class="stat-desc"><?php echo $stats['total_comments'] > 0 ? round(($stats['student_comments'] / $stats['total_comments']) * 100, 1) : 0; ?>% of total</div>
                        </div>
                    </div>

                    <div class="stats shadow">
                        <div class="stat">
                            <div class="stat-figure text-warning">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="stat-title">Lecturer Comments</div>
                            <div class="stat-value text-warning"><?php echo number_format($stats['lecturer_comments']); ?></div>
                            <div class="stat-desc"><?php echo $stats['total_comments'] > 0 ? round(($stats['lecturer_comments'] / $stats['total_comments']) * 100, 1) : 0; ?>% of total</div>
                        </div>
                    </div>
                </div>

                <!-- Additional Statistics Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="stats shadow">
                        <div class="stat">
                            <div class="stat-figure text-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                </svg>
                            </div>
                            <div class="stat-title">Announcement Comments</div>
                            <div class="stat-value text-primary text-2xl"><?php echo number_format($stats['announcement_comments']); ?></div>
                            <div class="stat-desc"><?php echo $stats['total_comments'] > 0 ? round(($stats['announcement_comments'] / $stats['total_comments']) * 100, 1) : 0; ?>% of total</div>
                        </div>
                    </div>

                    <div class="stats shadow">
                        <div class="stat">
                            <div class="stat-figure text-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="stat-title">Event Comments</div>
                            <div class="stat-value text-secondary text-2xl"><?php echo number_format($stats['event_comments']); ?></div>
                            <div class="stat-desc"><?php echo $stats['total_comments'] > 0 ? round(($stats['event_comments'] / $stats['total_comments']) * 100, 1) : 0; ?>% of total</div>
                        </div>
                    </div>

                    <div class="stats shadow">
                        <div class="stat">
                            <div class="stat-figure text-accent">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div class="stat-title">Activity Comments</div>
                            <div class="stat-value text-accent text-2xl"><?php echo number_format($stats['activity_comments']); ?></div>
                            <div class="stat-desc"><?php echo $stats['total_comments'] > 0 ? round(($stats['activity_comments'] / $stats['total_comments']) * 100, 1) : 0; ?>% of total</div>
                        </div>
                    </div>
                </div>

                <!-- Filters Card -->
                <div class="card bg-base-100 shadow-xl mb-6 no-print">
                    <div class="card-body">
                        <h2 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            Filters
                        </h2>
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                            <!-- Post Type -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Post Type</span>
                                </label>
                                <select name="type" class="select select-bordered select-sm">
                                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                    <option value="announcement" <?php echo $filter_type === 'announcement' ? 'selected' : ''; ?>>Announcements</option>
                                    <option value="event" <?php echo $filter_type === 'event' ? 'selected' : ''; ?>>Events</option>
                                    <option value="activity" <?php echo $filter_type === 'activity' ? 'selected' : ''; ?>>Activities</option>
                                </select>
                            </div>

                            <!-- Commenter Type -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Commenter Type</span>
                                </label>
                                <select name="commenter" class="select select-bordered select-sm">
                                    <option value="all" <?php echo $filter_commenter === 'all' ? 'selected' : ''; ?>>All Users</option>
                                    <option value="student" <?php echo $filter_commenter === 'student' ? 'selected' : ''; ?>>Students</option>
                                    <option value="lecturer" <?php echo $filter_commenter === 'lecturer' ? 'selected' : ''; ?>>Lecturers</option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Status</span>
                                </label>
                                <select name="status" class="select select-bordered select-sm">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active Only</option>
                                    <option value="deleted" <?php echo $filter_status === 'deleted' ? 'selected' : ''; ?>>Deleted Only</option>
                                </select>
                            </div>

                            <!-- Club Filter -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Club/Society</span>
                                </label>
                                <select name="club" class="select select-bordered select-sm">
                                    <option value="all" <?php echo $filter_club === 'all' ? 'selected' : ''; ?>>All Clubs</option>
                                    <?php foreach ($clubs as $club): ?>
                                        <option value="<?php echo $club['clubID']; ?>" <?php echo $filter_club == $club['clubID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($club['clubName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Search -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Search</span>
                                </label>
                                <input type="text" 
                                       name="search" 
                                       placeholder="Search comments..." 
                                       class="input input-bordered input-sm"
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>

                            <!-- Submit Button -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text opacity-0">Action</span>
                                </label>
                                <div class="flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        Apply
                                    </button>
                                    <a href="comments_log.php" class="btn btn-ghost btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Comments List -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="card-title">
                                Comments List
                                <span class="badge badge-primary"><?php echo number_format($total_comments); ?> total</span>
                            </h2>
                            <div class="text-sm text-gray-600">
                                Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                            </div>
                        </div>

                        <?php if (!empty($comments)): ?>
                            <div class="space-y-4">
                                <?php foreach ($comments as $comment): ?>
                                    <div class="border rounded-lg p-4 hover:shadow-md transition-shadow <?php echo $comment['is_deleted'] ? 'bg-red-50' : 'bg-white'; ?>">
                                        <!-- Comment Header -->
                                        <div class="flex items-start gap-4 mb-3">
                                            <!-- Avatar -->
                                            <div class="avatar">
                                                <div class="w-12 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                                                    <?php if (!empty($comment['commenter_profile_img'])): ?>
                                                    <?php 
                                                        $img_path = $comment['commenter_type'] === 'student' 
                                                            ? '../uploads/student_profiles/' . $comment['commenter_profile_img']
                                                            : '../' . $comment['commenter_profile_img'];
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($img_path); ?>" alt="Profile" onerror="this.src='../assets/default-avatar.png'">
                                                    <?php else: ?>
                                                        <img src="../assets/default-avatar.png" alt="Default">
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Comment Info -->
                                            <div class="flex-1">
                                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                                    <h3 class="font-bold"><?php echo htmlspecialchars($comment['commenter_name']); ?></h3>
                                                    <span class="badge badge-sm <?php echo $comment['commenter_type'] === 'student' ? 'badge-info' : 'badge-warning'; ?>">
                                                        <?php echo ucfirst($comment['commenter_type']); ?>
                                                    </span>
                                                    <?php if ($comment['is_deleted']): ?>
                                                        <span class="badge badge-sm badge-error">Deleted</span>
                                                    <?php endif; ?>
                                                    <?php if ($comment['is_edited']): ?>
                                                        <span class="badge badge-sm badge-ghost">Edited</span>
                                                    <?php endif; ?>
                                                    <?php if ($comment['parent_comment_id']): ?>
                                                        <span class="badge badge-sm badge-accent">Reply</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($comment['commenter_detail']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($comment['commenter_email']); ?></p>
                                            </div>

                                            <!-- Timestamp -->
                                            <div class="text-right">
                                                <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($comment['created_at'])); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($comment['created_at'])); ?></p>
                                            </div>
                                        </div>

                                        <!-- Parent Comment (if reply) -->
                                        <?php if ($comment['parent_comment_id'] && $comment['parent_content']): ?>
                                            <div class="bg-gray-100 rounded-lg p-3 mb-3 ml-16">
                                                <p class="text-xs text-gray-600 mb-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                    </svg>
                                                    Replying to <strong><?php echo htmlspecialchars($comment['parent_commenter_name']); ?></strong>
                                                </p>
                                                <p class="text-xs text-gray-700 italic">"<?php echo htmlspecialchars(substr($comment['parent_content'], 0, 100)); ?><?php echo strlen($comment['parent_content']) > 100 ? '...' : ''; ?>"</p>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Comment Content -->
                                        <div class="ml-16 mb-3">
                                            <p class="text-sm <?php echo $comment['is_deleted'] ? 'text-gray-500 line-through' : ''; ?>">
                                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                            </p>
                                            <?php if ($comment['is_deleted'] && $comment['deleted_by_name']): ?>
                                                <p class="text-xs text-error mt-2">
                                                    Deleted by <?php echo htmlspecialchars($comment['deleted_by_name']); ?> 
                                                    on <?php echo date('M j, Y \a\t g:i A', strtotime($comment['deleted_at'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Comment Footer -->
                                        <div class="flex flex-wrap items-center gap-4 ml-16 text-xs">
                                            <!-- Post Info -->
                                            <div class="flex items-center gap-1">
                                                <?php 
                                                $badge_class = 'badge-primary';
                                                $link_url = '#';
                                                
                                                if ($comment['post_type'] === 'announcement') {
                                                    $badge_class = 'badge-primary';
                                                    $link_url = 'announcement_post.php?annID=' . $comment['post_id'];
                                                } elseif ($comment['post_type'] === 'event') {
                                                    $badge_class = 'badge-secondary';
                                                    $link_url = 'event_post.php?eventID=' . $comment['post_id'];
                                                } elseif ($comment['post_type'] === 'activity') {
                                                    $badge_class = 'badge-accent';
                                                    $link_url = 'activity_post.php?actID=' . $comment['post_id'];
                                                }
                                                ?>
                                                <span class="badge badge-sm <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($comment['post_type']); ?>
                                                </span>
                                                <a href="<?php echo $link_url; ?>" 
                                                   class="link link-hover font-semibold" 
                                                   target="_blank">
                                                    <?php echo htmlspecialchars(substr($comment['post_title'], 0, 50)); ?><?php echo strlen($comment['post_title']) > 50 ? '...' : ''; ?>
                                                </a>
                                            </div>

                                            <div class="divider divider-horizontal mx-0"></div>

                                            <!-- Club Info -->
                                            <?php if (!empty($comment['club_name'])): ?>
                                            <div class="flex items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                                <a href="club_profile.php?clubID=<?php echo $comment['club_id']; ?>" class="link link-hover">
                                                    <?php echo htmlspecialchars($comment['club_name']); ?>
                                                </a>
                                            </div>

                                            <div class="divider divider-horizontal mx-0"></div>
                                            <?php endif; ?>

                                            <!-- Engagement Stats -->
                                            <div class="flex items-center gap-3">
                                                <div class="flex items-center gap-1 text-gray-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                                    </svg>
                                                    <span><?php echo $comment['like_count']; ?> likes</span>
                                                </div>
                                                <div class="flex items-center gap-1 text-gray-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                    </svg>
                                                    <span><?php echo $comment['reply_count']; ?> replies</span>
                                                </div>
                                            </div>

                                            <div class="divider divider-horizontal mx-0"></div>

                                            <!-- Comment ID -->
                                            <div class="text-gray-500">
                                                ID: #<?php echo $comment['commentID']; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="flex justify-center mt-6 no-print">
                                    <div class="join">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $filter_type; ?>&commenter=<?php echo $filter_commenter; ?>&status=<?php echo $filter_status; ?>&club=<?php echo $filter_club; ?>&search=<?php echo urlencode($search_query); ?>" 
                                               class="join-item btn btn-sm">«</a>
                                        <?php endif; ?>

                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <a href="?page=<?php echo $i; ?>&type=<?php echo $filter_type; ?>&commenter=<?php echo $filter_commenter; ?>&status=<?php echo $filter_status; ?>&club=<?php echo $filter_club; ?>&search=<?php echo urlencode($search_query); ?>" 
                                               class="join-item btn btn-sm <?php echo $i === $page ? 'btn-active' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $filter_type; ?>&commenter=<?php echo $filter_commenter; ?>&status=<?php echo $filter_status; ?>&club=<?php echo $filter_club; ?>&search=<?php echo urlencode($search_query); ?>" 
                                               class="join-item btn btn-sm">»</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-12">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <h3 class="text-xl font-semibold text-gray-600 mb-2">No Comments Found</h3>
                                <p class="text-gray-500">No comments match your current filters. Try adjusting your search criteria.</p>
                                <a href="comments_log.php" class="btn btn-primary btn-sm mt-4">Reset Filters</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
        <?php include 'includes/sidebar.php'; ?>
    </div>
</body>
</html>
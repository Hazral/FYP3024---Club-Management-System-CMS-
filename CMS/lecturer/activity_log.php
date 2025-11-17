<?php
session_start();
require_once "../config/connect.php";

if (!isset($_SESSION['lect_id'])) {
    header('Location: ../user_access.php');
    exit;
}

$lectID = $_SESSION['lect_id'];

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && isset($_GET['activity'])) {
    $activityID = $_GET['activity'];
    
    // Verify lecturer owns this activity
    $verify_query = "SELECT c.clubID FROM casactivity a 
                    INNER JOIN clubsocieties c ON a.clubID = c.clubID 
                    WHERE a.actID = ? AND c.lectID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->execute([$activityID, $lectID]);
    
    if ($verify_stmt->fetch()) {
        // Get activity details
        $activity_query = "SELECT a.*, c.clubName 
                          FROM casactivity a
                          INNER JOIN clubsocieties c ON a.clubID = c.clubID
                          WHERE a.actID = ?";
        $stmt = $conn->prepare($activity_query);
        $stmt->execute([$activityID]);
        $activity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get participants (all club members)
        $participants_query = "SELECT s.studName, s.studNoID, s.studEmail,
                              COALESCE(aa.status, 'Absent') as status,
                              aa.actAttendDate, aa.actAttendTime, aa.remarks
                              FROM membership cm
                              INNER JOIN student s ON cm.studID = s.studID
                              LEFT JOIN casactivity_attendance aa ON s.studID = aa.studID AND aa.actID = ?
                              WHERE cm.clubID = ?
                              ORDER BY s.studName";
        $stmt = $conn->prepare($participants_query);
        $stmt->execute([$activityID, $activity['clubID']]);
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="activity_attendance_' . $activityID . '_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Activity details header
        fputcsv($output, ['Activity Attendance Report']);
        fputcsv($output, ['Generated On', date('F d, Y g:i A')]);
        fputcsv($output, []);
        fputcsv($output, ['Activity Title', $activity['actTitle']]);
        fputcsv($output, ['Club/Society', $activity['clubName']]);
        fputcsv($output, ['Activity Type', ucfirst($activity['actType'])]);
        fputcsv($output, ['Date', date('F d, Y', strtotime($activity['actDate']))]);
        if ($activity['actTime']) {
            fputcsv($output, ['Time', date('g:i A', strtotime($activity['actTime']))]);
        }
        fputcsv($output, ['Description', $activity['actDescription']]);
        fputcsv($output, []);
        
        // Statistics
        $present_count = count(array_filter($participants, function($p) { return $p['status'] === 'Present'; }));
        $absent_count = count(array_filter($participants, function($p) { return $p['status'] === 'Absent'; }));
        $total_count = count($participants);
        $attendance_rate = $total_count > 0 ? round(($present_count / $total_count) * 100, 2) : 0;
        
        fputcsv($output, ['Attendance Statistics']);
        fputcsv($output, ['Total Members', $total_count]);
        fputcsv($output, ['Present', $present_count]);
        fputcsv($output, ['Absent', $absent_count]);
        fputcsv($output, ['Attendance Rate', $attendance_rate . '%']);
        fputcsv($output, []);
        
        // Participants table
        fputcsv($output, ['Student Name', 'Student ID', 'Email', 'Status', 'Check-in Date', 'Check-in Time', 'Remarks']);
        foreach ($participants as $participant) {
            fputcsv($output, [
                $participant['studName'],
                $participant['studNoID'],
                $participant['studEmail'],
                $participant['status'],
                $participant['actAttendDate'] ? date('M d, Y', strtotime($participant['actAttendDate'])) : '-',
                $participant['actAttendTime'] ? date('g:i A', strtotime($participant['actAttendTime'])) : '-',
                $participant['remarks'] ?? '-'
            ]);
        }
        
        fclose($output);
        exit;
    }
}

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $conn->beginTransaction();
            
            switch ($_POST['action']) {
                case 'mark_attendance':
                    // Verify the activity belongs to lecturer's club
                    $verify_query = "SELECT c.clubID FROM casactivity a 
                                    INNER JOIN clubsocieties c ON a.clubID = c.clubID 
                                    WHERE a.actID = ? AND c.lectID = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->execute([$_POST['actID'], $lectID]);
                    $activity_info = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$activity_info) {
                        throw new Exception('You do not have permission to mark attendance for this activity.');
                    }

                    // Check if attendance already exists
                    $check_query = "SELECT actAttendanceID FROM casactivity_attendance WHERE actID = ? AND studID = ?";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->execute([$_POST['actID'], $_POST['studID']]);
                    
                    if ($check_stmt->fetch()) {
                        throw new Exception('Attendance already marked for this student.');
                    }

                    // Create attendance record
                    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
                    $insert_attend = "INSERT INTO casactivity_attendance (actID, studID, status, actAttendTime, actAttendDate, remarks) 
                                     VALUES (?, ?, 'Present', CURRENT_TIME(), CURRENT_DATE(), ?)";
                    $attend_stmt = $conn->prepare($insert_attend);
                    $attend_stmt->execute([$_POST['actID'], $_POST['studID'], $remarks]);
                    
                    $conn->commit();
                    $_SESSION['success'] = 'Attendance marked as Present successfully!';
                    break;

                case 'mark_absent':
                    // Verify the activity belongs to lecturer's club
                    $verify_query = "SELECT c.clubID FROM casactivity a 
                                    INNER JOIN clubsocieties c ON a.clubID = c.clubID 
                                    WHERE a.actID = ? AND c.lectID = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->execute([$_POST['actID'], $lectID]);
                    
                    if (!$verify_stmt->fetch()) {
                        throw new Exception('You do not have permission to modify attendance for this activity.');
                    }

                    // Check if attendance already exists
                    $check_query = "SELECT actAttendanceID FROM casactivity_attendance WHERE actID = ? AND studID = ?";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->execute([$_POST['actID'], $_POST['studID']]);
                    
                    if ($check_stmt->fetch()) {
                        throw new Exception('Attendance already recorded for this student.');
                    }

                    // Create attendance record with Absent status
                    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
                    $insert_attend = "INSERT INTO casactivity_attendance (actID, studID, status, actAttendDate, remarks) 
                                     VALUES (?, ?, 'Absent', CURRENT_DATE(), ?)";
                    $attend_stmt = $conn->prepare($insert_attend);
                    $attend_stmt->execute([$_POST['actID'], $_POST['studID'], $remarks]);
                    
                    $conn->commit();
                    $_SESSION['success'] = 'Attendance marked as Absent successfully!';
                    break;

                case 'update_attendance':
                    // Verify the activity belongs to lecturer's club
                    $verify_query = "SELECT c.clubID FROM casactivity a 
                                    INNER JOIN clubsocieties c ON a.clubID = c.clubID 
                                    INNER JOIN casactivity_attendance aa ON a.actID = aa.actID
                                    WHERE aa.actAttendanceID = ? AND c.lectID = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->execute([$_POST['actAttendanceID'], $lectID]);
                    
                    if (!$verify_stmt->fetch()) {
                        throw new Exception('You do not have permission to modify this attendance.');
                    }

                    $new_status = $_POST['status'];
                    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
                    
                    // Update attendance record
                    if ($new_status === 'Present') {
                        $update = "UPDATE casactivity_attendance SET status = ?, actAttendTime = CURRENT_TIME(), 
                                  actAttendDate = CURRENT_DATE(), remarks = ? WHERE actAttendanceID = ?";
                    } else {
                        $update = "UPDATE casactivity_attendance SET status = ?, actAttendTime = NULL, 
                                  actAttendDate = CURRENT_DATE(), remarks = ? WHERE actAttendanceID = ?";
                    }
                    $stmt = $conn->prepare($update);
                    $stmt->execute([$new_status, $remarks, $_POST['actAttendanceID']]);
                    
                    $conn->commit();
                    $_SESSION['success'] = 'Attendance updated successfully!';
                    break;

                case 'delete_attendance':
                    // Verify the activity belongs to lecturer's club
                    $verify_query = "SELECT c.clubID FROM casactivity a 
                                    INNER JOIN clubsocieties c ON a.clubID = c.clubID 
                                    INNER JOIN casactivity_attendance aa ON a.actID = aa.actID
                                    WHERE aa.actAttendanceID = ? AND c.lectID = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->execute([$_POST['actAttendanceID'], $lectID]);
                    
                    if (!$verify_stmt->fetch()) {
                        throw new Exception('You do not have permission to delete this attendance.');
                    }

                    // Delete attendance record
                    $delete = "DELETE FROM casactivity_attendance WHERE actAttendanceID = ?";
                    $stmt = $conn->prepare($delete);
                    $stmt->execute([$_POST['actAttendanceID']]);
                    
                    $conn->commit();
                    $_SESSION['success'] = 'Attendance record removed successfully!';
                    break;
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = $e->getMessage();
        }
        
        // Build redirect URL with all parameters
        $redirect_params = [];
        if (isset($_POST['actID'])) {
            $redirect_params[] = 'activity=' . $_POST['actID'];
        }
        if (isset($_GET['month'])) {
            $redirect_params[] = 'month=' . $_GET['month'];
        }
        if (isset($_GET['year'])) {
            $redirect_params[] = 'year=' . $_GET['year'];
        }
        if (isset($_GET['club'])) {
            $redirect_params[] = 'club=' . $_GET['club'];
        }
        
        $redirect_url = "activity_log.php" . (!empty($redirect_params) ? "?" . implode('&', $redirect_params) : "");
        header("Location: " . $redirect_url);
        exit;
    }
}

// Fetch clubs where the logged-in lecturer is in charge
$clubs_query = "SELECT clubID, clubName FROM clubsocieties WHERE lectID = ? ORDER BY clubName";
$stmt = $conn->prepare($clubs_query);
$stmt->execute([$lectID]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected month/year (default to current)
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get club filter
$club_filter = isset($_GET['club']) && $_GET['club'] !== '' ? $_GET['club'] : null;

// Get selected activity for attendance
$selected_activity = isset($_GET['activity']) && $_GET['activity'] !== '' ? $_GET['activity'] : null;

// Fetch activities for the selected month from lecturer's clubs
$activities_query = "SELECT a.*, c.clubName, c.clubID,
                     COUNT(DISTINCT CASE WHEN aa.status = 'Present' THEN aa.actAttendanceID END) as present_count,
                     COUNT(DISTINCT CASE WHEN aa.status = 'Absent' THEN aa.actAttendanceID END) as absent_count,
                     COUNT(DISTINCT cm.cmID) as total_members
                     FROM casactivity a
                     INNER JOIN clubsocieties c ON a.clubID = c.clubID
                     LEFT JOIN membership cm ON c.clubID = cm.clubID
                     LEFT JOIN casactivity_attendance aa ON a.actID = aa.actID
                     WHERE c.lectID = ?
                     AND MONTH(a.actDate) = ?
                     AND YEAR(a.actDate) = ?";
$params = [$lectID, $selected_month, $selected_year];

if ($club_filter !== null) {
    $activities_query .= " AND a.clubID = ?";
    $params[] = $club_filter;
}

$activities_query .= " GROUP BY a.actID ORDER BY a.actDate, a.actTime";
$stmt = $conn->prepare($activities_query);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group activities by date
$activities_by_date = [];
foreach ($activities as $activity) {
    $date = $activity['actDate'];
    if (!isset($activities_by_date[$date])) {
        $activities_by_date[$date] = [];
    }
    $activities_by_date[$date][] = $activity;
}

// If an activity is selected, get its details and participants
$activity_details = null;
$activity_members = [];
if ($selected_activity) {
    $activity_query = "SELECT a.*, c.clubName, c.clubID
                      FROM casactivity a
                      INNER JOIN clubsocieties c ON a.clubID = c.clubID
                      WHERE a.actID = ? AND c.lectID = ?";
    $stmt = $conn->prepare($activity_query);
    $stmt->execute([$selected_activity, $lectID]);
    $activity_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($activity_details) {
        // Get all club members with attendance status
        $members_query = "SELECT s.studID, s.studName, s.studNoID, s.studEmail,
                         aa.actAttendanceID, aa.status, aa.actAttendDate, aa.actAttendTime, aa.remarks
                         FROM membership cm
                         INNER JOIN student s ON cm.studID = s.studID
                         LEFT JOIN casactivity_attendance aa ON s.studID = aa.studID AND aa.actID = ?
                         WHERE cm.clubID = ?
                         ORDER BY s.studName";
        $stmt = $conn->prepare($members_query);
        $stmt->execute([$selected_activity, $activity_details['clubID']]);
        $activity_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Calendar helper functions
function getFirstDayOfMonth($month, $year) {
    return (int)date('w', strtotime("$year-$month-01"));
}

function getDaysInMonth($month, $year) {
    return (int)date('t', strtotime("$year-$month-01"));
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Attendance Management</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .calendar-day {
            min-height: 120px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }
        .calendar-day:hover {
            background-color: #f0f7ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .activity-badge {
            font-size: 0.7rem;
            padding: 3px 8px;
            margin: 2px 0;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        .activity-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .stats-card {
            transition: transform 0.2s ease;
        }
        .stats-card:hover {
            transform: translateY(-4px);
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .pulse-dot {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .status-present {
            background-color: #10b981;
            color: white;
        }
        .status-absent {
            background-color: #ef4444;
            color: white;
        }
    </style>
</head>
<body>
    <div class="drawer lg:drawer-open">
        <input id="my-drawer-2" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content">
            <div class="p-4" style="background-color: #bed3f3ff;">
                <div class="navbar bg-base-100 shadow-lg rounded-box mb-4">
                    <div class="flex-1">
                        <label for="my-drawer-2" class="btn btn-ghost drawer-button lg:hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </label>
                        <div class="text-sm breadcrumbs hidden sm:inline-block">
                            <ul>
                                <li><a href="dashboard.php">Dashboard</a></li>
                                <li>Activities Log</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error mb-4 transition-opacity duration-500 fade-in" id="error-alert">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                        <button onclick="dismissAlert('error-alert')" class="btn btn-sm btn-ghost">×</button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success mb-4 transition-opacity duration-500 fade-in" id="success-alert">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                        <button onclick="dismissAlert('success-alert')" class="btn btn-sm btn-ghost">×</button>
                    </div>
                <?php endif; ?>

                <?php if (empty($clubs)): ?>
                    <div class="card bg-base-100 shadow-xl fade-in">
                        <div class="card-body text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <h3 class="text-2xl font-bold text-gray-500">No Clubs Assigned</h3>
                            <p class="text-gray-400 mt-2">You need to be in charge of a club to manage activity attendance.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Statistics Summary -->
                    <?php if (!$selected_activity): ?>
                        <?php
                        $total_present = array_sum(array_column($activities, 'present_count'));
                        $total_absent = array_sum(array_column($activities, 'absent_count'));
                        $total_attendance = $total_present + $total_absent;
                        $attendance_rate = $total_attendance > 0 ? round(($total_present / $total_attendance) * 100, 1) : 0;
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <div class="stats shadow stats-card fade-in">
                                <div class="stat">
                                    <div class="stat-figure text-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                    </div>
                                    <div class="stat-title">Total Activities</div>
                                    <div class="stat-value text-primary"><?= count($activities) ?></div>
                                    <div class="stat-desc">This month</div>
                                </div>
                            </div>
                            
                            <div class="stats shadow stats-card fade-in" style="animation-delay: 0.1s;">
                                <div class="stat">
                                    <div class="stat-figure text-success">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="stat-title">Present</div>
                                    <div class="stat-value text-success"><?= $total_present ?></div>
                                    <div class="stat-desc"><?= $attendance_rate ?>% attendance rate</div>
                                </div>
                            </div>
                            
                            <div class="stats shadow stats-card fade-in" style="animation-delay: 0.2s;">
                                <div class="stat">
                                    <div class="stat-figure text-error">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="stat-title">Absent</div>
                                    <div class="stat-value text-error"><?= $total_absent ?></div>
                                    <div class="stat-desc"><?= $total_attendance > 0 ? round(($total_absent / $total_attendance) * 100, 1) : 0 ?>% absence rate</div>
                                </div>
                            </div>
                            
                            <div class="stats shadow stats-card fade-in" style="animation-delay: 0.3s;">
                                <div class="stat">
                                    <div class="stat-figure text-accent">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="stat-title">Active Clubs</div>
                                    <div class="stat-value text-accent"><?= count($clubs) ?></div>
                                    <div class="stat-desc">Under your management</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Calendar Controls -->
                    <div class="card bg-base-100 shadow-xl mb-4 fade-in">
                        <div class="card-body">
                            <div class="flex flex-wrap justify-between items-center gap-4">
                                <div class="flex items-center gap-4">
                                    <a href="?month=<?= $selected_month == 1 ? 12 : $selected_month - 1 ?>&year=<?= $selected_month == 1 ? $selected_year - 1 : $selected_year ?><?= $club_filter ? '&club=' . $club_filter : '' ?>" 
                                       class="btn btn-sm btn-circle btn-outline">
                                        ←
                                    </a>
                                    <h2 class="text-2xl font-bold">
                                        <?= date('F Y', strtotime("$selected_year-$selected_month-01")) ?>
                                    </h2>
                                    <a href="?month=<?= $selected_month == 12 ? 1 : $selected_month + 1 ?>&year=<?= $selected_month == 12 ? $selected_year + 1 : $selected_year ?><?= $club_filter ? '&club=' . $club_filter : '' ?>" 
                                       class="btn btn-sm btn-circle btn-outline">
                                        →
                                    </a>
                                    <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?><?= $club_filter ? '&club=' . $club_filter : '' ?>" 
                                       class="btn btn-sm btn-ghost gap-2">
                                        <span class="pulse-dot w-2 h-2 bg-primary rounded-full"></span>
                                        Today
                                    </a>
                                </div>
                                
                                <div class="form-control">
                                    <select name="club" class="select select-bordered select-sm" onchange="window.location.href='?month=<?= $selected_month ?>&year=<?= $selected_year ?>&club=' + this.value">
                                        <option value="">📚 All Clubs</option>
                                        <?php foreach ($clubs as $club): ?>
                                            <option value="<?= $club['clubID'] ?>" <?= $club_filter == $club['clubID'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($club['clubName']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar -->
                    <?php if (!$selected_activity): ?>
                    <div class="card bg-base-100 shadow-xl mb-4 fade-in">
                        <div class="card-body">
                            <div class="grid grid-cols-7 gap-0 text-center font-bold mb-2 bg-base-200 rounded-lg">
                                <div class="p-3 text-red-500">Sun</div>
                                <div class="p-3">Mon</div>
                                <div class="p-3">Tue</div>
                                <div class="p-3">Wed</div>
                                <div class="p-3">Thu</div>
                                <div class="p-3">Fri</div>
                                <div class="p-3 text-blue-500">Sat</div>
                            </div>
                            
                            <div class="grid grid-cols-7 gap-1">
                                <?php
                                $first_day = getFirstDayOfMonth($selected_month, $selected_year);
                                $days_in_month = getDaysInMonth($selected_month, $selected_year);
                                $current_date = date('Y-m-d');
                                
                                // Empty cells before month starts
                                for ($i = 0; $i < $first_day; $i++) {
                                    echo '<div class="calendar-day bg-gray-50 opacity-50"></div>';
                                }
                                
                                // Days of the month
                                for ($day = 1; $day <= $days_in_month; $day++) {
                                    $date = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $day);
                                    $is_today = ($date == $current_date);
                                    $has_activities = isset($activities_by_date[$date]);
                                    
                                    echo '<div class="calendar-day p-3 relative rounded-lg ' . ($is_today ? 'bg-blue-50 ring-2 ring-blue-400' : 'bg-white') . '">';
                                    echo '<div class="text-sm font-bold mb-1 ' . ($is_today ? 'text-blue-600' : 'text-gray-700') . '">';
                                    if ($is_today) {
                                        echo '<span class="inline-flex items-center gap-1">';
                                        echo '<span class="w-2 h-2 bg-blue-600 rounded-full pulse-dot"></span>';
                                        echo $day;
                                        echo '</span>';
                                    } else {
                                        echo $day;
                                    }
                                    echo '</div>';
                                    
                                    if ($has_activities) {
                                        foreach ($activities_by_date[$date] as $activity) {
                                            $activity_datetime = strtotime($activity['actDate'] . ' ' . ($activity['actTime'] ?? '00:00:00'));
                                            $current_datetime = time();
                                            $can_mark_attendance = $activity_datetime <= $current_datetime;
                                            
                                            // Badge color based on activity type
                                            $badge_color = 'badge-primary';
                                            $icon = '📋';
                                            
                                            switch ($activity['actType']) {
                                                case 'Meeting':
                                                    $badge_color = $can_mark_attendance ? 'badge-info' : 'badge-ghost';
                                                    $icon = '📋';
                                                    break;
                                                case 'Leadership/Team Building':
                                                    $badge_color = $can_mark_attendance ? 'badge-warning' : 'badge-ghost';
                                                    $icon = '👥';
                                                    break;
                                                case 'Social/Gathering':
                                                    $badge_color = $can_mark_attendance ? 'badge-error' : 'badge-ghost';
                                                    $icon = '👥';
                                                    break;
                                                case 'Recruitment/Orientation':
                                                    $badge_color = $can_mark_attendance ? 'badge-success' : 'badge-ghost';
                                                    $icon = '👥';
                                                    break;
                                                default:
                                                    $badge_color = $can_mark_attendance ? 'badge-accent' : 'badge-ghost';
                                                    $icon = '📋';
                                            }
                                            
                                            $attendance_info = '';
                                            if ($activity['present_count'] > 0 || $activity['absent_count'] > 0) {
                                                $attendance_info = ' (' . $activity['present_count'] . '/' . $activity['total_members'] . ')';
                                            }
                                            
                                            echo '<a href="?month=' . $selected_month . '&year=' . $selected_year . 
                                                 ($club_filter ? '&club=' . $club_filter : '') . 
                                                 '&activity=' . $activity['actID'] . '" class="badge ' . $badge_color . ' activity-badge gap-1" title="' . 
                                                 htmlspecialchars($activity['actTitle']) . ' - ' . 
                                                 htmlspecialchars($activity['clubName']) . $attendance_info . '">' .
                                                 $icon . ' ' .
                                                 htmlspecialchars(substr($activity['actTitle'], 0, 12)) . 
                                                 (strlen($activity['actTitle']) > 12 ? '...' : '') .
                                                 '</a>';
                                        }
                                    }
                                    
                                    echo '</div>';
                                }
                                
                                // Empty cells after month ends
                                $total_cells = $first_day + $days_in_month;
                                $remaining_cells = (7 - ($total_cells % 7)) % 7;
                                for ($i = 0; $i < $remaining_cells; $i++) {
                                    echo '<div class="calendar-day bg-gray-50 opacity-50"></div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Activity Details & Attendance -->
                    <?php if ($activity_details): ?>
                        <?php
                        $activity_datetime = strtotime($activity_details['actDate'] . ' ' . ($activity_details['actTime'] ?? '00:00:00'));
                        $current_datetime = time();
                        $can_mark_attendance = $activity_datetime <= $current_datetime;
                        $present_count = count(array_filter($activity_members, function($m) { return $m['status'] === 'Present'; }));
                        $absent_count = count(array_filter($activity_members, function($m) { return $m['status'] === 'Absent'; }));
                        $not_marked = count(array_filter($activity_members, function($m) { return $m['actAttendanceID'] === null; }));
                        $total_count = count($activity_members);
                        $attendance_rate = ($present_count + $absent_count) > 0 ? round(($present_count / ($present_count + $absent_count)) * 100, 1) : 0;
                        ?>
                        
                        <div class="card bg-base-100 shadow-xl fade-in">
                            <div class="card-body">
                                <!-- Activity Header -->
                                <div class="flex flex-wrap justify-between items-start gap-4 mb-6">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-3">
                                            <h2 class="card-title text-3xl"><?= htmlspecialchars($activity_details['actTitle']) ?></h2>
                                            <?php
                                            $type_icon = '📋';
                                            switch ($activity_details['actType']) {
                                                case 'Meeting': $type_icon = '👥'; break;
                                                case 'Workshop': $type_icon = '🛠️'; break;
                                                case 'Competition': $type_icon = '🏆'; break;
                                                case 'Social': $type_icon = '🎉'; break;
                                            }
                                            ?>
                                            <span class="text-2xl" title="<?= $activity_details['actType'] ?>"><?= $type_icon ?></span>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="badge badge-primary badge-lg gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                </svg>
                                                <?= htmlspecialchars($activity_details['clubName']) ?>
                                            </span>
                                            <span class="badge badge-secondary badge-lg gap-2">
                                                <?= ucfirst($activity_details['actType']) ?>
                                            </span>
                                            <?php if ($can_mark_attendance): ?>
                                                <span class="badge badge-success badge-lg gap-2">
                                                    <span class="w-2 h-2 bg-white rounded-full pulse-dot"></span>
                                                    Attendance Open
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-info badge-lg">Upcoming Activity</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <a href="?export=csv&activity=<?= $activity_details['actID'] ?>" 
                                           class="btn btn-success gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Export Report
                                        </a>
                                        <a href="?month=<?= $selected_month ?>&year=<?= $selected_year ?><?= $club_filter ? '&club=' . $club_filter : '' ?>" 
                                           class="btn btn-ghost gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                            </svg>
                                            Back to Calendar
                                        </a>
                                    </div>
                                </div>

                                <!-- Activity Details Grid -->
                                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                                    <div class="stats shadow">
                                        <div class="stat">
                                            <div class="stat-figure text-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <div class="stat-title">Date & Time</div>
                                            <div class="stat-value text-lg"><?= date('M d, Y', strtotime($activity_details['actDate'])) ?></div>
                                            <?php if ($activity_details['actTime']): ?>
                                                <div class="stat-desc"><?= date('g:i A', strtotime($activity_details['actTime'])) ?></div>
                                            <?php else: ?>
                                                <div class="stat-desc">All Day</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="stats shadow">
                                        <div class="stat">
                                            <div class="stat-figure text-success">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <div class="stat-title">Present</div>
                                            <div class="stat-value text-lg text-success"><?= $present_count ?></div>
                                            <div class="stat-desc"><?= $attendance_rate ?>% attendance rate</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stats shadow">
                                        <div class="stat">
                                            <div class="stat-figure text-error">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <div class="stat-title">Absent</div>
                                            <div class="stat-value text-lg text-error"><?= $absent_count ?></div>
                                            <div class="stat-desc"><?= $not_marked ?> not marked yet</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stats shadow">
                                        <div class="stat">
                                            <div class="stat-figure text-secondary">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </div>
                                            <div class="stat-title">Total Members</div>
                                            <div class="stat-value text-lg"><?= $total_count ?></div>
                                            <div class="stat-desc">Club members</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Activity Description -->
                                <div class="alert alert-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <h3 class="font-bold">Activity Description</h3>
                                        <div class="text-sm"><?= nl2br(htmlspecialchars($activity_details['actDescription'])) ?></div>
                                    </div>
                                </div>

                                <?php if (!$can_mark_attendance): ?>
                                    <div class="alert alert-info mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Attendance can only be marked during or after the activity time.</span>
                                    </div>
                                <?php endif; ?>

                                <div class="divider">Member Attendance Management</div>

                                <!-- Attendance Table -->
                                <div class="overflow-x-auto">
                                    <?php if (empty($activity_members)): ?>
                                        <div class="text-center py-12">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <p class="text-gray-500 text-lg">No club members found.</p>
                                        </div>
                                    <?php else: ?>
                                        <table class="table table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Student Name</th>
                                                    <th>Student ID</th>
                                                    <th>Email</th>
                                                    <th>Status</th>
                                                    <th>Check-in Time</th>
                                                    <th>Remarks</th>
                                                    <?php if ($can_mark_attendance): ?>
                                                        <th>Actions</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $counter = 1; ?>
                                                <?php foreach ($activity_members as $member): ?>
                                                    <tr class="hover">
                                                        <td><?= $counter++ ?></td>
                                                        <td>
                                                            <div class="flex items-center gap-3">
                                                                <div class="avatar placeholder">
                                                                    <div class="bg-neutral text-neutral-content rounded-full w-10">
                                                                        <span class="text-sm"><?= strtoupper(substr($member['studName'], 0, 2)) ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="font-bold"><?= htmlspecialchars($member['studName']) ?></div>
                                                            </div>
                                                        </td>
                                                        <td><?= htmlspecialchars($member['studNoID']) ?></td>
                                                        <td class="text-sm"><?= htmlspecialchars($member['studEmail']) ?></td>
                                                        <td>
                                                            <?php if ($member['status'] === 'Present'): ?>
                                                                <span class="badge badge-success gap-2 status-present">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                    </svg>
                                                                    Present
                                                                </span>
                                                            <?php elseif ($member['status'] === 'Absent'): ?>
                                                                <span class="badge badge-error gap-2 status-absent">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                    </svg>
                                                                    Absent
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge badge-ghost">Not Marked</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-sm">
                                                            <?php if ($member['actAttendDate'] && $member['actAttendTime']): ?>
                                                                <div class="flex flex-col">
                                                                    <span><?= date('M d, Y', strtotime($member['actAttendDate'])) ?></span>
                                                                    <span class="text-xs text-gray-500"><?= date('g:i A', strtotime($member['actAttendTime'])) ?></span>
                                                                </div>
                                                            <?php elseif ($member['actAttendDate']): ?>
                                                                <?= date('M d, Y', strtotime($member['actAttendDate'])) ?>
                                                            <?php else: ?>
                                                                <span class="text-gray-400">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-sm max-w-xs">
                                                            <span class="truncate block" title="<?= htmlspecialchars($member['remarks'] ?? '') ?>">
                                                                <?= $member['remarks'] ? htmlspecialchars($member['remarks']) : '<span class="text-gray-400">-</span>' ?>
                                                            </span>
                                                        </td>
                                                        <?php if ($can_mark_attendance): ?>
                                                            <td>
                                                                <div class="flex gap-1">
                                                                    <?php if ($member['actAttendanceID']): ?>
                                                                        <!-- Edit Button -->
                                                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($member)) ?>, <?= $activity_details['actID'] ?>)" 
                                                                                class="btn btn-xs btn-info gap-1">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                            </svg>
                                                                            Edit
                                                                        </button>
                                                                        <!-- Delete Button -->
                                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this attendance record?');">
                                                                            <input type="hidden" name="action" value="delete_attendance">
                                                                            <input type="hidden" name="actAttendanceID" value="<?= $member['actAttendanceID'] ?>">
                                                                            <input type="hidden" name="actID" value="<?= $activity_details['actID'] ?>">
                                                                            <button type="submit" class="btn btn-xs btn-error gap-1">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                                </svg>
                                                                                Delete
                                                                            </button>
                                                                        </form>
                                                                    <?php else: ?>
                                                                        <!-- Mark Present Button -->
                                                                        <button onclick="openMarkModal(<?= $member['studID'] ?>, '<?= htmlspecialchars($member['studName']) ?>', 'Present', <?= $activity_details['actID'] ?>)" 
                                                                                class="btn btn-xs btn-success gap-1">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                            </svg>
                                                                            Present
                                                                        </button>
                                                                        <!-- Mark Absent Button -->
                                                                        <button onclick="openMarkModal(<?= $member['studID'] ?>, '<?= htmlspecialchars($member['studName']) ?>', 'Absent', <?= $activity_details['actID'] ?>)" 
                                                                                class="btn btn-xs btn-error gap-1">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                            </svg>
                                                                            Absent
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Legend -->
                    <div class="card bg-base-100 shadow-xl mt-4 fade-in">
                        <div class="card-body">
                            <h3 class="font-bold text-lg mb-3">📌Information:</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                                    <span class="text-2xl">👥</span>
                                    <div>
                                        <div class="font-bold">Meeting</div>
                                        <div class="text-xs text-gray-500">Club meetings and discussions</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                                    <span class="text-2xl">🛠️</span>
                                    <div>
                                        <div class="font-bold">Workshop</div>
                                        <div class="text-xs text-gray-500">Learning sessions and training</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                                    <span class="text-2xl">🏆</span>
                                    <div>
                                        <div class="font-bold">Competition</div>
                                        <div class="text-xs text-gray-500">Contests and challenges</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                                    <span class="text-2xl">🎉</span>
                                    <div>
                                        <div class="font-bold">Social</div>
                                        <div class="text-xs text-gray-500">Social gatherings and celebrations</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                                    <span class="badge badge-success">Present</span>
                                    <div>
                                        <div class="font-bold">Student Attended</div>
                                        <div class="text-xs text-gray-500">Marked as present with check-in time recorded</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                                    <span class="badge badge-error">Absent</span>
                                    <div>
                                        <div class="font-bold">Student Absent</div>
                                        <div class="text-xs text-gray-500">Marked as absent, no check-in time</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <!-- Mark Attendance Modal -->
    <dialog id="markModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg mb-4">Mark Attendance</h3>
            <form method="POST" id="markForm">
                <input type="hidden" name="action" id="markAction" value="">
                <input type="hidden" name="actID" id="markActID" value="">
                <input type="hidden" name="studID" id="markStudID" value="">
                
                <div class="alert alert-info mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <div class="font-bold">Student: <span id="markStudentName"></span></div>
                        <div class="text-sm">Status: <span id="markStatus" class="font-bold"></span></div>
                    </div>
                </div>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Remarks (Optional)</span>
                    </label>
                    <textarea name="remarks" class="textarea textarea-bordered h-24" placeholder="Add any notes or remarks..."></textarea>
                </div>
                
                <div class="modal-action">
                    <button type="button" class="btn" onclick="closeMarkModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- Edit Attendance Modal -->
    <dialog id="editModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg mb-4">Edit Attendance</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_attendance">
                <input type="hidden" name="actID" id="editActID" value="">
                <input type="hidden" name="actAttendanceID" id="editActAttendanceID" value="">
                
                <div class="alert alert-info mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <div class="font-bold">Student: <span id="editStudentName"></span></div>
                        <div class="text-sm">Student ID: <span id="editStudentID"></span></div>
                    </div>
                </div>
                
                <div class="form-control mb-4">
                    <label class="label">
                        <span class="label-text">Attendance Status</span>
                    </label>
                    <select name="status" id="editStatus" class="select select-bordered">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Remarks</span>
                    </label>
                    <textarea name="remarks" id="editRemarks" class="textarea textarea-bordered h-24" placeholder="Add any notes or remarks..."></textarea>
                </div>
                
                <div class="modal-action">
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <script>
        function dismissAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 500);
            }
        }

        function openMarkModal(studID, studName, status, actID) {
            document.getElementById('markStudID').value = studID;
            document.getElementById('markActID').value = actID;
            document.getElementById('markStudentName').textContent = studName;
            document.getElementById('markStatus').textContent = status;
            document.getElementById('markStatus').className = status === 'Present' ? 'font-bold text-success' : 'font-bold text-error';
            document.getElementById('markAction').value = status === 'Present' ? 'mark_attendance' : 'mark_absent';
            document.getElementById('markModal').showModal();
        }

        function closeMarkModal() {
            document.getElementById('markModal').close();
            document.getElementById('markForm').reset();
        }

        function openEditModal(member, actID) {
            document.getElementById('editActAttendanceID').value = member.actAttendanceID;
            document.getElementById('editActID').value = actID;
            document.getElementById('editStudentName').textContent = member.studName;
            document.getElementById('editStudentID').textContent = member.studNoID;
            document.getElementById('editStatus').value = member.status || 'Present';
            document.getElementById('editRemarks').value = member.remarks || '';
            document.getElementById('editModal').showModal();
        }

        function closeEditModal() {
            document.getElementById('editModal').close();
            document.getElementById('editForm').reset();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts
            ['error-alert', 'success-alert'].forEach(id => {
                const alert = document.getElementById(id);
                if (alert) setTimeout(() => dismissAlert(id), 5000);
            });

            // Close modals on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeMarkModal();
                    closeEditModal();
                }
            });
        });
    </script>
</body>
</html>
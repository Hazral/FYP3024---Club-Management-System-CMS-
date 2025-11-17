<?php
session_start();
require_once "../config/connect.php";

// Check if student is logged in
$is_logged_in = isset($_SESSION['stud_id']);
if (!$is_logged_in) {
    // Allow viewing but restrict content for non-logged-in users
    $student_id = null;
} else {
    $student_id = $_SESSION['stud_id'];
}

// Get club ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: available_clubs.php");
    exit();
}

$club_id = $_GET['id'];

// Get club details with member count
$query = "SELECT c.*, l.lectName, l.lectEmail, l.lectProfileImg,
          (SELECT COUNT(*) FROM membership WHERE clubID = c.clubID) as current_members
          FROM clubsocieties c
          LEFT JOIN lecturer l ON c.lectID = l.lectID
          WHERE c.clubID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if club exists
if (!$club) {
    header("Location: available_clubs.php");
    exit();
}

// Check if student is a member of this club
$is_member = false;
if ($is_logged_in) {
    $member_check = "SELECT COUNT(*) FROM membership WHERE clubID = ? AND studID = ?";
    $member_stmt = $conn->prepare($member_check);
    $member_stmt->execute([$club_id, $_SESSION['stud_id']]);
    $is_member = $member_stmt->fetchColumn() > 0;
}

// Check if club is full
$is_full = $club['current_members'] >= $club['clubCapacity'];

// Get recent announcements from this club
if ($is_member) {
    // Show all announcements for members
    $query = "SELECT annID, anntitle, annPosted_at, annImg, content, annType
              FROM casannouncement
              WHERE clubID = ?
              ORDER BY annPosted_at DESC
              LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
} else {
    // Show only public announcements for non-members (annType = 'General')
    $query = "SELECT annID, anntitle, annPosted_at, annImg, content, annType
              FROM casannouncement
              WHERE clubID = ? AND annType = 'Public'
              ORDER BY annPosted_at DESC
              LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
}
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming events from this club
if ($is_member) {
    // Show all events for members
    $query = "SELECT eventID, evTitle, evDate, evTime, evLocation, evDescription, evImg, evType
              FROM casevents
              WHERE clubID = ? AND evDate >= CURDATE()
              ORDER BY evDate ASC
              LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
} else {
    // Show only public events for non-members
    $query = "SELECT eventID, evTitle, evDate, evTime, evLocation, evDescription, evImg, evType
              FROM casevents
              WHERE clubID = ? AND evDate >= CURDATE() AND evType = 'Public'
              ORDER BY evDate ASC
              LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
}
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get club members (only for logged-in users)
$members = [];
if ($is_logged_in) {
    $query = "SELECT s.studName, s.studEmail, m.joined_at
              FROM membership m
              JOIN student s ON m.studID = s.studID
              WHERE m.clubID = ?
              ORDER BY m.joined_at DESC
              LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get club activities (only for members)
$activities = [];
if ($is_member) {
    $query = "SELECT actID, actTitle, actDescription, actDate, actTime, actPosted_at, actType
              FROM casactivity
              WHERE clubID = ?
              ORDER BY actDate DESC, actPosted_at DESC
              LIMIT 4";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate percentage filled
$percentage = $club['clubCapacity'] > 0 ? round(($club['current_members'] / $club['clubCapacity']) * 100) : 0;

// Get capacity color
function getCapacityColor($percentage) {
    if ($percentage >= 90) return 'bg-red-600';
    if ($percentage >= 70) return 'bg-orange-600';
    if ($percentage >= 50) return 'bg-yellow-600';
    return 'bg-green-600';
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($club['clubName']); ?> - Student Club Management</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .banner-overlay {
            background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.6));
        }
        .card-hover:hover {
            transform: translateY(-4px);
            transition: all 0.3s ease;
        }
        .stat-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }
        .drawer-side {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .drawer-toggle:checked ~ .drawer-side {
            transform: translateX(0);
        }
        .drawer-overlay {
            transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: rgba(0, 0, 0, 0.4);
        }
        .drawer-toggle:checked ~ .drawer-side ~ .drawer-overlay {
            position: fixed;
        }
        .modal-backdrop {
            backdrop-filter: blur(8px);
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .modal-content {
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .modal-icon {
            animation: pulse 2s infinite;
        }
        .btn-hover-effect:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        .gradient-border {
            position: relative;
            background: linear-gradient(white, white) padding-box,
                        linear-gradient(135deg, #667eea 0%, #764ba2 100%) border-box;
            border: 3px solid transparent;
        }
    </style>
</head>
<body class="bg-base-200">
    <div class="drawer">
        <input id="my-drawer-3" type="checkbox" class="drawer-toggle" /> 
        
        <!-- Main Content -->
        <div class="drawer-content flex flex-col">
            <!-- Include Navbar -->
            <?php include "includes/navbar.php"; ?>

            <!-- Toast Notifications -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="toast toast-top toast-end z-50">
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="toast toast-top toast-end z-50">
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Hero Section with Banner -->
            <div class="relative pt-16">
                <!-- Club Banner -->
                <?php if (!empty($club['clubBanner'])): ?>
                <div class="relative h-80 overflow-hidden mt-4.9">
                    <img src="../<?php echo htmlspecialchars($club['clubBanner']); ?>" 
                         alt="<?php echo htmlspecialchars($club['clubName']); ?> Banner"
                         class="w-full h-full object-cover"
                         onerror="this.parentElement.innerHTML='<div class=\'hero-gradient w-full h-full\'></div>'">
                    <div class="banner-overlay absolute inset-0"></div>
                </div>
                <?php else: ?>
                <div class="hero-gradient h-80"></div>
                <?php endif; ?>

                <!-- Club Info Overlay -->
                <div class="absolute bottom-0 left-0 right-0 text-white pb-8">
                    <div class="container mx-auto px-4">
                        <div class="flex flex-col md:flex-row items-end md:items-center gap-6">
                            <!-- Club Logo/Avatar -->
                            <div class="avatar">
                                <div class="w-32 h-32 rounded-full ring ring-white ring-offset-4 bg-white">
                                    <?php if (!empty($club['clubLogo'])): ?>
                                    <img src="../<?php echo htmlspecialchars($club['clubLogo']); ?>" 
                                         alt="<?php echo htmlspecialchars($club['clubName']); ?>"
                                         class="rounded-full object-cover"
                                         onerror="this.parentElement.innerHTML='<div class=\'w-32 h-32 rounded-full bg-white flex items-center justify-center text-purple-600 text-4xl font-bold\'><?php echo strtoupper(substr($club['clubName'], 0, 2)); ?></div>'">
                                    <?php else: ?>
                                    <div class="w-32 h-32 rounded-full bg-white flex items-center justify-center text-purple-600 text-4xl font-bold">
                                        <?php echo strtoupper(substr($club['clubName'], 0, 2)); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Club Info -->
                            <div class="flex-1 text-center md:text-left">
                                <h1 class="text-5xl font-bold mb-3"><?php echo htmlspecialchars($club['clubName']); ?></h1>
                                <p class="text-xl text-white/90 mb-4"><?php echo htmlspecialchars($club['clubDescription']); ?></p>
                                
                                <div class="flex flex-wrap gap-2 justify-center md:justify-start">
                                    <?php if ($is_member): ?>
                                        <div class="badge badge-lg bg-green-500 text-white border-none gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            Member
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_full): ?>
                                        <div class="badge badge-lg badge-error text-white gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                                            </svg>
                                            Club Full
                                        </div>
                                    <?php else: ?>
                                        <div class="badge badge-lg bg-white/20 text-white border-none">
                                            <?php echo ($club['clubCapacity'] - $club['current_members']); ?> Spots Available
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Join/Leave Button with Modal or Login Required -->
                            <?php if ($is_logged_in): ?>
                                <?php if (!$is_member && !$is_full): ?>
                                <div>
                                    <button onclick="showJoinModal()" 
                                           class="btn btn-lg bg-white text-purple-600 hover:bg-gray-100 border-none btn-hover-effect">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                                        </svg>
                                        Join This Club
                                    </button>
                                </div>
                                <?php elseif ($is_member): ?>
                                <div>
                                    <button onclick="showLeaveModal()" 
                                           class="btn btn-lg btn-outline text-white hover:bg-red-600 hover:border-red-600 btn-hover-effect">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                                        </svg>
                                        Leave Club
                                    </button>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                            <div>
                                <button onclick="showLoginModal()" 
                                       class="btn btn-lg bg-white text-purple-600 hover:bg-gray-100 border-none btn-hover-effect">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    Login to Join
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="container mx-auto px-4 py-8">
                <!-- Club Advisor -->
                        <div class="card bg-base-100 shadow-xl mb-5">
                            <div class="card-body">
                                <h3 class="card-title text-lg mb-4">Club Advisor</h3>
                                <?php if (!empty($club['lectName'])): ?>
                                <div class="flex items-center gap-3">
                                    <div class="avatar">
                                        <div class="w-16 h-16 rounded-full">
                                            <?php if (!empty($club['lectProfileImg'])): ?>
                                            <img src="../<?php echo htmlspecialchars($club['lectProfileImg']); ?>" 
                                                 alt="<?php echo htmlspecialchars($club['lectName']); ?>"
                                                 class="rounded-full object-cover"
                                                 onerror="this.parentElement.innerHTML='<div class=\'w-16 h-16 rounded-full bg-purple-600 text-white flex items-center justify-center text-2xl font-bold\'><?php echo strtoupper(substr($club['lectName'], 0, 1)); ?></div>'">
                                            <?php else: ?>
                                            <div class="w-16 h-16 rounded-full bg-purple-600 text-white flex items-center justify-center text-2xl font-bold">
                                                <?php echo strtoupper(substr($club['lectName'], 0, 1)); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-bold"><?php echo htmlspecialchars($club['lectName']); ?></h4>
                                    </div>
                                </div>
                                <?php else: ?>
                                <p class="text-gray-500 text-center py-4">No advisor assigned</p>
                                <?php endif; ?>
                            </div>
                        </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                
                    <!-- Main Column -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Club Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="card bg-base-100 shadow-xl stat-card">
                                <div class="card-body items-center text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-purple-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <div class="stat-value text-3xl text-purple-600"><?php echo $club['current_members']; ?></div>
                                    <div class="stat-title">Members</div>
                                </div>
                            </div>

                            <div class="card bg-base-100 shadow-xl stat-card">
                                <div class="card-body items-center text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-purple-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <div class="stat-value text-3xl text-purple-600"><?php echo $club['clubCapacity']; ?></div>
                                    <div class="stat-title">Capacity</div>
                                </div>
                            </div>

                            <div class="card bg-base-100 shadow-xl stat-card">
                                <div class="card-body items-center text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-purple-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    <div class="stat-value text-3xl text-purple-600"><?php echo $percentage; ?>%</div>
                                    <div class="stat-title">Filled</div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Announcements -->
                        <?php if (!empty($announcements)): ?>
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <h3 class="card-title text-xl mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                    </svg>
                                    Recent Announcements
                                    <?php if (!$is_member): ?>
                                    <span class="badge badge-sm badge-info">Public Only</span>
                                    <?php endif; ?>
                                </h3>
                                <div class="space-y-4">
                                    <?php 
                                    $displayed_announcements = 0;
                                    foreach ($announcements as $announcement): 
                                        // Skip private announcements if user is not a member
                                        if (!$is_member && $announcement['annType'] === 'Private') {
                                            continue;
                                        }
                                        $displayed_announcements++;
                                    ?>
                                    <a href="announcement_post.php?id=<?php echo $announcement['annID']; ?>" 
                                    class="block hover:bg-base-200 p-4 rounded-lg transition card-hover">
                                        <div class="flex gap-4">
                                            <?php if (!empty($announcement['annImg'])): ?>
                                            <div class="avatar">
                                                <div class="w-16 h-16 rounded">
                                                    <img src="../<?php echo htmlspecialchars($announcement['annImg']); ?>" 
                                                        alt="<?php echo htmlspecialchars($announcement['anntitle']); ?>"
                                                        onerror="this.parentElement.innerHTML='<div class=\'w-16 h-16 rounded bg-gradient-to-br from-blue-400 to-blue-600\'></div>'">
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <h4 class="font-bold text-lg line-clamp-1"><?php echo htmlspecialchars($announcement['anntitle']); ?></h4>
                                                    <?php if ($announcement['annType'] === 'Private'): ?>
                                                    <span class="badge badge-sm badge-error">Private</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)); ?>...</p>
                                                <p class="text-xs text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($announcement['annPosted_at'])); ?></p>
                                            </div>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($displayed_announcements === 0): ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <p>No announcements available</p>
                                        <?php if (!$is_member): ?>
                                        <p class="text-xs mt-2">Join the club to see private announcements!</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Upcoming Events -->
                        <?php if (!empty($events)): ?>
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <h3 class="card-title text-xl mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Upcoming Events
                                    <?php if (!$is_member): ?>
                                    <span class="badge badge-sm badge-info">Public Only</span>
                                    <?php endif; ?>
                                </h3>
                                <div class="space-y-4">
                                    <?php 
                                    $displayed_events = 0;
                                    foreach ($events as $event): 
                                        // Skip private events if user is not a member
                                        if (!$is_member && $event['evType'] === 'Private') {
                                            continue;
                                        }
                                        $displayed_events++;
                                    ?>
                                    <a href="event_post.php?id=<?php echo $event['eventID']; ?>" 
                                    class="block hover:bg-base-200 p-4 rounded-lg transition card-hover">
                                        <div class="flex gap-4">
                                            <?php if (!empty($event['evImg'])): ?>
                                            <!-- Event Image -->
                                            <div class="flex-shrink-0">
                                                <div class="w-16 h-16 rounded-lg overflow-hidden">
                                                    <img src="../<?php echo htmlspecialchars($event['evImg']); ?>" 
                                                        alt="<?php echo htmlspecialchars($event['evTitle']); ?>"
                                                        class="w-full h-full object-cover"
                                                        onerror="this.parentElement.innerHTML='<div class=\'bg-gradient-to-br from-green-400 to-green-600 text-white rounded-lg w-16 h-16 flex flex-col items-center justify-center\'><span class=\'text-xs font-semibold\'><?php echo date('M', strtotime($event['evDate'])); ?></span><span class=\'text-2xl font-bold\'><?php echo date('d', strtotime($event['evDate'])); ?></span></div>'">
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <!-- Date Badge Fallback -->
                                            <div class="flex-shrink-0">
                                                <div class="bg-gradient-to-br from-green-400 to-green-600 text-white rounded-lg w-16 h-16 flex flex-col items-center justify-center">
                                                    <span class="text-xs font-semibold"><?php echo date('M', strtotime($event['evDate'])); ?></span>
                                                    <span class="text-2xl font-bold"><?php echo date('d', strtotime($event['evDate'])); ?></span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <h4 class="font-bold text-lg line-clamp-1"><?php echo htmlspecialchars($event['evTitle']); ?></h4>
                                                    <?php if ($event['evType'] === 'Private'): ?>
                                                    <span class="badge badge-sm badge-secondary">Private</span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-600 line-clamp-1"><?php echo htmlspecialchars($event['evDescription']); ?></p>
                                                <div class="flex flex-wrap gap-3 mt-2 text-xs text-gray-500">
                                                    <?php if (!empty($event['evTime'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                        </svg>
                                                        <?php echo htmlspecialchars($event['evTime']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($event['evLocation'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                                        </svg>
                                                        <?php echo htmlspecialchars($event['evLocation']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($displayed_events === 0): ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <p>No upcoming events available</p>
                                        <?php if (!$is_member): ?>
                                        <p class="text-xs mt-2">Join the club to see private events!</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    
                    <!-- Club Activities (Members Only) -->
                    <?php if ($is_member && !empty($activities)): ?>
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <h3 class="card-title text-xl mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                Club Activities
                                <span class="badge badge-sm badge-primary">Members Only</span>
                            </h3>
                            <div class="space-y-4">
                                <?php foreach ($activities as $activity): ?>
                                <a href="activity_post.php?id=<?php echo $activity['actID']; ?>" class="block hover:bg-base-200 p-4 rounded-lg transition card-hover cursor-pointer group">
                                    <div class="flex gap-4">
                                        <!-- Activity Icon/Date Badge -->
                                        <div class="flex-shrink-0">
                                            <div class="bg-gradient-to-br from-purple-400 to-purple-600 text-white rounded-lg w-16 h-16 flex flex-col items-center justify-center group-hover:shadow-lg transition-shadow">
                                                <span class="text-xs font-semibold"><?php echo date('M', strtotime($activity['actDate'])); ?></span>
                                                <span class="text-2xl font-bold"><?php echo date('d', strtotime($activity['actDate'])); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <h4 class="font-bold text-lg group-hover:text-purple-600 transition-colors">
                                                    <?php echo htmlspecialchars($activity['actTitle']); ?>
                                                </h4>
                                                <?php 
                                                // Activity type badge colors
                                                $actTypeBadges = [
                                                    'Recruitment/Orientation' => ['class' => 'badge-info', 'text' => 'Recruitment/Orientation'],
                                                    'Leadership/Team Building' => ['class' => 'badge-secondary', 'text' => 'Leadership'],
                                                    'Meeting' => ['class' => 'badge-success', 'text' => 'Meeting'],
                                                    'Social/Gathering' => ['class' => 'badge-warning', 'text' => 'Social']
                                                ];
                                                $typeBadge = $actTypeBadges[$activity['actType']] ?? ['class' => 'badge-ghost', 'text' => $activity['actType']];
                                                ?>
                                                <span class="badge badge-sm <?php echo $typeBadge['class']; ?>">
                                                    <?php echo $typeBadge['text']; ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 line-clamp-2 mb-2">
                                                <?php echo htmlspecialchars($activity['actDescription']); ?>
                                            </p>
                                            <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                                                <?php if (!empty($activity['actTime'])): ?>
                                                <span class="flex items-center gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                    </svg>
                                                    <?php echo date('g:i A', strtotime($activity['actTime'])); ?>
                                                </span>
                                                <?php endif; ?>
                                                <span class="flex items-center gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                    </svg>
                                                    <?php echo date('M j, Y', strtotime($activity['actDate'])); ?>
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                    </svg>
                                                    Posted <?php echo date('M j, Y', strtotime($activity['actPosted_at'])); ?>
                                                </span>
                                            </div>
                                            
                                            <!-- View Details Button (appears on hover) -->
                                            <div class="mt-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <span class="text-sm text-purple-600 font-medium flex items-center gap-1">
                                                    View Details
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Optional: Activity Image Thumbnail -->
                                        <?php if (!empty($activity['actImg'])): ?>
                                        <div class="flex-shrink-0 hidden sm:block">
                                            <div class="w-24 h-24 rounded-lg overflow-hidden">
                                                <img src="../<?php echo htmlspecialchars($activity['actImg']); ?>" 
                                                    alt="<?php echo htmlspecialchars($activity['actTitle']); ?>"
                                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                                    onerror="this.parentElement.innerHTML='<div class=\'w-24 h-24 bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center rounded-lg\'><svg xmlns=\'http://www.w3.org/2000/svg\' class=\'h-8 w-8 text-purple-400\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\' /></svg></div>'">
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- View All Activities Link -->
                            <?php if (count($activities) >= 5): ?>
                            <div class="divider"></div>
                            <a href="updates.php?filter=activities&club=<?php echo $club['clubID']; ?>" class="btn btn-outline btn-sm btn-block">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" />
                                </svg>
                                View All Activities
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif ($is_member): ?>
                    <!-- Show when member but no activities -->
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <h3 class="card-title text-xl mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                Club Activities
                                <span class="badge badge-sm badge-primary">Members Only</span>
                            </h3>
                            <div class="text-center py-8 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p class="font-semibold">No activities recorded yet</p>
                                <p class="text-xs mt-2">Stay tuned for upcoming club activities!</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer -->
            <?php include "includes/footer.php"; ?>
            
        </div>
        
        <!-- Drawer Side (Mobile Menu) -->
        <?php include "includes/mobile_drawer.php"; ?>
        
    </div>

    <!-- Login Required Modal -->
    <dialog id="loginModal" class="modal">
        <div class="modal-box max-w-md">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <div class="text-center py-6">
                <div class="mb-6">
                    <div class="w-20 h-20 mx-auto bg-gradient-to-r from-purple-600 to-blue-600 rounded-full flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-2xl mb-2">Login Required</h3>
                    <p class="text-gray-600">Please login or register to join clubs and access all features!</p>
                </div>
                
                <div class="flex flex-col gap-3">
                    <a href="../user_access.php" class="btn btn-lg bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        Login / Register
                    </a>
                    <form method="dialog">
                        <button class="btn btn-outline btn-lg w-full">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <?php if ($is_logged_in): ?>
    <!-- Join Confirmation Modal -->
    <div id="joinModal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop bg-black/50">
        <div class="modal-content gradient-border bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
            <!-- Modal Header with Gradient -->
            <div class="bg-gradient-to-r from-green-400 to-green-500 p-6 text-center">
                <div class="modal-icon inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-white">Join Club?</h3>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <p class="text-gray-700 text-center mb-2 text-lg">
                    Are you sure you want to join
                </p>
                <p class="text-purple-600 font-bold text-center text-xl mb-4">
                    <?php echo htmlspecialchars($club['clubName']); ?>?
                </p>
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded mb-6">
                    <div class="flex items-center gap-2 text-sm text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        <span>You'll receive updates about club activities and events!</span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button onclick="hideJoinModal()" 
                            class="flex-1 btn btn-outline btn-lg btn-hover-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Cancel
                    </button>
                    <a href="join_club.php?id=<?php echo $club_id; ?>" 
                       class="flex-1 btn btn-lg bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-blue-600 text-white border-none btn-hover-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        Yes, Join!
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Confirmation Modal -->
    <div id="leaveModal" class="fixed inset-0 z-50 hidden items-center justify-center modal-backdrop bg-black/50">
        <div class="modal-content gradient-border bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
            <!-- Modal Header with Gradient -->
            <div class="bg-gradient-to-r from-red-400 to-red-500 p-6 text-center">
                <div class="modal-icon inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-white">Leave Club?</h3>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <p class="text-gray-700 text-center mb-2 text-lg">
                    Are you sure you want to leave
                </p>
                <p class="text-purple-600 font-bold text-center text-xl mb-4">
                    <?php echo htmlspecialchars($club['clubName']); ?>?
                </p>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded mb-6">
                    <div class="flex items-center gap-2 text-sm text-red-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>You will no longer receive club updates and access to member-only events.</span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button onclick="hideLeaveModal()" 
                            class="flex-1 btn btn-outline btn-lg btn-hover-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Cancel
                    </button>
                    <a href="leave_club.php?id=<?php echo $club_id; ?>" 
                       class="flex-1 btn btn-lg bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-pink-600 text-white border-none btn-hover-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        Yes, Leave
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Login Modal Functions
        function showLoginModal() {
            document.getElementById('loginModal').showModal();
        }

        <?php if ($is_logged_in): ?>
        // Join Modal Functions
        function showJoinModal() {
            const modal = document.getElementById('joinModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function hideJoinModal() {
            const modal = document.getElementById('joinModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // Leave Modal Functions
        function showLeaveModal() {
            const modal = document.getElementById('leaveModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function hideLeaveModal() {
            const modal = document.getElementById('leaveModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('joinModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                hideJoinModal();
            }
        });

        document.getElementById('leaveModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                hideLeaveModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideJoinModal();
                hideLeaveModal();
            }
        });
        <?php endif; ?>

        // Auto-hide toast notifications after 5 seconds
        setTimeout(function() {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.5s ease-out';
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
        
        // Smooth scroll to sections
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
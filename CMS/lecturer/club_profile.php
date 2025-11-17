<?php
session_start();
require_once "../config/connect.php";

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    header("Location: ../user_access.php");
    exit();
}

// Get club ID from URL
if (!isset($_GET['clubID']) || empty($_GET['clubID'])) {
    header("Location: my_clubs.php");
    exit();
}

$club_id = $_GET['clubID'];
$lect_id = $_SESSION['lect_id'];

// Get club details with member count - verify lecturer is in charge
$query = "SELECT c.*, l.lectName, l.lectEmail,
          (SELECT COUNT(*) FROM membership WHERE clubID = c.clubID) as current_members
          FROM clubsocieties c
          LEFT JOIN lecturer l ON c.lectID = l.lectID
          WHERE c.clubID = ? AND c.lectID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$club_id, $lect_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if club exists and belongs to this lecturer
if (!$club) {
    $_SESSION['error'] = "Club not found or you don't have permission to access it.";
    header("Location: my_clubs.php");
    exit();
}

// Get recent announcements from this club
$query = "SELECT annID, anntitle, annPosted_at, annImg, content
          FROM casannouncement
          WHERE clubID = ?
          ORDER BY annPosted_at DESC
          LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->execute([$club_id]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming events from this club
$query = "SELECT eventID, evTitle, evDate, evTime, evLocation, evDescription
          FROM casevents
          WHERE clubID = ? AND evDate >= CURDATE()
          ORDER BY evDate ASC
          LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->execute([$club_id]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent club activities - UPDATED to include actTitle
$query = "SELECT actID, actTitle, actDescription, actType, actDate, actTime, actPosted_at, actImg
          FROM casactivity
          WHERE clubID = ?
          ORDER BY actPosted_at DESC
          LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->execute([$club_id]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get club members
$query = "SELECT s.studID, s.studNoID, s.studName, s.studEmail, m.joined_at
          FROM membership m
          JOIN student s ON m.studID = s.studID
          WHERE m.clubID = ?
          ORDER BY m.joined_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate percentage filled
$percentage = $club['clubCapacity'] > 0 ? round(($club['current_members'] / $club['clubCapacity']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($club['clubName']); ?> - Club Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            transition: all 0.3s ease;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            #printableReport, #printableReport * {
                visibility: visible;
            }
            #printableReport {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="drawer lg:drawer-open">
        <input id="my-drawer-2" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content">
            <!-- Page content -->
            <div class="p-4" style="background-color: #bed3f3ff;">
                <!-- Navbar -->
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
                                <li><a href="clubs.php"> My Clubs</a></li>
                                <li><?php echo htmlspecialchars($club['clubName']); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>

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
                <div class="card bg-base-100 shadow-xl mb-6 overflow-hidden">
                    <!-- Banner Image -->
                    <div class="relative h-64 bg-black">
                        <?php if (!empty($club['clubBanner'])): ?>
                        <img src="../<?php echo htmlspecialchars($club['clubBanner']); ?>" 
                             alt="Club Banner" 
                             class="w-full h-full"
                             onerror="this.style.display='none'">
                        <?php endif; ?>
                        <button onclick="editClubModal.showModal()" 
                                class="absolute top-4 right-4 btn btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                            </svg>
                            Edit Club
                        </button>
                    </div>

                    <!-- Club Info -->
                    <div class="card-body">
                        <div class="flex flex-col md:flex-row gap-6 items-start">
                            <!-- Club Logo -->
                            <div class="avatar">
                                <div class="w-24 h-24 rounded-full ring ring-blue-600 ring-offset-2">
                                    <?php if (!empty($club['clubLogo'])): ?>
                                    <img src="../<?php echo htmlspecialchars($club['clubLogo']); ?>" 
                                         alt="<?php echo htmlspecialchars($club['clubName']); ?>"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%23667eea%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2250%22 font-size=%2240%22 text-anchor=%22middle%22 dy=%22.35em%22 fill=%22white%22%3E<?php echo strtoupper(substr($club['clubName'], 0, 2)); ?>%3C/text%3E%3C/svg%3E'">
                                    <?php else: ?>
                                    <div class="w-24 h-24 rounded-lg bg-primary flex items-center justify-center text-white text-3xl font-bold">
                                        <?php echo strtoupper(substr($club['clubName'], 0, 2)); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Club Details -->
                            <div class="flex-1">
                                <h1 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($club['clubName']); ?></h1>
                                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($club['clubDescription']); ?></p>
                                
                                <div class="flex flex-wrap gap-2">
                                    <div class="badge badge-lg badge-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                        </svg>
                                        <?php echo $club['current_members']; ?> / <?php echo $club['clubCapacity']; ?> Members
                                    </div>
                                    <div class="badge badge-lg badge-primary">
                                        <?php echo count($announcements); ?> Recent Announcements
                                    </div>
                                    <div class="badge badge-lg badge-success">
                                        <?php echo count($events); ?> Upcoming Events
                                    </div>
                                    
                                    <div class="badge badge-lg badge-warning">
                                        <?php echo count($activities); ?> Recent Activities
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-6">
                            <div class="flex justify-between text-sm mb-2">
                                <span class="font-semibold">Member Capacity</span>
                                <span class="font-semibold"><?php echo $percentage; ?>%</span>
                            </div>
                            <progress 
                                class="progress <?php echo ($percentage >= 90) ? 'progress-error' : (($percentage >= 70) ? 'progress-warning' : 'progress-success'); ?> w-full h-3" 
                                value="<?php echo $club['current_members']; ?>" 
                                max="<?php echo $club['clubCapacity']; ?>">
                            </progress>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Column -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Quick Actions -->
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <h3 class="card-title mb-4">Quick Actions</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <button onclick="membersModal.showModal()" class="btn btn-outline btn-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                        </svg>
                                        Members
                                    </button>
                                    <button onclick="newAnnouncementModal.showModal()" class="btn btn-outline btn-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z" />
                                            <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z" />
                                        </svg>
                                        New Announcement
                                    </button>
                                    <button onclick="newEventModal.showModal()" class="btn btn-outline btn-success">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                                        </svg>
                                        New Event
                                    </button>
                                    <button onclick="newActivityModal.showModal()" class="btn btn-outline btn-warning">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                        </svg>
                                        New Activity
                                    </button>
                                    
                                </div>
                            </div>
                        </div>

                        <!-- Recent Announcements -->
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="card-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                        </svg>
                                        Recent Announcements
                                    </h3>
                                    <a href="all_announcements.php?clubID=<?php echo $club_id; ?>" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <?php if (!empty($announcements)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($announcements as $announcement): ?>
                                    <a href="announcement_post.php?annID=<?php echo $announcement['annID']; ?>" class="block hover:bg-base-200 p-4 rounded-lg transition card-hover cursor-pointer">
                                        <h4 class="font-bold text-lg"><?php echo htmlspecialchars($announcement['anntitle']); ?></h4>
                                        <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)); ?>...</p>
                                        <p class="text-xs text-gray-500 mt-2"><?php echo date('M j, Y', strtotime($announcement['annPosted_at'])); ?></p>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p>No announcements yet</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Upcoming Events -->
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="card-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Upcoming Events
                                    </h3>
                                    <a href="all_events.php?clubID=<?php echo $club_id; ?>" class="btn btn-sm btn-success">View All</a>
                                </div>
                                <?php if (!empty($events)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($events as $event): ?>
                                    <a href="event_post.php?eventID=<?php echo $event['eventID']; ?>" class="block hover:bg-base-200 p-4 rounded-lg transition card-hover cursor-pointer">
                                        <div class="flex gap-4">
                                            <div class="flex-shrink-0">
                                                <div class="bg-primary text-white rounded-lg w-16 h-16 flex flex-col items-center justify-center">
                                                    <span class="text-xs font-semibold"><?php echo date('M', strtotime($event['evDate'])); ?></span>
                                                    <span class="text-2xl font-bold"><?php echo date('d', strtotime($event['evDate'])); ?></span>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-bold text-lg"><?php echo htmlspecialchars($event['evTitle']); ?></h4>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($event['evDescription']); ?></p>
                                                <?php if (!empty($event['evLocation'])): ?>
                                                <p class="text-xs text-gray-500 mt-1">📍 <?php echo htmlspecialchars($event['evLocation']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p>No upcoming events</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Club Activities Sidebar -->
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="card-title">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    Club Activities
                                </h3>
                                <a href="all_activities.php?clubID=<?php echo $club_id; ?>" class="btn btn-sm btn-warning">View All</a>
                            </div>
                            <?php if (!empty($activities)): ?>
                            <div class="space-y-4">
                                <?php foreach ($activities as $activity): ?>
                                <a href="activity_post.php?actID=<?php echo $activity['actID']; ?>" class="block hover:bg-base-200 p-4 rounded-lg transition card-hover">
                                    <div class="flex gap-4">
                                        <?php if (!empty($activity['actImg'])): ?>
                                        <div class="flex-shrink-0">
                                            <img src="../<?php echo htmlspecialchars($activity['actImg']); ?>" 
                                                alt="Activity" 
                                                class="w-20 h-20 rounded-lg object-cover"
                                                onerror="this.style.display='none'">
                                        </div>
                                        <?php endif; ?>
                                        <div class="flex-1">
                                            <!-- Activity Title -->
                                            <?php if (!empty($activity['actTitle'])): ?>
                                            <h4 class="font-bold text-base mb-1"><?php echo htmlspecialchars($activity['actTitle']); ?></h4>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="badge badge-sm badge-warning">
                                                    <?php echo htmlspecialchars($activity['actType']); ?>
                                                </span>
                                                <span class="text-xs text-gray-500">
                                                    <?php echo date('M j, Y', strtotime($activity['actDate'])); ?>
                                                    <?php if (!empty($activity['actTime'])): ?>
                                                    • <?php echo date('g:i A', strtotime($activity['actTime'])); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-700 line-clamp-2"><?php echo htmlspecialchars($activity['actDescription']); ?></p>
                                            <p class="text-xs text-gray-500 mt-2">Posted <?php echo date('M j, Y', strtotime($activity['actPosted_at'])); ?></p>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p>No activities recorded yet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <!-- Edit Club Modal -->
    <dialog id="editClubModal" class="modal">
        <div class="modal-box max-w-2xl">
            <h3 class="font-bold text-2xl mb-6">Edit Club Profile</h3>
            <form action="update_club.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="clubID" value="<?php echo $club_id; ?>">
                
                <div class="space-y-4">
                    <!-- Club Name -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Club Name</span>
                        </label>
                        <input type="text" name="clubName" value="<?php echo htmlspecialchars($club['clubName']); ?>" 
                               class="input input-bordered" placeholder="Enter the club name..." required>
                    </div>

                    <!-- Club Description -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Description</span>
                        </label>
                        <textarea name="clubDescription" class="textarea textarea-bordered h-24" placeholder="Describe the club..." required><?php echo htmlspecialchars($club['clubDescription']); ?></textarea>
                    </div>

                    <!-- Club Capacity -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Member Capacity</span>
                        </label>
                        <input type="number" name="clubCapacity" value="<?php echo $club['clubCapacity']; ?>" 
                               class="input input-bordered" min="<?php echo $club['current_members']; ?>" required>
                        <label class="label">
                            <span class="label-text text-semibold text-error">Note: Cannot be less than current members (<?php echo $club['current_members']; ?>)</span>
                        </label>
                    </div>

                    <!-- Club Logo -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Club Logo</span>
                        </label>
                        <input type="file" name="clubLogo" accept=".jpg,.jpeg,.png" class="file-input file-input-bordered">
                        <?php if (!empty($club['clubLogo'])): ?>
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Only accept JPG, JPEG and PNG Files</span>
                        </label>
                        <?php endif; ?>
                    </div>

                    <!-- Club Banner -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Club Banner</span>
                        </label>
                        <input type="file" name="clubBanner" accept=".jpg, .jpeg, .png" class="file-input file-input-bordered">
                        <?php if (!empty($club['clubBanner'])): ?>
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Only accept JPG, JPEG and PNG Files</span>
                        </label>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" onclick="editClubModal.close()">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
                        </svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- Members Modal -->
    <dialog id="membersModal" class="modal">
        <div class="modal-box max-w-4xl">
            <h3 class="font-bold text-2xl mb-6">Club Members (<?php echo count($members); ?>)</h3>
            
            <div class="mb-4">
                <input type="text" id="memberSearch" placeholder="Search members..." class="input input-bordered w-full" onkeyup="filterMembers()">
            </div>

            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>No ID</th>
                            <th>Email</th>
                            <th>Joined Date</th>
                        </tr>
                    </thead>
                    <tbody id="membersTableBody">
                        <?php if (!empty($members)): ?>
                            <?php foreach ($members as $index => $member): ?>
                            <tr class="member-row">
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span><?php echo htmlspecialchars($member['studName']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($member['studNoID']); ?></td>
                                <td><?php echo htmlspecialchars($member['studEmail']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($member['joined_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-gray-500">No members yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-ghost" onclick="membersModal.close()">Close</button>
                <button type="button" class="btn btn-info" onclick="exportMembersToCSV()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    Export to CSV
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
    
    <!-- New Announcement Modal -->
    <dialog id="newAnnouncementModal" class="modal">
        <div class="modal-box max-w-2xl">
            <h3 class="font-bold text-2xl mb-6">Create New Announcement</h3>
            <form action="add_announcement.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="clubID" value="<?php echo $club_id; ?>">
                
                <div class="space-y-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Announcement Title</span>
                        </label>
                        <input type="text" name="anntitle" class="input input-bordered" placeholder="Enter the announcement title..."required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Announcement Type</span>
                        </label>
                        <select name="annType" class="select select-bordered" required>
                            <option value="Public">Public</option>
                            <option value="Private">Private</option>
                        </select>
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Public for every students, Private for club members only.</span>
                        </label>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Content</span>
                        </label>
                        <textarea name="content" class="textarea textarea-bordered h-32" placeholder="Enter the announcement content..."required></textarea>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Announcement Image</span>
                        </label>
                        <input type="file" name="annImg" accept=".jpg,.jpeg,.png" class="file-input file-input-bordered">
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Only accept JPG, JPEG and PNG files.</span>
                        </label>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Announcement Video</span>
                        </label>
                        <input type="file" name="annVid" accept=".mp4,.avi,.mov,.wmv" class="file-input file-input-bordered">
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Only accept MP4, AVI, MOV, and WMV files.</span>
                        </label>
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" onclick="newAnnouncementModal.close()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Post Announcement</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
    
    <!-- New Event Modal -->
    <dialog id="newEventModal" class="modal">
        <div class="modal-box max-w-2xl">
            <h3 class="font-bold text-2xl mb-6">Create New Event</h3>
            <form action="add_event.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="clubID" value="<?php echo $club_id; ?>">
                
                <div class="space-y-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Event Title</span>
                        </label>
                        <input type="text" name="evTitle" class="input input-bordered" placeholder="Enter the event title..." required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Description</span>
                        </label>
                        <textarea name="evDescription" class="textarea textarea-bordered h-24" placeholder="Describe the event..." required></textarea>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Event Type</span>
                        </label>
                        <select name="evType" class="select select-bordered" required>
                            <option value="Public">Public</option>
                            <option value="Private">Private</option>
                        </select>
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Public for every students, Private for club members only.</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Event Date</span>
                            </label>
                            <input type="date" name="evDate" class="input input-bordered" required>
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Event Time</span>
                            </label>
                            <input type="time" name="evTime" class="input input-bordered" required>
                        </div>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Location</span>
                        </label>
                        <input type="text" name="evLocation" class="input input-bordered" placeholder="Enter the event location..." required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Event Capacity (Max Participants)</span>
                        </label>
                        <input type="number" name="evCapacity" class="input input-bordered" min="0" value="0" required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Event Image</span>
                        </label>
                        <input type="file" name="evImg" accept=".jpg,.jpeg,.png" class="file-input file-input-bordered">
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Only accept JPG, JPEG and PNG files.</span>
                        </label>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Event Video (Optional)</span>
                        </label>
                        <input type="file" name="evVid" accept=".mp4,.avi,.mov,.wmv" class="file-input file-input-bordered">
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Only accept Only MP4, AVI, MOV, and WMV files.</span>
                        </label>
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" onclick="newEventModal.close()">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Event</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <!-- New Activity Modal -->
    <dialog id="newActivityModal" class="modal">
        <div class="modal-box max-w-2xl">
            <h3 class="font-bold text-2xl mb-6">Add New Club Activity</h3>
            <form action="add_activity.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="clubID" value="<?php echo $club_id; ?>">
                
                <div class="space-y-4">
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Activity Title</span>
                        </label>
                        <input type="text" name="actTitle" class="input input-bordered" 
                               placeholder="Enter activity title..." required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Activity Description</span>
                        </label>
                        <textarea name="actDescription" class="textarea textarea-bordered h-32" 
                                placeholder="Describe the activity..." required></textarea>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Activity Type</span>
                        </label>
                        <select name="actType" class="select select-bordered" required>
                            <option value="Recruitment/Orientation">Recruitment/Orientation</option>
                            <option value="Leadership/Team Building">Leadership/Team Building</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Social/Gathering">Social/Gathering</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Activity Date</span>
                            </label>
                            <input type="date" name="actDate" class="input input-bordered" required>
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text font-semibold">Activity Time</span>
                            </label>
                            <input type="time" name="actTime" class="input input-bordered">
                        </div>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Activity Image</span>
                        </label>
                        <input type="file" name="actImg" accept=".jpg,.jpeg,.png" class="file-input file-input-bordered">
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Only accept JPG, JPEG and PNG files.</span>
                        </label>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold">Activity Video</span>
                        </label>
                        <input type="file" name="actVid" accept=".mp4,.avi,.mov,.wmv" class="file-input file-input-bordered">
                        <label class="label">
                            <span class="label-text-alt text-gray-500">Only accept MP4, AVI, MOV and WMV files.</span>
                        </label>
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" onclick="newActivityModal.close()">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                        </svg>
                        Add Activity
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    


    <script>
    // Auto-hide toast notifications after 5 seconds
    setTimeout(function() {
        const toasts = document.querySelectorAll('.toast');
        toasts.forEach(toast => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease-out';
            setTimeout(() => toast.remove(), 500);
        });
    }, 5000);

    // Filter members function
    function filterMembers() {
        const searchInput = document.getElementById('memberSearch').value.toLowerCase();
        const rows = document.querySelectorAll('.member-row');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchInput) ? '' : 'none';
        });
    }

    // Export members to CSV function - FIXED VERSION FOR NEW TABLE STRUCTURE
function exportMembersToCSV() {
    console.log('Export function called'); // Debug log
    
    // Get all member rows
    const rows = document.querySelectorAll('#membersTableBody .member-row');
    console.log('Found rows:', rows.length); // Debug log
    
    if (rows.length === 0) {
        alert('No members to export!');
        return;
    }
    
    // CSV Headers - Updated to match new table structure
    let csvContent = 'No,Name,No ID,Email,Joined Date\n';
    
    // Add data rows
    let rowCount = 0;
    rows.forEach((row) => {
        // Skip hidden rows from search filter
        if (row.style.display === 'none') {
            return;
        }
        
        rowCount++;
        const cells = row.querySelectorAll('td');
        
        // Extract text content from each cell based on NEW table structure:
        // Column 0: # (number)
        // Column 1: Name (studName)
        // Column 2: No ID (studNoID)
        // Column 3: Email
        // Column 4: Joined Date
        
        const no = cells[0].textContent.trim();
        
        // For name, get only the text content, not the avatar
        const name = cells[1].textContent.trim();
        
        const studNoID = cells[2].textContent.trim();
        const email = cells[3].textContent.trim();
        const joinedDate = cells[4].textContent.trim();
        
        // Escape fields that contain commas or quotes
        const escapedName = name.includes(',') ? `"${name}"` : name;
        const escapedEmail = email.includes(',') ? `"${email}"` : email;
        const escapedNoID = studNoID.includes(',') ? `"${studNoID}"` : studNoID;
        
        csvContent += `${no},${escapedName},${escapedNoID},${escapedEmail},${joinedDate}\n`;
    });
    
    console.log('Rows processed:', rowCount); // Debug log
    console.log('CSV Content:', csvContent); // Debug log
    
    if (rowCount === 0) {
        alert('No visible members to export! Try clearing your search filter.');
        return;
    }
    
    try {
        // Create blob and download
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        
        // Create download link
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        // Set filename
        const clubName = '<?php echo preg_replace("/[^a-zA-Z0-9]+/", "_", $club["clubName"]); ?>';
        const today = new Date();
        const dateStr = today.getFullYear() + '-' + 
                      String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                      String(today.getDate()).padStart(2, '0');
        const filename = clubName + '_members_' + dateStr + '.csv';
        
        link.href = url;
        link.download = filename;
        
        // Append to body, click, and remove
        document.body.appendChild(link);
        link.click();
        
        // Clean up
        setTimeout(() => {
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }, 100);
        
        console.log('Download triggered'); // Debug log
        
        // Show success notification
        const toast = document.createElement('div');
        toast.className = 'toast toast-top toast-end z-50';
        toast.innerHTML = `
            <div class="alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Members list exported successfully as ${filename}</span>
            </div>
        `;
        document.body.appendChild(toast);
        
        // Remove toast after 3 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease-out';
            setTimeout(() => toast.remove(), 500);
        }, 3000);
        
    } catch (error) {
        console.error('Export error:', error);
        alert('Error exporting CSV: ' + error.message);
    }
}
</script>
</body>
</html>
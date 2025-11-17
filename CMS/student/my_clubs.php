<?php
session_start();
require_once "../config/connect.php";

// Check if student is logged in
if (!isset($_SESSION['stud_id'])) {
    header("Location: ../user_access.php");
    exit();
}

$student_id = $_SESSION['stud_id'];

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get last notification check
$last_check = $_SESSION['last_notification_check'] ?? date('Y-m-d H:i:s', strtotime('-30 days'));

// Build query based on filters
$query = "SELECT c.*, l.lectName, m.joined_at,
          (SELECT COUNT(*) FROM membership WHERE clubID = c.clubID) as member_count,
          (SELECT COUNT(*) FROM casannouncement WHERE clubID = c.clubID AND annPosted_at > ?) as new_announcements,
          (SELECT COUNT(*) FROM casevents WHERE clubID = c.clubID AND evDate >= CURDATE()) as upcoming_events
          FROM membership m
          JOIN clubsocieties c ON m.clubID = c.clubID
          LEFT JOIN lecturer l ON c.lectID = l.lectID
          WHERE m.studID = ?";

$params = [$last_check, $student_id];

// Add search filter
if (!empty($search)) {
    $query .= " AND (c.clubName LIKE ? OR c.clubDescription LIKE ? OR l.lectName LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY m.joined_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$all_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter clubs based on filter type
$clubs = [];
foreach ($all_clubs as $club) {
    if ($filter === 'recent' && strtotime($club['joined_at']) > strtotime('-7 days')) {
        $clubs[] = $club;
    } elseif ($filter === 'active' && ($club['new_announcements'] > 0 || $club['upcoming_events'] > 0)) {
        $clubs[] = $club;
    } elseif ($filter === 'events' && $club['upcoming_events'] > 0) {
        $clubs[] = $club;
    } elseif ($filter === 'all') {
        $clubs[] = $club;
    }
}

// Get statistics
$total_clubs = count($all_clubs);
$total_events = array_sum(array_column($all_clubs, 'upcoming_events'));
$total_announcements = array_sum(array_column($all_clubs, 'new_announcements'));
$recent_clubs = count(array_filter($all_clubs, function($club) {
    return strtotime($club['joined_at']) > strtotime('-7 days');
}));
$active_clubs = count(array_filter($all_clubs, function($club) {
    return $club['new_announcements'] > 0 || $club['upcoming_events'] > 0;
}));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Clubs - Student Club Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .club-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .club-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #059669);
        }
        
        .club-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .club-avatar {
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
            position: relative;
        }
        
        .club-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
        }
        
        .search-container {
            position: relative;
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        .search-input {
            padding-left: 2.75rem;
        }
        
        .empty-state {
            min-height: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="drawer">
        <input id="my-drawer-3" type="checkbox" class="drawer-toggle" /> 
        <div class="drawer-content flex flex-col">
            <!-- Include Navbar -->
            <?php include "includes/navbar.php"; ?>

            <!-- Main Content -->
            <main class="min-h-screen pt-16 bg-gradient-to-br from-gray-50 to-gray-100">
                <!-- Header Section -->
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 text-white py-12">
                    <div class="container mx-auto px-4">
                        <div class="max-w-4xl mx-auto text-center">
                            <h1 class="text-5xl font-bold mb-4">My Clubs</h1>
                            <p class="text-xl opacity-90">Manage and explore your club memberships</p>
                        </div>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="container mx-auto px-4 -mt-8 mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 max-w-6xl mx-auto">
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $total_clubs; ?></div>
                            <div class="text-sm opacity-90">Total Clubs</div>
                        </div>
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $total_events; ?></div>
                            <div class="text-sm opacity-90">Upcoming Events</div>
                        </div>
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $total_announcements; ?></div>
                            <div class="text-sm opacity-90">New Announcements</div>
                        </div>
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $active_clubs; ?></div>
                            <div class="text-sm opacity-90">Active Clubs</div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="container mx-auto px-4 mb-8">
                    <div class="bg-white rounded-xl shadow-lg p-6 max-w-6xl mx-auto">
                        <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                            <!-- Filter Buttons -->
                            <div class="flex gap-2 flex-wrap">
                                <a href="?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="filter-btn btn btn-sm <?php echo $filter === 'all' ? 'active' : 'btn-outline'; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" />
                                    </svg>
                                    All (<?php echo $total_clubs; ?>)
                                </a>
                                <a href="?filter=active<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="filter-btn btn btn-sm <?php echo $filter === 'active' ? 'active' : 'btn-outline'; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                                    </svg>
                                    Active (<?php echo $active_clubs; ?>)
                                </a>
                                <a href="?filter=recent<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="filter-btn btn btn-sm <?php echo $filter === 'recent' ? 'active' : 'btn-outline'; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                    </svg>
                                    Recent (<?php echo $recent_clubs; ?>)
                                </a>
                            </div>

                            <!-- Search Bar -->
                            <form method="GET" class="search-container flex-1 max-w-md">
                                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 search-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                                <input 
                                    type="text" 
                                    name="search" 
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Search your clubs..." 
                                    class="input input-bordered w-full search-input"
                                >
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Clubs Grid -->
                <div class="container mx-auto px-4 pb-16">
                    <?php if (!empty($clubs)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
                            <?php foreach ($clubs as $club): ?>
                            <div class="card bg-white shadow-xl club-card">
                                <div class="card-body">

                                    <!-- Club Avatar -->
                                    <div class="club-avatar">
                                        <div class="w-full h-full rounded-full ring ring-green-500 ring-offset-base-100 ring-offset-2">
                                            <img src="../<?php echo htmlspecialchars($club['clubLogo']); ?>" 
                                                alt="<?php echo htmlspecialchars($club['clubName']); ?>"
                                                onerror="this.src='../assets/images/default-club.png'">
                                        </div>
                                    </div>

                                    <!-- Club Name -->
                                    <h3 class="card-title justify-center text-xl mb-2">
                                        <?php echo htmlspecialchars($club['clubName']); ?>
                                    </h3>

                                    <!-- Description -->
                                    <p class="text-sm text-gray-600 text-center line-clamp-2 mb-3">
                                        <?php echo htmlspecialchars($club['clubDescription']); ?>
                                    </p>

                                    <!-- Stats Grid -->
                                    <div class="grid grid-cols-1 gap-3 mb-4">
                                        <div class="bg-green-50 rounded-lg p-3 text-center">
                                            <div class="flex items-center justify-center gap-2 text-green-600 mb-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                                </svg>
                                                <span class="text-xs font-semibold">Members</span>
                                            </div>
                                            <div class="text-lg font-bold text-gray-800">
                                                <?php echo $club['member_count']; ?>/<?php echo $club['clubCapacity']; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Joined Date -->
                                    <div class="text-xs text-gray-500 text-center mb-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                        </svg>
                                        Joined <?php echo date('M d, Y', strtotime($club['joined_at'])); ?>
                                    </div>

                                    <!-- Advisor -->
                                    <?php if (!empty($club['lectName'])): ?>
                                    <div class="text-xs text-gray-500 text-center mb-4 bg-gray-50 rounded-lg p-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z" />
                                        </svg>
                                        Advisor: <?php echo htmlspecialchars($club['lectName']); ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Actions -->
                                    <div class="card-actions justify-center">
                                        <a href="club_profile.php?id=<?php echo $club['clubID']; ?>" 
                                           class="btn btn-sm bg-gradient-to-r from-green-600 to-emerald-600 text-white border-none hover:shadow-lg">
                                            View Details
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="text-center max-w-md mx-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto mb-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <h3 class="text-2xl font-bold text-gray-700 mb-2">No Clubs Found</h3>
                                <p class="text-gray-500 mb-6">
                                    <?php if (!empty($search)): ?>
                                        No clubs match your search "<?php echo htmlspecialchars($search); ?>".
                                    <?php elseif ($filter === 'active'): ?>
                                        No clubs have recent activity.
                                    <?php elseif ($filter === 'events'): ?>
                                        No clubs have upcoming events.
                                    <?php elseif ($filter === 'recent'): ?>
                                        No clubs joined in the last 7 days.
                                    <?php else: ?>
                                        You haven't joined any clubs yet.
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($search) || $filter !== 'all'): ?>
                                    <a href="my_clubs.php" class="btn bg-gradient-to-r from-green-600 to-emerald-600 text-white border-none mb-4">
                                        Clear Filters
                                    </a>
                                <?php endif; ?>
                                <?php if ($total_clubs === 0): ?>
                                    <a href="available_clubs.php" class="btn bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none">
                                        Discover Clubs
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Button -->
                <?php if (!empty($clubs)): ?>
                <div class="text-center pb-16">
                    <a href="available_clubs.php" class="btn btn-lg bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z" />
                        </svg>
                        Discover More Clubs
                    </a>
                </div>
                <?php endif; ?>
            </main>

            <!-- Footer -->
            <?php include "includes/footer.php"; ?>
        </div> 

        <!-- Include Mobile Drawer -->
        <?php include "includes/mobile_drawer.php"; ?>
    </div>
</body>
</html>
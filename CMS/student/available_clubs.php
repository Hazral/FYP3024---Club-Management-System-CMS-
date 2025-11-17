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

// Get clubs that the current user has joined
$query = "SELECT clubID FROM membership WHERE studID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$joined_club_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build query - EXCLUDE joined clubs from all results
$query = "SELECT c.*, l.lectName,
          (SELECT COUNT(*) FROM membership WHERE clubID = c.clubID) as current_members
          FROM clubsocieties c
          LEFT JOIN lecturer l ON c.lectID = l.lectID
          WHERE c.clubID NOT IN (SELECT clubID FROM membership WHERE studID = ?)";

$params = [$student_id];

// Add search filter
if (!empty($search)) {
    $query .= " AND (c.clubName LIKE ? OR c.clubDescription LIKE ? OR l.lectName LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY c.clubName ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$all_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter clubs by availability after fetching
$clubs = [];
foreach ($all_clubs as $club) {
    $percentage = ($club['clubCapacity'] > 0) ? round(($club['current_members'] / $club['clubCapacity']) * 100) : 0;
    $is_full = $club['current_members'] >= $club['clubCapacity'];
    
    if ($filter === 'available' && !$is_full) {
        $clubs[] = $club;
    } elseif ($filter === 'open' && $percentage < 90) {
        $clubs[] = $club;
    } elseif ($filter === 'all') {
        $clubs[] = $club;
    }
}

// Get statistics - updated to reflect only non-joined clubs
$stats_query = "SELECT 
    COUNT(*) as total_clubs,
    COUNT(CASE WHEN (SELECT COUNT(*) FROM membership WHERE clubID = c.clubID) < c.clubCapacity THEN 1 END) as available_clubs
    FROM clubsocieties c
    WHERE c.clubID NOT IN (SELECT clubID FROM membership WHERE studID = ?)";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$student_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get joined clubs count separately
$joined_query = "SELECT COUNT(*) as joined_clubs FROM membership WHERE studID = ?";
$joined_stmt = $conn->prepare($joined_query);
$joined_stmt->execute([$student_id]);
$joined_stats = $joined_stmt->fetch(PDO::FETCH_ASSOC);
$stats['joined_clubs'] = $joined_stats['joined_clubs'];

// Calculate percentage filled for clubs
function getPercentageFilled($current, $capacity) {
    if ($capacity == 0) return 0;
    return round(($current / $capacity) * 100);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Clubs - Student Club Management System</title>
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
            background: linear-gradient(90deg, #667eea, #764ba2);
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
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                <div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white py-12">
                    <div class="container mx-auto px-4">
                        <div class="max-w-4xl mx-auto text-center">
                            <h1 class="text-5xl font-bold mb-4">Explore Clubs</h1>
                            <p class="text-xl opacity-90">Join clubs that match your interests and expand your university experience</p>
                        </div>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="container mx-auto px-4 -mt-8 mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $stats['total_clubs']; ?></div>
                            <div class="text-sm opacity-90">Available Clubs</div>
                        </div>
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $stats['joined_clubs']; ?></div>
                            <div class="text-sm opacity-90">Clubs Joined</div>
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
                                    All (<?php echo $stats['total_clubs']; ?>)
                                </a>
                                <a href="?filter=available<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="filter-btn btn btn-sm <?php echo $filter === 'available' && 'open' ? 'active' : 'btn-outline'; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                                    </svg>
                                    Available (<?php echo $stats['available_clubs']; ?>)
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
                                    placeholder="Search clubs..." 
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
                            <?php foreach ($clubs as $club): 
                                $percentage = getPercentageFilled($club['current_members'], $club['clubCapacity']);
                                $isFull = $club['current_members'] >= $club['clubCapacity'];
                            ?>
                            <div class="card bg-white shadow-xl club-card">
                                <div class="card-body">
                                    <!-- Status Badge -->
                                    <?php if ($isFull): ?>
                                        <div class="absolute top-4 right-4">
                                            <div class="badge badge-error text-white border-none">Full</div>
                                        </div>
                                    <?php elseif ($percentage >= 90): ?>
                                        <div class="absolute top-4 right-4">
                                            <div class="badge badge-warning text-white border-none gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                                Almost Full
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Club Avatar -->
                                    <div class="club-avatar">
                                        <div class="w-full h-full rounded-full ring ring-purple-500 ring-offset-base-100 ring-offset-2">
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

                                    <!-- Capacity -->
                                    <div class="mb-4">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-gray-600">Capacity</span>
                                            <span class="font-semibold">
                                                <?php echo $club['current_members']; ?> / <?php echo $club['clubCapacity']; ?>
                                            </span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="h-2 rounded-full transition-all duration-500 <?php 
                                                if ($percentage >= 90) echo 'bg-red-500';
                                                elseif ($percentage >= 70) echo 'bg-orange-500';
                                                elseif ($percentage >= 50) echo 'bg-yellow-500';
                                                else echo 'bg-green-500';
                                            ?>" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Advisor -->
                                    <?php if (!empty($club['lectName'])): ?>
                                    <div class="text-xs text-gray-500 text-center mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z" />
                                        </svg>
                                        Advisor: <?php echo htmlspecialchars($club['lectName']); ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Actions -->
                                    <div class="card-actions justify-center">
                                        <a href="club_profile.php?id=<?php echo $club['clubID']; ?>" 
                                           class="btn btn-sm bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-lg">
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
                                    <?php elseif ($filter === 'available'): ?>
                                        No clubs available to join at the moment.
                                    <?php else: ?>
                                        There are no clubs available at the moment.
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($search) || $filter !== 'all'): ?>
                                    <a href="available_clubs.php" class="btn bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none">
                                        Clear Filters
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>

            <!-- Footer -->
            <?php include "includes/footer.php"; ?>
        </div> 

        <!-- Include Mobile Drawer -->
        <?php include "includes/mobile_drawer.php"; ?>
    </div>
</body>
</html>
<?php
session_start();
require_once "../config/connect.php";

// Check if user is logged in
if (!isset($_SESSION['stud_id'])) {
    header("Location: ../user_access.php");
    exit();
}

$stud_id = $_SESSION['stud_id'];

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on filters
$query = "SELECT DISTINCT a.*, c.clubName, c.clubID
          FROM casannouncement a 
          LEFT JOIN clubsocieties c ON a.clubID = c.clubID
          WHERE (
              a.annType = 'Public' 
              OR (
                  a.annType = 'Private' 
                  AND a.clubID IN (
                      SELECT clubID FROM membership WHERE studID = ?
                  )
              )
          )";

$params = [$stud_id];

// Add search filter
if (!empty($search)) {
    $query .= " AND (a.anntitle LIKE ? OR a.content LIKE ? OR c.clubName LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add type filter
if ($filter === 'public') {
    $query .= " AND a.annType = 'Public'";
} elseif ($filter === 'private') {
    $query .= " AND a.annType = 'Private'";
}

$query .= " ORDER BY a.annPosted_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(CASE WHEN annType = 'Public' THEN 1 END) as public_count,
    COUNT(CASE WHEN annType = 'Private' AND clubID IN (SELECT clubID FROM membership WHERE studID = ?) THEN 1 END) as private_count
    FROM casannouncement
    WHERE annType = 'Public' 
       OR (annType = 'Private' AND clubID IN (SELECT clubID FROM membership WHERE studID = ?))";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$stud_id, $stud_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
$total_count = $stats['public_count'] + $stats['private_count'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Announcements - Student Club Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .announcement-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .announcement-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .announcement-card.private::before {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }
        
        .announcement-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .card-image-container {
            position: relative;
            overflow: hidden;
            height: 220px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .announcement-card:hover .card-image {
            transform: scale(1.1);
        }
        
        .new-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            animation: bounce-subtle 2s infinite;
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
            z-index: 10;
        }
        
        .private-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        @keyframes bounce-subtle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
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
                            <h1 class="text-5xl font-bold mb-4">All Announcements</h1>
                            <p class="text-xl opacity-90">Stay updated with the latest news from your clubs and campus</p>
                        </div>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="container mx-auto px-4 -mt-8 mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $total_count; ?></div>
                            <div class="text-sm opacity-90">Total Announcements</div>
                        </div>
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $stats['public_count']; ?></div>
                            <div class="text-sm opacity-90">Public Announcements</div>
                        </div>
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $stats['private_count']; ?></div>
                            <div class="text-sm opacity-90">Private Announcements</div>
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
                                    All (<?php echo $total_count; ?>)
                                </a>
                                <a href="?filter=public<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="filter-btn btn btn-sm <?php echo $filter === 'public' ? 'active' : 'btn-outline'; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 011.523-1.943A5.977 5.977 0 0116 10c0 .34-.028.675-.083 1H15a2 2 0 00-2 2v2.197A5.973 5.973 0 0110 16v-2a2 2 0 00-2-2 2 2 0 01-2-2 2 2 0 00-1.668-1.973z" clip-rule="evenodd" />
                                    </svg>
                                    Public (<?php echo $stats['public_count']; ?>)
                                </a>
                                <a href="?filter=private<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="filter-btn btn btn-sm <?php echo $filter === 'private' ? 'active' : 'btn-outline'; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                    Private (<?php echo $stats['private_count']; ?>)
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
                                    placeholder="Search announcements..." 
                                    class="input input-bordered w-full search-input"
                                >
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Announcements Grid -->
                <div class="container mx-auto px-4 pb-16">
                    <?php if (!empty($announcements)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
                            <?php foreach ($announcements as $announcement): 
                                $isNew = (strtotime($announcement['annPosted_at']) > strtotime('-3 days'));
                                $isPrivate = $announcement['annType'] === 'Private';
                            ?>
                            <div class="card bg-white shadow-xl announcement-card <?php echo $isPrivate ? 'private' : ''; ?>">
                                <div class="card-image-container">
                                    <?php if ($isNew): ?>
                                        <div class="new-badge">NEW</div>
                                    <?php endif; ?>
                                    
                                    <?php if ($isPrivate): ?>
                                        <div class="private-badge">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                            </svg>
                                            Private
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($announcement['annImg'])): ?>
                                        <img src="../<?php echo htmlspecialchars($announcement['annImg']); ?>" 
                                             alt="<?php echo htmlspecialchars($announcement['anntitle']); ?>"
                                             class="card-image"
                                             onerror="this.src='../assets/images/default-announcement.jpg'">
                                    <?php else: ?>
                                        <div class="card-image flex items-center justify-center bg-gradient-to-br from-purple-400 to-blue-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-white opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <div class="flex items-start justify-between mb-3">
                                        <a href="club_detail.php?id=<?php echo $announcement['clubID']; ?>" 
                                           class="badge bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-lg text-xs">
                                            <?php echo htmlspecialchars($announcement['clubName'] ?? 'General'); ?>
                                        </a>
                                        <span class="text-xs text-gray-500">
                                            <?php 
                                            $date = new DateTime($announcement['annPosted_at']);
                                            $now = new DateTime();
                                            $diff = $now->diff($date);
                                            
                                            if ($diff->days == 0) {
                                                echo "Today";
                                            } elseif ($diff->days == 1) {
                                                echo "Yesterday";
                                            } else {
                                                echo $diff->days . " days ago";
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <h3 class="card-title text-xl mb-2 line-clamp-2">
                                        <?php echo htmlspecialchars($announcement['anntitle']); ?>
                                    </h3>
                                    
                                    <p class="text-gray-600 line-clamp-3 mb-4">
                                        <?php echo htmlspecialchars($announcement['content']); ?>
                                    </p>
                                    
                                    <div class="card-actions justify-between items-center mt-auto">
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('M d, Y • g:i A', strtotime($announcement['annPosted_at'])); ?>
                                        </div>
                                        <a href="announcement_post.php?id=<?php echo $announcement['annID']; ?>" 
                                           class="btn btn-sm bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-lg">
                                            Read More
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <h3 class="text-2xl font-bold text-gray-700 mb-2">No Announcements Found</h3>
                                <p class="text-gray-500 mb-6">
                                    <?php if (!empty($search)): ?>
                                        No announcements match your search "<?php echo htmlspecialchars($search); ?>".
                                    <?php else: ?>
                                        There are no announcements available at the moment.
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($search)): ?>
                                    <a href="all_announcement.php" class="btn bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none">
                                        Clear Search Filter
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
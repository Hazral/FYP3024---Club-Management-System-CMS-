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
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'upcoming';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query based on filters
$query = "SELECT DISTINCT e.*, c.clubName, c.clubID
          FROM casevents e 
          LEFT JOIN clubsocieties c ON e.clubID = c.clubID
          WHERE (
              e.evType = 'Public' 
              OR (
                  e.evType = 'Private' 
                  AND e.clubID IN (
                      SELECT clubID FROM membership WHERE studID = ?
                  )
              )
          )";

$params = [$stud_id];

// Add time filter with current date AND time
if ($time_filter === 'upcoming') {
    $query .= " AND CONCAT(e.evDate, ' ', e.evTime) >= NOW()";
} elseif ($time_filter === 'past') {
    $query .= " AND CONCAT(e.evDate, ' ', e.evTime) < NOW()";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (e.evTitle LIKE ? OR e.evDescription LIKE ? OR e.evLocation LIKE ? OR c.clubName LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add type filter
if ($filter === 'public') {
    $query .= " AND e.evType = 'Public'";
} elseif ($filter === 'private') {
    $query .= " AND e.evType = 'Private'";
}

$query .= " ORDER BY e.evDate DESC, e.evTime DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(CASE WHEN evType = 'Public' AND evDate >= CURDATE() THEN 1 END) as public_count,
    COUNT(CASE WHEN evType = 'Private' AND evDate >= CURDATE() AND clubID IN (SELECT clubID FROM membership WHERE studID = ?) THEN 1 END) as private_count,
    COUNT(CASE WHEN evDate >= CURDATE() AND (evType = 'Public' OR (evType = 'Private' AND clubID IN (SELECT clubID FROM membership WHERE studID = ?))) THEN 1 END) as upcoming_count,
    COUNT(CASE WHEN evDate < CURDATE() AND (evType = 'Public' OR (evType = 'Private' AND clubID IN (SELECT clubID FROM membership WHERE studID = ?))) THEN 1 END) as past_count
    FROM casevents";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$stud_id, $stud_id, $stud_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
$total_upcoming = $stats['upcoming_count'];
$total_past = $stats['past_count'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Events - Student Club Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .event-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #f093fb, #f5576c);
        }
        
        .event-card.private::before {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }
        
        .event-card.past::before {
            background: linear-gradient(90deg, #6b7280, #4b5563);
        }
        
        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .card-image-container {
            position: relative;
            overflow: hidden;
            height: 220px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .event-card.past .card-image-container {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            filter: grayscale(0.5);
        }
        
        .card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .event-card:hover .card-image {
            transform: scale(1.1);
        }
        
        .event-card.past .card-image {
            filter: grayscale(0.5);
        }
        
        .soon-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            animation: bounce-subtle 2s infinite;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            z-index: 10;
        }
        
        .private-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
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
        
        .past-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.4);
            z-index: 10;
        }
        
        .date-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: white;
            border-radius: 0.5rem;
            padding: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            text-align: center;
            min-width: 60px;
            z-index: 10;
        }
        
        .date-badge-day {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            color: #f093fb;
        }
        
        .event-card.past .date-badge-day {
            color: #6b7280;
        }
        
        .date-badge-month {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #f5576c;
        }
        
        .event-card.past .date-badge-month {
            color: #4b5563;
        }
        
        @keyframes bounce-subtle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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

        .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: rgba(245, 87, 108, 0.1);
            color: #f5576c;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .event-card.past .time-badge {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
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
                <div class="bg-gradient-to-r from-pink-600 to-red-600 text-white py-12">
                    <div class="container mx-auto px-4">
                        <div class="max-w-4xl mx-auto text-center">
                            <h1 class="text-5xl font-bold mb-4">All Events</h1>
                            <p class="text-xl opacity-90">Discover and join exciting events from your clubs and campus</p>
                        </div>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="container mx-auto px-4 -mt-8 mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 max-w-5xl mx-auto">
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $total_upcoming; ?></div>
                            <div class="text-sm opacity-90">Upcoming Events</div>
                        </div>
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $stats['public_count']; ?></div>
                            <div class="text-sm opacity-90">Public Events</div>
                        </div>
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $stats['private_count']; ?></div>
                            <div class="text-sm opacity-90">Private Events</div>
                        </div>
                        <div class="stats-card rounded-xl shadow-xl p-6 text-white text-center">
                            <div class="text-4xl font-bold mb-2"><?php echo $total_past; ?></div>
                            <div class="text-sm opacity-90">Past Events</div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="container mx-auto px-4 mb-8">
                    <div class="bg-white rounded-xl shadow-lg p-6 max-w-6xl mx-auto">
                        <div class="flex flex-col gap-4">
                            <!-- Top Row: Type Filters -->
                            <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                                <div class="flex gap-2 flex-wrap">
                                    <a href="?filter=all&time=<?php echo $time_filter; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="filter-btn btn btn-sm <?php echo $filter === 'all' ? 'active' : 'btn-outline'; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" />
                                        </svg>
                                        All Types
                                    </a>
                                    <a href="?filter=public&time=<?php echo $time_filter; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="filter-btn btn btn-sm <?php echo $filter === 'public' ? 'active' : 'btn-outline'; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 011.523-1.943A5.977 5.977 0 0116 10c0 .34-.028.675-.083 1H15a2 2 0 00-2 2v2.197A5.973 5.973 0 0110 16v-2a2 2 0 00-2-2 2 2 0 01-2-2 2 2 0 00-1.668-1.973z" clip-rule="evenodd" />
                                        </svg>
                                        Public
                                    </a>
                                    <a href="?filter=private&time=<?php echo $time_filter; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="filter-btn btn btn-sm <?php echo $filter === 'private' ? 'active' : 'btn-outline'; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                        Private
                                    </a>
                                </div>

                                <!-- Time Filters -->
                                <div class="flex gap-2 flex-wrap">
                                    <a href="?filter=<?php echo $filter; ?>&time=upcoming<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="filter-btn btn btn-sm <?php echo $time_filter === 'upcoming' ? 'active' : 'btn-outline'; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                        </svg>
                                        Upcoming (<?php echo $total_upcoming; ?>)
                                    </a>
                                    <a href="?filter=<?php echo $filter; ?>&time=past<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="filter-btn btn btn-sm <?php echo $time_filter === 'past' ? 'active' : 'btn-outline'; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.707-10.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L9.414 11H13a1 1 0 100-2H9.414l1.293-1.293z" clip-rule="evenodd" />
                                        </svg>
                                        Past (<?php echo $total_past; ?>)
                                    </a>
                                    <a href="?filter=<?php echo $filter; ?>&time=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="filter-btn btn btn-sm <?php echo $time_filter === 'all' ? 'active' : 'btn-outline'; ?>">
                                        All Time
                                    </a>
                                </div>
                            </div>

                            <!-- Search Bar -->
                            <form method="GET" class="search-container w-full">
                                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                                <input type="hidden" name="time" value="<?php echo htmlspecialchars($time_filter); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 search-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                                <input 
                                    type="text" 
                                    name="search" 
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Search events by title, description, location or club..." 
                                    class="input input-bordered w-full search-input"
                                >
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Events Grid -->
                <div class="container mx-auto px-4 pb-16">
                    <?php if (!empty($events)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
                            <?php foreach ($events as $event): 
                                $eventDate = new DateTime($event['evDate']);
                                $today = new DateTime();
                                $isPast = $eventDate < $today;
                                $isUpcoming = !$isPast && (strtotime($event['evDate']) <= strtotime('+7 days'));
                                $isPrivate = $event['evType'] === 'Private';
                            ?>
                            <div class="card bg-white shadow-xl event-card <?php echo $isPrivate ? 'private' : ''; ?> <?php echo $isPast ? 'past' : ''; ?>">
                                <div class="card-image-container">
                                    <?php if ($isPast): ?>
                                        <div class="past-badge">PAST</div>
                                    <?php elseif ($isUpcoming): ?>
                                        <div class="soon-badge">SOON</div>
                                    <?php endif; ?>
                                    
                                    <?php if ($isPrivate && !$isPast): ?>
                                        <div class="private-badge" style="left: auto; right: 5.5rem;">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                            </svg>
                                            Private
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="date-badge">
                                        <div class="date-badge-day"><?php echo $eventDate->format('d'); ?></div>
                                        <div class="date-badge-month"><?php echo $eventDate->format('M'); ?></div>
                                    </div>
                                    
                                    <?php if (!empty($event['evImg'])): ?>
                                        <img src="../<?php echo htmlspecialchars($event['evImg']); ?>" 
                                             alt="<?php echo htmlspecialchars($event['evTitle']); ?>"
                                             class="card-image"
                                             onerror="this.src='../assets/images/default-event.jpg'">
                                    <?php else: ?>
                                        <div class="card-image flex items-center justify-center bg-gradient-to-br from-pink-400 to-red-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-white opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <div class="flex items-start justify-between mb-3">
                                        <a href="club_detail.php?id=<?php echo $event['clubID']; ?>" 
                                           class="badge bg-gradient-to-r from-pink-600 to-red-600 text-white border-none hover:shadow-lg text-xs">
                                            <?php echo htmlspecialchars($event['clubName'] ?? 'General'); ?>
                                        </a>
                                        <div class="time-badge">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                            </svg>
                                            <?php echo date('g:i A', strtotime($event['evTime'])); ?>
                                        </div>
                                    </div>
                                    
                                    <h3 class="card-title text-xl mb-2 line-clamp-2">
                                        <?php echo htmlspecialchars($event['evTitle']); ?>
                                    </h3>
                                    
                                    <p class="text-gray-600 text-sm line-clamp-2 mb-3">
                                        <?php echo htmlspecialchars($event['evDescription']); ?>
                                    </p>
                                    
                                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="line-clamp-1"><?php echo htmlspecialchars($event['evLocation']); ?></span>
                                    </div>
                                    
                                    <div class="card-actions justify-between items-center mt-auto">
                                        <div class="text-xs text-gray-500">
                                            <?php echo $eventDate->format('M d, Y'); ?>
                                        </div>
                                        <a href="event_post.php?id=<?php echo $event['eventID']; ?>" 
                                           class="btn btn-sm bg-gradient-to-r from-pink-600 to-red-600 text-white border-none hover:shadow-lg">
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <h3 class="text-2xl font-bold text-gray-700 mb-2">No Events Found</h3>
                                <p class="text-gray-500 mb-6">
                                    <?php if (!empty($search)): ?>
                                        No events match your search "<?php echo htmlspecialchars($search); ?>".
                                    <?php elseif ($time_filter === 'past'): ?>
                                        There are no past events to display.
                                    <?php elseif ($time_filter === 'upcoming'): ?>
                                        There are no upcoming events at the moment. Check back later!
                                    <?php else: ?>
                                        There are no events available at the moment.
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($search)): ?>
                                    <a href="all_events.php" class="btn bg-gradient-to-r from-pink-600 to-red-600 text-white border-none">
                                        Clear Search Filter
                                    </a>
                                <?php elseif ($time_filter === 'past'): ?>
                                    <a href="?time=upcoming" class="btn bg-gradient-to-r from-pink-600 to-red-600 text-white border-none">
                                        View Upcoming Events
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
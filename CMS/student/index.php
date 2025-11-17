<?php
session_start();
require_once "../config/connect.php";

// Get latest PUBLIC announcements (5)
$query = "SELECT a.*, c.clubName, c.clubID
          FROM casannouncement a 
          LEFT JOIN clubsocieties c ON a.clubID = c.clubID
          WHERE a.annType = 'Public'
          ORDER BY a.annPosted_at DESC 
          LIMIT 4";
$stmt = $conn->prepare($query);
$stmt->execute();
$public_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get latest PUBLIC events (5)
$query = "SELECT e.*, c.clubName, c.clubID
          FROM casevents e 
          LEFT JOIN clubsocieties c ON e.clubID = c.clubID
          WHERE e.evType = 'Public'
          ORDER BY e.evDate DESC, e.evPosted_at DESC
          LIMIT 4";
$stmt = $conn->prepare($query);
$stmt->execute();
$public_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured clubs (8 random)
$query = "SELECT c.*, l.lectName,
          (SELECT COUNT(*) FROM membership WHERE clubID = c.clubID) as member_count
          FROM clubsocieties c
          LEFT JOIN lecturer l ON c.lectID = l.lectID
          ORDER BY RAND()
          LIMIT 4";
$stmt = $conn->prepare($query);
$stmt->execute();
$featured_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for logged-in students
$stats = [
    'total_clubs' => 0,
    'my_clubs' => 0,
    'upcoming_events' => 0,
    'new_announcements' => 0
];

if (isset($_SESSION['stud_id'])) {
    $stud_id = $_SESSION['stud_id'];
    
    // Total clubs
    $stmt = $conn->query("SELECT COUNT(*) as total FROM clubsocieties");
    $stats['total_clubs'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // My clubs
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM membership WHERE studID = ?");
    $stmt->execute([$stud_id]);
    $stats['my_clubs'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Upcoming events (next 30 days)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM casevents WHERE evDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND evType = 'Public'");
    $stats['upcoming_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // New announcements (last 7 days)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM casannouncement WHERE annPosted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND annType = 'Public'");
    $stats['new_announcements'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Club Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="index.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .hero-bg {
            background-image: 
                linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                url('../assets/hero-bg.jpg');
            background-size: cover;
            background-position: center;

        }
        
        .scroll-container {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: thin;
            scrollbar-color: #667eea #f1f1f1;
            gap: 1.5rem;
            padding: 1rem 0;
            -webkit-overflow-scrolling: touch;
        }
        
        .scroll-container::-webkit-scrollbar {
            height: 8px;
        }
        
        .scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .scroll-container::-webkit-scrollbar-thumb {
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
        }
        
        .scroll-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(90deg, #764ba2, #667eea);
        }
        
        /* Announcement Card */
        .announcement-card {
            min-width: 380px;
            max-width: 380px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }
        
        .announcement-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }
        
        .announcement-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.25);
        }
        
        .announcement-card:hover .card-image {
            transform: scale(1.1);
        }

        .event-bg {
            background-image: 
                linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                url('../assets/event-bg.png');
            background-size: cover;
            background-position: center;

        }
        
        .event-card {
            min-width: 380px;
            max-width: 380px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background-color: white;
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }
        
        .event-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 20px 40px rgba(245, 87, 108, 0.25);
        }
        
        .event-card:hover .card-image {
            transform: scale(1.1);
        }
        
        @keyframes shimmer {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .card-image-container {
            position: relative;
            overflow: hidden;
            height: 220px;
            background-color: whitesmoke;
        }
        
        .event-card .card-image-container {
            background-color: whitesmoke ;
        }
        
        .card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .card-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            padding: 1rem;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .announcement-card:hover .card-overlay,
        .event-card:hover .card-overlay {
            transform: translateY(0);
        }
        
        .scroll-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            background: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            opacity: 0;
            pointer-events: none;
        }
        
        .scroll-wrapper:hover .scroll-btn {
            opacity: 1;
            pointer-events: all;
        }
        
        .scroll-btn:hover {
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }
        
        .scroll-btn.left {
            left: -24px;
        }
        
        .scroll-btn.right {
            right: -24px;
        }
        
        .scroll-btn svg {
            width: 24px;
            height: 24px;
            color: #667eea;
        }
        
        .scroll-wrapper {
            position: relative;
            padding: 0 2rem;
        }
        

        .badge-pulse {
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(102, 126, 234, 0);
            }
        }
        
        .club-card:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }
        
        .empty-state {
            min-width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
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
        }
        
        .date-badge-day {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            color: #667eea;
        }
        
        .date-badge-month {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #764ba2;
        }

        .new-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background-color: lightseagreen;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            animation: bounce-subtle 2s infinite;
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
        }

        @keyframes bounce-subtle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
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
            <main class="min-h-screen pt-16">
                <!-- Hero Section -->
                <div class="hero min-h-[70vh] hero-bg">
                    <div class="hero-content text-center text-white">
                        <div class="max-w-2xl">
                            <h1 class="mb-5 text-6xl font-bold">Join. Connect. Grow.</h1>
                            <p class="mb-8 text-xl">Discover amazing clubs, make lasting friendships, and enhance your university experience.</p>
                            <div class="flex gap-4 justify-center flex-wrap">
                                <?php if (isset($_SESSION['stud_id'])): ?>
                                    <a href="available_clubs.php" class="btn btn-lg bg-white text-purple-600 hover:bg-gray-100 border-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        Explore Clubs
                                    </a>
                                    <a href="my_clubs.php" class="btn btn-lg btn-outline text-white hover:bg-white hover:text-purple-600 border-white">
                                        My Clubs
                                    </a>
                                <?php else: ?>
                                    <button onclick="showLoginModal()" class="btn btn-lg bg-white text-purple-600 hover:bg-gray-100 border-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        Explore Clubs
                                    </button>
                                    <a href="../user_access.php" class="btn btn-lg btn-outline text-white hover:bg-white hover:text-purple-600 border-white">Get Started</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Latest Public Announcements Section -->
                <section class="py-20 bg-gradient-to-br from-gray-50 to-gray-300 overflow-hidden">
                    <div class="container mx-auto px-4">
                        <div class="text-center mb-12">
                            <div class="inline-flex items-center gap-2 bg-purple-100 text-purple-700 px-4 py-2 rounded-full mb-4 badge-pulse">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                                </svg>
                                <span class="font-semibold">Latest Updates</span>
                            </div>
                            <h2 class="text-5xl font-bold mb-4 bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                                Campus Announcements
                            </h2>
                            <p class="text-gray-600 text-lg">Stay informed about the latest news and updates</p>
                        </div>
                        
                        <div class="scroll-wrapper">
                            <button class="scroll-btn left" onclick="scrollContainer('announcements', -400)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            
                            <div id="announcements-scroll" class="scroll-container">
                                <?php if (!empty($public_announcements)): ?>
                                    <?php foreach ($public_announcements as $announcement): 
                                        $isNew = (strtotime($announcement['annPosted_at']) > strtotime('-3 days'));
                                    ?>
                                    <div class="announcement-card">
                                        <div class="card-image-container">
                                            <?php if ($isNew): ?>
                                                <div class="new-badge">NEW</div>
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
                                            
                                            <div class="card-overlay">
                                                <div class="text-white text-sm font-semibold">
                                                    <?php echo date('F j, Y', strtotime($announcement['annPosted_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="p-6">
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
                                            
                                            <h3 class="font-bold text-xl mb-3 line-clamp-2 min-h-[3.5rem]">
                                                <?php echo htmlspecialchars($announcement['anntitle']); ?>
                                            </h3>
                                            
                                            <p class="text-gray-600 text-sm line-clamp-3 mb-4 min-h-[4.5rem]">
                                                <?php echo htmlspecialchars($announcement['content']); ?>
                                            </p>
                                            
                                            <div class="flex justify-end">
                                                <?php if (isset($_SESSION['stud_id'])): ?>
                                                    <a href="announcement_post.php?id=<?php echo $announcement['annID']; ?>" 
                                                       class="btn btn-sm bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-lg">
                                                        Read More
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                        </svg>
                                                    </a>
                                                <?php else: ?>
                                                    <button onclick="showLoginModal()" class="btn btn-sm bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-lg">
                                                        Read More
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                        </svg>   
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <p class="text-xl text-gray-500">No announcements available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <button class="scroll-btn right" onclick="scrollContainer('announcements', 400)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                        
                        <div class="text-center mt-12">
                            <?php if (isset($_SESSION['stud_id'])): ?>
                                <a href="all_announcement.php" class="btn btn-lg bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-xl">
                                    View All Announcements
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <button onclick="showLoginModal()" class="btn btn-lg bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-xl">
                                    View All Announcements
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Upcoming Public Events Section -->
                <section class="event-bg py-20 overflow-hidden">
                    <div class="container mx-auto px-4">
                        <div class="text-center mb-12">
                            <div class="inline-flex items-center gap-2 bg-pink-100 text-pink-700 px-4 py-2 rounded-full mb-4 badge-pulse">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                </svg>
                                <span class="font-semibold">Upcoming Events</span>
                            </div>
                            <h2 class="text-5xl font-bold mb-4 text-white bg-clip-text text-transparent">
                                Don't Miss Out!
                            </h2>
                            <p class="text-white text-lg">Join exciting events happening on campus</p>
                        </div>
                        
                        <div class="scroll-wrapper">
                            <button class="scroll-btn left" onclick="scrollContainer('events', -400)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            
                            <div id="events-scroll" class="scroll-container">
                                <?php if (!empty($public_events)): ?>
                                    <?php foreach ($public_events as $event): 
                                        $eventDate = new DateTime($event['evDate']);
                                        $isUpcoming = (strtotime($event['evDate']) > strtotime('today') && strtotime($event['evDate']) <= strtotime('+7 days'));
                                    ?>
                                    <div class="event-card">
                                        <div class="card-image-container">
                                            <?php if ($isUpcoming): ?>
                                                <div class="new-badge">SOON</div>
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
                                            
                                            <div class="card-overlay">
                                                <div class="text-white text-sm font-semibold">
                                                    <?php echo date('g:i A', strtotime($event['evTime'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="p-6">
                                            <div class="flex items-start justify-between mb-3">
                                                <a href="club_profile.php?id=<?php echo $event['clubID']; ?>" 
                                                   class="badge bg-gradient-to-r from-pink-600 to-red-600 text-white border-none hover:shadow-lg text-xs">
                                                    <?php echo htmlspecialchars($event['clubName'] ?? 'General'); ?>
                                                </a>
                                                <div class="text-right">
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo $eventDate->format('M d, Y'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <h3 class="font-bold text-xl mb-3 line-clamp-2 min-h-[3.5rem]">
                                                <?php echo htmlspecialchars($event['evTitle']); ?>
                                            </h3>
                                            
                                            <p class="text-gray-600 text-sm line-clamp-2 mb-3 min-h-[3rem]">
                                                <?php echo htmlspecialchars($event['evDescription']); ?>
                                            </p>
                                            
                                            <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                                </svg>
                                                <span class="line-clamp-1"><?php echo htmlspecialchars($event['evLocation']); ?></span>
                                            </div>
                                            
                                            <div class="flex justify-end">
                                                <?php if (isset($_SESSION['stud_id'])): ?>
                                                    <a href="event_post.php?id=<?php echo $event['eventID']; ?>" 
                                                       class="btn btn-sm bg-gradient-to-r from-pink-600 to-red-600 text-white border-none hover:shadow-lg">
                                                        View Details
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                        </svg>
                                                    </a>
                                                <?php else: ?>
                                                    <button onclick="showLoginModal()" class="btn btn-sm bg-gradient-to-r from-pink-600 to-red-600 text-white border-none hover:shadow-lg">
                                                        View Details
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <p class="text-xl text-gray-500">No upcoming events</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <button class="scroll-btn right" onclick="scrollContainer('events', 400)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                        
                        <div class="text-center mt-12">
                            <?php if (isset($_SESSION['stud_id'])): ?>
                                <a href="all_event.php" class="btn btn-lg bg-gradient-to-r from-pink-600 to-red-600 text-white border-none hover:shadow-xl">
                                    View All Events
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <button onclick="showLoginModal()" class="btn btn-lg bg-gradient-to-r from-pink-600 to-red-600 text-white border-none hover:shadow-xl">
                                    View All Events
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Featured Clubs Section -->
                <section class="py-20 bg-gradient-to-br from-gray-50 to-gray-300 overflow-hidden">
                    <div class="container mx-auto px-4">
                        <div class="text-center mb-12">
                            <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-4 py-2 rounded-full mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                                </svg>
                                <span class="font-semibold">Discover More</span>
                            </div>
                            <h2 class="text-5xl font-bold mb-4 bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                                Featured Clubs
                            </h2>
                            <p class="text-gray-600 text-lg">Find your community and make new connections</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <?php foreach ($featured_clubs as $club): ?>
                            <div class="card bg-white shadow-xl club-card">
                                <div class="card-body text-center">
                                    <div class="avatar mx-auto mb-4">
                                        <div class="w-24 rounded-full ring ring-white-500 ring-offset-2">
                                            <div class="w-24 h-24 rounded-full flex items-center justify-center text-white text-3xl font-bold">
                                                <img src="../<?php echo htmlspecialchars($club['clubLogo']); ?>" 
                                                alt="<?php echo htmlspecialchars($club['clubName']); ?>"
                                                onerror="this.src='../assets/images/default-club.png'">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <h3 class="card-title justify-center text-lg">
                                        <?php echo htmlspecialchars($club['clubName']); ?>
                                    </h3>
                                    
                                    <p class="text-sm text-gray-600 line-clamp-2 mb-2">
                                        <?php echo htmlspecialchars($club['clubDescription']); ?>
                                    </p>
                                    
                                    <div class="flex items-center justify-center gap-2 text-sm text-gray-500 mt-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                                        </svg>
                                        <?php echo $club['member_count']; ?> / <?php echo $club['clubCapacity']; ?>
                                    </div>
                                    
                                    <?php if (!empty($club['lectName'])): ?>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Advisor: <?php echo htmlspecialchars($club['lectName']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-actions justify-center mt-4">
                                        <?php if (isset($_SESSION['stud_id'])): ?>
                                            <a href="club_profile.php?id=<?php echo $club['clubID']; ?>" 
                                            class="btn btn-sm bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:shadow-lg border-none">
                                                View Details
                                            </a>
                                        <?php else: ?>
                                            <button onclick="showLoginModal()" class="btn btn-sm bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:shadow-lg border-none">
                                                View Details
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-10">
                            <?php if (isset($_SESSION['stud_id'])): ?>
                                <a href="available_clubs.php" class="btn btn-lg bg-gradient-to-r from-blue-600 to-purple-600 text-white border-none hover:shadow-xl">
                                    Explore All Clubs
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php else: ?>
                                <button onclick="showLoginModal()" class="btn btn-lg bg-gradient-to-r from-blue-600 to-purple-600 text-white border-none hover:shadow-xl">
                                    Explore All Clubs
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Call to Action Section -->
                 <?php if (!isset($_SESSION['stud_id'])): ?>
                <section class="py-10 bg-gradient-to-r from-blue-600 to-blue-600">
                    <div class="container mx-auto px-4 text-center text-white">
                        <h2 class="text-4xl font-bold mb-4">Ready to Get Involved?</h2>
                        <p class="text-xl mb-8 opacity-90">Join a club today and start making memories!</p>
                        <div class="flex gap-4 justify-center flex-wrap">
                                <a href="../user_access.php" class="btn btn-lg btn-outline text-white hover:bg-white hover:text-purple-600 border-white">
                                    Login / Register
                                </a>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
            </main>

            <!-- Footer -->
            <?php include "includes/footer.php"; ?>
        </div> 

        <!-- Include Mobile Drawer -->
        <?php include "includes/mobile_drawer.php"; ?>
    </div>

    <!-- Login/Register Modal -->
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
                    <p class="text-gray-600">Please login or register to explore clubs and join our community!</p>
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

    <script>
        // Show login modal
        function showLoginModal() {
            document.getElementById('loginModal').showModal();
        }

        // Scroll container function
        function scrollContainer(type, amount) {
            const container = document.getElementById(type + '-scroll');
            container.scrollBy({
                left: amount,
                behavior: 'smooth'
            });
        }

        // Auto-hide scroll buttons at edges
        function updateScrollButtons() {
            const containers = ['announcements-scroll', 'events-scroll'];
            
            containers.forEach(containerId => {
                const container = document.getElementById(containerId);
                if (!container) return;
                
                const wrapper = container.parentElement;
                const leftBtn = wrapper.querySelector('.scroll-btn.left');
                const rightBtn = wrapper.querySelector('.scroll-btn.right');
                
                if (container.scrollLeft <= 0) {
                    leftBtn.style.opacity = '0';
                    leftBtn.style.pointerEvents = 'none';
                } else {
                    leftBtn.style.opacity = '1';
                    leftBtn.style.pointerEvents = 'all';
                }
                
                if (container.scrollLeft >= container.scrollWidth - container.clientWidth - 10) {
                    rightBtn.style.opacity = '0';
                    rightBtn.style.pointerEvents = 'none';
                } else {
                    rightBtn.style.opacity = '1';
                    rightBtn.style.pointerEvents = 'all';
                }
            });
        }

        // Add scroll event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const scrollContainers = document.querySelectorAll('.scroll-container');
            scrollContainers.forEach(container => {
                container.addEventListener('scroll', updateScrollButtons);
            });
            
            // Initial check
            updateScrollButtons();
            
            // Recheck on window resize
            window.addEventListener('resize', updateScrollButtons);
        });

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
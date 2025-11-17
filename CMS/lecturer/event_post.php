<?php
session_start();
require_once "../config/connect.php";

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    header("Location: ../user_access.php");
    exit();
}

// Get event ID from URL
if (!isset($_GET['eventID']) || empty($_GET['eventID'])) {
    header("Location: clubs.php");
    exit();
}

$event_id = $_GET['eventID'];
$lect_id = $_SESSION['lect_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'remove_participant') {
        $part_id = $_POST['part_id'] ?? null;
        
        if (!$part_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid participant ID']);
            exit();
        }
        
        try {
            // Verify the participant belongs to this event
            $verify_query = "SELECT ep.*, e.clubID, c.lectID 
                           FROM events_participant ep
                           JOIN casevents e ON ep.eventID = e.eventID
                           JOIN clubsocieties c ON e.clubID = c.clubID
                           WHERE ep.partID = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->execute([$part_id]);
            $participant = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$participant) {
                echo json_encode(['success' => false, 'message' => 'Participant not found']);
                exit();
            }
            
            // Check if current lecturer is the club advisor
            if ($participant['lectID'] != $lect_id) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized action']);
                exit();
            }
            
            // Delete the participant
            $delete_query = "DELETE FROM events_participant WHERE partID = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->execute([$part_id]);
            
            echo json_encode(['success' => true, 'message' => 'Participant removed successfully']);
            exit();
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
    }
}

// Get event details
$event_query = "SELECT e.*, c.clubName, c.clubID, c.clubLogo, l.lectName, l.lectProfileImg
              FROM casevents e
              LEFT JOIN clubsocieties c ON e.clubID = c.clubID
              LEFT JOIN lecturer l ON c.lectID = l.lectID
              WHERE e.eventID = ?";
$event_stmt = $conn->prepare($event_query);
$event_stmt->execute([$event_id]);
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    $_SESSION['error'] = "Event not found.";
    header("Location: clubs.php");
    exit();
}

// Get current lecturer info for display
$query = "SELECT lectName, lectProfileImg FROM lecturer WHERE lectID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$lect_id]);
$current_lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

// Prepare current user avatar HTML for JavaScript
$current_user_avatar_html = '';
if (!empty($current_lecturer['lectProfileImg'])) {
    $current_user_avatar_html = '<img src="../' . htmlspecialchars($current_lecturer['lectProfileImg']) . '" alt="' . htmlspecialchars($current_lecturer['lectName']) . '" class="w-full h-full object-cover">';
} else {
    $current_user_avatar_html = '<img src="../assets/default-avatar.png" alt="Default Avatar">';
}

// Get participant count
$participant_query = "SELECT COUNT(*) as participant_count FROM events_participant WHERE eventID = ?";
$participant_stmt = $conn->prepare($participant_query);
$participant_stmt->execute([$event_id]);
$participant_data = $participant_stmt->fetch(PDO::FETCH_ASSOC);
$participant_count = $participant_data['participant_count'];

// Calculate remaining slots
$remaining_slots = $event['evCapacity'] - $participant_count;

// Get all comments with user info and like counts
$query = "SELECT c.*, 
          s.studName, s.studProfileImg, s.studProgramme,
          l.lectName, l.lectProfileImg,
          (SELECT COUNT(*) FROM comment_likes WHERE commentID = c.commentID) as like_count,
          (SELECT COUNT(*) FROM comment_likes WHERE commentID = c.commentID AND liker_type = 'lecturer' AND liker_id = ?) as user_liked,
          (SELECT COUNT(*) FROM comments WHERE parent_comment_id = c.commentID AND is_deleted = 0) as reply_count
          FROM comments c
          LEFT JOIN student s ON c.commenter_type = 'student' AND CAST(c.commenter_id AS UNSIGNED) = CAST(s.studID AS UNSIGNED)
          LEFT JOIN lecturer l ON c.commenter_type = 'lecturer' AND CAST(c.commenter_id AS UNSIGNED) = CAST(l.lectID AS UNSIGNED)
          WHERE c.post_type = 'event' 
            AND c.post_id = ? 
            AND c.is_deleted = 0
            AND c.parent_comment_id IS NULL
          ORDER BY c.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$lect_id, $event_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get replies for a parent comment
function getReplies($conn, $parent_id, $current_lect_id) {
    $replies_query = "SELECT 
        c.*,
        s.studName, s.studProfileImg, s.studProgramme,
        l.lectName, l.lectProfileImg,
        (SELECT COUNT(*) FROM comment_likes WHERE commentID = c.commentID) as like_count,
        (SELECT COUNT(*) FROM comment_likes WHERE commentID = c.commentID AND liker_type = 'lecturer' AND liker_id = ?) as user_liked
    FROM comments c
    LEFT JOIN student s ON c.commenter_type = 'student' AND CAST(c.commenter_id AS UNSIGNED) = CAST(s.studID AS UNSIGNED)
    LEFT JOIN lecturer l ON c.commenter_type = 'lecturer' AND CAST(c.commenter_id AS UNSIGNED) = CAST(l.lectID AS UNSIGNED)
    WHERE c.parent_comment_id = ? 
        AND c.is_deleted = 0
    ORDER BY c.created_at ASC";
    $replies_stmt = $conn->prepare($replies_query);
    $replies_stmt->execute([$current_lect_id, $parent_id]);
    return $replies_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get total comment count
$total_comments = count($comments);
foreach ($comments as $comment) {
    $total_comments += $comment['reply_count'];
}

// Get event participants with their details
$participants_query = "SELECT 
    ep.*,
    s.studName,
    s.studNoID,
    s.studEmail,
    s.studProfileImg,
    s.studProgramme,
    CASE 
        WHEN cm.cmID IS NOT NULL THEN 'Yes'
        ELSE 'No'
    END as is_member
FROM events_participant ep
LEFT JOIN student s ON ep.studID = s.studID
LEFT JOIN membership cm ON s.studID = cm.studID AND cm.clubID = ?
WHERE ep.eventID = ?
ORDER BY ep.partDate DESC";
$participants_stmt = $conn->prepare($participants_query);
$participants_stmt->execute([$event['clubID'], $event_id]);
$participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);

$media_items = [];
if (!empty($event['evImg'])) {
    $media_items[] = ['type' => 'image', 'src' => $event['evImg']];
}
if (!empty($event['evVid'])) {
    $media_items[] = ['type' => 'video', 'src' => $event['evVid']];
}

?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['evTitle']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .carousel-item {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 400px;
        }

        .carousel-dot {
            transition: all 0.3s ease;
        }

        .carousel-dot:hover,
        .carousel-dot.active {
            background-color: white !important;
            transform: scale(1.2);
        }

        .btn-circle:hover {
            transform: scale(1.1);
        }

        .comment-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
            flex-shrink: 0;
        }
        
        .reply-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
            flex-shrink: 0;
        }
        
        .comment-card {
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            border-left: 4px solid #3b82f6;
            transition: all 0.3s ease;
        }
        
        .comment-card:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .reply-card {
            background: #f8fafc;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        
        .reply-card:hover {
            background: #f1f5f9;
        }

        .like-btn.liked {
            color: #ef4444;
        }

        .reply-indent {
            margin-left: 2rem;
            border-left: 2px solid #e5e7eb;
            padding-left: 1rem;
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="drawer lg:drawer-open">
        <input id="my-drawer-2" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content">
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
                                <li><a href="clubs.php">Clubs</a></li>
                                <li><a href="club_profile.php?clubID=<?php echo $event['clubID']; ?>"><?php echo htmlspecialchars($event['clubName']); ?></a></li>
                                <li>Event</li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex-none">
                        <button onclick="window.history.back()" class="btn btn-ghost btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back
                        </button>
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

                <!-- Main Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Event Content -->
                    <div class="lg:col-span-2">
                        <div class="card bg-base-100 shadow-xl">
                            <!-- Header with Club Info -->
                            <div class="card-body border-b">
                                <div class="flex items-center gap-4">
                                    <div class="avatar">
                                        <div class="w-12 h-12 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                                            <?php if (!empty($event['clubLogo'])): ?>
                                                <img src="../<?php echo htmlspecialchars($event['clubLogo']); ?>" 
                                                    alt="<?php echo htmlspecialchars($event['clubName']); ?>" 
                                                    class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="bg-gradient-to-br from-purple-400 to-blue-400 text-white flex items-center justify-center text-xl font-bold w-full h-full rounded-full">
                                                    <?php echo strtoupper(substr($event['clubName'], 0, 2)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="font-bold"><?php echo htmlspecialchars($event['clubName']); ?></h3>
                                        <p class="text-sm text-gray-500">by <?php echo htmlspecialchars($event['lectName']); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo date('F j, Y \a\t g:i A', strtotime($event['evPosted_at'])); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Title -->
                            <div class="card-body">
                                <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($event['evTitle']); ?></h1>

                                <!-- Event Info Badge -->
                                <div class="flex flex-wrap gap-2 mb-6">
                                    <div class="badge badge-lg badge-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <?php echo date('F j, Y', strtotime($event['evDate'])); ?>
                                    </div>
                                    <div class="badge badge-lg badge-secondary">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <?php echo date('g:i A', strtotime($event['evTime'])); ?>
                                    </div>
                                    <div class="badge badge-lg badge-accent">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <?php echo htmlspecialchars($event['evLocation']); ?>
                                    </div>
                                    <button onclick="participantsModal.showModal()" 
                                            class="badge badge-lg <?php echo $remaining_slots > 0 ? 'badge-success' : 'badge-error'; ?> cursor-pointer hover:brightness-110 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <?php echo $participant_count; ?> / <?php echo $event['evCapacity']; ?> Participants
                                    </button>
                                    <?php if (!empty($event['evType'])): ?>
                                    <div class="badge badge-lg badge-outline">
                                        <?php echo htmlspecialchars($event['evType']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($media_items)): ?>
                                <figure class="relative mb-6">
                                    <div class="carousel w-full rounded-lg overflow-hidden bg-black" id="mediaCarousel">
                                        <?php foreach ($media_items as $index => $media): ?>
                                            <div id="slide<?php echo $index + 1; ?>" class="carousel-item relative w-full">
                                                <?php if ($media['type'] === 'image'): ?>
                                                    <img src="../<?php echo htmlspecialchars($media['src']); ?>" 
                                                        alt="<?php echo htmlspecialchars($event['evTitle']); ?>"
                                                        class="w-full max-h-[600px] object-contain mx-auto"
                                                        onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22300%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%2220%22%3EImage not available%3C/text%3E%3C/svg%3E'">
                                                <?php else: ?>
                                                    <video controls class="w-full max-h-[600px] object-contain mx-auto" controlsList="nodownload">
                                                        <source src="../<?php echo htmlspecialchars($media['src']); ?>" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                <?php endif; ?>
                                                
                                                <?php if (count($media_items) > 1): ?>
                                                    <div class="absolute flex justify-between transform -translate-y-1/2 left-5 right-5 top-1/2">
                                                        <a href="#slide<?php echo $index === 0 ? count($media_items) : $index; ?>" 
                                                        class="btn btn-circle btn-sm bg-white/80 hover:bg-white border-none backdrop-blur-sm shadow-lg">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                                            </svg>
                                                        </a> 
                                                        <a href="#slide<?php echo ($index + 2) > count($media_items) ? 1 : $index + 2; ?>" 
                                                        class="btn btn-circle btn-sm bg-white/80 hover:bg-white border-none backdrop-blur-sm shadow-lg">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                            </svg>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if (count($media_items) > 1): ?>
                                        <!-- Dots Navigation -->
                                        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex gap-2 z-10">
                                            <?php for ($i = 1; $i <= count($media_items); $i++): ?>
                                                <a href="#slide<?php echo $i; ?>" 
                                                class="carousel-dot w-2 h-2 rounded-full bg-white/50 hover:bg-white transition-all duration-300"></a>
                                            <?php endfor; ?>
                                        </div>
                                        
                                        <!-- Slide Counter -->
                                        <div class="absolute top-4 right-4 z-10">
                                            <div class="badge badge-lg bg-black/70 text-white border-none backdrop-blur-sm font-semibold gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span id="currentSlide">1</span>/<span><?php echo count($media_items); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Media Type Badges -->
                                    <div class="absolute top-4 left-4 z-10 flex gap-2">
                                        <?php 
                                        $hasImage = false;
                                        $hasVideo = false;
                                        foreach ($media_items as $item) {
                                            if ($item['type'] === 'image') $hasImage = true;
                                            if ($item['type'] === 'video') $hasVideo = true;
                                        }
                                        ?>

                                        <?php if ($hasImage): ?>
                                            <div class="badge badge-lg bg-blue-500/90 text-white border-none backdrop-blur-sm font-semibold gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                                </svg>
                                                Photo
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($hasVideo): ?>
                                            <div class="badge badge-lg bg-red-500/90 text-white border-none backdrop-blur-sm font-semibold gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
                                                </svg>
                                                Video
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </figure>
                                <?php endif; ?>


                                <!-- Description -->
                                <div class="prose max-w-none">
                                    <?php echo nl2br(htmlspecialchars($event['evDescription'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Comments Section -->
                        <div class="card bg-base-100 shadow-xl mt-6">
                            <div class="card-body">
                                <h2 class="card-title mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                    </svg>
                                    Comments (<span id="total-comment-count"><?php echo $total_comments; ?></span>)
                                </h2>

                                <!-- Add Comment Form -->
                                <form method="POST" class="mb-6" onsubmit="addComment(event)">
                                    <input type="hidden" name="action" value="add_comment">
                                    <div class="flex gap-3">
                                        <div class="avatar flex-shrink-0">
                                            <div class="w-10 h-10 rounded-full">
                                                <?php if (!empty($current_lecturer['lectProfileImg'])): ?>
                                                <img src="../<?php echo htmlspecialchars($current_lecturer['lectProfileImg']); ?>" 
                                                    alt="<?php echo htmlspecialchars($current_lecturer['lectName']); ?>">
                                                <?php else: ?>
                                                <img src="../assets/default-avatar.png" alt="Default Avatar">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <textarea name="content" 
                                                    class="textarea textarea-bordered w-full focus:textarea-primary" 
                                                    placeholder="Write a comment..." 
                                                    rows="3" 
                                                    required></textarea>
                                            <div class="flex justify-end mt-2">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    Send Comment
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" transform="rotate(90 10 10)"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <div class="divider"></div>

                                <!-- Comments List -->
                                <div class="space-y-6" id="comments-container">
                                    <?php if (empty($comments)): ?>
                                    <div class="text-center py-12 text-gray-500" id="no-comments-message">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        <p class="text-lg font-medium">No comments yet</p>
                                        <p class="text-sm">Be the first to share your thoughts!</p>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($comments as $comment): ?>
                                        <?php
                                        $commenterName = $comment['commenter_type'] === 'lecturer' ? $comment['lectName'] : $comment['studName'];
                                        $commenterImg = $comment['commenter_type'] === 'lecturer' ? $comment['lectProfileImg'] : $comment['studProfileImg'];
                                        $commenterDetail = $comment['commenter_type'] === 'lecturer' ? 'Lecturer' : $comment['studProgramme'];
                                        $isOwner = $comment['commenter_type'] === 'lecturer' && $comment['commenter_id'] == $lect_id;
                                        ?>
                                        
                                        <div class="comment-card rounded-xl p-4" id="comment-<?php echo $comment['commentID']; ?>">
                                            <div class="flex gap-3">
                                                <!-- Avatar -->
                                                <div class="flex-shrink-0">
                                                    <?php if (!empty($commenterImg)): ?>
                                                        <?php 
                                                            $img_path = $comment['commenter_type'] === 'student' 
                                                                ? '../uploads/student_profiles/' . $commenterImg
                                                                : '../' . $commenterImg;
                                                        ?>
                                                        <img src="<?php echo htmlspecialchars($img_path); ?>" 
                                                            alt="Profile" 
                                                            class="comment-avatar"
                                                            onerror="this.src='../assets/default-avatar.png'">
                                                    <?php else: ?>
                                                        <img src="../assets/default-avatar.png" 
                                                            alt="Default" 
                                                            class="comment-avatar">
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Comment Content -->
                                                <div class="flex-1 min-w-0">
                                                    <div class="bg-white rounded-lg p-4 shadow-sm">
                                                        <div class="flex justify-between items-start mb-2">
                                                            <div>
                                                                <h4 class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($commenterName); ?></h4>
                                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($commenterDetail); ?></p>
                                                            </div>
                                                            
                                                            <!-- Action Dropdown -->
                                                            <?php if ($isOwner || true): // Lecturers can delete any comment ?>
                                                            <div class="dropdown dropdown-end">
                                                                <label tabindex="0" class="btn btn-ghost btn-xs">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                                                    </svg>
                                                                </label>
                                                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-32">
                                                                    <?php if ($isOwner): ?>
                                                                    <li><a onclick="editComment(<?php echo $comment['commentID']; ?>)">Edit</a></li>
                                                                    <?php endif; ?>
                                                                    <li><a onclick="deleteComment(<?php echo $comment['commentID']; ?>)" class="text-error">Delete</a></li>
                                                                </ul>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Comment Text -->
                                                        <div class="comment-content-<?php echo $comment['commentID']; ?>">
                                                            <p class="mt-2 text-sm text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                                        </div>
                                                        
                                                        <!-- Edit Form (Hidden) -->
                                                        <form method="POST" class="edit-form-<?php echo $comment['commentID']; ?> hidden mb-2" onsubmit="editCommentAjax(event)">
                                                            <input type="hidden" name="action" value="edit_comment">
                                                            <input type="hidden" name="comment_id" value="<?php echo $comment['commentID']; ?>">
                                                            <textarea name="content" class="textarea textarea-bordered w-full mb-2" rows="3"><?php echo htmlspecialchars($comment['content']); ?></textarea>
                                                            <div class="flex gap-2">
                                                                <button type="submit" class="btn btn-primary btn-xs">Save</button>
                                                                <button type="button" onclick="cancelEdit(<?php echo $comment['commentID']; ?>)" class="btn btn-ghost btn-xs">Cancel</button>
                                                            </div>
                                                        </form>
                                                        
                                                        <?php if ($comment['is_edited']): ?>
                                                            <p class="text-xs text-gray-400 mt-2 italic">✎ Edited</p>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Comment Actions -->
                                                    <div class="flex gap-4 mt-3 text-xs items-center">
                                                        <span class="text-gray-500"><?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?></span>
                                                        
                                                        <!-- Like Button -->
                                                        <button onclick="likeComment(<?php echo $comment['commentID']; ?>)" 
                                                                class="like-btn flex items-center gap-1.5 hover:text-red-500 transition-colors <?php echo $comment['user_liked'] ? 'liked text-red-500' : 'text-gray-500'; ?>"
                                                                id="like-btn-<?php echo $comment['commentID']; ?>">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                                            </svg>
                                                            <span id="like-count-<?php echo $comment['commentID']; ?>"><?php echo $comment['like_count']; ?></span>
                                                        </button>

                                                        <!-- Reply Button -->
                                                        <button onclick="toggleReplyForm(<?php echo $comment['commentID']; ?>)" 
                                                                class="flex items-center gap-1.5 hover:text-primary transition-colors font-medium">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                            </svg>
                                                            Reply
                                                        </button>

                                                        <?php if ($comment['reply_count'] > 0): ?>
                                                            <button onclick="toggleReplies(<?php echo $comment['commentID']; ?>)" 
                                                                    class="text-primary hover:underline font-medium"
                                                                    id="toggle-replies-btn-<?php echo $comment['commentID']; ?>">
                                                                View <?php echo $comment['reply_count']; ?> <?php echo $comment['reply_count'] == 1 ? 'reply' : 'replies'; ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Reply Form (Hidden) -->
                                                    <form method="POST" class="reply-form-<?php echo $comment['commentID']; ?> hidden mt-4" onsubmit="addComment(event, <?php echo $comment['commentID']; ?>)">
                                                        <input type="hidden" name="action" value="add_comment">
                                                        <input type="hidden" name="parent_id" value="<?php echo $comment['commentID']; ?>">
                                                        <div class="flex gap-3">
                                                            <div class="avatar flex-shrink-0">
                                                                <div class="w-8 h-8 rounded-full">
                                                                    <?php if (!empty($current_lecturer['lectProfileImg'])): ?>
                                                                    <img src="../<?php echo htmlspecialchars($current_lecturer['lectProfileImg']); ?>" 
                                                                        alt="<?php echo htmlspecialchars($current_lecturer['lectName']); ?>">
                                                                    <?php else: ?>
                                                                    <img src="../assets/default-avatar.png" alt="Default Avatar">
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="flex-1">
                                                                <textarea name="content" class="textarea textarea-bordered textarea-sm w-full" placeholder="Write a reply..." rows="2" required></textarea>
                                                                <div class="flex gap-2 mt-2">
                                                                    <button type="submit" class="btn btn-primary btn-xs">Reply</button>
                                                                    <button type="button" onclick="toggleReplyForm(<?php echo $comment['commentID']; ?>)" class="btn btn-ghost btn-xs">Cancel</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </form>

                                                    <!-- Replies List -->
                                                    <?php if ($comment['reply_count'] > 0): ?>
                                                        <div id="replies-<?php echo $comment['commentID']; ?>" class="hidden reply-indent mt-4 space-y-4">
                                                            <?php 
                                                            $replies = getReplies($conn, $comment['commentID'], $lect_id);
                                                            foreach ($replies as $reply): 
                                                                $replyCommenterName = $reply['commenter_type'] === 'lecturer' ? $reply['lectName'] : $reply['studName'];
                                                                $replyCommenterImg = $reply['commenter_type'] === 'lecturer' ? $reply['lectProfileImg'] : $reply['studProfileImg'];
                                                                $replyCommenterDetail = $reply['commenter_type'] === 'lecturer' ? 'Lecturer' : $reply['studProgramme'];
                                                                $isReplyOwner = $reply['commenter_type'] === 'lecturer' && $reply['commenter_id'] == $lect_id;
                                                            ?>
                                                                <div class="reply-card p-3" id="comment-<?php echo $reply['commentID']; ?>">
                                                                    <div class="flex gap-3">
                                                                        <div class="flex-shrink-0">
                                                                            <?php if (!empty($replyCommenterImg)): ?>
                                                                                <?php 
                                                                                    $img_path = $reply['commenter_type'] === 'student' 
                                                                                        ? '../uploads/student_profiles/' . $replyCommenterImg
                                                                                        : '../' . $replyCommenterImg;
                                                                                ?>
                                                                                <img src="<?php echo htmlspecialchars($img_path); ?>" 
                                                                                    alt="Profile" 
                                                                                    class="reply-avatar"
                                                                                    onerror="this.src='../assets/default-avatar.png'">
                                                                            <?php else: ?>
                                                                                <img src="../assets/default-avatar.png" 
                                                                                    alt="Default" 
                                                                                    class="reply-avatar">
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        
                                                                        <div class="flex-1 min-w-0">
                                                                            <div class="flex justify-between items-start mb-2">
                                                                                <div>
                                                                                    <h5 class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($replyCommenterName); ?></h5>
                                                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($replyCommenterDetail); ?></p>
                                                                                </div>
                                                                                
                                                                                <?php if ($isReplyOwner || true): ?>
                                                                                <div class="dropdown dropdown-end">
                                                                                    <label tabindex="0" class="btn btn-ghost btn-xs">
                                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                                                                        </svg>
                                                                                    </label>
                                                                                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-32">
                                                                                        <?php if ($isReplyOwner): ?>
                                                                                        <li><a onclick="editComment(<?php echo $reply['commentID']; ?>)">Edit</a></li>
                                                                                        <?php endif; ?>
                                                                                        <li><a onclick="deleteComment(<?php echo $reply['commentID']; ?>)" class="text-error">Delete</a></li>
                                                                                    </ul>
                                                                                </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                            
                                                                            <!-- Reply Content -->
                                                                            <div class="comment-content-<?php echo $reply['commentID']; ?>">
                                                                                <p class="text-sm text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
                                                                            </div>
                                                                            
                                                                            <!-- Edit Form -->
                                                                            <form method="POST" class="edit-form-<?php echo $reply['commentID']; ?> hidden mb-2" onsubmit="editCommentAjax(event)">
                                                                                <input type="hidden" name="action" value="edit_comment">
                                                                                <input type="hidden" name="comment_id" value="<?php echo $reply['commentID']; ?>">
                                                                                <textarea name="content" class="textarea textarea-bordered textarea-sm w-full mb-2" rows="2"><?php echo htmlspecialchars($reply['content']); ?></textarea>
                                                                                <div class="flex gap-2">
                                                                                    <button type="submit" class="btn btn-primary btn-xs">Save</button>
                                                                                    <button type="button" onclick="cancelEdit(<?php echo $reply['commentID']; ?>)" class="btn btn-ghost btn-xs">Cancel</button>
                                                                                </div>
                                                                            </form>
                                                                            
                                                                            <?php if ($reply['is_edited']): ?>
                                                                                <p class="text-xs text-gray-400 mt-1 italic">✎ Edited</p>
                                                                            <?php endif; ?>

                                                                            <div class="flex gap-3 mt-2 text-xs items-center">
                                                                                <span class="text-gray-500"><?php echo date('M j, g:i A', strtotime($reply['created_at'])); ?></span>
                                                                                
                                                                                <!-- Like Button -->
                                                                                <button onclick="likeComment(<?php echo $reply['commentID']; ?>)" 
                                                                                        class="like-btn flex items-center gap-1.5 hover:text-red-500 transition-colors <?php echo $reply['user_liked'] ? 'liked text-red-500' : 'text-gray-500'; ?>"
                                                                                        id="like-btn-<?php echo $reply['commentID']; ?>">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                                                                    </svg>
                                                                                    <span id="like-count-<?php echo $reply['commentID']; ?>"><?php echo $reply['like_count']; ?></span>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="lg:col-span-1">
                        <!-- Event Info Card -->
                        <div class="card bg-base-100 shadow-xl mb-6 sticky top-4">
                            <div class="card-body">
                                <h3 class="card-title text-lg">Event Details</h3>
                                <div class="divider my-2"></div>
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Club Name</p>
                                        <p class="font-bold"><?php echo htmlspecialchars($event['clubName']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Organizer</p>
                                        <p><?php echo htmlspecialchars($event['lectName']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Date & Time</p>
                                        <p><?php echo date('F j, Y', strtotime($event['evDate'])); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($event['evTime'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Location</p>
                                        <p><?php echo htmlspecialchars($event['evLocation']); ?></p>
                                    </div>
                                    <?php if (!empty($event['evType'])): ?>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Event Type</p>
                                        <p><?php echo htmlspecialchars($event['evType']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Capacity</p>
                                        <div class="flex items-center gap-2">
                                            <progress class="progress <?php echo $remaining_slots > 0 ? 'progress-success' : 'progress-error'; ?> w-full" 
                                                    value="<?php echo $participant_count; ?>" 
                                                    max="<?php echo $event['evCapacity']; ?>"></progress>
                                        </div>
                                        <button onclick="participantsModal.showModal()" class="text-sm mt-1 hover:text-primary transition-colors text-left w-full">
                                            <span class="font-bold"><?php echo $participant_count; ?></span> / <?php echo $event['evCapacity']; ?> participants
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                        <?php if ($remaining_slots > 0): ?>
                                            <p class="text-xs text-success mt-1"><?php echo $remaining_slots; ?> slots remaining</p>
                                        <?php else: ?>
                                            <p class="text-xs text-error mt-1">Event is full</p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Posted On</p>
                                        <p><?php echo date('F j, Y', strtotime($event['evPosted_at'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Total Comments</p>
                                        <p id="sidebar-comment-count"><?php echo $total_comments; ?></p>
                                    </div>
                                </div>
                                <div class="divider my-2"></div>
                                <a href="club_profile.php?clubID=<?php echo $event['clubID']; ?>" class="btn btn-primary btn-sm btn-block">
                                    View Club Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Participants Modal -->
        <dialog id="participantsModal" class="modal">
            <div class="modal-box max-w-6xl max-h-[90vh]">
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                </form>
                
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-2xl flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Event Participants
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo $participant_count; ?> / <?php echo $event['evCapacity']; ?> registered
                            <?php if ($remaining_slots > 0): ?>
                                <span class="text-success">• <?php echo $remaining_slots; ?> slots remaining</span>
                            <?php else: ?>
                                <span class="text-error">• Event is full</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($participants)): ?>
                    <button onclick="exportParticipants()" class="btn btn-outline btn-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export CSV
                    </button>
                    <?php endif; ?>
                </div>

                <div class="divider mt-0"></div>

                 <?php if (!empty($participants)): ?>
                    <!-- Search Bar -->
                    <div class="form-control mb-4">
                        <div class="input-group">
                            <input type="text" 
                                id="participantSearch" 
                                placeholder="Search by name, ID, or programme..." 
                                class="input input-bordered w-full focus:outline-none focus:ring-2 focus:ring-primary"
                                onkeyup="filterParticipantsTable()">
                            <button class="btn btn-primary btn-square">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Interactive Table -->
                    <div class="overflow-x-auto rounded-lg border border-base-300 shadow-sm">
                        <table class="table table-zebra">
                            <thead class="bg-base-200 sticky top-0 z-10">
                                <tr>
                                    <th class="cursor-pointer hover:bg-base-300 transition-colors" onclick="sortTable(0)">
                                        <div class="flex items-center gap-1 font-semibold">
                                            No
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                            </svg>
                                        </div>
                                    </th>
                                    <th class="cursor-pointer hover:bg-base-300 transition-colors" onclick="sortTable(1)">
                                        <div class="flex items-center gap-1 font-semibold">
                                            Participant
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                            </svg>
                                        </div>
                                    </th>
                                    <th class="cursor-pointer hover:bg-base-300 transition-colors" onclick="sortTable(2)">
                                        <div class="flex items-center gap-1 font-semibold">
                                            Student ID
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                            </svg>
                                        </div>
                                    </th>
                                    <th class="cursor-pointer hover:bg-base-300 transition-colors" onclick="sortTable(3)">
                                        <div class="flex items-center gap-1 font-semibold">
                                            Programme
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                            </svg>
                                        </div>
                                    </th>
                                    <?php if (strtolower($event['evType']) === 'public'): ?>
                                    <th class="cursor-pointer hover:bg-base-300 transition-colors" onclick="sortTable(4)">
                                        <div class="flex items-center gap-1 font-semibold">
                                            Member Status
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                            </svg>
                                        </div>
                                    </th>
                                    <?php endif; ?>
                                    <th class="cursor-pointer hover:bg-base-300 transition-colors" onclick="sortTable(<?php echo strtolower($event['evType']) === 'public' ? '5' : '4'; ?>)">
                                        <div class="flex items-center gap-1 font-semibold">
                                            Registration Date
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                            </svg>
                                        </div>
                                    </th>
                                    <th class="font-semibold">Status</th>
                                    <th class="text-center font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="participantsTableBody">
                                <?php foreach ($participants as $index => $participant): ?>
                                    <tr class="hover:bg-base-200/50 participant-row transition-colors" 
                                        data-part-id="<?php echo $participant['partID']; ?>"
                                        data-name="<?php echo strtolower(htmlspecialchars($participant['studName'])); ?>"
                                        data-id="<?php echo strtolower(htmlspecialchars($participant['studNoID'])); ?>"
                                        data-programme="<?php echo strtolower(htmlspecialchars($participant['studProgramme'])); ?>">
                                        <td class="font-bold">
                                            <div class="badge badge-primary badge-sm">#<?php echo $index + 1; ?></div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="avatar">
                                                    <div class="w-12 h-12 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                                                        <?php if (!empty($participant['studProfileImg'])): ?>
                                                            <img src="../uploads/student_profiles/<?php echo htmlspecialchars($participant['studProfileImg']); ?>" 
                                                                alt="<?php echo htmlspecialchars($participant['studName']); ?>"
                                                                class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                            <div class="w-full h-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-bold text-lg">
                                                                <?php echo strtoupper(substr($participant['studName'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-sm"><?php echo htmlspecialchars($participant['studName']); ?></div>
                                                    <div class="text-xs text-gray-500 flex items-center gap-1">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                        </svg>
                                                        <?php echo htmlspecialchars($participant['studEmail']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-mono text-xs bg-base-200 px-2 py-1 rounded"><?php echo htmlspecialchars($participant['studNoID']); ?></span>
                                        </td>
                                        <td>
                                            <div class="badge badge-ghost badge-md">
                                                <?php echo htmlspecialchars($participant['studProgramme']); ?>
                                            </div>
                                        </td>
                                        <?php if (strtolower($event['evType']) === 'public'): ?>
                                        <td>
                                            <?php if ($participant['is_member'] === 'Yes'): ?>
                                                <div class="badge badge-success badge-sm gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                                    </svg>
                                                    Member
                                                </div>
                                            <?php else: ?>
                                                <div class="badge badge-ghost badge-sm gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                    </svg>
                                                    Non-Member
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-xs font-medium">
                                                    <?php echo date('M j, Y', strtotime($participant['partDate'])); ?>
                                                </span>
                                                <span class="text-xs text-gray-500">
                                                    <?php echo date('g:i A', strtotime($participant['partDate'])); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="badge badge-success badge-sm gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                                Registered
                                            </div>
                                        </td>
                                        <!-- Actions column - Properly centered and interactive -->
                                        <td>
                                            <div class="flex items-center justify-center">
                                                <button onclick="removeParticipant(<?php echo $participant['partID']; ?>, '<?php echo addslashes($participant['studName']); ?>')" 
                                                        class="btn btn-outline btn-error btn-sm gap-2 hover:scale-105 transition-transform" >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
                                                    </svg>
                                                    Remove
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- No Results Message -->
                    <div id="noResultsTable" class="hidden text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <p class="text-gray-500">No participants found matching your search.</p>
                    </div>

                    <!-- Table Info -->
                    <div class="flex justify-between items-center mt-4 text-sm text-gray-500">
                        <div id="tableInfo">
                            Showing <span id="visibleCount"><?php echo count($participants); ?></span> of <?php echo count($participants); ?> participants
                        </div>
                        <form method="dialog">
                            <button class="btn btn-primary btn-sm">Close</button>
                        </form>
                    </div>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <h4 class="text-lg font-semibold text-gray-600 mb-2">No Participants Yet</h4>
                        <p class="text-sm text-gray-500">Be the first to register for this event!</p>
                    </div>
                    <div class="modal-action">
                        <form method="dialog">
                            <button class="btn">Close</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
        <?php include 'includes/sidebar.php'; ?>
    </div>


    <!-- Delete Confirmation Modal -->
    <dialog id="deleteModal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg mb-4">Delete Comment?</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete this comment? This action cannot be undone.</p>
            <input type="hidden" id="deleteCommentId">
            <div class="modal-action">
                <button type="button" onclick="document.getElementById('deleteModal').close()" class="btn btn-ghost">Cancel</button>
                <button type="button" onclick="confirmDelete()" class="btn btn-error">Delete</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <script>
         // Carousel functionality
        const carouselDots = document.querySelectorAll('.carousel-dot');
        const carousel = document.getElementById('mediaCarousel');

        if (carousel) {
            function updateCarousel() {
                const slides = carousel.querySelectorAll('.carousel-item');
                let activeIndex = 0;
                
                slides.forEach((slide, index) => {
                    const rect = slide.getBoundingClientRect();
                    const carouselRect = carousel.getBoundingClientRect();
                    
                    if (Math.abs(rect.left - carouselRect.left) < 10) {
                        activeIndex = index;
                    }
                });
                
                // Update dots
                carouselDots.forEach((dot, index) => {
                    if (index === activeIndex) {
                        dot.classList.add('active');
                        dot.style.width = '24px';
                    } else {
                        dot.classList.remove('active');
                        dot.style.width = '8px';
                    }
                });
                
                // Update counter
                const counter = document.getElementById('currentSlide');
                if (counter) {
                    counter.textContent = activeIndex + 1;
                }
            }
            
            carousel.addEventListener('scroll', updateCarousel);
            updateCarousel();
            
            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    const prevBtn = carousel.querySelector('.btn-circle:first-of-type');
                    if (prevBtn) prevBtn.click();
                } else if (e.key === 'ArrowRight') {
                    const nextBtn = carousel.querySelector('.btn-circle:last-of-type');
                    if (nextBtn) nextBtn.click();
                }
            });
            
            // Pause videos when not visible
            const videos = carousel.querySelectorAll('video');
            videos.forEach(video => {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (!entry.isIntersecting) {
                            video.pause();
                        }
                    });
                }, { threshold: 0.5 });
                
                observer.observe(video);
            });
        }

        // Swipe support for mobile
        let touchStartX = 0;
        let touchEndX = 0;

        if (carousel) {
            carousel.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            carousel.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
            
            function handleSwipe() {
                if (touchEndX < touchStartX - 50) {
                    // Swipe left
                    const nextBtn = carousel.querySelector('.btn-circle:last-of-type');
                    if (nextBtn) nextBtn.click();
                }
                if (touchEndX > touchStartX + 50) {
                    // Swipe right
                    const prevBtn = carousel.querySelector('.btn-circle:first-of-type');
                    if (prevBtn) prevBtn.click();
                }
            }
        }

        function removeParticipant(partId, studentName) {
            if (!confirm(`Are you sure you want to remove ${studentName} from this event?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'remove_participant');
            formData.append('part_id', partId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from table
                    const row = document.querySelector(`tr[data-part-id="${partId}"]`);
                    if (row) {
                        row.style.transition = 'opacity 0.3s ease';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            // Renumber the remaining rows
                            const rows = document.querySelectorAll('.participant-row');
                            rows.forEach((r, index) => {
                                r.querySelector('.badge').textContent = '#' + (index + 1);
                            });
                            
                            // Update counts
                            const totalRows = rows.length;
                            document.getElementById('visibleCount').textContent = totalRows;
                            
                            // Show empty state if no participants left
                            if (totalRows === 0) {
                                const modalContent = document.querySelector('#participantsModal .modal-box');
                                const emptyState = `
                                    <div class="text-center py-12">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <h4 class="text-lg font-semibold text-gray-600 mb-2">No Participants Yet</h4>
                                        <p class="text-sm text-gray-500">Be the first to register for this event!</p>
                                    </div>
                                `;
                                document.querySelector('.overflow-x-auto').outerHTML = emptyState;
                            }
                        }, 300);
                    }
                    
                    // Refresh the page to update counts in badges
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                    
                    showToast('Participant removed successfully!', 'success');
                } else {
                    showToast(data.message || 'Failed to remove participant', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            });
        }
        // ============================================
        // COMMENT FUNCTIONS
        // ============================================

        // Get post info
        function getPostInfo() {
            return {
                postType: 'event',
                postId: <?php echo $event_id; ?>
            };
        }

        // Update comment count
        function updateCommentCount(change) {
            const headerCount = document.getElementById('total-comment-count');
            const sidebarCount = document.getElementById('sidebar-comment-count');
            
            if (headerCount) {
                const currentCount = parseInt(headerCount.textContent);
                const newCount = Math.max(0, currentCount + change);
                headerCount.textContent = newCount;
            }
            
            if (sidebarCount) {
                const currentCount = parseInt(sidebarCount.textContent);
                const newCount = Math.max(0, currentCount + change);
                sidebarCount.textContent = newCount;
            }
        }

        // Add comment with AJAX
        function addComment(event, parentId = null) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const { postType, postId } = getPostInfo();
            
            formData.append('post_type', postType);
            formData.append('post_id', postId);
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Posting...';
            
            fetch('ajax_comment_handler_lecturer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    form.reset();
                    
                    if (parentId) {
                        insertReply(parentId, data.comment);
                        form.classList.add('hidden');
                    } else {
                        insertComment(data.comment);
                    }
                    
                    showToast('Comment posted successfully!', 'success');
                    updateCommentCount(1);
                } else {
                    showToast(data.message || 'Failed to post comment', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        }

        // Edit comment with AJAX
        function editCommentAjax(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const commentId = formData.get('comment_id');
            const { postType, postId } = getPostInfo();
            
            formData.append('post_type', postType);
            formData.append('post_id', postId);
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading loading-spinner loading-xs"></span>';
            
            fetch('ajax_comment_handler_lecturer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const contentDiv = document.querySelector('.comment-content-' + commentId);
                    const newContent = formData.get('content');
                    contentDiv.querySelector('p').innerHTML = newContent.replace(/\n/g, '<br>');
                    
                    const commentCard = contentDiv.closest('.bg-white, .reply-card');
                    if (commentCard && !commentCard.querySelector('.italic')) {
                        const editedBadge = document.createElement('p');
                        editedBadge.className = 'text-xs text-gray-400 mt-2 italic';
                        editedBadge.textContent = '✎ Edited';
                        contentDiv.after(editedBadge);
                    }
                    
                    cancelEdit(commentId);
                    showToast('Comment updated successfully!', 'success');
                } else {
                    showToast(data.message || 'Failed to update comment', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        }

        // Delete comment with AJAX
        function confirmDelete() {
            const commentId = document.getElementById('deleteCommentId').value;
            
            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('comment_id', commentId);
            const { postType, postId } = getPostInfo();
            formData.append('post_type', postType);
            formData.append('post_id', postId);
            
            fetch('ajax_comment_handler_lecturer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const commentElement = document.getElementById('comment-' + commentId);
                    
                    if (!commentElement) {
                        console.error('Comment element not found:', commentId);
                        return;
                    }
                    
                    const isReply = commentElement.closest('.reply-indent') !== null;
                    let totalToDelete = 1;
                    
                    if (!isReply) {
                        const repliesContainer = document.getElementById('replies-' + commentId);
                        if (repliesContainer) {
                            const replyElements = repliesContainer.querySelectorAll('.reply-card');
                            totalToDelete += replyElements.length;
                        }
                    }
                    
                    commentElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    commentElement.style.opacity = '0';
                    commentElement.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        if (isReply) {
                            const parentReplyContainer = commentElement.closest('.reply-indent');
                            if (parentReplyContainer) {
                                const parentId = parentReplyContainer.id.replace('replies-', '');
                                updateReplyCount(parentId, -1);
                                
                                const remainingReplies = parentReplyContainer.querySelectorAll('.reply-card');
                                if (remainingReplies.length === 1) {
                                    parentReplyContainer.classList.add('hidden');
                                }
                            }
                        }
                        
                        commentElement.remove();
                        updateCommentCount(-totalToDelete);
                        
                        const commentsSection = document.getElementById('comments-container');
                        if (commentsSection) {
                            const allComments = commentsSection.querySelectorAll('.comment-card');
                            const topLevelComments = Array.from(allComments).filter(comment => 
                                !comment.closest('.reply-indent')
                            );
                            
                            if (topLevelComments.length === 0) {
                                commentsSection.innerHTML = `
                                    <div class="text-center py-12 text-gray-500" id="no-comments-message">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        <p class="text-lg font-medium">No comments yet</p>
                                        <p class="text-sm">Be the first to share your thoughts!</p>
                                    </div>
                                `;
                            }
                        }
                    }, 300);
                    
                    showToast('Comment deleted successfully!', 'success');
                    document.getElementById('deleteModal').close();
                } else {
                    showToast(data.message || 'Failed to delete comment', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            });
        }

        // Insert new comment into DOM
        function insertComment(comment) {
            const commentsSection = document.getElementById('comments-container');
            
            if (!commentsSection) {
                console.error('Comments section not found');
                return;
            }
            
            const noCommentsMsg = document.getElementById('no-comments-message');
            if (noCommentsMsg) {
                noCommentsMsg.remove();
            }
            
            comment.current_user_avatar = `<?php echo addslashes($current_user_avatar_html); ?>`;
            
            const commentHTML = createCommentHTML(comment);
            commentsSection.insertAdjacentHTML('afterbegin', commentHTML);
            
            const newComment = commentsSection.firstElementChild;
            newComment.style.opacity = '0';
            newComment.style.transform = 'translateY(-20px)';
            
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    newComment.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    newComment.style.opacity = '1';
                    newComment.style.transform = 'translateY(0)';
                });
            });
            
            setTimeout(() => {
                newComment.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        }

        // Insert reply into DOM
        function insertReply(parentId, reply) {
            let repliesContainer = document.getElementById('replies-' + parentId);
            const parentComment = document.getElementById('comment-' + parentId);
            
            if (!parentComment) {
                console.error('Parent comment not found:', parentId);
                return;
            }
            
            if (!repliesContainer) {
                const replyForm = parentComment.querySelector('.reply-form-' + parentId);
                
                repliesContainer = document.createElement('div');
                repliesContainer.id = 'replies-' + parentId;
                repliesContainer.className = 'reply-indent mt-4 space-y-4';
                
                replyForm.parentNode.insertBefore(repliesContainer, replyForm.nextSibling);
            }
            
            repliesContainer.classList.remove('hidden');
            
            const replyHTML = createReplyHTML(reply);
            repliesContainer.insertAdjacentHTML('beforeend', replyHTML);
            
            const newReply = repliesContainer.lastElementChild;
            newReply.style.opacity = '0';
            newReply.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                newReply.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                newReply.style.opacity = '1';
                newReply.style.transform = 'translateX(0)';
            }, 10);
            
            updateReplyCount(parentId, 1);
        }

        // Update reply count button
        function updateReplyCount(parentId, change) {
            const parentComment = document.getElementById('comment-' + parentId);
            if (!parentComment) {
                console.error('Parent comment not found:', parentId);
                return;
            }
            
            const actionsDiv = parentComment.querySelector('.flex.gap-4.mt-3.text-xs');
            if (!actionsDiv) {
                console.error('Actions div not found');
                return;
            }
            
            let replyBtn = actionsDiv.querySelector('button[onclick*="toggleReplies"]');
            
            if (replyBtn) {
                const match = replyBtn.textContent.match(/(\d+)/);
                if (match) {
                    const currentCount = parseInt(match[0]);
                    const newCount = Math.max(0, currentCount + change);
                    
                    if (newCount === 0) {
                        replyBtn.remove();
                    } else {
                        const plural = newCount === 1 ? 'reply' : 'replies';
                        const isHidden = replyBtn.textContent.includes('Hide');
                        const action = isHidden ? 'Hide' : 'View';
                        replyBtn.textContent = `${action} ${newCount} ${plural}`;
                    }
                }
            } else if (change > 0) {
                const newReplyBtn = document.createElement('button');
                newReplyBtn.setAttribute('onclick', `toggleReplies(${parentId})`);
                newReplyBtn.className = 'text-primary hover:underline font-medium';
                newReplyBtn.id = 'toggle-replies-btn-' + parentId;
                newReplyBtn.textContent = `View ${change} ${change === 1 ? 'reply' : 'replies'}`;
                
                const replyButton = actionsDiv.children[1];
                if (replyButton) {
                    actionsDiv.insertBefore(newReplyBtn, replyButton.nextSibling);
                } else {
                    actionsDiv.appendChild(newReplyBtn);
                }
            }
        }

        // Like comment
        function likeComment(commentId) {
            const formData = new FormData();
            formData.append('action', 'like_comment');
            formData.append('comment_id', commentId);
            const { postType, postId } = getPostInfo();
            formData.append('post_type', postType);
            formData.append('post_id', postId);
            
            fetch('ajax_comment_handler_lecturer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const likeBtn = document.getElementById('like-btn-' + commentId);
                    const likeCount = document.getElementById('like-count-' + commentId);
                    
                    if (data.liked) {
                        likeBtn.classList.add('liked', 'text-red-500');
                    } else {
                        likeBtn.classList.remove('liked', 'text-red-500');
                    }
                    
                    likeCount.textContent = data.count;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to like comment', 'error');
            });
        }

        // Create comment HTML
        function createCommentHTML(comment) {
            const avatarHTML = comment.profile_img ? 
                `<img src="../${comment.profile_img}" alt="Profile" class="comment-avatar">` :
                `<img src="../assets/default-avatar.png" alt="Default" class="comment-avatar">`;
            
            const editDeleteButtons = comment.is_owner ? `
                <div class="dropdown dropdown-end">
                    <label tabindex="0" class="btn btn-ghost btn-xs">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                        </svg>
                    </label>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-32">
                        <li><a onclick="editComment(${comment.id})">Edit</a></li>
                        <li><a onclick="deleteComment(${comment.id})" class="text-error">Delete</a></li>
                    </ul>
                </div>
            ` : `
                <div class="dropdown dropdown-end">
                    <label tabindex="0" class="btn btn-ghost btn-xs">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                        </svg>
                    </label>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-32">
                        <li><a onclick="deleteComment(${comment.id})" class="text-error">Delete</a></li>
                    </ul>
                </div>
            `;
            
            return `
                <div class="comment-card rounded-xl p-4 animate-fade-in" id="comment-${comment.id}">
                    <div class="flex gap-3">
                        <div class="flex-shrink-0">
                            ${avatarHTML}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="bg-white rounded-lg p-4 shadow-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="font-bold text-sm text-gray-900">${comment.name}</h4>
                                        <p class="text-xs text-gray-500">${comment.commenter_type === 'lecturer' ? 'Lecturer' : comment.detail}</p>
                                    </div>
                                    ${editDeleteButtons}
                                </div>
                                <div class="comment-content-${comment.id}">
                                    <p class="mt-2 text-sm text-gray-700 leading-relaxed">${comment.content.replace(/\n/g, '<br>')}</p>
                                </div>
                                <form method="POST" class="edit-form-${comment.id} hidden mb-2" onsubmit="editCommentAjax(event)">
                                    <input type="hidden" name="action" value="edit_comment">
                                    <input type="hidden" name="comment_id" value="${comment.id}">
                                    <textarea name="content" class="textarea textarea-bordered w-full mb-2" rows="3">${comment.content}</textarea>
                                    <div class="flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-xs">Save</button>
                                        <button type="button" onclick="cancelEdit(${comment.id})" class="btn btn-ghost btn-xs">Cancel</button>
                                    </div>
                                </form>
                            </div>
                            <div class="flex gap-4 mt-3 text-xs items-center">
                                <span class="text-gray-500">Just now</span>
                                <button onclick="likeComment(${comment.id})" 
                                        class="like-btn flex items-center gap-1.5 hover:text-red-500 transition-colors text-gray-500"
                                        id="like-btn-${comment.id}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                    </svg>
                                    <span id="like-count-${comment.id}">0</span>
                                </button>
                                <button onclick="toggleReplyForm(${comment.id})" 
                                        class="flex items-center gap-1.5 hover:text-primary transition-colors font-medium">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                    </svg>
                                    Reply
                                </button>
                            </div>
                            <form method="POST" class="reply-form-${comment.id} hidden mt-4" onsubmit="addComment(event, ${comment.id})">
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="parent_id" value="${comment.id}">
                                <div class="flex gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <div class="w-8 h-8 rounded-full">
                                            ${comment.current_user_avatar || ''}
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <textarea name="content" class="textarea textarea-bordered textarea-sm w-full" placeholder="Write a reply..." rows="2" required></textarea>
                                        <div class="flex gap-2 mt-2">
                                            <button type="submit" class="btn btn-primary btn-xs">Reply</button>
                                            <button type="button" onclick="toggleReplyForm(${comment.id})" class="btn btn-ghost btn-xs">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
        }

        // Create reply HTML
        function createReplyHTML(reply) {
            const avatarHTML = reply.profile_img ? 
                `<img src="../${reply.profile_img}" alt="Profile" class="reply-avatar">` :
                `<img src="../assets/default-avatar.png" alt="Default" class="reply-avatar">`;
            
            const editDeleteButtons = reply.is_owner ? `
                <div class="dropdown dropdown-end">
                    <label tabindex="0" class="btn btn-ghost btn-xs">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                        </svg>
                    </label>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-32">
                        <li><a onclick="editComment(${reply.id})">Edit</a></li>
                        <li><a onclick="deleteComment(${reply.id})" class="text-error">Delete</a></li>
                    </ul>
                </div>
            ` : `
                <div class="dropdown dropdown-end">
                    <label tabindex="0" class="btn btn-ghost btn-xs">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                        </svg>
                    </label>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-32">
                        <li><a onclick="deleteComment(${reply.id})" class="text-error">Delete</a></li>
                    </ul>
                </div>
            `;
            
            return `
                <div class="reply-card p-3 animate-fade-in" id="comment-${reply.id}">
                    <div class="flex gap-3">
                        <div class="flex-shrink-0">
                            ${avatarHTML}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h5 class="font-bold text-sm text-gray-900">${reply.name}</h5>
                                    <p class="text-xs text-gray-500">${reply.commenter_type === 'lecturer' ? 'Lecturer' : reply.detail}</p>
                                </div>
                                ${editDeleteButtons}
                            </div>
                            <div class="comment-content-${reply.id}">
                                <p class="text-sm text-gray-700 leading-relaxed">${reply.content.replace(/\n/g, '<br>')}</p>
                            </div>
                            <form method="POST" class="edit-form-${reply.id} hidden mb-2" onsubmit="editCommentAjax(event)">
                                <input type="hidden" name="action" value="edit_comment">
                                <input type="hidden" name="comment_id" value="${reply.id}">
                                <textarea name="content" class="textarea textarea-bordered textarea-sm w-full mb-2" rows="2">${reply.content}</textarea>
                                <div class="flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-xs">Save</button>
                                    <button type="button" onclick="cancelEdit(${reply.id})" class="btn btn-ghost btn-xs">Cancel</button>
                                </div>
                            </form>
                            <div class="flex gap-3 mt-2 text-xs items-center">
                                <span class="text-gray-500">Just now</span>
                                <button onclick="likeComment(${reply.id})" 
                                        class="like-btn flex items-center gap-1.5 hover:text-red-500 transition-colors text-gray-500"
                                        id="like-btn-${reply.id}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                    </svg>
                                    <span id="like-count-${reply.id}">0</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = 'toast toast-top toast-end z-50';
            
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const icon = type === 'success' ? 
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />' :
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />';
            
            toast.innerHTML = `
                <div class="alert ${alertClass}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        ${icon}
                    </svg>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.transition = 'opacity 0.5s ease';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        // Toggle reply form
        function toggleReplyForm(commentId) {
            const form = document.querySelector('.reply-form-' + commentId);
            form.classList.toggle('hidden');
            if (!form.classList.contains('hidden')) {
                const textarea = form.querySelector('textarea');
                textarea.focus();
            }
        }
        
        // Toggle replies visibility
        function toggleReplies(commentId) {
            const replies = document.getElementById('replies-' + commentId);
            const btn = document.getElementById('toggle-replies-btn-' + commentId);
            
            if (replies.classList.contains('hidden')) {
                replies.classList.remove('hidden');
                const match = btn.textContent.match(/(\d+)/);
                if (match) {
                    const count = match[0];
                    btn.textContent = `Hide ${count} ${count == 1 ? 'reply' : 'replies'}`;
                }
            } else {
                replies.classList.add('hidden');
                const match = btn.textContent.match(/(\d+)/);
                if (match) {
                    const count = match[0];
                    btn.textContent = `View ${count} ${count == 1 ? 'reply' : 'replies'}`;
                }
            }
        }
        
        // Edit comment
        function editComment(commentId) {
            const content = document.querySelector('.comment-content-' + commentId);
            const editForm = document.querySelector('.edit-form-' + commentId);
            
            content.classList.add('hidden');
            editForm.classList.remove('hidden');
            
            const textarea = editForm.querySelector('textarea');
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        }
        
        // Cancel edit
        function cancelEdit(commentId) {
            const content = document.querySelector('.comment-content-' + commentId);
            const editForm = document.querySelector('.edit-form-' + commentId);
            
            content.classList.remove('hidden');
            editForm.classList.add('hidden');
        }
        
        // Delete comment with confirmation
        function deleteComment(commentId) {
            document.getElementById('deleteCommentId').value = commentId;
            document.getElementById('deleteModal').showModal();
        }
        
        // Auto-hide toast after 5 seconds
        setTimeout(() => {
            const toast = document.querySelector('.toast');
            if (toast) {
                toast.style.transition = 'opacity 0.5s ease';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            }
        }, 5000);

        // Participants modal functions (keeping your existing code)
        function filterParticipantsTable() {
            const searchInput = document.getElementById('participantSearch').value.toLowerCase();
            const rows = document.querySelectorAll('.participant-row');
            const noResults = document.getElementById('noResultsTable');
            const table = document.querySelector('.overflow-x-auto');
            const visibleCountSpan = document.getElementById('visibleCount');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const id = row.getAttribute('data-id');
                const programme = row.getAttribute('data-programme');
                
                if (name.includes(searchInput) || id.includes(searchInput) || programme.includes(searchInput)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            visibleCountSpan.textContent = visibleCount;

            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
                table.classList.add('hidden');
            } else {
                noResults.classList.add('hidden');
                table.classList.remove('hidden');
            }
        }

        let sortDirection = {};
        function sortTable(columnIndex) {
            const table = document.querySelector('#participantsTableBody');
            const rows = Array.from(table.querySelectorAll('tr.participant-row'));
            
            if (sortDirection[columnIndex] === undefined) {
                sortDirection[columnIndex] = 'asc';
            } else {
                sortDirection[columnIndex] = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
            }
            
            const isAscending = sortDirection[columnIndex] === 'asc';
            
            rows.sort((a, b) => {
                let aValue, bValue;
                
                if (columnIndex === 0) {
                    aValue = parseInt(a.querySelector('.badge').textContent.replace('#', ''));
                    bValue = parseInt(b.querySelector('.badge').textContent.replace('#', ''));
                } else if (columnIndex === 1) {
                    aValue = a.querySelector('.font-bold').textContent.toLowerCase();
                    bValue = b.querySelector('.font-bold').textContent.toLowerCase();
                } else if (columnIndex === 2) {
                    aValue = a.getAttribute('data-id');
                    bValue = b.getAttribute('data-id');
                } else if (columnIndex === 3) {
                    aValue = a.getAttribute('data-programme');
                    bValue = b.getAttribute('data-programme');
                } else if (columnIndex === 4) {
                    const cells = a.querySelectorAll('td');
                    aValue = cells[columnIndex].textContent.trim().toLowerCase();
                    bValue = b.querySelectorAll('td')[columnIndex].textContent.trim().toLowerCase();
                } else if (columnIndex === 5) {
                    const cells = a.querySelectorAll('td');
                    aValue = cells[columnIndex].textContent.trim();
                    bValue = b.querySelectorAll('td')[columnIndex].textContent.trim();
                }
                
                if (aValue < bValue) return isAscending ? -1 : 1;
                if (aValue > bValue) return isAscending ? 1 : -1;
                return 0;
            });
            
            rows.forEach((row, index) => {
                table.appendChild(row);
                row.querySelector('.badge').textContent = '#' + (index + 1);
            });
        }

        function exportParticipants() {
        const participants = [];
        const rows = document.querySelectorAll('.participant-row');
        const isPublicEvent = <?php echo json_encode(strtolower($event['evType']) === 'public'); ?>;
        
        rows.forEach((row, index) => {
            if (row.style.display !== 'none') {
                const cells = row.querySelectorAll('td');
                
                // Get name from the specific div
                const nameElement = cells[1].querySelector('.font-bold.text-sm');
                const name = nameElement ? nameElement.textContent.trim() : '';
                
                // Get email - it has an icon before it
                const emailElement = cells[1].querySelector('.text-xs.text-gray-500');
                const email = emailElement ? emailElement.textContent.trim() : '';
                
                // Get student ID from the span
                const studIdElement = cells[2].querySelector('span');
                const studId = studIdElement ? studIdElement.textContent.trim() : cells[2].textContent.trim();
                
                // Get programme from badge
                const programmeElement = cells[3].querySelector('.badge');
                const programme = programmeElement ? programmeElement.textContent.trim() : cells[3].textContent.trim();
                
                let dateText, timeText, isMember;
                
                if (isPublicEvent) {
                    // For public events, member status is in column 4
                    const memberBadge = cells[4].querySelector('.badge');
                    isMember = memberBadge && memberBadge.textContent.includes('Member') ? 'Yes' : 'No';
                    
                    // Date is in column 5
                    const dateSpan = cells[5].querySelector('.text-xs.font-medium');
                    const timeSpan = cells[5].querySelector('.text-xs.text-gray-500');
                    dateText = dateSpan ? dateSpan.textContent.trim() : '';
                    timeText = timeSpan ? timeSpan.textContent.trim() : '';
                } else {
                    // For private events, date is in column 4
                    const dateSpan = cells[4].querySelector('.text-xs.font-medium');
                    const timeSpan = cells[4].querySelector('.text-xs.text-gray-500');
                    dateText = dateSpan ? dateSpan.textContent.trim() : '';
                    timeText = timeSpan ? timeSpan.textContent.trim() : '';
                }
                
                const fullDateTime = `${dateText} ${timeText}`;
                
                const participantData = {
                    no: index + 1,
                    name: name.replace(/"/g, '""'), // Escape quotes
                    email: email.replace(/"/g, '""'),
                    id: studId.replace(/"/g, '""'),
                    programme: programme.replace(/"/g, '""'),
                    registered: fullDateTime.replace(/"/g, '""')
                };
                
                if (isPublicEvent) {
                    participantData.member = isMember;
                }
                
                participants.push(participantData);
            }
        });

        // Add BOM for proper UTF-8 encoding in Excel
        let csv = '\uFEFF';
        
        // Add headers
        if (isPublicEvent) {
            csv += 'No,Name,Email,Student ID,Programme,Club Member,Registration Date & Time\n';
        } else {
            csv += 'No,Name,Email,Student ID,Programme,Registration Date & Time\n';
        }
        
        // Add data rows
        participants.forEach(p => {
            if (isPublicEvent) {
                csv += `${p.no},"${p.name}","${p.email}","${p.id}","${p.programme}","${p.member}","${p.registered}"\n`;
            } else {
                csv += `${p.no},"${p.name}","${p.email}","${p.id}","${p.programme}","${p.registered}"\n`;
            }
        });

        // Create and download the file
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('href', url);
        
        // Clean filename - remove special characters
        const eventTitle = '<?php echo preg_replace('/[^a-zA-Z0-9_-]/', '_', $event['evTitle']); ?>';
        const dateStr = '<?php echo date('Y-m-d'); ?>';
        a.setAttribute('download', `event_participants_${eventTitle}_${dateStr}.csv`);
        
        a.style.visibility = 'hidden';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        // Show success message
        showToast('Participants list exported successfully!', 'success');
    }
    </script>
</body>
</html>



                                                                
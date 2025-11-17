<?php
session_start();
require_once "../config/connect.php";

// Check if student is logged in
if (!isset($_SESSION['stud_id'])) {
    header("Location: ../user_access.php");
    exit();
}

// Get announcement ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: updates.php");
    exit();
}

$announcement_id = $_GET['id'];
$student_id = $_SESSION['stud_id'];


// Get announcement details
$query = "SELECT a.annID, a.anntitle, a.content, a.annImg, a.annVid, a.annPosted_at, a.annType, 
          c.clubName, c.clubID, c.clubLogo, c.clubDescription, l.lectName
          FROM casannouncement a 
          LEFT JOIN clubsocieties c ON a.clubID = c.clubID
          LEFT JOIN lecturer l ON c.lectID = l.lectID
          WHERE a.annID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$announcement_id]);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if announcement exists
if (!$announcement) {
    header("Location: updates.php");
    exit();
}

// Check if student is member of this club
$query = "SELECT * FROM membership WHERE studID = ? AND clubID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id, $announcement['clubID']]);
$is_member = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all comments with user info and like counts
$query = "SELECT c.*, 
          s.studName, s.studProfileImg,
          l.lectName, l.lectProfileImg,
          (SELECT COUNT(*) FROM comment_likes WHERE commentID = c.commentID) as like_count,
          (SELECT COUNT(*) FROM comment_likes WHERE commentID = c.commentID AND liker_type = 'student' AND liker_id = ?) as user_liked,
          (SELECT COUNT(*) FROM comments WHERE parent_comment_id = c.commentID AND is_deleted = 0) as reply_count
          FROM comments c
          LEFT JOIN student s ON c.commenter_type = 'student' AND CAST(c.commenter_id AS UNSIGNED) = CAST(s.studID AS UNSIGNED)
          LEFT JOIN lecturer l ON c.commenter_type = 'lecturer' AND CAST(c.commenter_id AS UNSIGNED) = CAST(l.lectID AS UNSIGNED)
          WHERE c.post_type = 'announcement' 
            AND c.post_id = ? 
            AND (c.is_deleted = 0 
                 AND (c.parent_comment_id IS NULL 
                      OR (SELECT is_deleted FROM comments WHERE commentID = c.parent_comment_id) = 0))
          ORDER BY c.created_at ASC";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id, $announcement_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize comments into parent and replies
$parent_comments = [];
$replies = [];

foreach ($comments as $comment) {
    if ($comment['parent_comment_id'] === null) {
        $parent_comments[] = $comment;
    } else {
        $replies[$comment['parent_comment_id']][] = $comment;
    }
}

// Get related announcements
$query = "SELECT annID, anntitle, annPosted_at, annImg 
          FROM casannouncement 
          WHERE clubID = ? AND annID != ? 
          ORDER BY annPosted_at DESC 
          LIMIT 3";
$stmt = $conn->prepare($query);
$stmt->execute([$announcement['clubID'], $announcement_id]);
$related_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current student info for display
$query = "SELECT studName, studProfileImg FROM student WHERE studID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$student_id]);
$current_student = $stmt->fetch(PDO::FETCH_ASSOC);

// Prepare current user avatar HTML for JavaScript
$current_user_avatar_html = '';
if (!empty($current_student['studProfileImg'])) {
    $current_user_avatar_html = '<img src="../' . htmlspecialchars($current_student['studProfileImg']) . '" alt="' . htmlspecialchars($current_student['studName']) . '">';
} else {
    $current_user_avatar_html = '<div class="bg-gradient-to-br from-purple-400 to-blue-400 text-white flex items-center justify-center text-xs font-bold">' . strtoupper(substr($current_student['studName'], 0, 1)) . '</div>';
}


// Get total comment count
$total_comments = count($comments);
foreach ($comments as $comment) {
    $total_comments += $comment['reply_count'];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($announcement['anntitle']); ?> - Student Club Management</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .content-section {
            line-height: 1.8;
        }
        .content-section p {
            margin-bottom: 1rem;
        }
        .hero-image {
            max-height: 500px;
            object-fit: cover;
        }
        .comment-card {
            transition: all 0.3s ease;
        }
        .comment-card:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        .reply-indent {
            margin-left: 3rem;
            border-left: 3px solid #e5e7eb;
            padding-left: 1rem;
        }
        .like-btn.liked {
            color: #ef4444;
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .comment-actions {
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .comment-card:hover .comment-actions {
            opacity: 1;
        }

        .avatar > div {
            border-radius: 50% !important;
            overflow: hidden;
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .avatar .bg-gradient-to-br {
            border-radius: 50% !important;
        }

        /* Carousel enhancements */
        .carousel-item {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
        }

        /* Active dot indicator */
        .carousel-dot {
            transition: all 0.3s ease;
        }

        .carousel-dot:hover,
        .carousel-dot.active {
            background-color: white !important;
            transform: scale(1.2);
        }

        /* Smooth transitions for media */
        .carousel-item video,
        .carousel-item img {
            transition: opacity 0.3s ease;
        }

        /* Custom scrollbar for carousel (optional) */
        .carousel::-webkit-scrollbar {
            display: none;
        }

        /* Loading state for images */
        .carousel-item img {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        .carousel-item img[src] {
            background: none;
            animation: none;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Enhanced button hover effects */
        .btn-circle:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body class="bg-base-200">
    <div class="drawer">
        <input id="my-drawer-3" type="checkbox" class="drawer-toggle" /> 
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

            <!-- Main Content -->
            <div class="container mx-auto px-4 py-8 mt-20">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Announcement Card -->
                        <div class="card bg-base-100 shadow-xl animate-fade-in">
                            <!-- Featured Media Carousel -->
                            <?php 
                            // Collect all media into an array
                            $media_items = [];
                            if (!empty($announcement['annImg'])) {
                                $media_items[] = ['type' => 'image', 'src' => $announcement['annImg']];
                            }if (!empty($announcement['annVid'])) {
                                $media_items[] = ['type' => 'video', 'src' => $announcement['annVid']];
                            }
                            
                            ?>

                            <?php if (!empty($media_items)): ?>
                            <figure class="relative">
                                <div class="carousel w-full rounded-t-2xl overflow-hidden bg-black" id="mediaCarousel">
                                    <?php foreach ($media_items as $index => $media): ?>
                                        <div id="slide<?php echo $index + 1; ?>" class="carousel-item relative w-full">
                                            <?php if ($media['type'] === 'image'): ?>
                                                <!-- Image -->
                                                <img src="../<?php echo htmlspecialchars($media['src']); ?>" 
                                                    alt="<?php echo htmlspecialchars($announcement['anntitle']); ?>"
                                                    class="w-full max-h-[600px] object-contain mx-auto"
                                                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect fill=%22%23ddd%22 width=%22400%22 height=%22300%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%2220%22%3EImage not available%3C/text%3E%3C/svg%3E'">
                                            <?php else: ?>
                                                <!-- Video -->
                                                <video controls class="w-full max-h-[600px] object-contain mx-auto" controlsList="nodownload">
                                                    <source src="../<?php echo htmlspecialchars($media['src']); ?>" type="video/mp4">
                                                    Your browser does not support the video tag.
                                                </video>
                                                
                                            <?php endif; ?>
                                            
                                            <!-- Navigation Arrows (only show if more than 1 media item) -->
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
                                
                                <!-- Instagram-style Dots Indicator (only show if more than 1 media item) -->
                                <?php if (count($media_items) > 1): ?>
                                    <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex gap-2 z-10">
                                        <?php for ($i = 1; $i <= count($media_items); $i++): ?>
                                            <a href="#slide<?php echo $i; ?>" 
                                            class="carousel-dot w-2 h-2 rounded-full bg-white/50 hover:bg-white transition-all duration-300"
                                            data-slide="<?php echo $i; ?>"></a>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Media Counter Badge -->
                                <?php if (count($media_items) > 1): ?>
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
                                <div class="absolute top-4 left-4 z-2 flex gap-2">
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

                            <div class="card-body">
                                <!-- Header -->
                                <div class="flex items-center gap-2 mb-4">
                                    <span class="badge bg-gradient-to-r from-blue-500 to-blue-600 text-white border-none">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                                        </svg>
                                        Announcement
                                    </span>
                                    <?php if ($announcement['annType'] === 'Private'): ?>
                                    <span class="badge badge-secondary">Members Only</span>
                                    <?php endif; ?>
                                    <span class="text-sm text-gray-500 ml-auto">
                                        Posted on <?php echo date('F j, Y \a\t g:i A', strtotime($announcement['annPosted_at'])); ?>
                                    </span>
                                </div>

                                <!-- Title -->
                                <h1 class="text-4xl font-bold mb-4">
                                    <?php echo htmlspecialchars($announcement['anntitle']); ?>
                                </h1>

                                <!-- Club Info -->
                                <div class="flex items-center gap-3 mb-6 p-4 bg-base-200 rounded-lg">
                                    <div class="avatar">
                                        <div class="w-12 h-12 rounded-full">
                                            <?php if (!empty($announcement['clubLogo'])): ?>
                                            <img src="../<?php echo htmlspecialchars($announcement['clubLogo']); ?>" 
                                                alt="<?php echo htmlspecialchars($announcement['clubName']); ?>">
                                            <?php else: ?>
                                            <div class="bg-gradient-to-br from-purple-400 to-blue-400 text-white flex items-center justify-center text-xl font-bold">
                                                <?php echo strtoupper(substr($announcement['clubName'], 0, 2)); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="font-bold">
                                            <a href="club_profile.php?id=<?php echo $announcement['clubID']; ?>" class="link link-hover text-purple-600">
                                                <?php echo htmlspecialchars($announcement['clubName']); ?>
                                            </a>
                                        </h3>
                                        <?php if (!empty($announcement['lectName'])): ?>
                                            <p class="text-sm text-gray-600">Advisor: <?php echo htmlspecialchars($announcement['lectName']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="divider"></div>

                                <!-- Content -->
                                <div class="content-section prose max-w-none">
                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                </div>

                                <div class="divider"></div>

                                <!-- Actions -->
                                <div class="card-actions justify-between items-center">
                                    <?php if (!$is_member): ?>
                                        <a href="club_profile.php?id=<?php echo $announcement['clubID']; ?>" 
                                        class="btn bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none hover:shadow-lg">
                                            Join This Club
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <div class="badge badge-success gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            You're a member
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Comments Section -->
                        <div class="card bg-base-100 shadow-xl animate-fade-in">
                            <div class="card-body">
                                <h2 class="card-title text-2xl mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    Comments (<?php echo $total_comments; ?>)
                                </h2>

                                <!-- Add Comment Form -->
                                <form method="POST" class="mb-6" onsubmit="addComment(event)">
                                    <input type="hidden" name="action" value="add_comment">
                                    <div class="flex gap-3">
                                        <div class="avatar">
                                            <div class="w-10 h-10 rounded-full">
                                                <?php if (!empty($current_student['studProfileImg'])): ?>
                                                <img src="../uploads/student_profiles/<?php echo htmlspecialchars($current_student['studProfileImg']); ?>" 
                                                    alt="<?php echo htmlspecialchars($current_student['studName']); ?>">
                                                <?php else: ?>
                                                <div class="bg-gradient-to-br from-purple-400 to-blue-400 text-white flex items-center justify-center text-sm font-bold">
                                                    <?php echo strtoupper(substr($current_student['studName'], 0, 1)); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <textarea name="content" 
                                                    class="textarea textarea-bordered w-full" 
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
                                <div class="space-y-6">
                                    <?php if (empty($parent_comments)): ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        <p class="text-lg font-semibold mb-2">No comments yet</p>
                                        <p class="text-sm">Be the first to share your thoughts!</p>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($parent_comments as $comment): ?>
                                        <div class="comment-card p-4 rounded-lg" id="comment-<?php echo $comment['commentID']; ?>">
                                            <!-- Comment Header -->
                                            <div class="flex gap-3">
                                                <div class="avatar">
                                                    <div class="w-10 h-10 rounded-full">
                                                        <?php 
                                                        // Determine commenter info based on type
                                                        $commenterName = $comment['commenter_type'] === 'lecturer' ? $comment['lectName'] : $comment['studName'];
                                                        $commenterImg = $comment['commenter_type'] === 'lecturer' ? $comment['lectProfileImg'] : $comment['studProfileImg'];
                                                        $commenterInitial = strtoupper(substr($commenterName, 0, 1));
                                                        $avatarColor = $comment['commenter_type'] === 'lecturer' ? 'from-amber-400 to-orange-600' : 'from-blue-400 to-blue-600';
                                                        ?>
                                                        
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
                                                </div>
                                                
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                                        <span class="font-bold"><?php echo htmlspecialchars($commenterName); ?></span>
                                                        
                                                        <?php if ($comment['commenter_type'] === 'lecturer'): ?>
                                                        <span class="badge badge-sm bg-gradient-to-r from-amber-500 to-orange-500 text-white border-none">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                                                            </svg>
                                                            Lecturer
                                                        </span>
                                                        <?php endif; ?>
                                                        
                                                        <span class="text-xs text-gray-500">
                                                            <?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?>
                                                        </span>
                                                        <?php if ($comment['is_edited']): ?>
                                                        <span class="text-xs text-gray-400 italic">(edited)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Comment Content -->
                                                    <div class="comment-content-<?php echo $comment['commentID']; ?>">
                                                        <p class="text-gray-700 mb-2"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                                    </div>
                                                    
                                                    <!-- Edit Form (Hidden by default) -->
                                                    <form method="POST" class="edit-form-<?php echo $comment['commentID']; ?> hidden mb-2" onsubmit="editCommentAjax(event)">
                                                        <input type="hidden" name="action" value="edit_comment">
                                                        <input type="hidden" name="comment_id" value="<?php echo $comment['commentID']; ?>">
                                                        <textarea name="content" class="textarea textarea-bordered w-full mb-2" rows="3"><?php echo htmlspecialchars($comment['content']); ?></textarea>
                                                        <div class="flex gap-2">
                                                            <button type="submit" class="btn btn-primary btn-xs">Save</button>
                                                            <button type="button" onclick="cancelEdit(<?php echo $comment['commentID']; ?>)" class="btn btn-ghost btn-xs">Cancel</button>
                                                        </div>
                                                    </form>
                                                    
                                                    <!-- Comment Actions -->
                                                    <div class="flex items-center gap-4 text-sm">
                                                        <button onclick="likeComment(<?php echo $comment['commentID']; ?>)" 
                                                                class="like-btn flex items-center gap-1 hover:text-red-500 transition <?php echo $comment['user_liked'] ? 'liked text-red-500' : 'text-gray-500'; ?>"
                                                                id="like-btn-<?php echo $comment['commentID']; ?>">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                                            </svg>
                                                            <span id="like-count-<?php echo $comment['commentID']; ?>"><?php echo $comment['like_count']; ?></span>
                                                        </button>
                                                        
                                                        <button onclick="toggleReplyForm(<?php echo $comment['commentID']; ?>)" class="text-gray-500 hover:text-purple-600 transition">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v2a1 1 0 11-2 0v-2a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                            </svg>
                                                            Reply
                                                        </button>

                                                        <?php if ($comment['reply_count'] > 0): ?>
                                                            <button onclick="toggleReplies(<?php echo $comment['commentID']; ?>)" 
                                                                    class="text-primary hover:underline font-medium">
                                                                View <?php echo $comment['reply_count']; ?> <?php echo $comment['reply_count'] == 1 ? 'reply' : 'replies'; ?>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($comment['commenter_id'] == $student_id && $comment['commenter_type'] === 'student'): ?>
                                                        <div class="comment-actions flex gap-2">
                                                            <button onclick="editComment(<?php echo $comment['commentID']; ?>)" class="text-gray-500 hover:text-blue-600 transition">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                </svg>
                                                            </button>
                                                            <button onclick="deleteComment(<?php echo $comment['commentID']; ?>)" class="text-gray-500 hover:text-red-600 transition">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Reply Form (Hidden by default) -->
                                                    <form method="POST" class="reply-form-<?php echo $comment['commentID']; ?> hidden mt-3" onsubmit="addComment(event, <?php echo $comment['commentID']; ?>)">
                                                        <input type="hidden" name="action" value="add_comment">
                                                        <input type="hidden" name="parent_id" value="<?php echo $comment['commentID']; ?>">
                                                        <div class="flex gap-3">
                                                            <div class="avatar">
                                                                <div class="w-8 h-8 rounded-full">
                                                                    <?php if (!empty($current_student['studProfileImg'])): ?>
                                                                    <img src="../uploads/student_profiles/<?php echo htmlspecialchars($current_student['studProfileImg']); ?>" 
                                                                        alt="<?php echo htmlspecialchars($current_student['studName']); ?>">
                                                                    <?php else: ?>
                                                                    <div class="bg-gradient-to-br from-purple-400 to-blue-400 text-white flex items-center justify-center text-xs font-bold">
                                                                        <?php echo strtoupper(substr($current_student['studName'], 0, 1)); ?>
                                                                    </div>
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
                                                    
                                                    <!-- Replies (Hidden by default) -->
                                                    <?php if (isset($replies[$comment['commentID']])): ?>
                                                    <div id="replies-<?php echo $comment['commentID']; ?>" class="hidden reply-indent mt-4 space-y-4">
                                                        <?php foreach ($replies[$comment['commentID']] as $reply): ?>
                                                        <div class="comment-card p-3 rounded-lg" id="comment-<?php echo $reply['commentID']; ?>">
                                                            <div class="flex gap-3">
                                                                <div class="avatar">
                                                                    <div class="w-8 h-8 rounded-full">
                                                                        <?php 
                                                                        // Determine reply commenter info
                                                                        $replyCommenterName = $reply['commenter_type'] === 'lecturer' ? $reply['lectName'] : $reply['studName'];
                                                                        $replyCommenterImg = $reply['commenter_type'] === 'lecturer' ? $reply['lectProfileImg'] : $reply['studProfileImg'];
                                                                        $replyCommenterInitial = strtoupper(substr($replyCommenterName, 0, 1));
                                                                        $replyAvatarColor = $reply['commenter_type'] === 'lecturer' ? 'from-amber-400 to-orange-600' : 'from-green-400 to-green-600';
                                                                        ?>
                                                                        
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
                                                                </div>
                                                                
                                                                <div class="flex-1">
                                                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                                                        <span class="font-bold text-sm"><?php echo htmlspecialchars($replyCommenterName); ?></span>
                                                                        
                                                                        <?php if ($reply['commenter_type'] === 'lecturer'): ?>
                                                                        <span class="badge badge-xs bg-gradient-to-r from-amber-500 to-orange-500 text-white border-none">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5 mr-0.5" viewBox="0 0 20 20" fill="currentColor">
                                                                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                                                                            </svg>
                                                                            Lecturer
                                                                        </span>
                                                                        <?php endif; ?>
                                                                        
                                                                        <span class="text-xs text-gray-500">
                                                                            <?php echo date('M j, Y \a\t g:i A', strtotime($reply['created_at'])); ?>
                                                                        </span>
                                                                        <?php if ($reply['is_edited']): ?>
                                                                        <span class="text-xs text-gray-400 italic">(edited)</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    
                                                                    <!-- Reply Content -->
                                                                    <div class="comment-content-<?php echo $reply['commentID']; ?>">
                                                                        <p class="text-sm text-gray-700 mb-2"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
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
                                                                    
                                                                    <!-- Reply Actions -->
                                                                    <div class="flex items-center gap-4 text-xs">
                                                                        <button onclick="likeComment(<?php echo $reply['commentID']; ?>)" 
                                                                                class="like-btn flex items-center gap-1 hover:text-red-500 transition <?php echo $reply['user_liked'] ? 'liked text-red-500' : 'text-gray-500'; ?>"
                                                                                id="like-btn-<?php echo $reply['commentID']; ?>">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                                                            </svg>
                                                                            <span id="like-count-<?php echo $reply['commentID']; ?>"><?php echo $reply['like_count']; ?></span>
                                                                        </button>
                                                                        
                                                                        <?php if ($reply['commenter_id'] == $student_id && $reply['commenter_type'] === 'student'): ?>
                                                                        <div class="comment-actions flex gap-2">
                                                                            <button onclick="editComment(<?php echo $reply['commentID']; ?>)" class="text-gray-500 hover:text-blue-600 transition">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                                </svg>
                                                                            </button>
                                                                            <button onclick="deleteComment(<?php echo $reply['commentID']; ?>)" class="text-gray-500 hover:text-red-600 transition">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                                                </svg>
                                                                            </button>
                                                                        </div>
                                                                        <?php endif; ?>
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
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Club Info Card -->
                        <div class="card bg-base-100 shadow-xl animate-fade-in">
                            <div class="card-body">
                                <h3 class="card-title text-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd" />
                                    </svg>
                                    About This Club
                                </h3>
                                <div class="divider my-2"></div>
                                
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <div class="avatar">
                                            <div class="w-16 h-16 rounded-full">
                                                <?php if (!empty($announcement['clubLogo'])): ?>
                                                <img src="../<?php echo htmlspecialchars($announcement['clubLogo']); ?>" 
                                                    alt="<?php echo htmlspecialchars($announcement['clubName']); ?>">
                                                <?php else: ?>
                                                <div class="bg-gradient-to-br from-purple-400 to-blue-400 text-white flex items-center justify-center text-2xl font-bold">
                                                    <?php echo strtoupper(substr($announcement['clubName'], 0, 2)); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <h4 class="font-bold"><?php echo htmlspecialchars($announcement['clubName']); ?></h4>
                                            <?php if (!empty($announcement['lectName'])): ?>
                                            <p class="text-xs text-gray-500">Advisor: <?php echo htmlspecialchars($announcement['lectName']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <p class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($announcement['clubDescription']); ?>
                                    </p>
                                    
                                    <?php if ($is_member): ?>
                                        <div class="alert alert-success">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="text-sm">You're a member</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="club_profile.php?id=<?php echo $announcement['clubID']; ?>" 
                                    class="btn btn-block btn-outline btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                        </svg>
                                        View Club Details
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Related Announcements -->
                        <?php if (!empty($related_announcements)): ?>
                        <div class="card bg-base-100 shadow-xl animate-fade-in">
                            <div class="card-body">
                                <h3 class="card-title text-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z" />
                                        <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z" />
                                    </svg>
                                    More Announcements
                                </h3>
                                <div class="divider my-2"></div>
                                
                                <div class="space-y-4">
                                    <?php foreach ($related_announcements as $related): ?>
                                    <a href="announcement_post.php?id=<?php echo $related['annID']; ?>" 
                                    class="block hover:bg-base-200 p-3 rounded-lg transition">
                                        <div class="flex gap-3">
                                            <?php if (!empty($related['annImg'])): ?>
                                            <div class="avatar">
                                                <div class="w-16 h-16 rounded">
                                                    <img src="../<?php echo htmlspecialchars($related['annImg']); ?>" 
                                                        alt="<?php echo htmlspecialchars($related['anntitle']); ?>"
                                                        onerror="this.parentElement.innerHTML='<div class=\'w-16 h-16 rounded bg-gradient-to-br from-blue-400 to-blue-600\'></div>'">
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="flex-1">
                                                <h4 class="font-bold text-sm line-clamp-2">
                                                    <?php echo htmlspecialchars($related['anntitle']); ?>
                                                </h4>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <?php echo date('M j, Y', strtotime($related['annPosted_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div class="card bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-xl animate-fade-in">
                            <div class="card-body">
                                <h3 class="card-title text-lg">Quick Actions</h3>
                                <div class="space-y-2">
                                    <a href="available_clubs.php" class="btn btn-sm btn-ghost text-white w-full justify-start">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                                        </svg>
                                        Browse Clubs
                                    </a>
                                    <a href="dashboard.php" class="btn btn-sm btn-ghost text-white w-full justify-start">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                        </svg>
                                        Event Calendar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <dialog id="deleteModal" class="modal">
                <div class="modal-box">
                    <h3 class="font-bold text-lg mb-4">Delete Comment?</h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to delete this comment? This action cannot be undone.</p>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete_comment">
                        <input type="hidden" name="comment_id" id="deleteCommentId">
                        <div class="modal-action">
                            <button type="button" onclick="document.getElementById('deleteModal').close()" class="btn btn-ghost">Cancel</button>
                            <button type="button" onclick="deleteCommentAjax(document.getElementById('deleteCommentId').value)" class="btn btn-error">Delete</button>
                        </div>
                    </form>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button>close</button>
                </form>
            </dialog>

            <!-- Footer -->
            <?php include "includes/footer.php"; ?>
        </div>
        <!-- Include Mobile Drawer -->
        <?php include "includes/mobile_drawer.php"; ?>
    </div>

    <script>
    // Carousel slide counter and dot indicator
    const carouselDots = document.querySelectorAll('.carousel-dot');
    const carousel = document.getElementById('mediaCarousel');

    if (carousel) {
        // Update active dot and counter
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
        
        // Listen for scroll events on carousel
        carousel.addEventListener('scroll', updateCarousel);
        
        // Initial update
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
                // Swipe left - next
                const nextBtn = carousel.querySelector('.btn-circle:last-of-type');
                if (nextBtn) nextBtn.click();
            }
            if (touchEndX > touchStartX + 50) {
                // Swipe right - previous
                const prevBtn = carousel.querySelector('.btn-circle:first-of-type');
                if (prevBtn) prevBtn.click();
            }
        }
    }

    // ============================================
    // COMMENT FUNCTIONS
    // ============================================

    // Get post type and ID from the page
    function getPostInfo() {
        const urlParams = new URLSearchParams(window.location.search);
        const postId = urlParams.get('id');
        
        let postType = 'announcement';
        if (window.location.pathname.includes('event')) {
            postType = 'event';
        } else if (window.location.pathname.includes('activity')) {
            postType = 'activity';
        }
        
        return { postType, postId };
    }

    // Update comment count in header
    function updateCommentCount(change) {
        console.log('updateCommentCount called with change:', change);
        
        const countElement = document.querySelector('.card-title.text-2xl.mb-4');
        
        if (!countElement) {
            console.error('Comment count element not found');
            return;
        }
        
        // Match "Comments (number)"
        const match = countElement.textContent.match(/Comments\s*\((\d+)\)/i);
        
        if (match) {
            const currentCount = parseInt(match[1]);
            const newCount = Math.max(0, currentCount + change);
            
            console.log(`Updating count from ${currentCount} to ${newCount}`);
            
            // Replace in innerHTML to preserve SVG
            countElement.innerHTML = countElement.innerHTML.replace(
                /Comments\s*\(\d+\)/i,
                `Comments (${newCount})`
            );
        } else {
            console.error('Could not find comment count pattern');
        }
    }

    // Add comment with AJAX
    function addComment(event, parentId = null) {
        console.log('addComment called!', event, parentId);
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
        
        fetch('ajax_comment_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Server response:', data);
            
            if (data.success) {
                form.reset();
                
                if (parentId) {
                    console.log('Inserting reply to parent:', parentId);
                    insertReply(parentId, data.comment);
                    form.classList.add('hidden');
                } else {
                    console.log('Inserting parent comment');
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
        
        fetch('ajax_comment_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const contentDiv = document.querySelector('.comment-content-' + commentId);
                const newContent = formData.get('content');
                contentDiv.querySelector('p').innerHTML = newContent.replace(/\n/g, '<br>');
                
                const commentHeader = contentDiv.closest('.flex-1').querySelector('.flex.items-center');
                if (!commentHeader.querySelector('.italic')) {
                    const editedBadge = document.createElement('span');
                    editedBadge.className = 'text-xs text-gray-400 italic';
                    editedBadge.textContent = '(edited)';
                    commentHeader.appendChild(editedBadge);
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
    function deleteCommentAjax(commentId) {
        console.log('deleteCommentAjax called for comment:', commentId);
        
        const formData = new FormData();
        formData.append('action', 'delete_comment');
        formData.append('comment_id', commentId);
        const { postType, postId } = getPostInfo();
        formData.append('post_type', postType);
        formData.append('post_id', postId);
        
        fetch('ajax_comment_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Delete response:', data);
            
            if (data.success) {
                const commentElement = document.getElementById('comment-' + commentId);
                
                if (!commentElement) {
                    console.error('Comment element not found:', commentId);
                    return;
                }
                
                // Check if this is a reply (inside reply-indent)
                const isReply = commentElement.closest('.reply-indent') !== null;
                
                let totalToDelete = 1; // Always count the comment itself
                
                // If it's a parent comment, count all its replies
                if (!isReply) {
                    const repliesContainer = document.getElementById('replies-' + commentId);
                    if (repliesContainer) {
                        const replyElements = repliesContainer.querySelectorAll('.comment-card');
                        totalToDelete += replyElements.length;
                        console.log(`Parent comment has ${replyElements.length} replies. Total to delete: ${totalToDelete}`);
                    }
                } else {
                    console.log('Deleting a reply, count: 1');
                }
                
                // Animate removal
                commentElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                commentElement.style.opacity = '0';
                commentElement.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    // If it's a reply, update parent's reply count BEFORE removal
                    if (isReply) {
                        const parentReplyContainer = commentElement.closest('.reply-indent');
                        if (parentReplyContainer) {
                            const parentId = parentReplyContainer.id.replace('replies-', '');
                            console.log('Updating parent reply count for:', parentId);
                            
                            updateReplyCount(parentId, -1);
                            
                            // Check if this is the last reply
                            const remainingReplies = parentReplyContainer.querySelectorAll('.comment-card');
                            if (remainingReplies.length === 1) { // Will be 0 after removal
                                parentReplyContainer.classList.add('hidden');
                                console.log('Hiding replies container (was last reply)');
                            }
                        }
                    }
                    
                    // Remove the comment
                    commentElement.remove();
                    console.log('Comment removed from DOM');
                    
                    // Update total count
                    console.log('Calling updateCommentCount with:', -totalToDelete);
                    updateCommentCount(-totalToDelete);
                    
                    // Check if no comments left
                    const commentsSection = document.querySelector('.card-body .space-y-6');
                    if (commentsSection) {
                        const allComments = commentsSection.querySelectorAll('.comment-card');
                        const topLevelComments = Array.from(allComments).filter(comment => 
                            !comment.closest('.reply-indent')
                        );
                        
                        console.log('Remaining top-level comments:', topLevelComments.length);
                        
                        if (topLevelComments.length === 0) {
                            commentsSection.innerHTML = `
                                <div class="text-center py-8 text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <p class="text-lg font-semibold mb-2">No comments yet</p>
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
        const commentsSection = document.querySelector('.card-body .space-y-6');
        
        if (!commentsSection) {
            console.error('Comments section not found');
            return;
        }
        
        const noCommentsMsg = commentsSection.querySelector('.text-center.py-8');
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
        console.log('insertReply called for parent:', parentId);
        
        let repliesContainer = document.getElementById('replies-' + parentId);
        const parentComment = document.getElementById('comment-' + parentId);
        
        if (!parentComment) {
            console.error('Parent comment not found:', parentId);
            return;
        }
        
        if (!repliesContainer) {
            console.log('Creating new replies container');
            
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
        console.log('Reply inserted successfully');
    }

    // Update reply count button
    function updateReplyCount(parentId, change) {
        console.log('updateReplyCount called for parent:', parentId, 'change:', change);
        
        const parentComment = document.getElementById('comment-' + parentId);
        if (!parentComment) {
            console.error('Parent comment not found:', parentId);
            return;
        }
        
        const actionsDiv = parentComment.querySelector('.flex.items-center.gap-4.text-sm');
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
                
                console.log(`Updating reply count from ${currentCount} to ${newCount}`);
                
                if (newCount === 0) {
                    replyBtn.remove();
                    console.log('Removed reply button (count is 0)');
                } else {
                    const plural = newCount === 1 ? 'reply' : 'replies';
                    const isHidden = replyBtn.textContent.includes('Hide');
                    const action = isHidden ? 'Hide' : 'View';
                    replyBtn.textContent = `${action} ${newCount} ${plural}`;
                }
            }
        } else if (change > 0) {
            console.log('Creating new reply button');
            
            const newReplyBtn = document.createElement('button');
            newReplyBtn.setAttribute('onclick', `toggleReplies(${parentId})`);
            newReplyBtn.className = 'text-primary hover:underline font-medium';
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
        
        fetch('ajax_comment_handler.php', {
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
        const avatarColor = comment.commenter_type === 'lecturer' ? 'from-amber-400 to-orange-600' : 'from-blue-400 to-blue-600';
        const commenterBadge = comment.commenter_type === 'lecturer' ? `
            <span class="badge badge-sm bg-gradient-to-r from-amber-500 to-orange-500 text-white border-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                </svg>
                Lecturer
            </span>
        ` : '';
        
        const avatarHTML = comment.profile_img ? 
            `<img src="../${comment.profile_img}" alt="${comment.name}">` :
            `<div class="bg-gradient-to-br ${avatarColor} text-white flex items-center justify-center text-sm font-bold">
                ${comment.name.charAt(0).toUpperCase()}
            </div>`;
        
        const editDeleteButtons = comment.is_owner ? `
            <div class="comment-actions flex gap-2">
                <button onclick="editComment(${comment.id})" class="text-gray-500 hover:text-blue-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                    </svg>
                </button>
                <button onclick="deleteComment(${comment.id})" class="text-gray-500 hover:text-red-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        ` : '';
        
        return `
            <div class="comment-card p-4 rounded-lg" id="comment-${comment.id}">
                <div class="flex gap-3">
                    <div class="avatar">
                        <div class="w-10 h-10 rounded-full">
                            ${avatarHTML}
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="font-bold">${comment.name}</span>
                            ${commenterBadge}
                            <span class="text-xs text-gray-500">Just now</span>
                        </div>
                        <div class="comment-content-${comment.id}">
                            <p class="text-gray-700 mb-2">${comment.content.replace(/\n/g, '<br>')}</p>
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
                        <div class="flex items-center gap-4 text-sm">
                            <button onclick="likeComment(${comment.id})" 
                                    class="like-btn flex items-center gap-1 hover:text-red-500 transition text-gray-500"
                                    id="like-btn-${comment.id}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                </svg>
                                <span id="like-count-${comment.id}">0</span>
                            </button>
                            <button onclick="toggleReplyForm(${comment.id})" class="text-gray-500 hover:text-purple-600 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v2a1 1 0 11-2 0v-2a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                                Reply
                            </button>
                            ${editDeleteButtons}
                        </div>
                        <form method="POST" class="reply-form-${comment.id} hidden mt-3" onsubmit="addComment(event, ${comment.id})">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="parent_id" value="${comment.id}">
                            <div class="flex gap-3">
                                <div class="avatar">
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
        const avatarColor = reply.commenter_type === 'lecturer' ? 'from-amber-400 to-orange-600' : 'from-green-400 to-green-600';
        const commenterBadge = reply.commenter_type === 'lecturer' ? `
            <span class="badge badge-xs bg-gradient-to-r from-amber-500 to-orange-500 text-white border-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5 mr-0.5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L 5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                </svg>
                Lecturer
            </span>
        ` : '';
        
        const avatarHTML = reply.profile_img ? 
            `<img src="../${reply.profile_img}" alt="${reply.name}">` :
            `<div class="bg-gradient-to-br ${avatarColor} text-white flex items-center justify-center text-xs font-bold">
                ${reply.name.charAt(0).toUpperCase()}
            </div>`;
        
        const editDeleteButtons = reply.is_owner ? `
            <div class="comment-actions flex gap-2">
                <button onclick="editComment(${reply.id})" class="text-gray-500 hover:text-blue-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                    </svg>
                </button>
                <button onclick="deleteComment(${reply.id})" class="text-gray-500 hover:text-red-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        ` : '';
        
        return `
            <div class="comment-card p-3 rounded-lg" id="comment-${reply.id}">
                <div class="flex gap-3">
                    <div class="avatar">
                        <div class="w-8 h-8 rounded-full">
                            ${avatarHTML}
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="font-bold text-sm">${reply.name}</span>
                            ${commenterBadge}
                            <span class="text-xs text-gray-500">Just now</span>
                        </div>
                        <div class="comment-content-${reply.id}">
                            <p class="text-sm text-gray-700 mb-2">${reply.content.replace(/\n/g, '<br>')}</p>
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
                        <div class="flex items-center gap-4 text-xs">
                            <button onclick="likeComment(${reply.id})" 
                                    class="like-btn flex items-center gap-1 hover:text-red-500 transition text-gray-500"
                                    id="like-btn-${reply.id}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                </svg>
                                <span id="like-count-${reply.id}">0</span>
                            </button>
                            ${editDeleteButtons}
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
        const btn = event.target;
        
        if (replies.classList.contains('hidden')) {
            replies.classList.remove('hidden');
            btn.textContent = btn.textContent.replace('View', 'Hide');
        } else {
            replies.classList.add('hidden');
            btn.textContent = btn.textContent.replace('Hide', 'View');
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
    
    // Share announcement
    function shareAnnouncement() {
        const url = window.location.href;
        const title = '<?php echo addslashes($announcement['anntitle'] ?? ''); ?>';
        
        if (navigator.share) {
            navigator.share({
                title: title,
                url: url
            }).catch(err => console.log('Error sharing:', err));
        } else {
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copied to clipboard!');
            }).catch(err => {
                console.error('Error copying:', err);
            });
        }
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
    
    // Smooth scroll to comment if hash is present
    if (window.location.hash) {
        const target = document.querySelector(window.location.hash);
        if (target) {
            setTimeout(() => {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.classList.add('bg-yellow-50');
                setTimeout(() => target.classList.remove('bg-yellow-50'), 2000);
            }, 100);
        }
    }
</script>
</body>
</html>
    </script>
</body>
</html>
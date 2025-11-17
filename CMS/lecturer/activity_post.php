<?php
session_start();
require_once "../config/connect.php";

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    header("Location: ../user_access.php");
    exit();
}

// Get activity ID from URL
if (!isset($_GET['actID']) || empty($_GET['actID'])) {
    header("Location: clubs.php");
    exit();
}

$act_id = $_GET['actID'];
$lect_id = $_SESSION['lect_id'];

// Get activity details
$act_query = "SELECT a.*, c.clubName, c.clubID, c.clubLogo, l.lectName, l.lectProfileImg
              FROM casactivity a
              LEFT JOIN clubsocieties c ON a.clubID = c.clubID
              LEFT JOIN lecturer l ON c.lectID = l.lectID
              WHERE a.actID = ?";
$act_stmt = $conn->prepare($act_query);
$act_stmt->execute([$act_id]);
$activity = $act_stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    $_SESSION['error'] = "Activity not found.";
    header("Location: clubs.php");
    exit();
}

// Determine activity status
$current_date = date('Y-m-d');
$status = 'upcoming';
$status_text = 'Upcoming';
$status_color = 'badge-info';

if ($activity['actDate'] == $current_date) {
    $status = 'today';
    $status_text = 'Today';
    $status_color = 'badge-success';
} elseif ($activity['actDate'] < $current_date) {
    $status = 'completed';
    $status_text = 'Completed';
    $status_color = 'badge-error';
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
          WHERE c.post_type = 'activity' 
            AND c.post_id = ? 
            AND c.is_deleted = 0
            AND c.parent_comment_id IS NULL
          ORDER BY c.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$lect_id, $act_id]);
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

$media_items = [];
if (!empty($activity['actImg'])) {
    $media_items[] = ['type' => 'image', 'src' => $activity['actImg']];
}
if (!empty($activity['actVid'])) {
    $media_items[] = ['type' => 'video', 'src' => $activity['actVid']];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($activity['actDescription']); ?></title>
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
        .carousel-item video,
        .carousel-item img {
            transition: opacity 0.3s ease;
        }
        .carousel::-webkit-scrollbar {
            display: none;
        }
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
                                <li><a href="club_profile.php?clubID=<?php echo $activity['clubID']; ?>"><?php echo htmlspecialchars($activity['clubName']); ?></a></li>
                                <li>Activity</li>
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
                    <!-- Activity Content -->
                    <div class="lg:col-span-2">
                        <div class="card bg-base-100 shadow-xl">
                            <!-- Header with Club Info -->
                            <div class="card-body border-b">
                                <div class="flex items-center gap-4">
                                    <div class="avatar">
                                        <div class="w-12 h-12 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                                            <?php if (!empty($activity['clubLogo'])): ?>
                                                <img src="../<?php echo htmlspecialchars($activity['clubLogo']); ?>" 
                                                    alt="<?php echo htmlspecialchars($activity['clubName']); ?>" 
                                                    class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="bg-gradient-to-br from-purple-400 to-blue-400 text-white flex items-center justify-center text-xl font-bold w-full h-full rounded-full">
                                                    <?php echo strtoupper(substr($activity['clubName'], 0, 2)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="font-bold"><?php echo htmlspecialchars($activity['clubName']); ?></h3>
                                        <p class="text-sm text-gray-500">by <?php echo htmlspecialchars($activity['lectName']); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo date('F j, Y \a\t g:i A', strtotime($activity['actPosted_at'])); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Activity Content -->
                            <div class="card-body">
                                <!-- Status Badge -->
                                <div class="mb-4">
                                    <span class="badge <?php echo $status_color; ?> badge-lg"><?php echo $status_text; ?></span>
                                </div>

                                <?php if (!empty($media_items)): ?>
                                <figure class="relative mb-6">
                                    <div class="carousel w-full rounded-lg overflow-hidden bg-black" id="mediaCarousel">
                                        <?php foreach ($media_items as $index => $media): ?>
                                            <div id="slide<?php echo $index + 1; ?>" class="carousel-item relative w-full">
                                                <?php if ($media['type'] === 'image'): ?>
                                                    <img src="../<?php echo htmlspecialchars($media['src']); ?>" 
                                                        alt="Activity Image"
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
                                    <h2 class="text-2xl font-bold mb-4">Activity Description</h2>
                                    <?php echo nl2br(htmlspecialchars($activity['actDescription'])); ?>
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
                        <!-- Activity Info Card -->
                        <div class="card bg-base-100 shadow-xl mb-6 sticky top-4">
                            <div class="card-body">
                                <h3 class="card-title text-lg">Activity Details</h3>
                                <div class="divider my-2"></div>
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Club Name</p>
                                        <p class="font-bold"><?php echo htmlspecialchars($activity['clubName']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Advisor</p>
                                        <p><?php echo htmlspecialchars($activity['lectName']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Activity Type</p>
                                        <p><?php echo htmlspecialchars($activity['actType']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Date</p>
                                        <p><?php echo date('F j, Y', strtotime($activity['actDate'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Time</p>
                                        <p><?php echo date('g:i A', strtotime($activity['actTime'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Status</p>
                                        <span class="badge <?php echo $status_color; ?>"><?php echo $status_text; ?></span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Posted On</p>
                                        <p><?php echo date('F j, Y', strtotime($activity['actPosted_at'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-600">Total Comments</p>
                                        <p id="sidebar-comment-count"><?php echo $total_comments; ?></p>
                                    </div>
                                </div>
                                <div class="divider my-2"></div>
                                <a href="club_profile.php?clubID=<?php echo $activity['clubID']; ?>" class="btn btn-primary btn-sm btn-block">
                                    View Club Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
        // ============================================
        // COMMENT FUNCTIONS
        // ============================================

        // Get post info
        function getPostInfo() {
            return {
                postType: 'activity',
                postId: <?php echo $act_id; ?>
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
                    
                    // Check if this is a reply
                    const isReply = commentElement.closest('.reply-indent') !== null;
                    
                    let totalToDelete = 1;
                    
                    // If parent comment, count replies
                    if (!isReply) {
                        const repliesContainer = document.getElementById('replies-' + commentId);
                        if (repliesContainer) {
                            const replyElements = repliesContainer.querySelectorAll('.reply-card');
                            totalToDelete += replyElements.length;
                        }
                    }
                    
                    // Animate removal
                    commentElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    commentElement.style.opacity = '0';
                    commentElement.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        // Update parent reply count if this is a reply
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
                        
                        // Check if no comments left
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
    </script>
</body>
</html>
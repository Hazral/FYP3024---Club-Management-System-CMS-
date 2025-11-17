<?php
session_start();
require_once "../config/connect.php";

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    header("Location: ../user_access.php");
    exit();
}

// Handle edit event request
if (isset($_POST['edit_event'])) {
    $event_id = $_POST['event_id'];
    $club_id = $_POST['club_id'];
    $lect_id = $_SESSION['lect_id'];
    $evTitle = $_POST['evTitle'];
    $evDescription = $_POST['evDescription'];
    $evLocation = $_POST['evLocation'];
    $evDate = $_POST['evDate'];
    $evTime = $_POST['evTime'];
    $evType = $_POST['evType'];
    $evCapacity = $_POST['evCapacity'];
    
    // Verify lecturer owns this club before editing
    $verify_query = "SELECT c.clubID FROM clubsocieties c
                     INNER JOIN casevents ce ON c.clubID = ce.clubID
                     WHERE ce.eventID = ? AND c.lectID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->execute([$event_id, $lect_id]);
    
    if ($verify_stmt->fetch()) {
        // Get current event data
        $current_query = "SELECT evImg, evVid FROM casevents WHERE eventID = ?";
        $current_stmt = $conn->prepare($current_query);
        $current_stmt->execute([$event_id]);
        $current_data = $current_stmt->fetch(PDO::FETCH_ASSOC);
        
        $evImg = $current_data['evImg'];
        $evVid = $current_data['evVid'];
        
        // Handle image upload
        if (isset($_FILES['evImg']) && $_FILES['evImg']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['evImg']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = "Invalid image file type. Only JPG, JPEG, and PNG files are allowed.";
                header("Location: all_events.php?clubID=" . $club_id);
                exit();
            }
            
            $newname = 'event_' . uniqid() . '.' . $ext;
            $upload_dir = '../uploads/events/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['evImg']['tmp_name'], $upload_dir . $newname)) {
                // Delete old image if exists
                if (!empty($current_data['evImg']) && file_exists("../" . $current_data['evImg'])) {
                    unlink("../" . $current_data['evImg']);
                }
                $evImg = 'uploads/events/' . $newname;
            } else {
                $_SESSION['error'] = "Failed to upload image file. Please try again.";
                header("Location: all_events.php?clubID=" . $club_id);
                exit();
            }
        }
        
        // Handle video upload
        if (isset($_FILES['evVid']) && $_FILES['evVid']['error'] == 0) {
            $allowed = ['mp4', 'avi', 'mov', 'wmv'];
            $filename = $_FILES['evVid']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = "Invalid video file type. Only MP4, AVI, MOV, and WMV files are allowed.";
                header("Location: all_events.php?clubID=" . $club_id);
                exit();
            }
            
            $newname = 'event_vid_' . uniqid() . '.' . $ext;
            $upload_dir = '../uploads/events/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['evVid']['tmp_name'], $upload_dir . $newname)) {
                // Delete old video if exists
                if (!empty($current_data['evVid']) && file_exists("../" . $current_data['evVid'])) {
                    unlink("../" . $current_data['evVid']);
                }
                $evVid = 'uploads/events/' . $newname;
            } else {
                $_SESSION['error'] = "Failed to upload video file. Please try again.";
                header("Location: all_events.php?clubID=" . $club_id);
                exit();
            }
        }
        
        // Update the event
        $update_query = "UPDATE casevents 
                        SET evTitle = ?, evDescription = ?, evLocation = ?, evDate = ?, 
                            evTime = ?, evType = ?, evCapacity = ?, evImg = ?, evVid = ?
                        WHERE eventID = ?";
        $update_stmt = $conn->prepare($update_query);
        
        if ($update_stmt->execute([$evTitle, $evDescription, $evLocation, $evDate, $evTime, 
                                   $evType, $evCapacity, $evImg, $evVid, $event_id])) {
            $_SESSION['success'] = "Event updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update event.";
        }
    } else {
        $_SESSION['error'] = "You don't have permission to edit this event.";
    }
    
    header("Location: all_events.php?clubID=" . $club_id);
    exit();
}

// Handle delete request
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    $club_id = $_POST['club_id'];
    $lect_id = $_SESSION['lect_id'];
    
    // Verify lecturer owns this club before deleting
    $verify_query = "SELECT c.clubID FROM clubsocieties c
                     INNER JOIN casevents ce ON c.clubID = ce.clubID
                     WHERE ce.eventID = ? AND c.lectID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->execute([$event_id, $lect_id]);
    
    if ($verify_stmt->fetch()) {
        // Get event details to delete associated files
        $file_query = "SELECT evImg, evVid FROM casevents WHERE eventID = ?";
        $file_stmt = $conn->prepare($file_query);
        $file_stmt->execute([$event_id]);
        $files = $file_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the event
        $delete_query = "DELETE FROM casevents WHERE eventID = ?";
        $delete_stmt = $conn->prepare($delete_query);
        
        if ($delete_stmt->execute([$event_id])) {
            // Delete associated files if they exist
            if (!empty($files['evImg']) && file_exists("../" . $files['evImg'])) {
                unlink("../" . $files['evImg']);
            }
            if (!empty($files['evVid']) && file_exists("../" . $files['evVid'])) {
                unlink("../" . $files['evVid']);
            }
            
            $_SESSION['success'] = "Event deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete event.";
        }
    } else {
        $_SESSION['error'] = "You don't have permission to delete this event.";
    }
    
    header("Location: all_events.php?clubID=" . $club_id);
    exit();
}

// Get club ID from URL
if (!isset($_GET['clubID']) || empty($_GET['clubID'])) {
    header("Location: clubs.php");
    exit();
}

$club_id = $_GET['clubID'];
$lect_id = $_SESSION['lect_id'];

// Get filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_time = isset($_GET['time']) ? $_GET['time'] : 'all';

// Verify lecturer owns this club and get club details
$query = "SELECT c.*, l.lectName 
          FROM clubsocieties c
          LEFT JOIN lecturer l ON c.lectID = l.lectID
          WHERE c.clubID = ? AND c.lectID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$club_id, $lect_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    $_SESSION['error'] = "Club not found or you don't have permission to access it.";
    header("Location: clubs.php");
    exit();
}

// Build query with filters
$query = "SELECT eventID, evTitle, evDescription, evLocation, evPosted_at, evImg, evVid, 
                 evTime, evDate, evType, evCapacity, comment_count
          FROM casevents
          WHERE clubID = ?";

$params = [$club_id];

// Add type filter
if ($filter_type !== 'all') {
    $query .= " AND evType = ?";
    $params[] = $filter_type;
}

// Add time filter (upcoming/past)
$today = date('Y-m-d');
if ($filter_time === 'upcoming') {
    $query .= " AND evDate >= ?";
    $params[] = $today;
} elseif ($filter_time === 'past') {
    $query .= " AND evDate < ?";
    $params[] = $today;
}

$query .= " ORDER BY evDate DESC, evTime DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for filters
$count_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN evType = 'Public' THEN 1 ELSE 0 END) as public_count,
                SUM(CASE WHEN evType = 'Private' THEN 1 ELSE 0 END) as private_count,
                SUM(CASE WHEN evDate >= ? THEN 1 ELSE 0 END) as upcoming_count,
                SUM(CASE WHEN evDate < ? THEN 1 ELSE 0 END) as past_count
                FROM casevents WHERE clubID = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute([$today, $today, $club_id]);
$counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Events - <?php echo htmlspecialchars($club['clubName']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
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
                                <li><a href="clubs.php">Club & Societies</a></li>
                                <li><a href="club_profile.php?clubID=<?php echo $club_id; ?>"><?php echo htmlspecialchars($club['clubName']); ?></a></li>
                                <li>All Events</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success shadow-lg mb-4">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><?php echo $_SESSION['success']; ?></span>
                        </div>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error shadow-lg mb-4">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><?php echo $_SESSION['error']; ?></span>
                        </div>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Header -->
                <div class="card bg-base-100 shadow-xl mb-6">
                    <div class="card-body">
                        <div class="flex justify-between items-center">
                            <div>
                                <h1 class="text-3xl font-bold">All Events</h1>
                                <p class="text-gray-600"><?php echo htmlspecialchars($club['clubName']); ?></p>
                            </div>
                            <button onclick="window.history.back()" class="btn btn-ghost">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="card bg-base-100 shadow-xl mb-6">
                    <div class="card-body p-4">
                        <div class="flex flex-col gap-3">
                            <!-- Type Filter -->
                            <div>
                                <h3 class="font-semibold text-sm mb-2">Filter by Type:</h3>
                                <div class="tabs tabs-boxed">
                                    <a href="all_events.php?clubID=<?php echo $club_id; ?>&type=all&time=<?php echo $filter_time; ?>" 
                                       class="tab <?php echo $filter_type === 'all' ? 'tab-active' : ''; ?>">
                                        All 
                                        <span class="badge badge-sm ml-2"><?php echo $counts['total']; ?></span>
                                    </a>
                                    <a href="all_events.php?clubID=<?php echo $club_id; ?>&type=Public&time=<?php echo $filter_time; ?>" 
                                       class="tab <?php echo $filter_type === 'Public' ? 'tab-active' : ''; ?>">
                                        Public
                                        <span class="badge badge-sm ml-2"><?php echo $counts['public_count']; ?></span>
                                    </a>
                                    <a href="all_events.php?clubID=<?php echo $club_id; ?>&type=Private&time=<?php echo $filter_time; ?>" 
                                       class="tab <?php echo $filter_type === 'Private' ? 'tab-active' : ''; ?>">
                                        Private
                                        <span class="badge badge-sm ml-2"><?php echo $counts['private_count']; ?></span>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Time Filter -->
                            <div>
                                <h3 class="font-semibold text-sm mb-2">Filter by Time:</h3>
                                <div class="tabs tabs-boxed">
                                    <a href="all_events.php?clubID=<?php echo $club_id; ?>&type=<?php echo $filter_type; ?>&time=all" 
                                       class="tab <?php echo $filter_time === 'all' ? 'tab-active' : ''; ?>">
                                        All Events
                                        <span class="badge badge-sm ml-2"><?php echo $counts['total']; ?></span>
                                    </a>
                                    <a href="all_events.php?clubID=<?php echo $club_id; ?>&type=<?php echo $filter_type; ?>&time=upcoming" 
                                       class="tab <?php echo $filter_time === 'upcoming' ? 'tab-active' : ''; ?>">
                                        Upcoming
                                        <span class="badge badge-sm ml-2"><?php echo $counts['upcoming_count']; ?></span>
                                    </a>
                                    <a href="all_events.php?clubID=<?php echo $club_id; ?>&type=<?php echo $filter_type; ?>&time=past" 
                                       class="tab <?php echo $filter_time === 'past' ? 'tab-active' : ''; ?>">
                                        Past
                                        <span class="badge badge-sm ml-2"><?php echo $counts['past_count']; ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Events Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                        <?php 
                            $is_past = strtotime($event['evDate']) < strtotime($today);
                        ?>
                        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all <?php echo $is_past ? 'opacity-75' : ''; ?>">
                            <?php if (!empty($event['evImg'])): ?>
                            <figure class="h-48 relative">
                                <img src="../<?php echo htmlspecialchars($event['evImg']); ?>" 
                                     alt="<?php echo htmlspecialchars($event['evTitle']); ?>"
                                     class="w-full h-full object-cover">
                                <?php if ($is_past): ?>
                                <div class="absolute top-2 right-2 badge badge-neutral">Past Event</div>
                                <?php endif; ?>
                            </figure>
                            <?php endif; ?>
                            <div class="card-body">
                                <h2 class="card-title"><?php echo htmlspecialchars($event['evTitle']); ?></h2>
                                
                                <!-- Event Type Badge -->
                                <?php if (!empty($event['evType'])): ?>
                                <?php 
                                $badge_color = $event['evType'] === 'Public' ? 'badge-primary' : 'badge-error';
                                ?>
                                <div class="badge <?php echo $badge_color; ?> badge-sm mb-2"><?php echo htmlspecialchars($event['evType']); ?></div>
                                <?php endif; ?>
                                
                                <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars(substr($event['evDescription'], 0, 100)); ?>...</p>
                                
                                <!-- Event Details -->
                                <div class="text-xs text-gray-500 mt-2 space-y-1">
                                    <?php if (!empty($event['evDate'])): ?>
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span><?php echo date('M j, Y', strtotime($event['evDate'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($event['evTime'])): ?>
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span><?php echo date('g:i A', strtotime($event['evTime'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($event['evLocation'])): ?>
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span><?php echo htmlspecialchars($event['evLocation']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($event['evCapacity'])): ?>
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <span>Capacity: <?php echo htmlspecialchars($event['evCapacity']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-actions justify-end mt-4">
                                    <a href="event_post.php?eventID=<?php echo $event['eventID']; ?>" class="btn btn-sm btn-success">View</a>
                                    <button onclick="editModal<?php echo $event['eventID']; ?>.showModal()" class="btn btn-sm btn-info">Edit</button>
                                    <button onclick="confirmDelete<?php echo $event['eventID']; ?>.showModal()" class="btn btn-sm btn-error">Delete</button>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Modal -->
                        <dialog id="editModal<?php echo $event['eventID']; ?>" class="modal">
                            <div class="modal-box max-w-3xl">
                                <h3 class="font-bold text-lg mb-4">Edit Event</h3>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="event_id" value="<?php echo $event['eventID']; ?>">
                                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Event Title</span>
                                            </label>
                                            <input type="text" name="evTitle" value="<?php echo htmlspecialchars($event['evTitle']); ?>" 
                                                   class="input input-bordered" required>
                                        </div>
                                        
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Event Type</span>
                                            </label>
                                            <select name="evType" class="select select-bordered" required>
                                                <option value="Public" <?php echo $event['evType'] === 'Public' ? 'selected' : ''; ?>>Public</option>
                                                <option value="Private" <?php echo $event['evType'] === 'Private' ? 'selected' : ''; ?>>Private</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-control mt-4">
                                        <label>
                                            <span class= "label-text">Description</span>
                                        </label>
                                         <textarea name="evDescription" rows="4" class="textarea textarea-bordered" required><?php echo htmlspecialchars($event['evDescription']); ?></textarea>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Date</span>
                                            </label>
                                            <input type="date" name="evDate" value="<?php echo $event['evDate']; ?>" 
                                                   class="input input-bordered" required>
                                        </div>
                                        
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Time</span>
                                            </label>
                                            <input type="time" name="evTime" value="<?php echo $event['evTime']; ?>" 
                                                   class="input input-bordered" required>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Location</span>
                                            </label>
                                            <input type="text" name="evLocation" value="<?php echo htmlspecialchars($event['evLocation']); ?>" 
                                                   class="input input-bordered" required>
                                        </div>
                                        
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Capacity</span>
                                            </label>
                                            <input type="number" name="evCapacity" value="<?php echo $event['evCapacity']; ?>" 
                                                   class="input input-bordered" min="1" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-control mt-4">
                                        <label class="label">
                                            <span class="label-text">Image (Leave empty to keep current)</span>
                                        </label>
                                        <input type="file" name="evImg" accept=".jpg,.jpeg,.png" class="file-input file-input-bordered">
                                        <label class="label">
                                            <span class="label-text-alt text-gray-500">Only accept JPG, JPEG and PNG files.</span>
                                        </label>
                                    </div>
                                    
                                    <div class="form-control mt-4">
                                        <label class="label">
                                            <span class="label-text">Video (Leave empty to keep current)</span>
                                        </label>
                                        <input type="file" name="evVid" accept=".mp4,.avi,.mov,.wmv" class="file-input file-input-bordered">
                                        <label class="label">
                                            <span class="label-text-alt text-gray-500">Only accept MP4, AVI, MOV, and WMV files.</span>
                                        </label>
                                    </div>
                                    
                                    <div class="modal-action mt-6">
                                        <button type="button" onclick="editModal<?php echo $event['eventID']; ?>.close()" class="btn">Cancel</button>
                                        <button type="submit" name="edit_event" class="btn btn-success">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button>close</button>
                            </form>
                        </dialog>

                        <!-- Delete Confirmation Modal -->
                        <dialog id="confirmDelete<?php echo $event['eventID']; ?>" class="modal">
                            <div class="modal-box">
                                <h3 class="font-bold text-lg">Confirm Delete</h3>
                                <p class="py-4">Are you sure you want to delete the event "<?php echo htmlspecialchars($event['evTitle']); ?>"? This action cannot be undone.</p>
                                <div class="modal-action">
                                    <form method="dialog">
                                        <button class="btn">Cancel</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="event_id" value="<?php echo $event['eventID']; ?>">
                                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                                        <button type="submit" name="delete_event" class="btn btn-error">Delete</button>
                                    </form>
                                </div>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button>close</button>
                            </form>
                        </dialog>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full text-center py-16">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <h3 class="text-xl font-semibold mb-2 text-gray-600">No Events Found</h3>
                            <p class="text-gray-500">
                                <?php if ($filter_type === 'all' && $filter_time === 'all'): ?>
                                    Start by creating your first event!
                                <?php else: ?>
                                    No events match the selected filters. Try adjusting your filters.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include 'includes/sidebar.php'; ?>
    </div>
</body>
</html>
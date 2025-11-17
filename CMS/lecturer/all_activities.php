<?php
session_start();
require_once "../config/connect.php";

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    header("Location: ../user_access.php");
    exit();
}

// Handle edit request
if (isset($_POST['edit_activity'])) {
    $act_id = $_POST['act_id'];
    $club_id = $_POST['club_id'];
    $lect_id = $_SESSION['lect_id'];
    $act_title = $_POST['act_title'];
    $act_description = $_POST['act_description'];
    $act_date = $_POST['act_date'];
    $act_time = $_POST['act_time'];
    $act_type = $_POST['act_type'];
    
    // Verify lecturer owns this club before editing
    $verify_query = "SELECT c.clubID FROM clubsocieties c
                     INNER JOIN casactivity ca ON c.clubID = ca.clubID
                     WHERE ca.actID = ? AND c.lectID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->execute([$act_id, $lect_id]);
    
    if ($verify_stmt->fetch()) {
        // Get current files
        $file_query = "SELECT actImg, actVid FROM casactivity WHERE actID = ?";
        $file_stmt = $conn->prepare($file_query);
        $file_stmt->execute([$act_id]);
        $current_files = $file_stmt->fetch(PDO::FETCH_ASSOC);
        
        $actImg = $current_files['actImg'];
        $actVid = $current_files['actVid'];
        
        // Handle image upload
        if (isset($_FILES['act_img']) && $_FILES['act_img']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../uploads/activities/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $img_ext = pathinfo($_FILES['act_img']['name'], PATHINFO_EXTENSION);
            $img_name = "activity_" . $act_id . "_" . time() . "." . $img_ext;
            $img_path = $upload_dir . $img_name;
            
            if (move_uploaded_file($_FILES['act_img']['tmp_name'], $img_path)) {
                // Delete old image if exists
                if (!empty($current_files['actImg']) && file_exists("../" . $current_files['actImg'])) {
                    unlink("../" . $current_files['actImg']);
                }
                $actImg = "uploads/activities/" . $img_name;
            }
        }
        
        // Handle video upload
        if (isset($_FILES['act_vid']) && $_FILES['act_vid']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../uploads/activities/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $vid_ext = pathinfo($_FILES['act_vid']['name'], PATHINFO_EXTENSION);
            $vid_name = "activity_vid_" . $act_id . "_" . time() . "." . $vid_ext;
            $vid_path = $upload_dir . $vid_name;
            
            if (move_uploaded_file($_FILES['act_vid']['tmp_name'], $vid_path)) {
                // Delete old video if exists
                if (!empty($current_files['actVid']) && file_exists("../" . $current_files['actVid'])) {
                    unlink("../" . $current_files['actVid']);
                }
                $actVid = "uploads/activities/" . $vid_name;
            }
        }
        
        // Update the activity - UPDATED to include actTitle
        $update_query = "UPDATE casactivity 
                        SET actTitle = ?, actDescription = ?, actDate = ?, actTime = ?, actType = ?, actImg = ?, actVid = ?
                        WHERE actID = ?";
        $update_stmt = $conn->prepare($update_query);
        
        if ($update_stmt->execute([$act_title, $act_description, $act_date, $act_time, $act_type, $actImg, $actVid, $act_id])) {
            $_SESSION['success'] = "Activity updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update activity.";
        }
    } else {
        $_SESSION['error'] = "You don't have permission to edit this activity.";
    }
    
    header("Location: all_activities.php?clubID=" . $club_id);
    exit();
}

// Handle delete request
if (isset($_POST['delete_activity'])) {
    $act_id = $_POST['act_id'];
    $club_id = $_POST['club_id'];
    $lect_id = $_SESSION['lect_id'];
    
    // Verify lecturer owns this club before deleting
    $verify_query = "SELECT c.clubID FROM clubsocieties c
                     INNER JOIN casactivity ca ON c.clubID = ca.clubID
                     WHERE ca.actID = ? AND c.lectID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->execute([$act_id, $lect_id]);
    
    if ($verify_stmt->fetch()) {
        // Get activity details to delete associated files
        $file_query = "SELECT actImg, actVid FROM casactivity WHERE actID = ?";
        $file_stmt = $conn->prepare($file_query);
        $file_stmt->execute([$act_id]);
        $files = $file_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the activity
        $delete_query = "DELETE FROM casactivity WHERE actID = ?";
        $delete_stmt = $conn->prepare($delete_query);
        
        if ($delete_stmt->execute([$act_id])) {
            // Delete associated files if they exist
            if (!empty($files['actImg']) && file_exists("../" . $files['actImg'])) {
                unlink("../" . $files['actImg']);
            }
            if (!empty($files['actVid']) && file_exists("../" . $files['actVid'])) {
                unlink("../" . $files['actVid']);
            }
            
            $_SESSION['success'] = "Activity deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete activity.";
        }
    } else {
        $_SESSION['error'] = "You don't have permission to delete this activity.";
    }
    
    header("Location: all_activities.php?clubID=" . $club_id);
    exit();
}

// Get club ID from URL
if (!isset($_GET['clubID']) || empty($_GET['clubID'])) {
    header("Location: clubs.php");
    exit();
}

$club_id = $_GET['clubID'];
$lect_id = $_SESSION['lect_id'];

// Get filter status and type from URL
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';

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

// Get all activities from this club with optional filters - UPDATED to include actTitle
$query = "SELECT actID, actTitle, actDescription, actDate, actTime, actType, 
          actImg, actVid, actPosted_at
          FROM casactivity
          WHERE clubID = ?";

// Add filter condition based on status
$current_date = date('Y-m-d');
if ($filter_status === 'upcoming') {
    $query .= " AND actDate > '$current_date'";
} elseif ($filter_status === 'today') {
    $query .= " AND actDate = '$current_date'";
} elseif ($filter_status === 'completed') {
    $query .= " AND actDate < '$current_date'";
}

// Add filter condition based on type
if ($filter_type !== 'all') {
    $query .= " AND actType = '$filter_type'";
}

$query .= " ORDER BY actDate DESC, actTime DESC";

$stmt = $conn->prepare($query);
$stmt->execute([$club_id]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get count for each status
$upcoming_count = 0;
$today_count = 0;
$completed_count = 0;

$count_query = "SELECT actID, actDate FROM casactivity WHERE clubID = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute([$club_id]);
$all_activities = $count_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_activities as $act) {
    if ($act['actDate'] > $current_date) {
        $upcoming_count++;
    } elseif ($act['actDate'] == $current_date) {
        $today_count++;
    } else {
        $completed_count++;
    }
}

$total_count = count($all_activities);

// Get count for each type
$type_count_query = "SELECT actType, COUNT(*) as count 
                     FROM casactivity 
                     WHERE clubID = ? 
                     GROUP BY actType";
$type_count_stmt = $conn->prepare($type_count_query);
$type_count_stmt->execute([$club_id]);
$type_counts = $type_count_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Activities - <?php echo htmlspecialchars($club['clubName']); ?></title>
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
                                <li>All Activities</li>
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
                                <h1 class="text-3xl font-bold">All Activities</h1>
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

                <!-- Filter Tabs - Status -->
                <div class="card bg-base-100 shadow-xl mb-4">
                    <div class="card-body p-4">
                        <h3 class="font-semibold mb-2">Filter by Status:</h3>
                        <div class="tabs tabs-boxed">
                            <a href="all_activities.php?clubID=<?php echo $club_id; ?>&status=all&type=<?php echo $filter_type; ?>" 
                               class="tab <?php echo $filter_status === 'all' ? 'tab-active' : ''; ?>">
                                All 
                                <span class="badge badge-sm ml-2"><?php echo $total_count; ?></span>
                            </a>
                            <a href="all_activities.php?clubID=<?php echo $club_id; ?>&status=upcoming&type=<?php echo $filter_type; ?>" 
                               class="tab <?php echo $filter_status === 'upcoming' ? 'tab-active' : ''; ?>">
                                Upcoming
                                <span class="badge badge-sm ml-2"><?php echo $upcoming_count; ?></span>
                            </a>
                            <a href="all_activities.php?clubID=<?php echo $club_id; ?>&status=today&type=<?php echo $filter_type; ?>" 
                               class="tab <?php echo $filter_status === 'today' ? 'tab-active' : ''; ?>">
                                Today
                                <span class="badge badge-sm ml-2"><?php echo $today_count; ?></span>
                            </a>
                            <a href="all_activities.php?clubID=<?php echo $club_id; ?>&status=completed&type=<?php echo $filter_type; ?>" 
                               class="tab <?php echo $filter_status === 'completed' ? 'tab-active' : ''; ?>">
                                Completed
                                <span class="badge badge-sm ml-2"><?php echo $completed_count; ?></span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs - Type -->
                <div class="card bg-base-100 shadow-xl mb-6">
                    <div class="card-body p-4">
                        <h3 class="font-semibold mb-2">Filter by Type:</h3>
                        <div class="tabs tabs-boxed">
                            <a href="all_activities.php?clubID=<?php echo $club_id; ?>&status=<?php echo $filter_status; ?>&type=all" 
                               class="tab <?php echo $filter_type === 'all' ? 'tab-active' : ''; ?>">
                                All 
                                <span class="badge badge-sm ml-2"><?php echo $total_count; ?></span>
                            </a>
                            <a href="all_activities.php?clubID=<?php echo $club_id; ?>&status=<?php echo $filter_status; ?>&type=Recruitment/Orientation" 
                               class="tab <?php echo $filter_type === 'Recruitment/Orientation' ? 'tab-active' : ''; ?>">
                                Recruitment/Orientation
                                <span class="badge badge-sm ml-2"><?php echo isset($type_counts['Recruitment/Orientation']) ? $type_counts['Recruitment/Orientation'] : 0; ?></span>
                            </a>
                            <a href="all_activities.php?clubID=<?php echo $club_id; ?>&status=<?php echo $filter_status; ?>&type=Leadership/Team Building" 
                               class="tab <?php echo $filter_type === 'Leadership/Team Building' ? 'tab-active' : ''; ?>">
                                Leadership/Team Building
                                <span class="badge badge-sm ml-2"><?php echo isset($type_counts['Leadership/Team Building']) ? $type_counts['Leadership/Team Building'] : 0; ?></span>
                            </a>
                            <a href="all_activities.php?clubID=<?php echo $club_id; ?>&status=<?php echo $filter_status; ?>&type=Meeting" 
                               class="tab <?php echo $filter_type === 'Meeting' ? 'tab-active' : ''; ?>">
                                Meeting
                                <span class="badge badge-sm ml-2"><?php echo isset($type_counts['Meeting']) ? $type_counts['Meeting'] : 0; ?></span>
                            </a>
                            <a href="all_activities.php?clubID=<?php echo $club_id; ?>&status=<?php echo $filter_status; ?>&type=Social/Gathering" 
                               class="tab <?php echo $filter_type === 'Social/Gathering' ? 'tab-active' : ''; ?>">
                                Social/Gathering
                                <span class="badge badge-sm ml-2"><?php echo isset($type_counts['Social/Gathering']) ? $type_counts['Social/Gathering'] : 0; ?></span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Activities Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $activity): ?>
                        <?php
                        // Determine activity status
                        $status = 'upcoming';
                        $status_text = 'Upcoming';
                        $badge_color = 'badge-info';
                        
                        if ($activity['actDate'] == $current_date) {
                            $status = 'today';
                            $status_text = 'Today';
                            $badge_color = 'badge-success';
                        } elseif ($activity['actDate'] < $current_date) {
                            $status = 'completed';
                            $status_text = 'Completed';
                            $badge_color = 'badge-error';
                        }

                        // Type badge color
                        $type_badge_colors = [
                            'Recruitment/Orientation' => 'badge-primary',
                            'Leadership/Team Building' => 'badge-success',
                            'Meeting' => 'badge-warning',
                            'Social/Gathering' => 'badge-secondary'
                        ];
                        $type_badge_color = isset($type_badge_colors[$activity['actType']]) ? $type_badge_colors[$activity['actType']] : 'badge-neutral';
                        ?>
                        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all">
                            <?php if (!empty($activity['actImg'])): ?>
                            <figure class="h-48">
                                <img src="../<?php echo htmlspecialchars($activity['actImg']); ?>" 
                                     alt="Activity Image"
                                     class="w-full h-full object-cover">
                            </figure>
                            <?php endif; ?>
                            <div class="card-body">
                                <!-- Status and Type Badges -->
                                <div class="flex gap-2 mb-2">
                                    <div class="badge <?php echo $badge_color; ?>"><?php echo $status_text; ?></div>
                                    <div class="badge <?php echo $type_badge_color; ?>"><?php echo ucfirst($activity['actType']); ?></div>
                                </div>
                                
                                <!-- Activity Title-->
                                <?php if (!empty($activity['actTitle'])): ?>
                                <h2 class="card-title text-lg mb-2"><?php echo htmlspecialchars($activity['actTitle']); ?></h2>
                                <?php endif; ?>
                                
                                <p class="text-sm text-gray-600 line-clamp-3"><?php echo htmlspecialchars(substr($activity['actDescription'], 0, 150)); ?>...</p>
                                
                                <!-- Activity Details -->
                                <div class="space-y-1 mt-2">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <strong class="mr-2">Date:</strong>
                                        <?php echo date('M j, Y', strtotime($activity['actDate'])); ?>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <strong class="mr-2">Time:</strong>
                                        <?php echo date('g:i A', strtotime($activity['actTime'])); ?>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-500 text-xs">
                                        Posted: <?php echo date('M j, Y \a\t g:i A', strtotime($activity['actPosted_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="card-actions justify-end mt-4">
                                    <a href="activity_post.php?actID=<?php echo $activity['actID']; ?>" class="btn btn-sm btn-warning">View</a>
                                    <button onclick="editModal<?php echo $activity['actID']; ?>.showModal()" class="btn btn-sm btn-info">Edit</button>
                                    <button onclick="confirmDelete<?php echo $activity['actID']; ?>.showModal()" class="btn btn-sm btn-error">Delete</button>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Modal actTitle -->
                        <dialog id="editModal<?php echo $activity['actID']; ?>" class="modal">
                            <div class="modal-box max-w-2xl">
                                <h3 class="font-bold text-lg mb-4">Edit Activity</h3>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="act_id" value="<?php echo $activity['actID']; ?>">
                                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">

                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text font-semibold">Activity Title</span>
                                        </label>
                                        <input type="text" name="act_title" class="input input-bordered" 
                                               value="<?php echo htmlspecialchars($activity['actTitle']); ?>" 
                                               placeholder="Enter activity title..." required>
                                    </div>

                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text font-semibold">Description</span>
                                        </label>
                                        <textarea name="act_description" class="textarea textarea-bordered h-32" required><?php echo htmlspecialchars($activity['actDescription']); ?></textarea>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text font-semibold">Date</span>
                                            </label>
                                            <input type="date" name="act_date" class="input input-bordered" value="<?php echo $activity['actDate']; ?>" required>
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text font-semibold">Time</span>
                                            </label>
                                            <input type="time" name="act_time" class="input input-bordered" value="<?php echo $activity['actTime']; ?>" required>
                                        </div>
                                    </div>

                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text font-semibold">Type</span>
                                        </label>
                                        <select name="act_type" class="select select-bordered" required>
                                            <option value="Recruitment/Orientation" <?php echo $activity['actType'] === 'Recruitment/Orientation' ? 'selected' : ''; ?>>Recruitment/Orientation</option>
                                            <option value="Leadership/Team Building" <?php echo $activity['actType'] === 'Leadership/Team Building' ? 'selected' : ''; ?>>Leadership/Team Building</option>
                                            <option value="Meeting" <?php echo $activity['actType'] === 'Meeting' ? 'selected' : ''; ?>>Meeting</option>
                                            <option value="Social/Gathering" <?php echo $activity['actType'] === 'Social/Gathering' ? 'selected' : ''; ?>>Social/Gathering</option>
                                        </select>
                                    </div>

                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text font-semibold">Activity Image</span>
                                        </label>
                                        <input type="file" name="act_img" class="file-input file-input-bordered" accept="image/*">
                                    </div>

                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text font-semibold">Activity Video</span>
                                        </label>
                                        <input type="file" name="act_vid" class="file-input file-input-bordered" accept="video/*">
                                    </div>

                                    <div class="modal-action">
                                        <button type="button" class="btn" onclick="editModal<?php echo $activity['actID']; ?>.close()">Cancel</button>
                                        <button type="submit" name="edit_activity" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button>close</button>
                            </form>
                        </dialog>

                        <!-- Delete Confirmation Modal -->
                        <dialog id="confirmDelete<?php echo $activity['actID']; ?>" class="modal">
                            <div class="modal-box">
                                <h3 class="font-bold text-lg">Confirm Delete</h3>
                                <p class="py-4">Are you sure you want to delete this activity? This action cannot be undone.</p>
                                <div class="modal-action">
                                    <form method="dialog">
                                        <button class="btn">Cancel</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="act_id" value="<?php echo $activity['actID']; ?>">
                                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                                        <button type="submit" name="delete_activity" class="btn btn-error">Delete</button>
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
                            <h3 class="text-xl font-semibold mb-2 text-gray-600">No Activities Found</h3>
                            <p class="text-gray-500">
                                <?php if ($filter_status === 'all' && $filter_type === 'all'): ?>
                                    Start by creating your first activity!
                                <?php else: ?>
                                    No activities found with the selected filters. Try different filter options.
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
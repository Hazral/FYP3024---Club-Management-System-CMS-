<?php
session_start();
require_once "../config/connect.php";

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    header("Location: ../user_access.php");
    exit();
}

// Handle edit announcement request
if (isset($_POST['edit_announcement'])) {
    $ann_id = $_POST['ann_id'];
    $club_id = $_POST['club_id'];
    $lect_id = $_SESSION['lect_id'];
    $anntitle = $_POST['anntitle'];
    $content = $_POST['content'];
    $annType = $_POST['annType'];
    
    // Verify lecturer owns this club before editing
    $verify_query = "SELECT c.clubID FROM clubsocieties c
                     INNER JOIN casannouncement ca ON c.clubID = ca.clubID
                     WHERE ca.annID = ? AND c.lectID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->execute([$ann_id, $lect_id]);
    
    if ($verify_stmt->fetch()) {
        // Get current announcement data
        $current_query = "SELECT annImg, annVid FROM casannouncement WHERE annID = ?";
        $current_stmt = $conn->prepare($current_query);
        $current_stmt->execute([$ann_id]);
        $current_data = $current_stmt->fetch(PDO::FETCH_ASSOC);
        
        $annImg = $current_data['annImg'];
        $annVid = $current_data['annVid'];
        
        // Handle image upload
        if (isset($_FILES['annImg']) && $_FILES['annImg']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['annImg']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = "Invalid image file type. Only JPG, JPEG, and PNG files are allowed.";
                header("Location: all_announcements.php?clubID=" . $club_id);
                exit();
            }
            
            $newname = 'announcement_' . uniqid() . '.' . $ext;
            $upload_dir = '../uploads/announcements/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['annImg']['tmp_name'], $upload_dir . $newname)) {
                // Delete old image if exists
                if (!empty($current_data['annImg']) && file_exists("../" . $current_data['annImg'])) {
                    unlink("../" . $current_data['annImg']);
                }
                $annImg = 'uploads/announcements/' . $newname;
            } else {
                $_SESSION['error'] = "Failed to upload image file. Please try again.";
                header("Location: all_announcements.php?clubID=" . $club_id);
                exit();
            }
        }
        
        // Handle video upload
        if (isset($_FILES['annVid']) && $_FILES['annVid']['error'] == 0) {
            $allowed = ['mp4', 'avi', 'mov', 'wmv'];
            $filename = $_FILES['annVid']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = "Invalid video file type. Only MP4, AVI, MOV, and WMV files are allowed.";
                header("Location: all_announcements.php?clubID=" . $club_id);
                exit();
            }
            
            $newname = 'announcement_vid_' . uniqid() . '.' . $ext;
            $upload_dir = '../uploads/announcements/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['annVid']['tmp_name'], $upload_dir . $newname)) {
                // Delete old video if exists
                if (!empty($current_data['annVid']) && file_exists("../" . $current_data['annVid'])) {
                    unlink("../" . $current_data['annVid']);
                }
                $annVid = 'uploads/announcements/' . $newname;
            } else {
                $_SESSION['error'] = "Failed to upload video file. Please try again.";
                header("Location: all_announcements.php?clubID=" . $club_id);
                exit();
            }
        }
        
        // Update the announcement
        $update_query = "UPDATE casannouncement 
                        SET anntitle = ?, content = ?, annType = ?, annImg = ?, annVid = ?
                        WHERE annID = ?";
        $update_stmt = $conn->prepare($update_query);
        
        if ($update_stmt->execute([$anntitle, $content, $annType, $annImg, $annVid, $ann_id])) {
            $_SESSION['success'] = "Announcement updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update announcement.";
        }
    } else {
        $_SESSION['error'] = "You don't have permission to edit this announcement.";
    }
    
    header("Location: all_announcements.php?clubID=" . $club_id);
    exit();
}

// Handle delete request
if (isset($_POST['delete_announcement'])) {
    $ann_id = $_POST['ann_id'];
    $club_id = $_POST['club_id'];
    $lect_id = $_SESSION['lect_id'];
    
    // Verify lecturer owns this club before deleting
    $verify_query = "SELECT c.clubID FROM clubsocieties c
                     INNER JOIN casannouncement ca ON c.clubID = ca.clubID
                     WHERE ca.annID = ? AND c.lectID = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->execute([$ann_id, $lect_id]);
    
    if ($verify_stmt->fetch()) {
        // Get announcement details to delete associated files
        $file_query = "SELECT annImg, annVid FROM casannouncement WHERE annID = ?";
        $file_stmt = $conn->prepare($file_query);
        $file_stmt->execute([$ann_id]);
        $files = $file_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the announcement
        $delete_query = "DELETE FROM casannouncement WHERE annID = ?";
        $delete_stmt = $conn->prepare($delete_query);
        
        if ($delete_stmt->execute([$ann_id])) {
            // Delete associated files if they exist
            if (!empty($files['annImg']) && file_exists("../" . $files['annImg'])) {
                unlink("../" . $files['annImg']);
            }
            if (!empty($files['annVid']) && file_exists("../" . $files['annVid'])) {
                unlink("../" . $files['annVid']);
            }
            
            $_SESSION['success'] = "Announcement deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete announcement.";
        }
    } else {
        $_SESSION['error'] = "You don't have permission to delete this announcement.";
    }
    
    header("Location: all_announcements.php?clubID=" . $club_id);
    exit();
}

// Get club ID from URL
if (!isset($_GET['clubID']) || empty($_GET['clubID'])) {
    header("Location: clubs.php");
    exit();
}

$club_id = $_GET['clubID'];
$lect_id = $_SESSION['lect_id'];

// Get filter type from URL (default to 'all')
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

// Get all announcements from this club with optional filter
$query = "SELECT annID, anntitle, annPosted_at, annImg, annVid, content, annType
          FROM casannouncement
          WHERE clubID = ?";

// Add filter condition if not 'all'
if ($filter_type !== 'all') {
    $query .= " AND annType = ?";
}

$query .= " ORDER BY annPosted_at DESC";

$stmt = $conn->prepare($query);
if ($filter_type !== 'all') {
    $stmt->execute([$club_id, $filter_type]);
} else {
    $stmt->execute([$club_id]);
}
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get count for each type
$count_query = "SELECT annType, COUNT(*) as count 
                FROM casannouncement 
                WHERE clubID = ? 
                GROUP BY annType";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute([$club_id]);
$type_counts = $count_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get total count
$total_count = array_sum($type_counts);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Announcements - <?php echo htmlspecialchars($club['clubName']); ?></title>
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
                                <li>All Announcements</li>
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
                                <h1 class="text-3xl font-bold">All Announcements</h1>
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
                        <div class="tabs tabs-boxed">
                            <a href="all_announcements.php?clubID=<?php echo $club_id; ?>&type=all" 
                               class="tab <?php echo $filter_type === 'all' ? 'tab-active' : ''; ?>">
                                All 
                                <span class="badge badge-sm ml-2"><?php echo $total_count; ?></span>
                            </a>
                            <a href="all_announcements.php?clubID=<?php echo $club_id; ?>&type=Public" 
                               class="tab <?php echo $filter_type === 'Public' ? 'tab-active' : ''; ?>">
                                Public
                                <span class="badge badge-sm ml-2"><?php echo isset($type_counts['Public']) ? $type_counts['Public'] : 0; ?></span>
                            </a>
                            <a href="all_announcements.php?clubID=<?php echo $club_id; ?>&type=Private" 
                               class="tab <?php echo $filter_type === 'Private' ? 'tab-active' : ''; ?>">
                                Private
                                <span class="badge badge-sm ml-2"><?php echo isset($type_counts['Private']) ? $type_counts['Private'] : 0; ?></span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Announcements Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $announcement): ?>
                        <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all">
                            <?php if (!empty($announcement['annImg'])): ?>
                            <figure class="h-48">
                                <img src="../<?php echo htmlspecialchars($announcement['annImg']); ?>" 
                                     alt="<?php echo htmlspecialchars($announcement['anntitle']); ?>"
                                     class="w-full h-full object-cover">
                            </figure>
                            <?php endif; ?>
                            <div class="card-body">
                                <!-- Type Badge -->
                                <?php 
                                $badge_colors = [
                                    'Public' => 'badge-primary',
                                    'Private' => 'badge-error'
                                ];
                                $badge_color = isset($badge_colors[$announcement['annType']]) ? $badge_colors[$announcement['annType']] : 'badge-neutral';
                                ?>
                                <div class="badge <?php echo $badge_color; ?> mb-2"><?php echo ucfirst($announcement['annType']); ?></div>
                                
                                <h2 class="card-title"><?php echo htmlspecialchars($announcement['anntitle']); ?></h2>
                                <p class="text-sm text-gray-600 line-clamp-3"><?php echo htmlspecialchars(substr($announcement['content'], 0, 150)); ?>...</p>
                                <div class="text-xs text-gray-500 mt-2">
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($announcement['annPosted_at'])); ?>
                                </div>
                                <div class="card-actions justify-end mt-4">
                                    <a href="announcement_post.php?annID=<?php echo $announcement['annID']; ?>" class="btn btn-sm btn-primary">View</a>
                                    <button onclick="editModal<?php echo $announcement['annID']; ?>.showModal()" class="btn btn-sm btn-info">Edit</button>
                                    <button onclick="confirmDelete<?php echo $announcement['annID']; ?>.showModal()" class="btn btn-sm btn-error">Delete</button>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Modal -->
                        <dialog id="editModal<?php echo $announcement['annID']; ?>" class="modal">
                            <div class="modal-box max-w-2xl">
                                <h3 class="font-bold text-lg mb-4">Edit Announcement</h3>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="ann_id" value="<?php echo $announcement['annID']; ?>">
                                    <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                                    
                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text">Title</span>
                                        </label>
                                        <input type="text" name="anntitle" value="<?php echo htmlspecialchars($announcement['anntitle']); ?>" 
                                               class="input input-bordered" required>
                                    </div>
                                    
                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text">Content</span>
                                        </label>
                                        <textarea name="content" rows="5" class="textarea textarea-bordered" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                                    </div>
                                    
                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text">Type</span>
                                        </label>
                                        <select name="annType" class="select select-bordered" required>
                                            <option value="Public" <?php echo $announcement['annType'] === 'Public' ? 'selected' : ''; ?>>Public</option>
                                            <option value="Private" <?php echo $announcement['annType'] === 'Private' ? 'selected' : ''; ?>>Private</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text">Image (Leave empty to keep current)</span>
                                        </label>
                                        <?php if (!empty($announcement['annImg'])): ?>
                                            <div class="mb-2">
                                                <img src="../<?php echo htmlspecialchars($announcement['annImg']); ?>" class="w-32 h-32 object-cover rounded">
                                                <p class="text-xs text-gray-500 mt-1">Current image</p>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="annImg" accept=".jpg,.jpeg,.png" class="file-input file-input-bordered">
                                        <label class="label">
                                            <span class="label-text-alt text-gray-500">Only accept JPG, JPEG and PNG files.</span>
                                        </label>
                                    </div>
                                    
                                    <div class="form-control mb-4">
                                        <label class="label">
                                            <span class="label-text">Video (Leave empty to keep current)</span>
                                        </label>
                                        <?php if (!empty($announcement['annVid'])): ?>
                                            <p class="text-xs text-gray-500 mb-2">Current video attached</p>
                                        <?php endif; ?>
                                        <input type="file" name="annVid" accept=".mp4,.avi,.mov,.wmv" class="file-input file-input-bordered">
                                        <label class="label">
                                            <span class="label-text-alt text-gray-500">Only accept MP4, AVI, MOV, and WMV files.</span>
                                        </label>
                                    </div>
                                    
                                    <div class="modal-action">
                                        <button type="button" onclick="editModal<?php echo $announcement['annID']; ?>.close()" class="btn">Cancel</button>
                                        <button type="submit" name="edit_announcement" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button>close</button>
                            </form>
                        </dialog>

                        <!-- Delete Confirmation Modal -->
                        <dialog id="confirmDelete<?php echo $announcement['annID']; ?>" class="modal">
                            <div class="modal-box">
                                <h3 class="font-bold text-lg">Confirm Delete</h3>
                                <p class="py-4">Are you sure you want to delete the announcement "<?php echo htmlspecialchars($announcement['anntitle']); ?>"? This action cannot be undone.</p>
                                <div class="modal-action">
                                    <form method="dialog">
                                        <button class="btn">Cancel</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="ann_id" value="<?php echo $announcement['annID']; ?>">
                                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                                        <button type="submit" name="delete_announcement" class="btn btn-error">Delete</button>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <h3 class="text-xl font-semibold mb-2 text-gray-600">No Announcements Found</h3>
                            <p class="text-gray-500">
                                <?php if ($filter_type === 'all'): ?>
                                    Start by creating your first announcement!
                                <?php else: ?>
                                    No <?php echo htmlspecialchars($filter_type); ?> announcements found. Try a different filter.
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
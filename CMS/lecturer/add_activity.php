<?php
session_start();
require_once "../config/connect.php";

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    header("Location: ../user_access.php");
    exit();
}

// Check if form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: my_clubs.php");
    exit();
}

// Get and validate club ID
if (!isset($_POST['clubID']) || empty($_POST['clubID'])) {
    $_SESSION['error'] = "Club ID is required.";
    header("Location: my_clubs.php");
    exit();
}

$club_id = $_POST['clubID'];
$lect_id = $_SESSION['lect_id'];

// Verify that this lecturer is in charge of this club
$query = "SELECT clubID FROM clubsocieties WHERE clubID = ? AND lectID = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$club_id, $lect_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    $_SESSION['error'] = "You don't have permission to add activities to this club.";
    header("Location: my_clubs.php");
    exit();
}

// Get form data
$actTitle = trim($_POST['actTitle']);
$actDescription = trim($_POST['actDescription']);
$actType = $_POST['actType'];
$actDate = $_POST['actDate'];
$actTime = !empty($_POST['actTime']) ? $_POST['actTime'] : null;

// Validate required fields
if (empty($actTitle)  ||  empty($actDescription) || empty($actType) || empty($actDate)) {
    $_SESSION['error'] = "Please fill in all required fields.";
    header("Location: club_profile.php?clubID=" . $club_id);
    exit();
}


// Handle file uploads
$actImg = null;
$actVid = null;
$uploadDir = "../uploads/activities/";

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle image upload
if (isset($_FILES['actImg']) && $_FILES['actImg']['error'] === UPLOAD_ERR_OK) {
    $imgFile = $_FILES['actImg'];
    $imgName = $imgFile['name'];
    $imgTmpName = $imgFile['tmp_name'];
    $imgSize = $imgFile['size'];
    $imgError = $imgFile['error'];
    
    // Get file extension
    $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
    
    // Allowed image extensions
    $allowedImgExt = ['jpg', 'jpeg', 'png'];
    
    if (in_array($imgExt, $allowedImgExt)) {
        // Check file size (max 5MB)
        if ($imgSize <= 5000000) {
            // Generate unique filename
            $newImgName = 'activity_' . uniqid('', true) . '.' . $imgExt;
            $imgDestination = $uploadDir . $newImgName;
            
            if (move_uploaded_file($imgTmpName, $imgDestination)) {
                $actImg = 'uploads/activities/' . $newImgName;
            } else {
                $_SESSION['error'] = "Failed to upload activity image.";
                header("Location: club_profile.php?clubID=" . $club_id);
                exit();
            }
        } else {
            $_SESSION['error'] = "Activity image size must be less than 5MB.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
    } else {
        $_SESSION['error'] = "Invalid image format. Allowed formats: JPG, JPEG, PNG";
        header("Location: club_profile.php?clubID=" . $club_id);
        exit();
    }
}

// Handle video upload
if (isset($_FILES['actVid']) && $_FILES['actVid']['error'] === UPLOAD_ERR_OK) {
    $vidFile = $_FILES['actVid'];
    $vidName = $vidFile['name'];
    $vidTmpName = $vidFile['tmp_name'];
    $vidSize = $vidFile['size'];
    $vidError = $vidFile['error'];
    
    // Get file extension
    $vidExt = strtolower(pathinfo($vidName, PATHINFO_EXTENSION));
    
    // Allowed video extensions
    $allowedVidExt = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'];
    
    if (in_array($vidExt, $allowedVidExt)) {
        // Check file size (max 50MB)
        if ($vidSize <= 50000000) {
            // Generate unique filename
            $newVidName = 'activity_video_' . uniqid('', true) . '.' . $vidExt;
            $vidDestination = $uploadDir . $newVidName;
            
            if (move_uploaded_file($vidTmpName, $vidDestination)) {
                $actVid = 'uploads/activities/' . $newVidName;
            } else {
                $_SESSION['error'] = "Failed to upload activity video.";
                header("Location: club_profile.php?clubID=" . $club_id);
                exit();
            }
        } else {
            $_SESSION['error'] = "Activity video size must be less than 50MB.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
    } else {
        $_SESSION['error'] = "Invalid video format. Allowed formats: MP4, AVI, MOV, WMV";
        header("Location: club_profile.php?clubID=" . $club_id);
        exit();
    }
}

try {
    // Insert activity into database
    $query = "INSERT INTO casactivity (clubID, actTitle, actDescription, actType, actDate, actTime, actImg, actVid, actPosted_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        $club_id,
        $actTitle,
        $actDescription,
        $actType,
        $actDate,
        $actTime,
        $actImg,
        $actVid
    ]);
    
    $_SESSION['success'] = "Club activity added successfully!";
    header("Location: club_profile.php?clubID=" . $club_id);
    exit();
    
} catch (PDOException $e) {
    // If database insert fails, delete uploaded files
    if ($actImg && file_exists("../" . $actImg)) {
        unlink("../" . $actImg);
    }
    if ($actVid && file_exists("../" . $actVid)) {
        unlink("../" . $actVid);
    }
    
    $_SESSION['error'] = "Failed to add activity: " . $e->getMessage();
    header("Location: club_profile.php?clubID=" . $club_id);
    exit();
}
?>
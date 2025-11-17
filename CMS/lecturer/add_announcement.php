<?php
session_start();
require_once "../config/connect.php";

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    header("Location: ../user_access.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $club_id = $_POST['clubID'];
    $lect_id = $_SESSION['lect_id'];
    
    // Verify lecturer owns this club
    $query = "SELECT clubID FROM clubsocieties WHERE clubID = ? AND lectID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id, $lect_id]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "You don't have permission to add announcements to this club.";
        header("Location: club_profile.php?clubID=" . $club_id);
        exit();
    }
    
    // Get form data
    $anntitle = trim($_POST['anntitle']);
    $content = trim($_POST['content']);
    $annType = $_POST['annType']; // ADD THIS LINE - Get announcement type
    
    // Handle image upload
    $annImg = null;
    if (isset($_FILES['annImg']) && $_FILES['annImg']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['annImg']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "Invalid image file type. Only JPG, JPEG, and PNG files are allowed.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
        
        $newname = 'announcement_' . uniqid() . '.' . $ext;
        $upload_dir = '../uploads/announcements/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['annImg']['tmp_name'], $upload_dir . $newname)) {
            $annImg = 'uploads/announcements/' . $newname;
        } else {
            $_SESSION['error'] = "Failed to upload image file. Please try again.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
    }
    
    // Handle video upload
    $annVid = null;
    if (isset($_FILES['annVid']) && $_FILES['annVid']['error'] == 0) {
        $allowed = ['mp4', 'avi', 'mov', 'wmv'];
        $filename = $_FILES['annVid']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "Invalid video file type. Only MP4, AVI, MOV, and WMV files are allowed.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
        
        $newname = 'announcement_vid_' . uniqid() . '.' . $ext;
        $upload_dir = '../uploads/announcements/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['annVid']['tmp_name'], $upload_dir . $newname)) {
            $annVid = 'uploads/announcements/' . $newname;
        } else {
            $_SESSION['error'] = "Failed to upload video file. Please try again.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
    }
    
    // Insert announcement into database - UPDATED QUERY with annType
    $query = "INSERT INTO casannouncement (clubID, anntitle, content, annType, annImg, annVid, annPosted_at) 
              VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    
    try {
        $stmt->execute([$club_id, $anntitle, $content, $annType, $annImg, $annVid]);
        $_SESSION['success'] = "Announcement posted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to post announcement. Please try again.";
    }
    
    header("Location: club_profile.php?clubID=" . $club_id);
    exit();
} else {
    header("Location: clubs.php");
    exit();
}
?>
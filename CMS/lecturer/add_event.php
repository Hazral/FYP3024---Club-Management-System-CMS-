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
        $_SESSION['error'] = "You don't have permission to add events to this club.";
        header("Location: club_profile.php?clubID=" . $club_id);
        exit();
    }
    
    // Get form data
    $evTitle = trim($_POST['evTitle']);
    $evDescription = trim($_POST['evDescription']);
    $evDate = $_POST['evDate'];
    $evTime = $_POST['evTime'];
    $evLocation = trim($_POST['evLocation']);
    $evCapacity = isset($_POST['evCapacity']) ? intval($_POST['evCapacity']) : 0;
    $evType = isset($_POST['evType']) ? trim($_POST['evType']) : 'Private';
    
    // Handle image upload
    $evImg = null;
    if (isset($_FILES['evImg']) && $_FILES['evImg']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['evImg']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "Invalid image file type. Only JPG, JPEG and PNG files are allowed.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
        
        $newname = 'event_' . uniqid() . '.' . $ext;
        $upload_dir = '../uploads/events/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['evImg']['tmp_name'], $upload_dir . $newname)) {
            $evImg = 'uploads/events/' . $newname;
        } else {
            $_SESSION['error'] = "Failed to upload image file. Please try again.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
    }
    
    // Handle video upload
    $evVid = null;
    if (isset($_FILES['evVid']) && $_FILES['evVid']['error'] == 0) {
        $allowed = ['mp4', 'avi', 'mov', 'wmv'];
        $filename = $_FILES['evVid']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "Invalid video file type. Only MP4, AVI, MOV, and WMV files are allowed.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
        
        $newname = 'event_vid_' . uniqid() . '.' . $ext;
        $upload_dir = '../uploads/events/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['evVid']['tmp_name'], $upload_dir . $newname)) {
            $evVid = 'uploads/events/' . $newname;
        } else {
            $_SESSION['error'] = "Failed to upload video file. Please try again.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
    }
    
    // Insert event into database - REMOVED created_at, ADDED evCapacity and evType
    $query = "INSERT INTO casevents (clubID, evTitle, evDescription, evDate, evTime, evLocation, evCapacity, evType, evImg, evVid) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    try {
        $result = $stmt->execute([$club_id, $evTitle, $evDescription, $evDate, $evTime, $evLocation, $evCapacity, $evType, $evImg, $evVid]);
        
        if ($result) {
            $_SESSION['success'] = "Event created successfully!";
        } else {
            $_SESSION['error'] = "Failed to create event. Please try again.";
        }
    } catch (PDOException $e) {
        // Display actual error for debugging
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Event creation error: " . $e->getMessage()); // Log to server error log
    }
    
    header("Location: club_profile.php?clubID=" . $club_id);
    exit();
} else {
    header("Location: clubs.php");
    exit();
}
?>
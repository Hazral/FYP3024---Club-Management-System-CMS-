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
    
    // Verify club belongs to this lecturer
    $stmt = $conn->prepare("SELECT clubID, clubLogo, clubBanner FROM clubsocieties WHERE clubID = ? AND lectID = ?");
    $stmt->execute([$club_id, $lect_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$club) {
        $_SESSION['error'] = "Club not found or you don't have permission to edit it.";
        header("Location: my_clubs.php");
        exit();
    }
    
    $club_name = trim($_POST['clubName']);
    $club_description = trim($_POST['clubDescription']);
    $club_capacity = intval($_POST['clubCapacity']);
    
    // Validate capacity
    $stmt = $conn->prepare("SELECT COUNT(*) FROM membership WHERE clubID = ?");
    $stmt->execute([$club_id]);
    $current_members = $stmt->fetchColumn();
    
    if ($club_capacity < $current_members) {
        $_SESSION['error'] = "Capacity cannot be less than current member count ($current_members).";
        header("Location: club_profile.php?clubID=" . $club_id);
        exit();
    }
    
    $logo_path = $club['clubLogo'];
    $banner_path = $club['clubBanner'];
    
    // Handle logo upload
    if (isset($_FILES['clubLogo']) && $_FILES['clubLogo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['clubLogo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "Invalid logo file type. Only JPG, JPEG, and PNG files are allowed.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
        
        $new_filename = 'club_logo_' . $club_id . '_' . time() . '.' . $ext;
        $upload_path = '../uploads/clubs/logos/';
        
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['clubLogo']['tmp_name'], $upload_path . $new_filename)) {
            // Delete old logo if exists
            if (!empty($club['clubLogo']) && file_exists('../' . $club['clubLogo'])) {
                unlink('../' . $club['clubLogo']);
            }
            $logo_path = 'uploads/clubs/logos/' . $new_filename;
        } else {
            $_SESSION['error'] = "Failed to upload logo file. Please try again.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
    }
    
    // Handle banner upload
    if (isset($_FILES['clubBanner']) && $_FILES['clubBanner']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['clubBanner']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "Invalid banner file type. Only JPG, JPEG and PNG files are allowed.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
        
        $new_filename = 'club_banner_' . $club_id . '_' . time() . '.' . $ext;
        $upload_path = '../uploads/clubs/banners/';
        
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['clubBanner']['tmp_name'], $upload_path . $new_filename)) {
            // Delete old banner if exists
            if (!empty($club['clubBanner']) && file_exists('../' . $club['clubBanner'])) {
                unlink('../' . $club['clubBanner']);
            }
            $banner_path = 'uploads/clubs/banners/' . $new_filename;
        } else {
            $_SESSION['error'] = "Failed to upload banner file. Please try again.";
            header("Location: club_profile.php?clubID=" . $club_id);
            exit();
        }
    }
    
    // Update club information
    $query = "UPDATE clubsocieties SET 
              clubName = ?, 
              clubDescription = ?, 
              clubCapacity = ?, 
              clubLogo = ?, 
              clubBanner = ? 
              WHERE clubID = ? AND lectID = ?";
    
    $stmt = $conn->prepare($query);
    
    if ($stmt->execute([$club_name, $club_description, $club_capacity, $logo_path, $banner_path, $club_id, $lect_id])) {
        $_SESSION['success'] = "Club profile updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update club profile.";
    }
    
    header("Location: club_profile.php?clubID=" . $club_id);
    exit();
} else {
    header("Location: clubs.php");
    exit();
}
?>
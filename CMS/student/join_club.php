<?php
session_start();
require_once "../config/connect.php";

// Check if student is logged in
if (!isset($_SESSION['stud_id'])) {
    header("Location: ../user_access.php");
    exit();
}

// Check if club ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid club ID";
    header("Location: available_clubs.php");
    exit();
}

$club_id = $_GET['id'];
$student_id = $_SESSION['stud_id'];

try {
    // Check if club exists and get its details
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM membership WHERE clubID = c.clubID) as current_members
              FROM clubsocieties c
              WHERE c.clubID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$club) {
        $_SESSION['error'] = "Club not found";
        header("Location: available_clubs.php");
        exit();
    }

    // Check if student is already a member
    $query = "SELECT * FROM membership WHERE studID = ? AND clubID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$student_id, $club_id]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = "You are already a member of this club";
        header("Location: club_detail.php?id=" . $club_id);
        exit();
    }

    // Check if club is full
    if ($club['current_members'] >= $club['clubCapacity']) {
        $_SESSION['error'] = "This club is full. No more spots available.";
        header("Location: club_detail.php?id=" . $club_id);
        exit();
    }

    // Add student to the club
    $query = "INSERT INTO membership (studID, clubID, joined_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->execute([$student_id, $club_id]);

    $_SESSION['success'] = "You have successfully joined " . htmlspecialchars($club['clubName']) . "!";
    header("Location: club_profile.php?id=" . $club_id);
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "An error occurred while joining the club. Please try again.";
    error_log("Join club error: " . $e->getMessage());
    header("Location: club_profile.php?id=" . $club_id);
    exit();
}
?>
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
    // Check if club exists
    $query = "SELECT clubName FROM clubsocieties WHERE clubID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$club) {
        $_SESSION['error'] = "Club not found";
        header("Location: available_clubs.php");
        exit();
    }

    // Check if student is a member
    $query = "SELECT * FROM membership WHERE studID = ? AND clubID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$student_id, $club_id]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "You are not a member of this club";
        header("Location: club_detail.php?id=" . $club_id);
        exit();
    }

    // Remove student from the club
    $query = "DELETE FROM membership WHERE studID = ? AND clubID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$student_id, $club_id]);

    $_SESSION['success'] = "You have successfully left " . htmlspecialchars($club['clubName']) . ".";
    header("Location: club_profile.php?id=" . $club_id);
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "An error occurred while leaving the club. Please try again.";
    error_log("Leave club error: " . $e->getMessage());
    header("Location: club_profile.php?id=" . $club_id);
    exit();
}
?>
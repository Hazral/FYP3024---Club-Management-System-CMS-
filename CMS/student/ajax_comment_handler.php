<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "../config/connect.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['stud_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$student_id = $_SESSION['stud_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add_comment':
            $content = trim($_POST['content'] ?? '');
            $post_type = $_POST['post_type'] ?? 'announcement';
            $post_id = $_POST['post_id'] ?? 0;
            $parent_id = $_POST['parent_id'] ?? null;
            
            if (empty($content) || empty($post_id)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit();
            }
            
            // Insert comment
            $query = "INSERT INTO comments (post_type, post_id, commenter_type, commenter_id, content, parent_comment_id, created_at) 
                      VALUES (?, ?, 'student', ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->execute([$post_type, $post_id, $student_id, $content, $parent_id]);
            
            $comment_id = $conn->lastInsertId();
            
            // Get student info
            $query = "SELECT studName, studProfileImg FROM student WHERE studID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            $profile_img_path = !empty($student['studProfileImg']) 
                ? 'uploads/student_profiles/' . $student['studProfileImg'] 
                : '';

            // Return comment data
            echo json_encode([
                'success' => true,
                'comment' => [
                    'id' => $comment_id,
                    'name' => $student['studName'],
                    'profile_img' => $profile_img_path,  // ← Now with full path
                    'content' => $content,
                    'commenter_type' => 'student',
                    'is_owner' => true,
                    'parent_id' => $parent_id
                ]
            ]);
            break;
            
        case 'edit_comment':
            $comment_id = $_POST['comment_id'] ?? 0;
            $content = trim($_POST['content'] ?? '');
            
            if (empty($content) || empty($comment_id)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit();
            }
            
            // Verify ownership
            $query = "SELECT commenter_id FROM comments WHERE commentID = ? AND commenter_type = 'student'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$comment || $comment['commenter_id'] != $student_id) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            
            // Update comment
            $query = "UPDATE comments SET content = ?, is_edited = 1 WHERE commentID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$content, $comment_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_comment':
            $comment_id = $_POST['comment_id'] ?? 0;
            
            if (empty($comment_id)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit();
            }
            
            // Verify ownership
            $query = "SELECT commenter_id FROM comments WHERE commentID = ? AND commenter_type = 'student'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$comment || $comment['commenter_id'] != $student_id) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            
            // Soft delete comment
            $query = "UPDATE comments SET is_deleted = 1 WHERE commentID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$comment_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'like_comment':
            $comment_id = $_POST['comment_id'] ?? 0;
            
            if (empty($comment_id)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit();
            }
            
            // Check if already liked
            $query = "SELECT * FROM comment_likes WHERE commentID = ? AND liker_type = 'student' AND liker_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$comment_id, $student_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Unlike
                $query = "DELETE FROM comment_likes WHERE commentID = ? AND liker_type = 'student' AND liker_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$comment_id, $student_id]);
                $liked = false;
            } else {
                // Like
                $query = "INSERT INTO comment_likes (commentID, liker_type, liker_id) VALUES (?, 'student', ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$comment_id, $student_id]);
                $liked = true;
            }
            
            // Get updated count
            $query = "SELECT COUNT(*) as count FROM comment_likes WHERE commentID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$comment_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'liked' => $liked,
                'count' => $result['count']
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
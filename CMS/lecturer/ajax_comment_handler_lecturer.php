<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "../config/connect.php";

header('Content-Type: application/json');

// Check if lecturer is logged in
if (!isset($_SESSION['lect_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$lect_id = $_SESSION['lect_id'];
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
                      VALUES (?, ?, 'lecturer', ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->execute([$post_type, $post_id, $lect_id, $content, $parent_id]);
            
            $comment_id = $conn->lastInsertId();
            
            // Get lecturer info
            $query = "SELECT lectName, lectProfileImg FROM lecturer WHERE lectID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$lect_id]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Return comment data
            echo json_encode([
                'success' => true,
                'comment' => [
                    'id' => $comment_id,
                    'name' => $lecturer['lectName'],
                    'profile_img' => $lecturer['lectProfileImg'],
                    'content' => $content,
                    'commenter_type' => 'lecturer',
                    'detail' => 'Lecturer',
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
            $query = "SELECT commenter_id FROM comments WHERE commentID = ? AND commenter_type = 'lecturer'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$comment || $comment['commenter_id'] != $lect_id) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            
            // Update comment
            $query = "UPDATE comments SET content = ?, is_edited = 1, updated_at = NOW() WHERE commentID = ?";
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
            
            // Verify the comment exists
            $query = "SELECT commenter_id, commenter_type FROM comments WHERE commentID = ? AND is_deleted = 0";
            $stmt = $conn->prepare($query);
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$comment) {
                echo json_encode(['success' => false, 'message' => 'Comment not found']);
                exit();
            }
            
            // Lecturers can delete any comment (own or student comments)
            // But verify they are actually a lecturer
            $query = "UPDATE comments SET is_deleted = 1, deleted_at = NOW(), deleted_by_type = 'lecturer', deleted_by_id = ? WHERE commentID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$lect_id, $comment_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'like_comment':
            $comment_id = $_POST['comment_id'] ?? 0;
            
            if (empty($comment_id)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
                exit();
            }
            
            // Check if already liked
            $query = "SELECT * FROM comment_likes WHERE commentID = ? AND liker_type = 'lecturer' AND liker_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$comment_id, $lect_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Unlike
                $query = "DELETE FROM comment_likes WHERE commentID = ? AND liker_type = 'lecturer' AND liker_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$comment_id, $lect_id]);
                $liked = false;
            } else {
                // Like
                $query = "INSERT INTO comment_likes (commentID, liker_type, liker_id) VALUES (?, 'lecturer', ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$comment_id, $lect_id]);
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
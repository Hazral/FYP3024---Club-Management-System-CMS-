<?php
session_start();
require_once "../config/connect.php";

if (!isset($_SESSION['stud_id'])) {
    header('Location: ../user_access.php');
    exit;
}

$studID = $_SESSION['stud_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updates = [];
        $params = [];

        // Profile picture upload
        if (isset($_FILES['studProfileImg']) && $_FILES['studProfileImg']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file_type = $_FILES['studProfileImg']['type'];
            $file_size = $_FILES['studProfileImg']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
            }
            
            if ($file_size > $max_size) {
                throw new Exception("File size exceeds 5MB limit.");
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = "../uploads/student_profiles/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['studProfileImg']['name'], PATHINFO_EXTENSION);
            $new_filename = "student_" . $studID . "_" . time() . "." . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Delete old profile picture if exists
            $stmt = $conn->prepare("SELECT studProfileImg FROM student WHERE studID = ?");
            $stmt->execute([$studID]);
            $old_image = $stmt->fetchColumn();
            
            if ($old_image && file_exists("../uploads/student_profiles/" . $old_image)) {
                unlink("../uploads/student_profiles/" . $old_image);
            }
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['studProfileImg']['tmp_name'], $upload_path)) {
                $updates[] = "studProfileImg = ?";
                $params[] = $new_filename; // Store ONLY filename
            } else {
                throw new Exception("Failed to upload profile picture.");
            }
        }

        // Email update
        if (isset($_POST['studEmail']) && !empty($_POST['studEmail'])) {
            // Check if email is already taken by another student
            $check_query = "SELECT studID FROM student WHERE studEmail = ? AND studID != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([$_POST['studEmail'], $studID]);
            if ($check_stmt->fetch()) {
                throw new Exception("Email is already taken by another student");
            }
            $updates[] = "studEmail = ?";
            $params[] = $_POST['studEmail'];
        }

        // Password update
        if (!empty($_POST['new_password'])) {
            if (empty($_POST['current_password'])) {
                throw new Exception("Current password is required to set a new password");
            }
            
            // Verify current password
            $stmt = $conn->prepare("SELECT studPass FROM student WHERE studID = ?");
            $stmt->execute([$studID]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($_POST['current_password'], $student['studPass'])) {
                throw new Exception("Current password is incorrect");
            }
            
            // Validate new password
            if (strlen($_POST['new_password']) < 8) {
                throw new Exception("New password must be at least 8 characters long");
            }
            
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception("New passwords do not match");
            }
            
            $updates[] = "studPass = ?";
            $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        }

        // Update profile if there are changes
        if (!empty($updates)) {
            $params[] = $studID; // Add studID for WHERE clause
            $query = "UPDATE student SET " . implode(", ", $updates) . " WHERE studID = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            $_SESSION['success'] = "Profile updated successfully";
            header("Location: profile.php");
            exit;
        } else {
            $_SESSION['error'] = "No changes were made";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get student data
$stmt = $conn->prepare("SELECT * FROM student WHERE studID = ?");
$stmt->execute([$studID]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student's clubs
$clubs_query = "
    SELECT 
        c.*,
        m.joined_at,
        l.lectName as advisor_name,
        COUNT(DISTINCT cm.cmID) as member_count
    FROM membership m
    JOIN clubsocieties c ON m.clubID = c.clubID
    LEFT JOIN lecturer l ON c.lectID = l.lectID
    LEFT JOIN membership cm ON c.clubID = cm.clubID
    WHERE m.studID = ?
    GROUP BY c.clubID, c.clubName, c.clubDescription, c.clubCapacity, c.created_at, m.joined_at, l.lectName
    ORDER BY m.joined_at DESC
";
$stmt = $conn->prepare($clubs_query);
$stmt->execute([$studID]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Club Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .info-card {
            transition: all 0.3s ease;
        }
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .profile-image-preview {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #0045b4ff;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }
        .profile-image-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 32px rgba(59, 130, 246, 0.4);
        }
        .upload-area {
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            transform: scale(1.1);
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
        }
        .password-toggle:hover {
            color: #374151;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="drawer">
        <input id="my-drawer-3" type="checkbox" class="drawer-toggle" /> 
        <div class="drawer-content flex flex-col">
            <!-- Include Navbar -->
            <?php include "includes/navbar.php"; ?>

            <!-- Main Content -->
            <main class="min-h-screen pt-20 pb-10">
                <div class="container mx-auto px-4">
                    <!-- Header -->
                    <div class="mb-8">
                        <h1 class="text-4xl font-bold mb-2 bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                            My Profile
                        </h1>
                        <p class="text-gray-600">Manage your account information and settings</p>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success mb-4 shadow-lg transition-opacity duration-500" id="success-alert">
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                            <button onclick="dismissAlert('success-alert')" class="btn btn-sm btn-ghost">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-error mb-4 shadow-lg transition-opacity duration-500" id="error-alert">
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                            <button onclick="dismissAlert('error-alert')" class="btn btn-sm btn-ghost">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Profile Information Card -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Profile Picture Section -->
                            <div class="card bg-white shadow-xl">
                                <div class="card-body">
                                    <h2 class="card-title mb-4 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Profile Picture
                                    </h2>
                                    
                                    <form method="POST" enctype="multipart/form-data" id="profilePictureForm">
                                        <div class="flex flex-col items-center gap-4">
                                            <div class="relative">
                                                <?php 
                                                // Determine image source
                                                $imageSrc = '../assets/default-avatar.png'; // Default
                                                
                                                if (!empty($student['studProfileImg'])) {
                                                    $imagePath = '../uploads/student_profiles/' . $student['studProfileImg'];
                                                    if (file_exists($imagePath)) {
                                                        $imageSrc = $imagePath;
                                                    }
                                                }
                                                ?>
                                                
                                                <img id="profileImagePreview" 
                                                    src="<?php echo htmlspecialchars($imageSrc); ?>" 
                                                    alt="Profile" 
                                                    class="profile-image-preview"
                                                    onerror="this.src='../assets/default-avatar.png'">
                                                
                                                <label for="studProfileImg" class="absolute bottom-2 right-2 btn btn-circle btn-primary upload-area cursor-pointer">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                </label>
                                                
                                                <input type="file" 
                                                    id="studProfileImg" 
                                                    name="studProfileImg" 
                                                    accept="image/jpeg,image/png,image/jpg,image/gif" 
                                                    class="hidden"
                                                    onchange="previewImage(this)">
                                            </div>
                                            
                                            <div class="text-center">
                                                <p class="text-sm text-gray-600 font-medium">Click the camera icon to change photo</p>
                                                <p class="text-xs text-gray-400 mt-1">Max 5MB • JPG, PNG, GIF</p>
                                            </div>
                                            
                                            <button type="submit" 
                                                    id="uploadBtn" 
                                                    class="btn btn-primary gap-2 hidden">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                                Upload Photo
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>


                            <!-- Student Info Display -->
                            <div class="card bg-white shadow-xl">
                                <div class="card-body">
                                    <h2 class="card-title mb-4 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Student Information
                                    </h2>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="info-card p-4 bg-gradient-to-br from-purple-50 to-blue-50 rounded-lg border border-purple-100">
                                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Full Name</label>
                                            <p class="text-lg font-bold text-gray-800 mt-1"><?php echo htmlspecialchars($student['studName']); ?></p>
                                        </div>

                                        <div class="info-card p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-100">
                                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Student ID</label>
                                            <p class="text-lg font-bold text-gray-800 mt-1"><?php echo htmlspecialchars($student['studNoID']); ?></p>
                                        </div>

                                        <div class="info-card p-4 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg border border-indigo-100">
                                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Programme</label>
                                            <p class="text-lg font-bold text-gray-800 mt-1"><?php echo htmlspecialchars($student['studProgramme']); ?></p>
                                        </div>

                                        <div class="info-card p-4 bg-gradient-to-br from-purple-50 to-pink-50 rounded-lg border border-purple-100">
                                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Semester</label>
                                            <p class="text-lg font-bold text-gray-800 mt-1">Semester <?php echo htmlspecialchars($student['studSem']); ?></p>
                                        </div>

                                        <div class="info-card p-4 bg-gradient-to-br from-pink-50 to-rose-50 rounded-lg border border-pink-100 md:col-span-2">
                                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Registered Since</label>
                                            <p class="text-lg font-bold text-gray-800 mt-1">
                                                <?php echo date('F d, Y', strtotime($student['register_date'])); ?>
                                                <span class="text-sm text-gray-500 ml-2">
                                                    (<?php 
                                                        $register_date = new DateTime($student['register_date']);
                                                        $now = new DateTime();
                                                        $interval = $register_date->diff($now);
                                                        if ($interval->y > 0) {
                                                            echo $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
                                                        } elseif ($interval->m > 0) {
                                                            echo $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
                                                        } else {
                                                            echo $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
                                                        }
                                                    ?> ago)
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Editable Fields -->
                            <div class="card bg-white shadow-xl">
                                <div class="card-body">
                                    <h2 class="card-title mb-4 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Update Profile
                                    </h2>
                                    <form method="POST" class="space-y-6" id="settingsForm">
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text font-semibold flex items-center gap-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                    </svg>
                                                    Email Address
                                                </span>
                                            </label>
                                            <input type="email" name="studEmail" 
                                                   value="<?php echo htmlspecialchars($student['studEmail']); ?>" 
                                                   class="input input-bordered w-full" required>
                                            <label class="label">
                                                <span class="label-text-alt text-gray-500">Used for important notifications and account recovery</span>
                                            </label>
                                        </div>

                                        <div class="divider">
                                            <span class="flex items-center gap-2 text-gray-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                Change Password
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="form-control md:col-span-2">
                                                <label class="label">
                                                    <span class="label-text font-semibold">Current Password</span>
                                                </label>
                                                <div class="relative">
                                                    <input type="password" name="current_password" 
                                                           id="current_password"
                                                           class="input input-bordered w-full pr-12"
                                                           placeholder="Enter your current password">
                                                    <span class="password-toggle" onclick="togglePassword('current_password')">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </span>
                                                </div>
                                                <label class="label">
                                                    <span class="label-text-alt text-gray-500">Required to change password</span>
                                                </label>
                                            </div>

                                            <div class="form-control">
                                                <label class="label">
                                                    <span class="label-text font-semibold">New Password</span>
                                                </label>
                                                <div class="relative">
                                                    <input type="password" name="new_password" 
                                                           id="new_password"
                                                           class="input input-bordered w-full pr-12" 
                                                           minlength="8" 
                                                           placeholder="Minimum 8 characters">
                                                    <span class="password-toggle" onclick="togglePassword('new_password')">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="form-control">
                                                <label class="label">
                                                    <span class="label-text font-semibold">Confirm New Password</span>
                                                </label>
                                                <div class="relative">
                                                    <input type="password" name="confirm_password" 
                                                           id="confirm_password"
                                                           class="input input-bordered w-full pr-12" 
                                                           minlength="8"
                                                           placeholder="Re-enter new password">
                                                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-6 flex gap-3">
                                            <button type="submit" class="btn bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Save Changes
                                            </button>
                                            <button type="reset" class="btn btn-ghost">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- My Clubs Sidebar -->
                        <div class="lg:col-span-1">
                            <div class="card bg-white shadow-xl sticky top-24">
                                <div class="card-body">
                                    <h2 class="card-title mb-4 flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        My Clubs
                                    </h2>
                                    
                                    <?php if (empty($clubs)): ?>
                                        <div class="text-center py-8">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <p class="text-gray-500 text-sm mb-4">You haven't joined any clubs yet</p>
                                            <a href="clubs.php" class="btn btn-sm bg-gradient-to-r from-purple-600 to-blue-600 text-white border-none">
                                                Explore Clubs
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="space-y-3 max-h-[500px] overflow-y-auto">
                                            <?php foreach ($clubs as $club): ?>
                                                <div class="card bg-base-200 hover:shadow-md transition-all cursor-pointer" onclick="window.location.href='my_clubs.php?club_id=<?php echo $club['clubID']; ?>'">
                                                    <div class="card-body p-4">
                                                        <div class="flex items-center gap-3 mb-2">
                                                            <div class="avatar">
                                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-400 to-blue-400 flex items-center justify-center text-white font-bold text-sm">
                                                                    <img src="../<?php echo htmlspecialchars($club['clubLogo']); ?>" 
                                                                         alt="<?php echo strtoupper(substr($club['clubName'], 0, 2)); ?>" 
                                                                         class="w-10 h-10 rounded-full object-cover">
                                                                </div>
                                                            </div>
                                                            <div class="flex-1">
                                                                <h3 class="font-bold text-sm"><?php echo htmlspecialchars($club['clubName']); ?></h3>
                                                                <p class="text-xs text-gray-500">
                                                                    <?php echo $club['member_count']; ?> / <?php echo $club['clubCapacity']; ?> members
                                                                </p>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if (!empty($club['advisor_name'])): ?>
                                                            <div class="text-xs text-gray-500 flex items-center gap-1 mt-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                                                </svg>
                                                                <?php echo htmlspecialchars($club['advisor_name']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <progress 
                                                            class="progress progress-sm <?php echo ($club['member_count'] >= $club['clubCapacity']) ? 'progress-error' : 'progress-success'; ?> w-full mt-2" 
                                                            value="<?php echo $club['member_count']; ?>" 
                                                            max="<?php echo $club['clubCapacity']; ?>">
                                                        </progress>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="mt-4 pt-4 border-t">
                                            <div class="stats shadow w-full">
                                                <div class="stat p-4">
                                                    <div class="stat-title text-xs">Total Clubs</div>
                                                    <div class="stat-value text-2xl text-purple-600"><?php echo count($clubs); ?></div>
                                                    <div class="stat-desc">Active memberships</div>
                                                </div>
                                            </div>
                                        </div>

                                        <a href="my_clubs.php" class="btn btn-block btn-outline btn-primary mt-4">
                                            View All Clubs
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <?php include "includes/footer.php"; ?>
        </div>

        <!-- Include Mobile Drawer -->
        <?php include "includes/mobile_drawer.php"; ?>
    </div>

    <script>
        // Password visibility toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                `;
            } else {
                input.type = 'password';
                icon.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                `;
            }
        }

        // Profile picture preview
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                const uploadBtn = document.getElementById('uploadBtn');
                
                reader.onload = function(e) {
                    const preview = document.getElementById('profileImagePreview'); // ✅ Correct ID
                    
                    if (preview) {
                        preview.src = e.target.result;
                    }
                    
                    // Show upload button
                    uploadBtn.classList.remove('hidden');
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Auto-dismiss alerts after 4 seconds
        function dismissAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const errorAlert = document.getElementById('error-alert');
            const successAlert = document.getElementById('success-alert');
            
            if (errorAlert) {
                setTimeout(() => dismissAlert('error-alert'), 4000);
            }
            
            if (successAlert) {
                setTimeout(() => dismissAlert('success-alert'), 4000);
            }
        });

        // Password validation
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword && newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
        });

        // Add loading state to settings form
        document.getElementById('settingsForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            }
        });

        // Add loading state to profile picture form
        document.getElementById('profilePictureForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('uploadBtn');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="loading loading-spinner loading-sm"></span> Uploading...';
            }
        });
    </script>
</body>
</html>
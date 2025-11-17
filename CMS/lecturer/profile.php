<?php
session_start();
require_once "../config/connect.php";

if (!isset($_SESSION['lect_id'])) {
    header('Location: ../user_access.php');
    exit;
}

$lectID = $_SESSION['lect_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updates = [];
        $params = [];
        
        // Profile picture upload
        if (isset($_FILES['lectProfileImg']) && $_FILES['lectProfileImg']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file_type = $_FILES['lectProfileImg']['type'];
            $file_size = $_FILES['lectProfileImg']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
            }
            
            if ($file_size > $max_size) {
                throw new Exception("File size exceeds 5MB limit.");
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = "../uploads/lecturer_profiles/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['lectProfileImg']['name'], PATHINFO_EXTENSION);
            $new_filename = "lecturer_" . $lectID . "_" . time() . "." . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Delete old profile picture if exists
            $stmt = $conn->prepare("SELECT lectProfileImg FROM lecturer WHERE lectID = ?");
            $stmt->execute([$lectID]);
            $old_image = $stmt->fetchColumn();
            
            if ($old_image && file_exists("../" . $old_image)) {
                unlink("../" . $old_image);
            }
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['lectProfileImg']['tmp_name'], $upload_path)) {
                $updates[] = "lectProfileImg = ?";
                $params[] = "uploads/lecturer_profiles/" . $new_filename;
            } else {
                throw new Exception("Failed to upload profile picture.");
            }
        }

        // Email update
        if (isset($_POST['lectEmail']) && !empty($_POST['lectEmail'])) {
            // Check if email is already taken by another lecturer
            $check_query = "SELECT lectID FROM lecturer WHERE lectEmail = ? AND lectID != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([$_POST['lectEmail'], $lectID]);
            if ($check_stmt->fetch()) {
                throw new Exception("Email is already taken by another lecturer");
            }
            $updates[] = "lectEmail = ?";
            $params[] = $_POST['lectEmail'];
        }

        // Password update
        if (!empty($_POST['new_password'])) {
            if (empty($_POST['current_password'])) {
                throw new Exception("Current password is required to set a new password");
            }
            
            // Verify current password
            $stmt = $conn->prepare("SELECT lectPass FROM lecturer WHERE lectID = ?");
            $stmt->execute([$lectID]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($_POST['current_password'], $lecturer['lectPass'])) {
                throw new Exception("Current password is incorrect");
            }
            
            // Validate new password
            if (strlen($_POST['new_password']) <= 6) {
                throw new Exception("New password must be at least 8 characters long");
            }
            
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception("New passwords do not match");
            }
            
            $updates[] = "lectPass = ?";
            $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        }

        // Update profile if there are changes
        if (!empty($updates)) {
            $params[] = $lectID; // Add lectID for WHERE clause
            $query = "UPDATE lecturer SET " . implode(", ", $updates) . " WHERE lectID = ?";
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

// Get lecturer data
$stmt = $conn->prepare("SELECT * FROM lecturer WHERE lectID = ?");
$stmt->execute([$lectID]);
$lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get clubs managed by this lecturer
$clubs_query = "
    SELECT 
        c.*,
        COUNT(DISTINCT m.cmID) as member_count
    FROM clubsocieties c
    LEFT JOIN membership m ON c.clubID = m.clubID
    WHERE c.lectID = ?
    GROUP BY c.clubID, c.clubName, c.clubDescription, c.clubCapacity, c.clubLogo, c.created_at
    ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($clubs_query);
$stmt->execute([$lectID]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total statistics
$total_members = 0;
$total_capacity = 0;
foreach ($clubs as $club) {
    $total_members += $club['member_count'];
    $total_capacity += $club['clubCapacity'];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Lecturer</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .info-card {
            transition: all 0.3s ease;
        }
        .info-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
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
            cursor: pointer;
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
            transition: color 0.2s;
        }
        .password-toggle:hover {
            color: #374151;
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
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
                                <li>My Profile</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success mb-4 shadow-lg transition-opacity duration-500 animate-fade-in" id="success-alert">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                        <button onclick="dismissAlert('success-alert')" class="btn btn-sm btn-ghost">✕</button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error mb-4 shadow-lg transition-opacity duration-500 animate-fade-in" id="error-alert">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                        <button onclick="dismissAlert('error-alert')" class="btn btn-sm btn-ghost">✕</button>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Profile Information -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Profile Picture -->
                        <div class="card bg-base-100 shadow-xl animate-fade-in">
                            <div class="card-body">
                                <h2 class="card-title mb-4 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Profile Picture
                                </h2>
                                
                                <form method="POST" enctype="multipart/form-data" id="profilePictureForm">
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="relative">
                                            <img id="profileImagePreview" 
                                                 src="<?php echo !empty($lecturer['lectProfileImg']) ? '../' . htmlspecialchars($lecturer['lectProfileImg']) : '../assets/default-avatar.png'; ?>" 
                                                 alt="Profile" 
                                                 class="profile-image-preview"
                                                 onerror="this.src='../assets/default-avatar.png'">
                                            <label for="lectProfileImg" class="absolute bottom-2 right-2 btn btn-circle btn-primary upload-area">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </label>
                                            <input type="file" 
                                                   id="lectProfileImg" 
                                                   name="lectProfileImg" 
                                                   accept="image/*" 
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

                        <!-- Lecturer Info Display -->
                        <div class="card bg-base-100 shadow-xl animate-fade-in">
                            <div class="card-body">
                                <h2 class="card-title mb-4 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                    </svg>
                                    Lecturer Information
                                </h2>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="info-card p-5 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-100">
                                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-2 mb-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            Full Name
                                        </label>
                                        <p class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($lecturer['lectName']); ?></p>
                                    </div>

                                    <div class="info-card p-5 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg border border-indigo-100">
                                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-2 mb-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            Faculty
                                        </label>
                                        <p class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($lecturer['lectFaculty']); ?></p>
                                    </div>

                                    <div class="info-card p-5 bg-gradient-to-br from-purple-50 to-pink-50 rounded-lg border border-purple-100 md:col-span-2">
                                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-2 mb-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            Email Address
                                        </label>
                                        <p class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($lecturer['lectEmail']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Update Profile Form -->
                        <div class="card bg-base-100 shadow-xl animate-fade-in">
                            <div class="card-body">
                                <h2 class="card-title mb-4 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Update Profile Settings
                                </h2>
                                <form method="POST" class="space-y-6" id="settingsForm">
                                    <!-- Email -->
                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text font-semibold flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                </svg>
                                                Email Address
                                            </span>
                                        </label>
                                        <input type="email" name="lectEmail" 
                                               value="<?php echo htmlspecialchars($lecturer['lectEmail']); ?>" 
                                               class="input input-bordered w-full" required>
                                        <label class="label">
                                            <span class="label-text-alt text-gray-500">Used for notifications and account recovery</span>
                                        </label>
                                    </div>

                                    <div class="divider">
                                        <span class="flex items-center gap-2 text-gray-600 font-semibold">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            Change Password
                                        </span>
                                    </div>

                                    <!-- Password Fields -->
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
                                                <span class="label-text-alt text-gray-500">Required only if changing password</span>
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
                                        <button type="submit" class="btn btn-primary gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                        <div class="card bg-base-100 shadow-xl sticky top-24 animate-fade-in">
                            <div class="card-body">
                                <h2 class="card-title mb-4 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    Clubs I Manage
                                </h2>
                                
                                <?php if (empty($clubs)): ?>
                                    <div class="text-center py-8">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        <p class="text-gray-500 text-sm mb-4">No clubs assigned yet</p>
                                        <p class="text-xs text-gray-400">Contact admin to be assigned as club advisor</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-3 max-h-[500px] overflow-y-auto pr-2">
                                        <?php foreach ($clubs as $club): 
                                            $percentage = ($club['clubCapacity'] > 0) ? ($club['member_count'] / $club['clubCapacity']) * 100 : 0;
                                            $isFull = $club['member_count'] >= $club['clubCapacity'];
                                        ?>
                                            <div class="card bg-gradient-to-br from-gray-50 to-blue-50 hover:shadow-lg transition-all cursor-pointer border border-gray-200" 
                                                 onclick="window.location.href='clubs.php?id=<?php echo $club['clubID']; ?>'">
                                                <div class="card-body p-4">
                                                    <div class="flex items-start gap-3 mb-3">
                                                        <div class="avatar">
                                                            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center text-white font-bold shadow-md">
                                                                <?php if (!empty($club['clubLogo'])): ?>
                                                                    <img src="../<?php echo htmlspecialchars($club['clubLogo']); ?>" 
                                                                         alt="<?php echo htmlspecialchars($club['clubName']); ?>" 
                                                                         class="w-12 h-12 rounded-lg object-cover"
                                                                         onerror="this.style.display='none'; this.parentElement.innerHTML='<?php echo strtoupper(substr($club['clubName'], 0, 2)); ?>'">
                                                                <?php else: ?>
                                                                    <?php echo strtoupper(substr($club['clubName'], 0, 2)); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <h3 class="font-bold text-sm truncate"><?php echo htmlspecialchars($club['clubName']); ?></h3>
                                                            <div class="flex items-center gap-2 mt-1">
                                                                <span class="text-xs text-gray-600 font-medium">
                                                                    <?php echo $club['member_count']; ?> / <?php echo $club['clubCapacity']; ?>
                                                                </span>
                                                                <?php if ($isFull): ?>
                                                                    <span class="badge badge-error badge-xs">FULL</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="space-y-2">
                                                        <div class="flex justify-between text-xs text-gray-500">
                                                            <span><?php echo number_format($percentage, 1); ?>% Full</span>
                                                            <span><?php echo $club['clubCapacity'] - $club['member_count']; ?> spots</span>
                                                        </div>
                                                        <progress 
                                                            class="progress <?php echo $isFull ? 'progress-error' : 'progress-primary'; ?> w-full h-2" 
                                                            value="<?php echo $club['member_count']; ?>" 
                                                            max="<?php echo $club['clubCapacity']; ?>">
                                                        </progress>
                                                    </div>

                                                    <?php if (!empty($club['clubDescription'])): ?>
                                                        <p class="text-xs text-gray-500 mt-2 line-clamp-2">
                                                            <?php echo htmlspecialchars(substr($club['clubDescription'], 0, 80)) . (strlen($club['clubDescription']) > 80 ? '...' : ''); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Summary Stats -->
                                    <div class="mt-4 pt-4 border-t space-y-3">
                                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                            <span class="text-sm font-semibold text-gray-700">Total Clubs</span>
                                            <span class="text-2xl font-bold text-blue-600"><?php echo count($clubs); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                                            <span class="text-sm font-semibold text-gray-700">Total Members</span>
                                            <span class="text-2xl font-bold text-green-600"><?php echo $total_members; ?></span>
                                        </div>
                                    </div>

                                    <div class="flex gap-2 mt-4">
                                        <a href="clubs.php" class="btn btn-primary btn-sm flex-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View All
                                        </a>
                                        <a href="memberships.php" class="btn btn-outline btn-sm flex-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                            </svg>
                                            Members
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'includes/sidebar.php'; ?>
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
                const file = input.files[0];
                
                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size exceeds 5MB limit.');
                    input.value = '';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                const uploadBtn = document.getElementById('uploadBtn');
                
                reader.onload = function(e) {
                    document.getElementById('profileImagePreview').src = e.target.result;
                    uploadBtn.classList.remove('hidden');
                };
                
                reader.readAsDataURL(file);
            }
        }

        // Auto-dismiss alerts
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
            const currentPassword = document.querySelector('input[name="current_password"]').value;
            
            if (newPassword && !currentPassword) {
                e.preventDefault();
                alert('Please enter your current password to change your password.');
                return false;
            }
            
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

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php
session_start();
require_once "../config/connect.php";

if (!isset($_SESSION['lect_id'])) {
    header('Location: ../user_access.php');
    exit;
}

$lectID = $_SESSION['lect_id'];

// Fetch clubs where the logged-in lecturer is in charge
// Include counts for members, events, and announcements
$query = "SELECT 
            c.clubID,
            c.clubName,
            c.clubDescription,
            c.clubCapacity,
            c.clubLogo,
            c.created_at,
            l.lectName,
            COUNT(DISTINCT cm.cmID) as member_count,
            COUNT(DISTINCT e.eventID) as event_count,
            COUNT(DISTINCT a.annID) as announcement_count,
            COUNT(DISTINCT ca.actID) as activity_count
          FROM clubsocieties c
          LEFT JOIN lecturer l ON c.lectID = l.lectID
          LEFT JOIN membership cm ON c.clubID = cm.clubID
          LEFT JOIN casevents e ON c.clubID = e.clubID
          LEFT JOIN casannouncement a ON c.clubID = a.clubID
          LEFT JOIN casactivity ca ON c.clubID = ca.clubID
          WHERE c.lectID = ?
          GROUP BY c.clubID, c.clubName, c.clubDescription, c.clubCapacity, c.clubLogo, c.created_at, l.lectName
          ORDER BY c.clubName";

$stmt = $conn->prepare($query);
$stmt->execute([$lectID]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Clubs</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
</head>
<body>
    <div class="drawer lg:drawer-open">
        <input id="my-drawer-2" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content">
            <!-- Page content -->
            <div class="p-4" style="background-color: #bed3f3ff;">
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
                                <li>My Clubs</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success mb-4 transition-opacity duration-500" id="success-alert">
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

                <?php if (empty($clubs)): ?>
                    <!-- Empty State -->
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <h3 class="text-2xl font-bold text-gray-500">No Clubs Assigned</h3>
                            <p class="text-gray-400 mt-2">You are not currently in charge of any clubs or societies.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Clubs Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($clubs as $club): ?>
                            <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
                                <!-- Club Logo/Header -->
                                <figure class="px-6 pt-6 pb-6">
                                    <?php if (!empty($club['clubLogo'])): ?>
                                        <div class="avatar">
                                            <div class="w-24 h-24 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                                                <img src="../<?php echo htmlspecialchars($club['clubLogo']); ?>" 
                                                    alt="<?php echo htmlspecialchars($club['clubName']); ?>" 
                                                    class="object-cover" />
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar placeholder">
                                            <div class="bg-primary text-primary-content rounded-full w-24 h-24 ring ring-primary ring-offset-base-100 ring-offset-2">
                                                <span class="text-3xl font-bold">
                                                    <?php echo strtoupper(substr($club['clubName'], 0, 2)); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </figure>
                                
                                <div class="card-body items-center text-center">
                                    <!-- Club Name -->
                                    <h2 class="card-title text-xl font-bold mb-2">
                                        <?php echo htmlspecialchars($club['clubName']); ?>
                                    </h2>
                                    
                                    <!-- Club Description -->
                                    <p class="text-sm text-gray-600 line-clamp-3 mb-4">
                                        <?php echo htmlspecialchars($club['clubDescription']); ?>
                                    </p>
                                    
                                    <!-- Capacity Progress -->
                                    <div class="w-full mb-4">
                                        <div class="flex justify-between text-xs mb-2">
                                            <span class="font-semibold">Member Capacity</span>
                                            <span class="<?php echo ($club['member_count'] >= $club['clubCapacity']) ? 'text-error font-bold' : 'text-success'; ?>">
                                                <?php echo $club['member_count']; ?> / <?php echo $club['clubCapacity']; ?>
                                            </span>
                                        </div>
                                        <progress 
                                            class="progress <?php echo ($club['member_count'] >= $club['clubCapacity']) ? 'progress-error' : 'progress-success'; ?> w-full h-3" 
                                            value="<?php echo $club['member_count']; ?>" 
                                            max="<?php echo $club['clubCapacity']; ?>">
                                        </progress>
                                        <?php if ($club['member_count'] >= $club['clubCapacity']): ?>
                                            <div class="badge badge-error badge-sm mt-2">Full Capacity</div>
                                        <?php elseif ($club['member_count'] >= $club['clubCapacity'] * 0.8): ?>
                                            <div class="badge badge-warning badge-sm mt-2">Almost Full</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Stats Grid -->
                                    <div class="grid grid-cols-3 gap-3 w-full">

                                        <!-- Announcements -->
                                        <div class="stat bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-3 border border-purple-200">
                                            <div class="stat-title text-xs">Announcement</div>
                                            <div class="stat-value text-xl text-purple-600"><?php echo $club['announcement_count']; ?></div>
                                        </div>

                                        <!-- Events -->
                                        <div class="stat bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-3 border border-green-200">
                                            <div class="stat-title text-xs">Events</div>
                                            <div class="stat-value text-xl text-green-600"><?php echo $club['event_count']; ?></div>
                                        </div>

                                        <!-- Activities -->
                                        <div class="stat bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-3 border border-blue-200">
                                            <div class="stat-title text-xs">Activities</div>
                                            <div class="stat-value text-xl text-blue-600"><?php echo $club['activity_count']; ?></div>
                                        </div>

                                    </div>

                                    <!-- Action Button -->
                                    <div class="card-actions justify-center w-full mt-4">
                                        <a href="club_profile.php?clubID=<?php echo $club['clubID']; ?>" 
                                        class="btn btn-primary btn-block">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View Club Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <script>
        // Auto-dismiss alerts after 3 seconds
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
            const successAlert = document.getElementById('success-alert');
            if (successAlert) {
                setTimeout(() => dismissAlert('success-alert'), 3000);
            }
        });
    </script>
</body>
</html>
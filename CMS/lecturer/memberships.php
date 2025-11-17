<?php
session_start();
require_once "../config/connect.php";

if (!isset($_SESSION['lect_id'])) {
    header('Location: ../user_access.php');
    exit;
}

$lectID = $_SESSION['lect_id'];

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $club_filter = isset($_GET['club']) && $_GET['club'] !== '' ? $_GET['club'] : null;
    $date_filter = isset($_GET['date_filter']) && $_GET['date_filter'] !== '' ? $_GET['date_filter'] : null;
    $search = $_GET['search'] ?? '';

    // Build query for export
    $query = "SELECT cm.*, c.clubName, s.studName, s.studNoID, s.studEmail
              FROM membership cm
              INNER JOIN clubsocieties c ON cm.clubID = c.clubID
              LEFT JOIN student s ON cm.studID = s.studID
              WHERE c.lectID = ?";
    $params = [$lectID];

    if ($club_filter !== null) {
        $query .= " AND cm.clubID = ?";
        $params[] = $club_filter;
    }

    if ($date_filter !== null) {
        $query .= " AND DATE(cm.joined_at) = ?";
        $params[] = $date_filter;
    }

    if ($search) {
        $query .= " AND (c.clubName LIKE ? OR s.studName LIKE ? OR s.studNoID LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY cm.joined_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate CSV with BOM for proper UTF-8 encoding
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="club_members_' . date('Y-m-d_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    fputcsv($output, ['Club Name', 'Student Name', 'Student ID', 'Email', 'Joined Date']);
    
    // CSV Data
    foreach ($export_data as $row) {
        // Format date in Excel-friendly format
        $joinedDate = 'N/A';
        if (!empty($row['joined_at'])) {
            $timestamp = strtotime($row['joined_at']);
            if ($timestamp !== false) {
                // Use tab character to force text format in Excel
                $joinedDate = "\t" . date('Y-m-d H:i:s', $timestamp);
            }
        }
        
        fputcsv($output, [
            $row['clubName'] ?? 'Unknown Club',
            $row['studName'] ?? 'Unknown Student',
            $row['studNoID'] ?? 'N/A',
            $row['studEmail'] ?? 'N/A',
            $joinedDate
        ]);
    }
    
    fclose($output);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'remove':
                    $verify_query = "SELECT c.clubID FROM membership cm 
                                    INNER JOIN clubsocieties c ON cm.clubID = c.clubID 
                                    WHERE cm.cmID = ? AND c.lectID = ?";
                    $verify_stmt = $conn->prepare($verify_query);
                    $verify_stmt->execute([$_POST['cmID'], $lectID]);
                    
                    if (!$verify_stmt->fetch()) {
                        throw new Exception('You do not have permission to remove this membership.');
                    }

                    $query = "DELETE FROM membership WHERE cmID = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$_POST['cmID']]);
                    $_SESSION['success'] = "Student removed from club successfully!";
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header("Location: memberships.php");
        exit;
    }
}

// Fetch clubs
$clubs_query = "SELECT clubID, clubName FROM clubsocieties WHERE lectID = ? ORDER BY clubName";
$stmt = $conn->prepare($clubs_query);
$stmt->execute([$lectID]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$club_filter = isset($_GET['club']) && $_GET['club'] !== '' ? $_GET['club'] : null;
$date_filter = isset($_GET['date_filter']) && $_GET['date_filter'] !== '' ? $_GET['date_filter'] : null;
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT cm.*, c.clubName, s.studName, s.studNoID, s.studEmail
          FROM membership cm
          INNER JOIN clubsocieties c ON cm.clubID = c.clubID
          LEFT JOIN student s ON cm.studID = s.studID
          WHERE c.lectID = ?";
$params = [$lectID];

if ($club_filter !== null) {
    $query .= " AND cm.clubID = ?";
    $params[] = $club_filter;
}

if ($date_filter !== null) {
    $query .= " AND DATE(cm.joined_at) = ?";
    $params[] = $date_filter;
}

if ($search) {
    $query .= " AND (c.clubName LIKE ? OR s.studName LIKE ? OR s.studNoID LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY cm.joined_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get club stats
$club_stats_query = "
    SELECT 
        c.clubID,
        c.clubName,
        c.clubCapacity,
        COUNT(cm.cmID) as member_count
    FROM clubsocieties c
    LEFT JOIN membership cm ON c.clubID = cm.clubID
    WHERE c.lectID = ?
    GROUP BY c.clubID, c.clubName, c.clubCapacity
";
$stmt = $conn->prepare($club_stats_query);
$stmt->execute([$lectID]);
$club_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memberships</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png?v=<?php echo filemtime('../assets/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png?v=<?php echo filemtime('../assets/favicon-16x16.png'); ?>">
    <style>
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .table-row-hover {
            transition: background-color 0.2s;
        }
        .table-row-hover:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
        .filter-badge {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .export-btn {
            transition: all 0.3s ease;
        }
        .export-btn:hover {
            transform: scale(1.05);
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
                                <li>Memberships</li>
                            </ul>
                        </div>
                    </div>
                    <?php if (!empty($clubs)): ?>
                    <div class="flex-none">
                        <button onclick="exportToCSV()" class="btn btn-success btn-sm export-btn gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export CSV
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Alerts -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error mb-4 transition-opacity duration-500" id="error-alert">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                        <button onclick="dismissAlert('error-alert')" class="btn btn-sm btn-ghost">✕</button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success mb-4 transition-opacity duration-500" id="success-alert">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                        <button onclick="dismissAlert('success-alert')" class="btn btn-sm btn-ghost">✕</button>
                    </div>
                <?php endif; ?>

                <?php if (empty($clubs)): ?>
                    <!-- No Clubs -->
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <h3 class="text-2xl font-bold text-gray-500">No Clubs Assigned</h3>
                            <p class="text-gray-400 mt-2">You need to be in charge of a club to manage members.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Club Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                        <?php foreach ($club_stats as $stat): 
                            $percentage = ($stat['clubCapacity'] > 0) ? ($stat['member_count'] / $stat['clubCapacity']) * 100 : 0;
                            $isNearFull = $percentage >= 80;
                            $isFull = $stat['member_count'] >= $stat['clubCapacity'];
                        ?>
                            <div class="card bg-gradient-to-br from-white to-gray-50 shadow-xl stat-card cursor-pointer" onclick="filterByClub(<?= $stat['clubID'] ?>)">
                                <div class="card-body">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="card-title text-sm text-gray-700"><?= htmlspecialchars($stat['clubName']) ?></h3>
                                        <?php if ($isFull): ?>
                                            <span class="badge badge-error badge-sm">FULL</span>
                                        <?php elseif ($isNearFull): ?>
                                            <span class="badge badge-warning badge-sm">NEAR FULL</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-4xl font-bold text-primary"><?= $stat['member_count'] ?></p>
                                            <p class="text-sm text-gray-500">Members</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl text-gray-600">/ <?= $stat['clubCapacity'] ?></p>
                                            <p class="text-xs text-gray-500">Capacity</p>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                                            <span><?= number_format($percentage, 1) ?>% Full</span>
                                            <span><?= $stat['clubCapacity'] - $stat['member_count'] ?> spots left</span>
                                        </div>
                                        <progress 
                                            class="progress <?= $isFull ? 'progress-error' : ($isNearFull ? 'progress-warning' : 'progress-success') ?> w-full" 
                                            value="<?= $stat['member_count'] ?>" 
                                            max="<?= $stat['clubCapacity'] ?>">
                                        </progress>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Filters -->
                    <div class="card bg-base-100 shadow-xl mb-4">
                        <div class="card-body">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-lg flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                    Filters
                                </h3>
                                <div class="text-sm text-gray-500">
                                    <kbd class="kbd kbd-sm">/</kbd> to search
                                </div>
                            </div>
                            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="filter-form">
                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text font-semibold">Club</span>
                                    </label>
                                    <select name="club" class="select select-bordered w-full" onchange="this.form.submit()">
                                        <option value="">All My Clubs</option>
                                        <?php foreach ($clubs as $club): ?>
                                            <option value="<?php echo $club['clubID']; ?>" 
                                                <?php echo ($club_filter == $club['clubID']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($club['clubName']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text font-semibold">Joined Date</span>
                                    </label>
                                    <input type="date" name="date_filter" value="<?php echo htmlspecialchars($date_filter ?? ''); ?>" 
                                           class="input input-bordered w-full" onchange="this.form.submit()">
                                </div>

                                <div class="form-control lg:col-span-2">
                                    <label class="label">
                                        <span class="label-text font-semibold">Search</span>
                                    </label>
                                    <div class="join w-full">
                                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                               placeholder="Search by name, ID, or club..." class="input input-bordered join-item flex-1">
                                        <button class="btn btn-primary join-item">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <?php if ($club_filter || $date_filter || $search): ?>
                                <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t">
                                    <span class="text-sm font-semibold text-gray-600">Active Filters:</span>
                                    <?php if ($club_filter): 
                                        $selected_club = array_filter($clubs, fn($c) => $c['clubID'] == $club_filter);
                                        $selected_club = reset($selected_club);
                                    ?>
                                        <div class="badge badge-lg badge-primary gap-2 filter-badge">
                                            Club: <?= htmlspecialchars($selected_club['clubName']) ?>
                                            <button onclick="removeFilter('club')" class="text-white hover:text-error">✕</button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($date_filter): ?>
                                        <div class="badge badge-lg badge-secondary gap-2 filter-badge">
                                            Date: <?= date('M d, Y', strtotime($date_filter)) ?>
                                            <button onclick="removeFilter('date')" class="text-white hover:text-error">✕</button>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($search): ?>
                                        <div class="badge badge-lg badge-accent gap-2 filter-badge">
                                            Search: "<?= htmlspecialchars($search) ?>"
                                            <button onclick="removeFilter('search')" class="text-white hover:text-error">✕</button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="memberships.php" class="btn btn-ghost btn-xs">Clear All</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Members Table -->
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                                <h2 class="card-title text-xl">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    Club Members
                                </h2>
                                <div class="stats shadow">
                                    <div class="stat py-2 px-4">
                                        <div class="stat-title text-xs">Total Members</div>
                                        <div class="stat-value text-primary text-2xl"><?= count($memberships) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <?php if (empty($memberships)): ?>
                                    <div class="text-center py-12">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <h3 class="text-lg font-semibold text-gray-500 mb-2">No Members Found</h3>
                                        <p class="text-sm text-gray-400">Try adjusting your filters or search terms</p>
                                    </div>
                                <?php else: ?>
                                    <table class="table table-zebra">
                                        <thead>
                                            <tr>
                                                <th>
                                                    <div class="flex items-center gap-1">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                        </svg>
                                                        Club
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="flex items-center gap-1">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                        </svg>
                                                        Student Name
                                                    </div>
                                                </th>
                                                <th>Student ID</th>
                                                <th>Email</th>
                                                <th>Joined Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($memberships as $membership): ?>
                                                <tr class="table-row-hover">
                                                    <td>
                                                        <span class="badge badge-primary badge-lg">
                                                            <?php echo htmlspecialchars($membership['clubName'] ?? 'Unknown Club'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="flex items-center gap-3">
                                                            <div class="avatar placeholder">
                                                                <div class="bg-primary text-primary-content rounded-full w-10">
                                                                    <span class="text-lg"><?php echo strtoupper(substr($membership['studName'] ?? 'U', 0, 1)); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="font-bold"><?php echo htmlspecialchars($membership['studName'] ?? 'Unknown Student'); ?></div>
                                                        </div>
                                                    </td>
                                                    <td><span class="font-mono text-sm"><?php echo htmlspecialchars($membership['studNoID'] ?? 'N/A'); ?></span></td>
                                                    <td><?php echo htmlspecialchars($membership['studEmail'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <div class="flex items-center gap-2">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                            <?php echo date('M j, Y', strtotime($membership['joined_at'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning gap-2"
                                                                onclick="confirmRemove(<?php echo $membership['cmID']; ?>, '<?php echo htmlspecialchars($membership['studName'] ?? 'Unknown', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($membership['clubName'] ?? 'Unknown', ENT_QUOTES); ?>')">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
                                                            </svg>
                                                            Remove
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <!-- Remove Confirmation Modal -->
    <dialog id="remove_modal" class="modal">
        <form method="POST" class="modal-box bg-white rounded-2xl shadow-2xl p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-2xl font-semibold text-warning flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Remove Member
                </h3>
                <button type="button" onclick="remove_modal.close()" class="text-gray-500 hover:text-gray-800 transition">
                    ✕
                </button>
            </div>

            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="cmID" id="remove_id">

            <div class="space-y-2 text-gray-700">
                <p>Are you sure you want to remove 
                    <span id="remove_student" class="font-bold text-gray-900"></span> 
                    from 
                    <span id="remove_club" class="font-bold text-gray-900"></span>?
                </p>
                <p class="text-sm text-error font-medium">This will remove the student's membership from the club.</p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="btn" onclick="remove_modal.close()">Cancel</button>
                <button type="submit" class="btn btn-warning">Confirm Remove</button>
            </div>
        </form>
    </dialog>

    <script>
        // Export to CSV function
        function exportToCSV() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('export', 'csv');
            window.location.href = 'memberships.php?' + urlParams.toString();
        }

        // Filter by club (from stats card click)
        function filterByClub(clubID) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('club', clubID);
            urlParams.delete('export');
            window.location.href = 'memberships.php?' + urlParams.toString();
        }

        // Remove specific filter
        function removeFilter(filterType) {
            const urlParams = new URLSearchParams(window.location.search);
            if (filterType === 'club') {
                urlParams.delete('club');
            } else if (filterType === 'date') {
                urlParams.delete('date_filter');
            } else if (filterType === 'search') {
                urlParams.delete('search');
            }
            urlParams.delete('export');
            window.location.href = 'memberships.php?' + urlParams.toString();
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

        // Confirm remove modal
        function confirmRemove(cmID, studentName, clubName) {
            document.getElementById('remove_id').value = cmID;
            document.getElementById('remove_student').textContent = studentName;
            document.getElementById('remove_club').textContent = clubName;
            document.getElementById('remove_modal').showModal();
        }

        // Auto-dismiss alerts after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const errorAlert = document.getElementById('error-alert');
            const successAlert = document.getElementById('success-alert');
            
            if (errorAlert) {
                setTimeout(() => dismissAlert('error-alert'), 3000);
            }
            
            if (successAlert) {
                setTimeout(() => dismissAlert('success-alert'), 3000);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Press / to focus search
            if (e.key === '/' && !e.ctrlKey && !e.altKey && document.activeElement.tagName !== 'INPUT') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) searchInput.focus();
            }
            
            // Press Ctrl/Cmd + E to export CSV
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                const exportBtn = document.querySelector('.export-btn');
                if (exportBtn) exportToCSV();
            }

            // Press Escape to close modal
            if (e.key === 'Escape') {
                const modal = document.getElementById('remove_modal');
                if (modal) modal.close();
            }
        });

        // Add loading state to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.classList.contains('loading')) {
                    submitBtn.classList.add('loading');
                }
            });
        });

        // Add animation to table rows
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.table-row-hover');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    row.style.transition = 'opacity 0.3s ease, transform 0.3s ease, background-color 0.2s';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 30);
            });
        });

        // Tooltip for keyboard shortcuts
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                this.setAttribute('title', 'Tip: Press / to focus search anytime');
            });
        }
    </script>
</body>
</html>
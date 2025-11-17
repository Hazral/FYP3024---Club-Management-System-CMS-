<?php
$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}

// Function to get lecturer profile info
function getLecturerProfile($conn, $lectID) {
    try {
        $query = "SELECT lectName, lectProfileImg FROM lecturer WHERE lectID = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$lectID]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching lecturer profile: " . $e->getMessage());
        return null;
    }
}

// Call the function if lecturer is logged in
if (isset($_SESSION['lect_id'])) {
    $lecturer_profile = getLecturerProfile($conn, $_SESSION['lect_id']);
    
    // Update session with profile image if available
    if ($lecturer_profile) {
        $_SESSION['lectName'] = $lecturer_profile['lectName'];
        $_SESSION['lectProfileImg'] = $lecturer_profile['lectProfileImg'];
    }
}

?>
<div class="drawer-side z-10">
    <label for="my-drawer-2" class="drawer-overlay"></label>
    <aside class="bg-base-200 w-[var(--sidebar-width)] transition-all duration-300" id="sidebar"  style="background-color: #0971b6ff;">
        <div class="flex flex-col h-full">
            <!-- Header with Toggle -->
            <div class="p-4 flex items-center justify-between border-b border-base-300">
                <div class="flex items-center gap-4 sidebar-expanded">
                    <div class="avatar">
                        <div class="w-10 rounded-full ">
                            <?php if (!empty($_SESSION['lectProfileImg'])): ?>
                                <img src="../<?php echo htmlspecialchars($_SESSION['lectProfileImg']); ?>" 
                                     alt="Profile" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['lectName'] ?? 'Lecturer'); ?>&background=random" 
                                     alt="Default Avatar">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-sm" style="color: white;"><?php echo htmlspecialchars($_SESSION['lectName'] ?? 'Lecturer'); ?></span>
                        <span class="text-xs opacity-70" style="color: white;">Club Advisor</span>
                    </div>
                </div>
                <button class="btn btn-square btn-ghost btn-sm" id="sidebar-toggle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 p-4">
                <ul class="menu menu-vertical gap-2">
                    <li>
                        <a href="dashboard.php" class="nav-link flex items-center gap-3 h-11 rounded-lg <?php echo isActive('dashboard.php'); ?>" title="Dashboard">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span class="sidebar-expanded">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="clubs.php" class="nav-link flex items-center gap-3 h-11 rounded-lg <?php echo isActive('clubs.php'); ?>" title="My Clubs">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="sidebar-expanded">My Clubs</span>
                        </a>
                    </li>
                    <li>
                        <a href="event_log.php" class="nav-link flex items-center gap-3 h-11 rounded-lg <?php echo isActive('event_log.php'); ?>" title="Events Log">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="sidebar-expanded">Events Log</span>
                        </a>
                    </li>
                    <li>
                        <a href="activity_log.php" class="nav-link flex items-center gap-3 h-11 rounded-lg <?php echo isActive('activity_log.php'); ?>" title="Activity Log">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            <span class="sidebar-expanded">Activities Log</span>
                        </a>
                    </li>
                    <li>
                        <a href="memberships.php" class="nav-link flex items-center gap-3 h-11 rounded-lg <?php echo isActive('memberships.php'); ?>" title="Memberships">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                            </svg>
                            <span class="sidebar-expanded">Memberships</span>
                        </a>
                    </li>
                    <li>
                        <a href="comments_log.php" class="nav-link flex items-center gap-3 h-11 rounded-lg <?php echo isActive('comments_log.php'); ?>" title="Comments Log">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                            <span class="sidebar-expanded">Comments Log</span>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="nav-link flex items-center gap-3 h-11 rounded-lg <?php echo isActive('profile.php'); ?>" title="Profile">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="sidebar-expanded">Profile</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t border-base-300">
                <a href="lecturer_faq.php" class="nav-link flex items-center gap-3 h-11 rounded-lg <?php echo isActive('profile.php'); ?>" title="Profile">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="sidebar-expanded">Help & FAQs</span>
                </a>
                <a href="../student/logout.php" class="nav-link-logout flex items-center gap-3 h-11 rounded-lg" title="Logout">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="sidebar-expanded">Logout</span>
                </a>
            </div>
        </div>
    </aside>
</div>

<style>
:root {
    --sidebar-width: 280px;
}

#sidebar {
    min-height: 100vh;
    height: 100%;
}

#sidebar.compact {
    --sidebar-width: 80px;
}

#sidebar.compact .sidebar-expanded {
    display: none;
}

#sidebar.compact #sidebar-toggle svg {
    transform: rotate(180deg);
}

/* Navigation link styles */
.nav-link {
    color: white;
    transition: all 0.2s ease;
    padding: 0 1rem;
}

.nav-link:hover {
    background-color: rgba(255, 255, 255, 0.15);
    color: white;
}

.nav-link.active {
    background-color: rgba(255, 255, 255, 0.25);
    color: white;
    font-weight: 600;
}

.nav-link.active:hover {
    background-color: rgba(255, 255, 255, 0.3);
}

/* Logout link styles */
.nav-link-logout {
    color: #ffebee;
    transition: all 0.2s ease;
    padding: 0 1rem;
}

.nav-link-logout:hover {
    background-color: white;
    color: red;
    transform: translateX(2px);
}

.nav-link-logout:active {
    background-color: rgba(244, 67, 54, 0.3);
    transform: translateX(0);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebar-toggle');
    
    // Load saved state
    const isCompact = localStorage.getItem('sidebarCompact') === 'true';
    if (isCompact) {
        sidebar.classList.add('compact');
    }
    
    toggle.addEventListener('click', function() {
        sidebar.classList.toggle('compact');
        localStorage.setItem('sidebarCompact', sidebar.classList.contains('compact'));
    });
    
    // Add keyboard shortcut
    document.addEventListener('keydown', function(e) {
        // Alt + S to toggle sidebar
        if (e.altKey && e.key.toLowerCase() === 's') {
            e.preventDefault();
            toggle.click();
        }
    });
});
</script>
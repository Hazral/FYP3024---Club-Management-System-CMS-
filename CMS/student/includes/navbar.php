<?php 

require_once "../config/connect.php";

// Get student profile info only if logged in
$student_profile = null; // Initialize variable
if (isset($_SESSION['stud_id'])) {
    try {
        $query = "SELECT studName, studProfileImg FROM student WHERE studID = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$_SESSION['stud_id']]);
        $student_profile = $stmt->fetch(PDO::FETCH_ASSOC); // Changed from $result to $student_profile

    } catch (PDOException $e) {
        error_log("Error fetching student profile: " . $e->getMessage());
    }
}

?>

<style>

.dropdown-hover:hover .dropdown-content {
    display: block;
    animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}


.nav-link {
    transition: all 0.3s ease;
    position: relative;
    padding-bottom: 4px;
}

.nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: #9333ea;
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.nav-link:hover::after {
    width: 80%;
}


</style>

<!-- Navbar -->
<div class="w-full navbar bg-white fixed top-0 z-50 shadow-md">
    <div class="flex-none lg:hidden">
        <label for="my-drawer-3" class="btn btn-square btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-6 h-6 stroke-current text-gray-700">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </label>
    </div> 

    <!-- Logo -->
    <div class="flex-1 px-2 mx-2">
        <a href="index.php" class="flex items-center gap-2 ml-4">
            <img src="../assets/LOGO_UPTM.png" alt="Logo" class="h-16 w-auto object-contain">
        </a>
    </div>
    
    <div class="flex-none hidden lg:block">
        <ul class="menu menu-horizontal items-center gap-4">

            <li>
                <a href="index.php" class="nav-link text-gray-700 hover:text-blue-600 font-medium h-full flex items-center" title="Home">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-black-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </a>
            </li>
            
            <li>
                <a href="../student/dashboard.php" class="nav-link text-black-700 hover:text-blue-600 font-medium h-full flex items-center">
                    Dashboard
                </a>
            </li>

            <li>
                <a href="../student/available_clubs.php" class="nav-link text-black-700 hover:text-blue-600 font-medium h-full flex items-center">
                    Explore Clubs
                </a>
            </li>
            
            <?php if (isset($_SESSION['stud_id'])): ?>
                <li>
                    <a href="all_announcement.php" class="nav-link text-black-700 hover:text-blue-600 font-medium h-full flex items-center">
                        Announcements
                    </a>
                </li>
                <li>
                    <a href="all_event.php" class="nav-link text-black-700 hover:text-blue-600 font-medium h-full flex items-center">
                        Events
                    </a>
                </li>
                <li>
                    <a href="my_clubs.php" class="nav-link text-black-700 hover:text-blue-600 font-medium h-full flex items-center">
                        My Clubs
                    </a>
                </li>
                    
                <li>
                    <a href="student_faq.php" class="nav-link text-black-700 hover:text-blue-600 font-medium h-full flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Help & FAQs
                    </a>

                </li>

                <!-- Profile Picture Dropdown with Hover -->
                <li class="dropdown dropdown-end dropdown-hover">
                    <label tabindex="0" class="btn btn-ghost btn-circle avatar cursor-pointer">
                        <div class="w-10 rounded-full ring ring-blue-700">
                            <?php 
                            // Check if profile image exists and file is available
                            if (!empty($student_profile['studProfileImg']) && file_exists("../uploads/student_profiles/" . $student_profile['studProfileImg'])): 
                            ?>
                                <img src="../uploads/student_profiles/<?php echo htmlspecialchars($student_profile['studProfileImg']); ?>" 
                                    alt="Profile" 
                                    class="w-full h-full object-cover">
                            <?php else: ?>
                                <!-- Default avatar or first letter of name -->
                                <?php if ($student_profile && !empty($student_profile['studName'])): ?>
                                    <div class="w-full h-full rounded-full bg-gradient-to-br from-purple-400 to-blue-400 flex items-center justify-center text-white text-lg font-bold">
                                        <?php echo strtoupper(substr($student_profile['studName'], 0, 1)); ?>
                                    </div>
                                <?php else: ?>
                                    <img src="../assets/default-avatar.png" alt="Default Avatar">
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </label>
                    <ul tabindex="0" class="dropdown-content menu p-2 shadow-xl bg-base-100 rounded-box w-52 items-center hidden">
                        <li class="menu-title border-b pb-2 mb-2">
                            <span class="text-sm font-bold text-purple-600">
                                <?php echo $student_profile ? htmlspecialchars($student_profile['studName']) : 'Student'; ?>
                            </span>
                        </li>
                        <li>
                            <a href="profile.php" class="flex items-center gap-3 hover:bg-purple-50 rounded-lg transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span class="font-medium">My Profile</span>
                            </a>
                        </li>
                        <li>
                            <a href="../student/logout.php" class="flex items-center gap-3 text-error hover:bg-red-50 rounded-lg transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span class="font-medium">Logout</span>
                            </a>
                        </li>
                    </ul>
                </li>
            <?php else: ?>
                <li><a href="../user_access.php" class="btn btn-sm bg-purple-600 text-white hover:bg-purple-700 transition-colors border-none">Login / Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<script>
// Enhance dropdown hover behavior
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('.dropdown-hover');
    
    dropdowns.forEach(dropdown => {
        const content = dropdown.querySelector('.dropdown-content');
        let timeoutId;
        
        dropdown.addEventListener('mouseenter', function() {
            clearTimeout(timeoutId);
            if (content) {
                content.classList.remove('hidden');
            }
        });
        
        dropdown.addEventListener('mouseleave', function() {
            timeoutId = setTimeout(() => {
                if (content) {
                    content.classList.add('hidden');
                }
            }, 200);
        });
    });
});
</script>
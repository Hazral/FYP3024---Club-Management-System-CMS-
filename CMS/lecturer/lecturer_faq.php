<?php
session_start();
require_once '../config/connect.php';

if (!isset($_SESSION['lect_id'])) {
    header('Location: ../user_access.php');
    exit;
}

// Static FAQ data for lecturers
$faqs = [
    [
        'category' => 'Getting Started',
        'question' => 'How do I access my assigned clubs?',
        'answer' => 'Navigate to "My Clubs" from the sidebar menu. You\'ll see all clubs where you\'ve been assigned as an advisor. Click on any club to view its details, members, and activities.'
    ],
    [
        'category' => 'Getting Started',
        'question' => 'How do I update my profile information?',
        'answer' => 'Go to the Profile page from the sidebar. Fill the editable form to update your informations and profile picture. Remember to save your changes before leaving the page.'
    ],
    [
        'category' => 'Club Management',
        'question' => 'Can I manage multiple clubs?',
        'answer' => 'Yes, you can be assigned as an advisor to multiple clubs. Each club will appear in your "My Clubs" page. You can switch between clubs to manage their activities, events, and members separately.'
    ],
    [
        'category' => 'Club Management',
        'question' => 'How do I remove a member from a club?',
        'answer' => 'Navigate to the "Memberships" page, go to the Members section, find the member you want to remove, and click on the "Remove Member" option. You\'ll need to confirm the action.'
    ],
    [
        'category' => 'Events',
        'question' => 'Can I create events for the club?',
        'answer' => 'Yes, as a club advisor, you can create events. Go to the club page and click "Create Event". Fill in the event details including title, date, time, location, and description.'
    ],
    [
        'category' => 'Events',
        'question' => 'How do I track event attendance?',
        'answer' => 'In the Events Log, click on a completed event to view attendance records. You can see which members registered and attended. You can also export this data for reporting purposes.'
    ],
    [
        'category' => 'Monitoring',
        'question' => 'How do I view club activity logs?',
        'answer' => 'The "Activity Log" page shows all club activities. You can filter by club, date, or activity type to find specific information.'
    ],
    [
        'category' => 'Monitoring',
        'question' => 'How do I monitor club comments and discussions?',
        'answer' => 'Visit the "Comments Log" page to see all comments made in your clubs. You can review discussions, moderate inappropriate content, and respond to member questions or concerns.'
    ],
    [
        'category' => 'Monitoring',
        'question' => 'What reports can I generate?',
        'answer' => 'You can generate various reports including memberships, event attendance and activity summaries. Go to the respective pages and look for the "Export" or "Generate Report" options.'
    ],
    [
        'category' => 'Communication',
        'question' => 'How do I add new announcement of the club?',
        'answer' => 'Go to the club profile and click "New Announcement". Fill the form and select the type (public or private). Public is open for every student and Private is only for members.'
    ],
    [
        'category' => 'Communication',
        'question' => 'How do I add new event of the club?',
        'answer' => 'Go to the club profile and click "New Event". Fill the form and select the type (public or private). Public is open for every student and Private is only for members.'
    ],
    [
        'category' => 'Communication',
        'question' => 'How do I add new activity of the club?',
        'answer' => 'Go to the club profile and click "New Activity". Fill the form and submit.'
    ],
    [
        'category' => 'Technical Support',
        'question' => 'How do I reset my password?',
        'answer' => 'Go to Profile. Enter your current password, then your new password twice. Your password must be at least 8 characters. Then save changes'
    ],
    [
        'category' => 'Technical Support',
        'question' => 'Can I access this system on mobile devices?',
        'answer' => 'Yes, the system is fully responsive and works on smartphones and tablets.'
    ],
];

// Get unique categories
$categories = array_unique(array_column($faqs, 'category'));
sort($categories);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - Club Advisor Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --sidebar-width: 280px;
            --primary-blue: #0971b6;
        }

        body {
            background-color: #bed3f3ff;
        }

        .faq-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .faq-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(9, 113, 182, 0.1);
            border-left-color: var(--primary-blue);
        }

        .hero-gradient {
            background: linear-gradient(135deg, #0971b6 0%, #055a8c 100%);
        }

        .category-badge {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .category-badge:hover {
            transform: scale(1.05);
        }

        .category-badge.active {
            background-color: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .highlight {
            background-color: #fef08a;
            padding: 0 2px;
            border-radius: 2px;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #backToTop {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 99;
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
        }

        #backToTop.show {
            opacity: 1;
            visibility: visible;
        }

        .faq-collapse-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .faq-collapse-content.active {
            max-height: 1000px;
            transition: max-height 0.5s ease-in;
        }

        .faq-collapse-icon {
            transition: transform 0.3s ease;
        }

        .faq-collapse-icon.rotated {
            transform: rotate(45deg);
        }

        .search-box {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        .info-card {
            border-top: 4px solid var(--primary-blue);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(9, 113, 182, 0.15);
        }

        .contact-card {
            background: linear-gradient(135deg, #0971b6 0%, #055a8c 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="drawer lg:drawer-open">
        <input id="my-drawer-2" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content">
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
                                <li>My Profile</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Hero Section -->
                <div class="hero-gradient text-white py-12 mb-8 rounded-lg">
                    <div class="container mx-auto px-4 text-center">
                        <div class="flex justify-center mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h1 class="text-4xl font-bold mb-2">Club Advisor Help Center</h1>
                        <p class="text-xl opacity-90">Find answers to common questions about managing clubs and advising students</p>

                        <!-- Search Bar -->
                        <div class="max-w-3xl mx-auto mt-8">
                            <div class="card bg-white shadow-xl search-box">
                                <div class="card-body p-4">
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <div class="relative flex-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                            <input type="text" 
                                                   id="searchInput"
                                                   placeholder="Search for help topics..." 
                                                   class="input input-bordered text-black w-full pl-10"
                                                   autocomplete="off">
                                        </div>
                                        <button type="button" id="clearSearch" class="btn btn-ghost hidden">Clear</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container mx-auto px-4">
                    <!-- Category Filter -->
                    <div class="max-w-4xl mx-auto mb-6">
                        <h3 class="text-lg font-semibold mb-3 text-gray-700">Browse by Category</h3>
                        <div class="flex flex-wrap gap-2">
                            <button class="badge badge-lg category-badge active" data-category="all">
                                All Topics
                            </button>
                            <?php foreach ($categories as $cat): ?>
                                <button class="badge badge-lg category-badge badge-outline" data-category="<?php echo htmlspecialchars($cat); ?>">
                                    <?php echo htmlspecialchars($cat); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- FAQs List -->
                    <div class="max-w-4xl mx-auto">
                        <!-- Results Counter -->
                        <div id="resultsCounter" class="mb-4 text-gray-600 hidden">
                            <p id="resultsText"></p>
                        </div>

                        <!-- No Results Message -->
                        <div id="noResults" class="card bg-white shadow-xl hidden">
                            <div class="card-body text-center py-16">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="text-xl font-semibold text-gray-600 mb-2">No Results Found</h3>
                                <p class="text-gray-500" id="noResultsMessage">
                                    Try different keywords or browse all categories.
                                </p>
                            </div>
                        </div>

                        <div id="faqsContainer" class="space-y-4">
                            <?php 
                            $index = 0;
                            foreach ($faqs as $faq): 
                                $index++;
                            ?>
                                <div class="card bg-white shadow-lg faq-card fade-in" 
                                     data-category="<?php echo htmlspecialchars($faq['category']); ?>"
                                     data-question="<?php echo htmlspecialchars(strtolower($faq['question'])); ?>"
                                     data-answer="<?php echo htmlspecialchars(strtolower($faq['answer'])); ?>"
                                     data-faq-id="<?php echo $index; ?>">
                                    <div class="card-body p-0">
                                        <div class="faq-collapse-wrapper">
                                            <div class="faq-collapse-title text-lg font-medium cursor-pointer hover:bg-gray-50 transition-colors p-6" onclick="toggleFAQ(<?php echo $index; ?>)">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-shrink-0 mt-1">
                                                        <div class="badge" style="background-color: var(--primary-blue); color: white; border: none;"><?php echo $index; ?></div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="badge badge-outline badge-sm mb-2 faq-category-badge" style="border-color: var(--primary-blue); color: var(--primary-blue);">
                                                            <?php echo htmlspecialchars($faq['category']); ?>
                                                        </div>
                                                        <h3 class="font-semibold text-gray-800 pr-8 faq-question">
                                                            <?php echo htmlspecialchars($faq['question']); ?>
                                                        </h3>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 faq-collapse-icon" id="icon-<?php echo $index; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="faq-collapse-content bg-base-200" id="content-<?php echo $index; ?>">
                                                <div class="pt-4 pb-6 px-6">
                                                    <div class="flex items-start gap-3">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 flex-shrink-0 mt-1" style="color: var(--primary-blue);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <div class="flex-1">
                                                            <p class="text-gray-700 leading-relaxed faq-answer">
                                                                <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include "includes/sidebar.php"; ?>
    </div>

    <!-- Back to Top Button -->
    <button id="backToTop" class="btn btn-circle shadow-lg" style="background-color: var(--primary-blue); color: white; border: none;">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
    </button>

    <script>
        // State management
        let currentCategory = 'all';
        let currentSearch = '';
        
        // DOM elements
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearch');
        const categoryFilters = document.querySelectorAll('.category-badge');
        const faqCards = document.querySelectorAll('.faq-card');
        const resultsCounter = document.getElementById('resultsCounter');
        const resultsText = document.getElementById('resultsText');
        const noResults = document.getElementById('noResults');
        const faqsContainer = document.getElementById('faqsContainer');
        const backToTopBtn = document.getElementById('backToTop');

        // Toggle FAQ function
        function toggleFAQ(id) {
            const content = document.getElementById('content-' + id);
            const icon = document.getElementById('icon-' + id);
            
            const isActive = content.classList.contains('active');
            
            if (isActive) {
                content.classList.remove('active');
                icon.classList.remove('rotated');
            } else {
                content.classList.add('active');
                icon.classList.add('rotated');
            }
        }

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Filter FAQs
        function filterFAQs() {
            let visibleCount = 0;
            const searchTerm = currentSearch.toLowerCase().trim();
            
            faqCards.forEach(card => {
                const category = card.dataset.category;
                const question = card.dataset.question;
                const answer = card.dataset.answer;
                
                const categoryMatch = currentCategory === 'all' || category === currentCategory;
                const searchMatch = !searchTerm || 
                    question.includes(searchTerm) || 
                    answer.includes(searchTerm) ||
                    category.toLowerCase().includes(searchTerm);
                
                if (categoryMatch && searchMatch) {
                    card.classList.remove('hidden');
                    card.classList.add('fade-in');
                    visibleCount++;
                    
                    if (searchTerm) {
                        highlightText(card, searchTerm);
                    } else {
                        removeHighlight(card);
                    }
                } else {
                    card.classList.add('hidden');
                }
            });
            
            updateResultsUI(visibleCount);
            
            if (searchTerm || currentCategory !== 'all') {
                setTimeout(() => {
                    faqsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        }

        // Update results UI
        function updateResultsUI(count) {
            if (count === 0) {
                noResults.classList.remove('hidden');
                resultsCounter.classList.add('hidden');
                faqsContainer.classList.add('hidden');
                
                if (currentSearch) {
                    document.getElementById('noResultsMessage').textContent = 
                        `No FAQs match your search "${currentSearch}". Try different keywords.`;
                } else {
                    document.getElementById('noResultsMessage').textContent = 
                        'No FAQs found in this category.';
                }
            } else {
                noResults.classList.add('hidden');
                faqsContainer.classList.remove('hidden');
                
                if (currentSearch || currentCategory !== 'all') {
                    resultsCounter.classList.remove('hidden');
                    let message = `Found ${count} result${count !== 1 ? 's' : ''}`;
                    
                    if (currentSearch) {
                        message += ` for "${currentSearch}"`;
                    }
                    if (currentCategory !== 'all') {
                        message += ` in ${currentCategory}`;
                    }
                    
                    resultsText.textContent = message;
                } else {
                    resultsCounter.classList.add('hidden');
                }
            }
        }

        // Highlight search terms
        function highlightText(card, searchTerm) {
            const questionEl = card.querySelector('.faq-question');
            const answerEl = card.querySelector('.faq-answer');
            
            [questionEl, answerEl].forEach(el => {
                const originalText = el.textContent;
                const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
                const highlightedText = originalText.replace(regex, '<span class="highlight">$1</span>');
                
                if (highlightedText !== originalText) {
                    el.innerHTML = highlightedText;
                }
            });
        }

        // Remove highlights
        function removeHighlight(card) {
            const questionEl = card.querySelector('.faq-question');
            const answerEl = card.querySelector('.faq-answer');
            
            [questionEl, answerEl].forEach(el => {
                el.textContent = el.textContent;
            });
        }

        // Escape regex
        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // Search handler
        const handleSearch = debounce((e) => {
            currentSearch = e.target.value;
            filterFAQs();
            
            if (currentSearch) {
                clearSearchBtn.classList.remove('hidden');
            } else {
                clearSearchBtn.classList.add('hidden');
            }
        }, 300);

        searchInput.addEventListener('input', handleSearch);

        // Clear search
        clearSearchBtn.addEventListener('click', () => {
            searchInput.value = '';
            currentSearch = '';
            clearSearchBtn.classList.add('hidden');
            filterFAQs();
            searchInput.focus();
        });

        // Category filters
        categoryFilters.forEach(filter => {
            filter.addEventListener('click', (e) => {
                e.preventDefault();
                
                categoryFilters.forEach(f => {
                    f.classList.remove('active');
                });
                filter.classList.add('active');
                
                currentCategory = filter.dataset.category;
                filterFAQs();
            });
        });

        // Back to top
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });

        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
            
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                if (currentSearch) {
                    searchInput.value = '';
                    currentSearch = '';
                    clearSearchBtn.classList.add('hidden');
                    filterFAQs();
                } else {
                    searchInput.blur();
                }
            }
        });

        searchInput.setAttribute('title', 'Press Ctrl+K to focus (Cmd+K on Mac)');
        
        // Intersection observer for animations
        const observerOptions = {
            root: null,
            rootMargin: '50px',
            threshold: 0.01
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        faqCards.forEach(card => observer.observe(card));
    </script>
</body>
</html>
<?php
session_start();

if (!isset($_SESSION['stud_id'])) {
    header('Location: ../user_access.php');
    exit;
}

// Static FAQ data - easy to update without database
$faqs = [
    [
        'category' => 'Getting Started',
        'question' => 'How do I join a club?',
        'answer' => 'To join a club, navigate to the "Clubs" page from the main menu. Browse available clubs and click on the club you\'re interested in. Then click the "Join Club" button. Your request will be sent to the club administrator for approval.'
    ],
    [
        'category' => 'Getting Started',
        'question' => 'How do I update my profile?',
        'answer' => 'Go to your Profile page by clicking on your name in the top right corner. Fill in your information including profile picture. Don\'t forget to save your changes!'
    ],
    [
        'category' => 'Events',
        'question' => 'How do I join an event?',
        'answer' => 'Visit the "Events" page to see all events. Click on an event to view details. If the registration is open, you\'ll see a "JOIN THIS EVENT" button. Click it to confirm your registeration.'
    ],
    [
        'category' => 'Events',
        'question' => 'Can I cancel my event registration?',
        'answer' => 'Yes, you can cancel your registration. Go to "My Events" page, find the event, and click "Cancel Registration". Please note that some events may have different cancellation policies.'
    ],
    [
        'category' => 'Clubs',
        'question' => 'What are club roles?',
        'answer' => 'Clubs have different member roles: President (leads the club), Vice President (assists president), Secretary (manages communications), Treasurer (handles finances), and Member (participates in activities). Each role has different permissions and responsibilities.'
    ],
    [
        'category' => 'Clubs',
        'question' => 'How do I leave a club?',
        'answer' => 'To leave a club, go to the Club Profile page and click on "Leave Club" button. You\'ll be asked to confirm your decision.'
    ],
    [
        'category' => 'Account',
        'question' => 'How do I change my password?',
        'answer' => 'Go to Settings > Security. Click "Change Password", enter your current password, then your new password twice. Your password must be at least 8 characters long and include letters and numbers for security.'
    ],
    [
        'category' => 'Account',
        'question' => 'I forgot my password. What should I do?',
        'answer' => 'Click on "Forgot Password" on the login page. Enter your registered email address. You\'ll receive a password reset link via email. Click the link and follow the instructions to create a new password.'
    ],
    [
        'category' => 'Messaging',
        'question' => 'How do I contact club members?',
        'answer' => 'You can message other club members through the club page. Click on "Members" tab, select a member, and click the message icon. You can also participate in club discussions and group chats.'
    ],
    [
        'category' => 'Privacy',
        'question' => 'Who can see my profile information?',
        'answer' => 'By default, your basic profile information (name, program, clubs) is visible to other students. You can control visibility of other details in Settings > Privacy. Your email and phone number are always private unless you choose to share them.'
    ],
    [
        'category' => 'Technical',
        'question' => 'The website is not loading properly. What should I do?',
        'answer' => 'Try these steps: 1) Clear your browser cache and cookies, 2) Try a different browser, 3) Check your internet connection, 4) Disable browser extensions temporarily. If the problem persists, contact support with details about your browser and the issue.'
    ],
    [
        'category' => 'Technical',
        'question' => 'Can I use this system on my mobile phone?',
        'answer' => 'Yes! The system is fully responsive and works on all devices including smartphones and tablets. Simply access the website through your mobile browser. The interface will automatically adjust to fit your screen size.'
    ]
];

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get category filter
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';

// Filter FAQs based on search and category
$filteredFaqs = $faqs;

if (!empty($search)) {
    $filteredFaqs = array_filter($filteredFaqs, function($faq) use ($search) {
        return stripos($faq['question'], $search) !== false || 
               stripos($faq['answer'], $search) !== false ||
               stripos($faq['category'], $search) !== false;
    });
}

if (!empty($categoryFilter)) {
    $filteredFaqs = array_filter($filteredFaqs, function($faq) use ($categoryFilter) {
        return $faq['category'] === $categoryFilter;
    });
}

// Get unique categories
$categories = array_unique(array_column($faqs, 'category'));
sort($categories);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - Student Club Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon-16x16.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .faq-card {
            transition: all 0.3s ease;
        }
        .faq-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .category-badge {
            transition: all 0.2s ease;
        }
        .category-badge:hover {
            transform: scale(1.05);
        }
        .highlight {
            background-color: #fef08a;
            padding: 0 2px;
            border-radius: 2px;
        }
        .faq-hidden {
            display: none !important;
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
        /* Custom collapse styling for toggle functionality */
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
    </style>
</head>
<body class="bg-gray-50">
    <div class="drawer">
        <input id="my-drawer-3" type="checkbox" class="drawer-toggle" /> 
        <div class="drawer-content flex flex-col">
            <?php include "includes/navbar.php"; ?>

            <main class="min-h-screen pt-20 pb-10">
                <!-- Hero Section -->
                <div class="hero-gradient text-white py-12 mb-8">
                    <div class="container mx-auto px-4 text-center">
                        <div class="flex justify-center mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h1 class="text-4xl font-bold mb-2">Help Center</h1>
                        <p class="text-xl opacity-90">Find answers to common questions about using the system</p>
                    </div>

                    <!-- Search Bar -->
                    <div class="max-w-4xl mx-auto mt-8 px-4">
                        <div class="card bg-white shadow-xl">
                            <div class="card-body p-4">
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <div class="relative flex-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <input type="text" 
                                               id="searchInput"
                                               placeholder="Search for help..." 
                                               class="input input-bordered text-black w-full pl-10"
                                               autocomplete="off">
                                    </div>
                                    <button type="button" id="clearSearch" class="btn btn-ghost hidden">Clear</button>
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
                            <button class="badge badge-lg category-badge badge-primary category-filter" data-category="all">
                                All Topics
                            </button>
                            <?php foreach ($categories as $cat): ?>
                                <button class="badge badge-lg category-badge badge-outline category-filter" data-category="<?php echo htmlspecialchars($cat); ?>">
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
                                <div class="card bg-white shadow-xl faq-card fade-in" 
                                     data-category="<?php echo htmlspecialchars($faq['category']); ?>"
                                     data-question="<?php echo htmlspecialchars(strtolower($faq['question'])); ?>"
                                     data-answer="<?php echo htmlspecialchars(strtolower($faq['answer'])); ?>"
                                     data-faq-id="<?php echo $index; ?>">
                                    <div class="card-body p-0">
                                        <div class="faq-collapse-wrapper">
                                            <div class="faq-collapse-title text-lg font-medium cursor-pointer hover:bg-gray-50 transition-colors p-6" onclick="toggleFAQ(<?php echo $index; ?>)">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-shrink-0 mt-1">
                                                        <div class="badge badge-primary"><?php echo $index; ?></div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="badge badge-outline badge-sm mb-2 faq-category-badge"><?php echo htmlspecialchars($faq['category']); ?></div>
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
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-success flex-shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
            </main>

            <?php include "includes/footer.php"; ?>
        </div>
        <?php include "includes/mobile_drawer.php"; ?>
    </div>

    <!-- Back to Top Button -->
    <button id="backToTop" class="btn btn-circle btn-primary shadow-lg">
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
        const categoryFilters = document.querySelectorAll('.category-filter');
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
            
            // Check if currently active
            const isActive = content.classList.contains('active');
            
            if (isActive) {
                // Close this FAQ
                content.classList.remove('active');
                icon.classList.remove('rotated');
            } else {
                // Open this FAQ
                content.classList.add('active');
                icon.classList.add('rotated');
            }
        }

        // Debounce function for search
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

        // Filter FAQs based on search and category
        function filterFAQs() {
            let visibleCount = 0;
            const searchTerm = currentSearch.toLowerCase().trim();
            
            faqCards.forEach(card => {
                const category = card.dataset.category;
                const question = card.dataset.question;
                const answer = card.dataset.answer;
                
                // Check category filter
                const categoryMatch = currentCategory === 'all' || category === currentCategory;
                
                // Check search filter
                const searchMatch = !searchTerm || 
                    question.includes(searchTerm) || 
                    answer.includes(searchTerm) ||
                    category.toLowerCase().includes(searchTerm);
                
                if (categoryMatch && searchMatch) {
                    card.classList.remove('hidden');
                    card.classList.add('fade-in');
                    visibleCount++;
                    
                    // Highlight search terms
                    if (searchTerm) {
                        highlightText(card, searchTerm);
                    } else {
                        removeHighlight(card);
                    }
                } else {
                    card.classList.add('hidden');
                }
            });
            
            // Update UI based on results
            updateResultsUI(visibleCount);
            
            // Smooth scroll to results
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

        // Highlight search terms in text
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
                el.textContent = el.textContent; // Reset to plain text
            });
        }

        // Escape regex special characters
        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // Search input handler
        const handleSearch = debounce((e) => {
            currentSearch = e.target.value;
            filterFAQs();
            
            // Show/hide clear button
            if (currentSearch) {
                clearSearchBtn.classList.remove('hidden');
            } else {
                clearSearchBtn.classList.add('hidden');
            }
        }, 300);

        searchInput.addEventListener('input', handleSearch);

        // Clear search button
        clearSearchBtn.addEventListener('click', () => {
            searchInput.value = '';
            currentSearch = '';
            clearSearchBtn.classList.add('hidden');
            filterFAQs();
            searchInput.focus();
        });

        // Category filter handlers
        categoryFilters.forEach(filter => {
            filter.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Update active state
                categoryFilters.forEach(f => {
                    f.classList.remove('badge-primary');
                    f.classList.add('badge-outline');
                });
                filter.classList.remove('badge-outline');
                filter.classList.add('badge-primary');
                
                // Update current category
                currentCategory = filter.dataset.category;
                filterFAQs();
            });
        });

        // Back to top button
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
            // Focus search on Ctrl/Cmd + K
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
            
            // Clear search on Escape
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

        // Expand first FAQ if no search/filter active
        window.addEventListener('load', () => {
            if (!currentSearch && currentCategory === 'all') {
                const firstFaq = document.getElementById('faq-1');
                if (firstFaq) {
                    firstFaq.checked = true;
                }
            }
        });

        // Add tooltip for keyboard shortcut
        searchInput.setAttribute('title', 'Press Ctrl+K to focus (Cmd+K on Mac)');
        
        // Performance: Lazy load FAQ content
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